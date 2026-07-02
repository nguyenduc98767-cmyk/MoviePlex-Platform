<?php

class Showtime
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getById(int $id): array|false
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.*, m.title AS movie_title, m.duration_min, m.poster_url,
                    m.age_rating, c.name AS cinema_name, c.address AS cinema_address
             FROM showtimes s
             JOIN movies m ON m.id = s.movie_id
             JOIN cinemas c ON c.id = s.cinema_id
             WHERE s.id = :id AND s.is_cancelled = 0
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getByMovie(int $movieId, string $date = null): array
    {
        $where  = ['s.movie_id = :movie_id', 's.is_cancelled = 0'];
        $params = [':movie_id' => $movieId];

        if ($date !== null) {
            $where[]          = 's.show_date = :show_date';
            $params[':show_date'] = $date;
        } else {
            $where[] = 's.show_date >= CURDATE()';
        }

        $sql  = 'SELECT s.*, c.name AS cinema_name, c.city
                 FROM showtimes s
                 JOIN cinemas c ON c.id = s.cinema_id
                 WHERE ' . implode(' AND ', $where) . '
                 ORDER BY s.show_date ASC, s.start_time ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getByDate(string $date, int $cinemaId = null): array
    {
        $where  = ['s.show_date = :show_date', 's.is_cancelled = 0'];
        $params = [':show_date' => $date];

        if ($cinemaId !== null) {
            $where[]             = 's.cinema_id = :cinema_id';
            $params[':cinema_id'] = $cinemaId;
        }

        $sql  = 'SELECT s.*, m.title AS movie_title, m.poster_url, m.duration_min,
                        m.age_rating, c.name AS cinema_name
                 FROM showtimes s
                 JOIN movies m ON m.id = s.movie_id
                 JOIN cinemas c ON c.id = s.cinema_id
                 WHERE ' . implode(' AND ', $where) . '
                 ORDER BY s.start_time ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAvailableSeats(int $showtimeId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM seats
             WHERE showtime_id = :showtime_id
             ORDER BY seat_row ASC, seat_num ASC'
        );
        $stmt->execute([':showtime_id' => $showtimeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSeatsByStatus(int $showtimeId, string $status): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM seats
             WHERE showtime_id = :showtime_id AND status = :status
             ORDER BY seat_row ASC, seat_num ASC'
        );
        $stmt->execute([':showtime_id' => $showtimeId, ':status' => $status]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function holdSeats(int $showtimeId, array $seatIds): bool
    {
        if (empty($seatIds)) {
            return false;
        }

        $placeholders = implode(',', array_fill(0, count($seatIds), '?'));
        $params       = array_merge([$showtimeId], $seatIds);

        $stmt = $this->pdo->prepare(
            "UPDATE seats SET status = 'hold'
             WHERE showtime_id = ? AND id IN ($placeholders) AND status = 'available'"
        );
        return $stmt->execute($params);
    }

    public function bookSeats(int $showtimeId, array $seatIds): bool
    {
        if (empty($seatIds)) {
            return false;
        }

        $placeholders = implode(',', array_fill(0, count($seatIds), '?'));
        $params       = array_merge([$showtimeId], $seatIds);

        $stmt = $this->pdo->prepare(
            "UPDATE seats SET status = 'booked'
             WHERE showtime_id = ? AND id IN ($placeholders)"
        );
        return $stmt->execute($params);
    }

    public function releaseSeats(int $showtimeId, array $seatIds): bool
    {
        if (empty($seatIds)) {
            return false;
        }

        $placeholders = implode(',', array_fill(0, count($seatIds), '?'));
        $params       = array_merge([$showtimeId], $seatIds);

        $stmt = $this->pdo->prepare(
            "UPDATE seats SET status = 'available'
             WHERE showtime_id = ? AND id IN ($placeholders)"
        );
        return $stmt->execute($params);
    }

    public function decrementAvailableSeats(int $showtimeId, int $count): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE showtimes
            SET available_seats = available_seats - :count
            WHERE id = :id AND available_seats >= :min_count'
        );
        return $stmt->execute([
            ':count'     => $count,
            ':id'        => $showtimeId,
            ':min_count' => $count,
        ]);
    }
    public function incrementAvailableSeats(int $showtimeId, int $count): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE showtimes
             SET available_seats = available_seats + :count
             WHERE id = :id'
        );
        return $stmt->execute([':count' => $count, ':id' => $showtimeId]);
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO showtimes
                (movie_id, cinema_id, hall_name, show_date, start_time, end_time,
                 format, subtitle_type, price, total_seats, available_seats)
             VALUES
                (:movie_id, :cinema_id, :hall_name, :show_date, :start_time, :end_time,
                 :format, :subtitle_type, :price, :total_seats, :available_seats)'
        );
        $totalSeats = (int) ($data['total_seats'] ?? 120);
        $stmt->execute([
            ':movie_id'      => $data['movie_id'],
            ':cinema_id'     => $data['cinema_id'],
            ':hall_name'     => $data['hall_name'] ?? 'Phòng 01',
            ':show_date'     => $data['show_date'],
            ':start_time'    => $data['start_time'],
            ':end_time'      => $data['end_time'] ?? null,
            ':format'        => $data['format'] ?? '2D',
            ':subtitle_type' => $data['subtitle_type'] ?? 'Phụ đề',
            ':price'         => $data['price'] ?? 90000,
            ':total_seats'   => $totalSeats,
            ':available_seats' => $data['available_seats'] ?? $totalSeats,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $allowed = [
            'hall_name', 'show_date', 'start_time', 'end_time',
            'format', 'subtitle_type', 'price', 'total_seats', 'available_seats',
        ];
        $fields = [];
        $params = [':id' => $id];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $fields[]         = "$col = :$col";
                $params[":$col"] = $data[$col];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql  = 'UPDATE showtimes SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function cancel(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE showtimes SET is_cancelled = 1 WHERE id = :id'
        );
        return $stmt->execute([':id' => $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM showtimes WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    public function initSeats(int $showtimeId, array $seatMap): bool
    {
        if (empty($seatMap)) {
            return false;
        }

        $placeholders = implode(',', array_fill(0, count($seatMap), '(?, ?, ?, ?)'));
        $params       = [];

        foreach ($seatMap as $seat) {
            $params[] = $showtimeId;
            $params[] = $seat['seat_row'];
            $params[] = $seat['seat_num'];
            $params[] = $seat['seat_type'] ?? 'standard';
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO seats (showtime_id, seat_row, seat_num, seat_type)
             VALUES $placeholders"
        );
        return $stmt->execute($params);
    }

    public function getUpcoming(int $limit = 10): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.*, m.title AS movie_title, c.name AS cinema_name
             FROM showtimes s
             JOIN movies m ON m.id = s.movie_id
             JOIN cinemas c ON c.id = s.cinema_id
             WHERE s.show_date >= CURDATE() AND s.is_cancelled = 0
             ORDER BY s.show_date ASC, s.start_time ASC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAvailableDates(int $movieId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT DISTINCT show_date
             FROM showtimes
             WHERE movie_id = :movie_id AND is_cancelled = 0 AND show_date >= CURDATE()
             ORDER BY show_date ASC'
        );
        $stmt->execute([':movie_id' => $movieId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}