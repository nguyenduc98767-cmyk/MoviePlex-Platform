<?php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../core/Logger.php';

class AuthService
{
    private PDO    $pdo;
    private User   $userModel;
    private Logger $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo       = $pdo;
        $this->userModel = new User($pdo);
        $this->logger    = new Logger($pdo);
    }

    // -------------------------------------------------------------------------
    // LOGIN
    // -------------------------------------------------------------------------

    public function login(string $identifier, string $password): array
    {
        if (empty($identifier) || empty($password)) {
            return $this->fail('Vui lòng nhập tài khoản và mật khẩu của bạn.');
        }

        $user = $this->userModel->findByEmail($identifier);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return $this->fail('Tài khoản hoặc mật khẩu của bạn không đúng.');
        }

        if ($user['status'] !== 'active') {
            return $this->fail('Tài khoản của bạn đã bị khóa. Vui lòng liên hệ hỗ trợ.');
        }

        $this->startSession($user);
        $this->userModel->updateLastLogin($user['id']);

        $redirect = $this->resolveRedirect($user['role']);

        $this->logger->auth('Đăng nhập', "User [{$user['email']}] đăng nhập thành công từ IP " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

        return [
            'success'  => true,
            'message'  => 'Đăng nhập thành công!',
            'redirect' => $redirect,
            'user'     => [
                'id'    => $user['id'],
                'name'  => $user['full_name'],
                'email' => $user['email'],
                'role'  => $user['role'],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // REGISTER
    // -------------------------------------------------------------------------

    public function register(array $data): array
    {
        $fullName       = trim($data['full_name']        ?? '');
        $email          = trim($data['email']            ?? '');
        $phone          = trim($data['phone']            ?? '');
        $password       = $data['password']              ?? '';
        $confirmPassword = $data['confirm_password']     ?? '';

        // Validation
        if (empty($fullName) || empty($email) || empty($password)) {
            return $this->fail('Vui lòng điền đầy đủ thông tin bắt buộc.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->fail('Định dạng email không hợp lệ. Vui lòng kiểm tra lại.');
        }

        if (!empty($phone) && !preg_match('/^(0[35789])[0-9]{8}$/', $phone)) {
            return $this->fail('Số điện thoại không hợp lệ. Vui lòng nhập số điện thoại Việt Nam gồm 10 chữ số (bắt đầu bằng 03, 05, 07, 08, 09).');
        }

        if (strlen($password) < 6) {
            return $this->fail('Mật khẩu phải có ít nhất 6 ký tự.');
        }

        if ($password !== $confirmPassword) {
            return $this->fail('Mật khẩu xác nhận không khớp.');
        }

        if ($this->userModel->emailExists($email)) {
            return $this->fail('Email này đã được đăng ký. Vui lòng dùng email khác.');
        }

        // Create user
        $userId = $this->userModel->create([
            'full_name'     => $fullName,
            'email'         => $email,
            'phone'         => $phone ?: null,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
        ]);

        // Auto-assign welcome vouchers
        $this->assignWelcomeVouchers($userId);

        $this->logger->log('Đăng ký', "User mới [{$email}] đã đăng ký tài khoản.", $fullName, 'user');

        return [
            'success' => true,
            'message' => 'Đăng ký thành công! Bạn có thể đăng nhập ngay.',
        ];
    }

    // -------------------------------------------------------------------------
    // LOGOUT
    // -------------------------------------------------------------------------

    public function logout(): array
    {
        $email = $_SESSION['user_email'] ?? 'unknown';
        $name  = $_SESSION['user_name']  ?? 'unknown';

        $this->logger->log('Đăng xuất', "User [{$email}] đăng xuất.", $name, $_SESSION['user_role'] ?? 'user');

        session_destroy();

        return [
            'success'  => true,
            'redirect' => $this->getRedirectUrl('/fe/pages/login.php'),
        ];
    }

    // -------------------------------------------------------------------------
    // FORGOT PASSWORD — STEP 1: Send OTP
    // -------------------------------------------------------------------------

    public function sendPasswordResetOtp(string $email): array
    {
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->fail('Địa chỉ email không hợp lệ.');
        }

        $user = $this->userModel->findByEmail($email);

        // Return success even if user not found (prevent email enumeration)
        if (!$user) {
            return [
                'success' => true,
                'message' => 'Nếu email tồn tại trong hệ thống, mã OTP sẽ được gửi tới bạn.',
                'dev_otp' => null,
            ];
        }

        $otp       = sprintf('%06d', mt_rand(1, 999999));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        $this->userModel->savePasswordResetToken($email, $otp, $expiresAt);

        require_once __DIR__ . '/MailService.php';
        $mailService = new MailService();
        $isSent = $mailService->sendPasswordReset($email, $user['full_name'], $otp);

        if (!$isSent) {
            return $this->fail('Không thể gửi email lúc này, vui lòng kiểm tra lại cấu hình.');
        }

        $this->logger->log('Quên mật khẩu', "Yêu cầu OTP cho email [{$email}].", $user['full_name'], 'user');

        return [
            'success' => true,
            'message' => 'Mã OTP đã được gửi đến email của bạn.',
        ];
    }

    // -------------------------------------------------------------------------
    // FORGOT PASSWORD — STEP 2: Verify OTP
    // -------------------------------------------------------------------------

    public function verifyPasswordResetOtp(string $email, string $otp): array
    {
        if (empty($email) || empty($otp)) {
            return $this->fail('Thiếu thông tin xác thực.');
        }

        $record = $this->userModel->findPasswordResetToken($email, $otp);

        if (!$record) {
            return $this->fail('Mã OTP không chính xác hoặc đã hết hạn.');
        }

        return ['success' => true, 'message' => 'Xác thực OTP thành công.'];
    }

    // -------------------------------------------------------------------------
    // FORGOT PASSWORD — STEP 3: Reset Password
    // -------------------------------------------------------------------------

    public function resetPassword(string $email, string $otp, string $newPassword, string $confirmPassword): array
    {
        if (strlen($newPassword) < 6) {
            return $this->fail('Mật khẩu mới phải có ít nhất 6 ký tự.');
        }

        if ($newPassword !== $confirmPassword) {
            return $this->fail('Mật khẩu xác nhận không khớp.');
        }

        // Re-verify token before applying change
        $record = $this->userModel->findPasswordResetToken($email, $otp);
        if (!$record) {
            return $this->fail('Phiên đặt lại mật khẩu không hợp lệ hoặc đã hết hạn. Vui lòng thực hiện lại.');
        }

        $user = $this->userModel->findByEmail($email);
        if (!$user) {
            return $this->fail('Tài khoản không tồn tại.');
        }

        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $this->userModel->updatePassword($user['id'], $hash);
        $this->userModel->deletePasswordResetToken($email);

        $this->logger->log('Đổi mật khẩu', "User [{$email}] đã đặt lại mật khẩu thành công.", $user['full_name'], 'user');

        return [
            'success' => true,
            'message' => 'Đổi mật khẩu thành công! Vui lòng đăng nhập bằng mật khẩu mới.',
        ];
    }

    // -------------------------------------------------------------------------
    // PRIVATE HELPERS
    // -------------------------------------------------------------------------

    private function startSession(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name']  = $user['full_name'];
        $_SESSION['user_role']  = $user['role'];
    }

    private function resolveRedirect(string $role): string
    {
        $path = match ($role) {
            'admin', 'admin_monitor' => '/fe/admin/index.php',
            'staff'                  => '/fe/pages/staff.php',
            default                  => '/fe/pages/home.php',
        };
        return $this->getRedirectUrl($path);
    }

    private function getRedirectUrl(string $path): string
    {
        $base = dirname(dirname($_SERVER['SCRIPT_NAME']));
        $base = ($base === '/' || $base === '\\') ? '' : str_replace('\\', '/', $base);
        return $base . $path;
    }

    private function assignWelcomeVouchers(int $userId): void
    {
        $codes = ['SUMMER30', 'NEWUSER50', 'MOVIE20'];

        foreach ($codes as $code) {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM vouchers WHERE code = ? AND user_id IS NULL AND is_active = 1 LIMIT 1'
            );
            $stmt->execute([$code]);
            $template = $stmt->fetch();

            if (!$template) {
                continue;
            }

            $uniqueCode = $code . '-' . strtoupper(substr(uniqid(), -6));

            $this->pdo->prepare(
                'INSERT INTO vouchers (code, description, discount_pct, discount_amt, min_order, max_uses, used_count, expire_date, is_active, user_id)
                 VALUES (?, ?, ?, ?, ?, 1, 0, DATE_ADD(CURDATE(), INTERVAL 30 DAY), 1, ?)'
            )->execute([
                $uniqueCode,
                $template['description'],
                $template['discount_pct'],
                $template['discount_amt'],
                $template['min_order'],
                $userId,
            ]);
        }
    }

    private function fail(string $message): array
    {
        return ['success' => false, 'message' => $message];
    }
}