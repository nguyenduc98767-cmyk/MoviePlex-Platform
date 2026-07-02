<?php

require_once __DIR__ . '/../models/Booking.php';
require_once __DIR__ . '/../models/Showtime.php';
require_once __DIR__ . '/../models/Voucher.php';
require_once __DIR__ . '/../services/VoucherService.php';

class BookingService
{
    private PDO $pdo;
    private Booking $bookingModel;
    private Showtime $showtimeModel;
    private VoucherService $voucherService;
    private array $vipRows = ['E', 'F', 'G'];

    public function __construct(PDO $pdo)
    {
        $this->pdo            = $pdo;
        $this->bookingModel   = new Booking($pdo);
        $this->showtimeModel  = new Showtime($pdo);
        $this->voucherService = new VoucherService($pdo);
    }

    public function parseSeatGroups(string $seatParam): array
    {
        $groups = array_filter(array_map('trim', explode('|', $seatParam)));
        $result = [];

        foreach ($groups as $group) {
            $seats = array_filter(array_map('trim', explode(',', $group)));
            if (!empty($seats)) {
                $result[] = array_values($seats);
            }
        }

        return $result;
    }

    public function calculateSeatPricing(array $seatGroups, int $basePrice): array
    {
        $items = [];
        $serverTotal = 0;

        foreach ($seatGroups as $group) {
            $row = strtoupper(substr($group[0] ?? '', 0, 1));
            if (count($group) === 2) {
                $subtotal = $basePrice * 2;
                $quantity = 2;
            } elseif (in_array($row, $this->vipRows, true)) {
                $subtotal = (int) round($basePrice * 1.3);
                $quantity = 1;
            } else {
                $subtotal = $basePrice;
                $quantity = 1;
            }

            $items[] = [
                'seats'    => $group,
                'row'      => $row,
                'quantity' => $quantity,
                'subtotal' => $subtotal,
            ];
            $serverTotal += $subtotal;
        }

        return [
            'items'       => $items,
            'serverTotal' => $serverTotal,
            'count'       => count($seatGroups),
        ];
    }

    public function getSnackCatalog(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM snacks ORDER BY category, price');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getShowtime(int $showtimeId): array|false
    {
        return $this->showtimeModel->getById($showtimeId);
    }

    public function getBookedSeats(int $showtimeId): array
    {
        return $this->bookingModel->getSeatsByShowtime($showtimeId);
    }

    public function getCheckoutContext(int $userId, int $showtimeId, string $seatsParam): array
    {
        $seatGroups = $this->parseSeatGroups($seatsParam);
        if (empty($seatGroups)) {
            throw new Exception('Vui lòng chọn ghế hợp lệ.');
        }

        $show = $this->getShowtime($showtimeId);
        if (!$show) {
            throw new Exception('Suất chiếu không tồn tại.');
        }

        if ((int) $show['is_cancelled'] === 1) {
            throw new Exception('Suất chiếu đã bị hủy.');
        }

        $showtimeTs = strtotime($show['show_date'] . ' ' . $show['start_time']);
        if ($showtimeTs + 20 * 60 < time()) {
            throw new Exception('Suất chiếu đã quá hạn để đặt vé.');
        }

        $pricing = $this->calculateSeatPricing($seatGroups, (int) $show['price']);

        return [
            'show'       => $show,
            'seatGroups' => $seatGroups,
            'allSeats'   => array_merge(...$seatGroups),
            'pricing'    => $pricing,
            'snacks'     => $this->getSnackCatalog(),
            'vouchers'   => $this->voucherService->getUserActiveVouchers($userId),
        ];
    }

    private function calculateSnackTotal(array $snacks): float
    {
        if (empty($snacks)) {
            return 0.0;
        }

        $ids = array_values(array_unique(array_column($snacks, 'id')));

        if (empty($ids)) {
            return 0.0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT id, price FROM snacks WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        $priceMap = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // id => price

        $total = 0.0;
        foreach ($snacks as $snack) {
            $id  = $snack['id'] ?? null;
            $qty = (int) ($snack['quantity'] ?? 1);

            if ($id === null || !isset($priceMap[$id])) {
                continue; // hoặc throw exception nếu muốn strict
            }

            $total += (float) $priceMap[$id] * $qty;
        }

        return $total;
    }

    public function createBooking(int $userId, int $showtimeId, string $seatsParam, string $paymentMethod, string $voucherCode, array $snacks): array
    {
        $seatGroups = $this->parseSeatGroups($seatsParam);
        if (empty($seatGroups)) {
            return ['success' => false, 'message' => 'Không có ghế hợp lệ để đặt.'];
        }

        $show = $this->getShowtime($showtimeId);
        if (!$show) {
            return ['success' => false, 'message' => 'Suất chiếu không tồn tại.'];
        }

        if ((int) $show['is_cancelled'] === 1) {
            return ['success' => false, 'message' => 'Suất chiếu đã bị hủy.'];
        }

        $showtimeTs = strtotime($show['show_date'] . ' ' . $show['start_time']);
        if ($showtimeTs + 20 * 60 < time()) {
            return ['success' => false, 'message' => 'Suất chiếu đã quá hạn để đặt vé.'];
        }

        $pricing = $this->calculateSeatPricing($seatGroups, (int) $show['price']);
        $snacksTotal = $this->calculateSnackTotal($snacks);

        $voucher = null;
        $discount = 0;
        if ($voucherCode !== '') {
            $voucher = $this->voucherService->validateCheckoutVoucher($voucherCode, $userId, $pricing['serverTotal'], $snacksTotal);
            if (!$voucher['valid']) {
                return ['success' => false, 'message' => $voucher['message']];
            }
            $discount = (int) $voucher['discount'];
        }

        $transactionId = 'TX-' . date('Ymd') . strtoupper(substr(uniqid('', true), -6));
        $firstBookingCode = null;
        $accumulatedDiscount = 0;
        $n = count($pricing['items']);

        try {
            $this->pdo->beginTransaction();

            foreach ($pricing['items'] as $index => $item) {
                if ($index === $n - 1) {
                    $seatDiscount = $discount - $accumulatedDiscount;
                } else {
                    $seatDiscount = (int) round($discount * ($item['subtotal'] / max(1, $pricing['serverTotal'])));
                }
                $accumulatedDiscount += $seatDiscount;

                $seatTotal = $item['subtotal'] - $seatDiscount;
                if ($index === 0) {
                    $seatTotal += $snacksTotal;
                }
                $seatTotal = max(0, $seatTotal);

                $bookingCode = $this->bookingModel->generateCode();
                if ($firstBookingCode === null) {
                    $firstBookingCode = $bookingCode;
                }

                $this->bookingModel->create([
                    'booking_code'   => $bookingCode,
                    'user_id'        => $userId,
                    'showtime_id'    => $showtimeId,
                    'seats_json'     => json_encode($item['seats'], JSON_UNESCAPED_UNICODE),
                    'num_tickets'    => $item['quantity'],
                    'subtotal'       => $item['subtotal'],
                    'discount'       => $seatDiscount,
                    'total_amount'   => $seatTotal,
                    'payment_method' => $paymentMethod,
                    'payment_status' => 'paid',
                    'status'         => 'confirmed',
                    'voucher_code'   => $voucherCode !== '' ? $voucherCode : null,
                    'transaction_id' => $transactionId,
                ]);
            }

            $this->showtimeModel->decrementAvailableSeats($showtimeId, count(array_merge(...$seatGroups)));

            if ($voucherCode !== '' && $voucher !== null && $voucher['valid']) {
                $this->voucherService->markVoucherUsed($voucherCode);
            }

            $this->pdo->commit();

            return [
                'success'           => true,
                'message'           => 'Đặt vé thành công.',
                'booking_code'      => $firstBookingCode,
                'transaction_id'    => $transactionId,
            ];
        } catch (Exception $e) {

            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine()
            ];
        }
    }

    public function getBookingConfirmation(string $bookingCode, int $userId, string $userRole): array
    {
        $booking = $this->bookingModel->findByCode($bookingCode);
        if (!$booking) {
            throw new Exception('Không tìm thấy thông tin đặt vé.');
        }

        if ($userRole !== 'staff' && $userRole !== 'admin') {
            if ((int) $booking['user_id'] !== $userId) {
                throw new Exception('Bạn không có quyền xem thông tin này.');
            }
        }

        $related = [];
        if (!empty($booking['transaction_id'])) {
            $sql = 'SELECT booking_code, seats_json, subtotal, discount, total_amount, status
                    FROM bookings
                    WHERE transaction_id = ?';
            $params = [$booking['transaction_id']];
            if ($userRole !== 'staff' && $userRole !== 'admin') {
                $sql .= ' AND user_id = ?';
                $params[] = $userId;
            }
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $related = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $seats = json_decode($booking['seats_json'], true) ?: [];
        $ticketCodesMap = [];
        foreach ($seats as $seat) {
            $ticketCodesMap[$seat] = $booking['booking_code'];
        }

        $totalSubtotal = (float) $booking['subtotal'];
        $totalDiscount = (float) $booking['discount'];
        $totalAmount = (float) $booking['total_amount'];

        if (!empty($related) && count($related) > 1) {
            $seats = [];
            $totalSubtotal = 0;
            $totalDiscount = 0;
            $totalAmount = 0;
            $ticketCodesMap = [];

            foreach ($related as $rb) {
                $rbSeats = json_decode($rb['seats_json'], true) ?: [];
                foreach ($rbSeats as $seat) {
                    $seats[] = $seat;
                    $ticketCodesMap[$seat] = $rb['booking_code'];
                }
                $totalSubtotal += (float) $rb['subtotal'];
                $totalDiscount += (float) $rb['discount'];
                $totalAmount += (float) $rb['total_amount'];
            }
        }

        $booking['title'] = $booking['movie_title'] ?? $booking['title'] ?? '';
        $booking['cinema_name'] = $booking['cinema_name'] ?? $booking['cinema_name'] ?? '';

        return [
            'booking'         => $booking,
            'seats'           => $seats,
            'ticketCodesMap'  => $ticketCodesMap,
            'totalSubtotal'   => $totalSubtotal,
            'totalDiscount'   => $totalDiscount,
            'totalAmount'     => $totalAmount,
            'relatedBookings' => $related,
        ];
    }

    public function getSeatSelectionContext(int $showtimeId): array
    {
        $show = $this->getShowtime($showtimeId);
        if (!$show) {
            throw new Exception('Suất chiếu không tồn tại.');
        }

        if ((int) $show['is_cancelled'] === 1) {
            throw new Exception('Suất chiếu đã bị hủy.');
        }

        $showtimeTs = strtotime($show['show_date'] . ' ' . $show['start_time']);
        if ($showtimeTs + 20 * 60 < time()) {
            throw new Exception('Suất chiếu đã quá hạn để chọn ghế.');
        }

        return [
            'show'        => $show,
            'bookedSeats' => $this->getBookedSeats($showtimeId),
        ];
    }
}
