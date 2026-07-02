<?php

class MailService
{
    private bool   $devMode;
    private string $smtpHost;
    private int    $smtpPort;
    private string $smtpUser;
    private string $smtpPass;
    private string $fromEmail;
    private string $fromName;

    public function __construct()
    {
        // DEV_MODE = true: skip actual SMTP, log OTP to error_log instead
        $this->devMode = false;
        $this->smtpHost  = getenv('MAIL_HOST')     ?: 'smtp.gmail.com';
        $this->smtpPort  = (int)(getenv('MAIL_PORT') ?: 587);
        $this->smtpUser  = getenv('MAIL_USERNAME') ?: '';
        $this->smtpPass  = getenv('MAIL_PASSWORD') ?: '';
        $this->fromEmail = getenv('MAIL_FROM')     ?: 'noreply@movieflex.vn';
        $this->fromName  = getenv('MAIL_FROM_NAME') ?: 'MovieFlex';
    }

    // -------------------------------------------------------------------------
    // PUBLIC METHODS
    // -------------------------------------------------------------------------

    public function sendPasswordReset(string $toEmail, string $toName, string $otp): bool
    {
        $subject = '[MovieFlex] Mã xác nhận đặt lại mật khẩu';
        $body    = $this->buildOtpEmailHtml($toName, $otp);

        return $this->send($toEmail, $toName, $subject, $body);
    }

    public function sendBookingConfirmation(string $toEmail, string $toName, array $booking): bool
    {
        $subject = "[MovieFlex] Xác nhận đặt vé #{$booking['booking_code']}";
        $body    = $this->buildBookingEmailHtml($toName, $booking);

        return $this->send($toEmail, $toName, $subject, $body);
    }

    // -------------------------------------------------------------------------
    // PRIVATE: SEND
    // -------------------------------------------------------------------------

    private function send(string $toEmail, string $toName, string $subject, string $htmlBody): bool
    {
        if ($this->devMode) {
            error_log("[MailService DEV] To: {$toEmail} | Subject: {$subject} | Body: {$htmlBody}");
            return true;
        }

        // Production: use PHPMailer (requires composer install)
        $autoload = __DIR__ . '/../../vendor/autoload.php';
        if (!file_exists($autoload)) {
            error_log('[MailService] vendor/autoload.php not found. Run composer install.');
            return false;
        }

        require_once $autoload;

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = $this->smtpHost;
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->smtpUser;
            $mail->Password   = $this->smtpPass;
            $mail->SMTPSecure = $this->smtpPort === 465 ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $this->smtpPort;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom($this->fromEmail, $this->fromName);
            $mail->addAddress($toEmail, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;

            $mail->send();
            return true;
        } catch (\Exception $e) {
            error_log('[MailService] Send failed: ' . $e->getMessage());
            return false;
        }
    }

    // -------------------------------------------------------------------------
    // PRIVATE: EMAIL TEMPLATES
    // -------------------------------------------------------------------------

    private function buildOtpEmailHtml(string $name, string $otp): string
    {
        return <<<HTML
        <div style="font-family:Inter,sans-serif;max-width:480px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08)">
          <div style="background:linear-gradient(135deg,#3B82F6,#6366F1);padding:32px;text-align:center">
            <h1 style="color:#fff;margin:0;font-size:24px;font-weight:800">MovieFlex</h1>
          </div>
          <div style="padding:32px">
            <p style="color:#0F172A;font-size:16px;font-weight:600;margin-bottom:8px">Xin chào {$name},</p>
            <p style="color:#64748B;font-size:14px;line-height:1.6;margin-bottom:24px">
              Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn. Dưới đây là mã OTP của bạn:
            </p>
            <div style="background:#F0F4FF;border:2px dashed #3B82F6;border-radius:10px;padding:20px;text-align:center;margin-bottom:24px">
              <span style="font-size:36px;font-weight:800;letter-spacing:8px;color:#3B82F6">{$otp}</span>
            </div>
            <p style="color:#94A3B8;font-size:12px;text-align:center">
              Mã có hiệu lực trong <strong>15 phút</strong>. Không chia sẻ mã này với bất kỳ ai.
            </p>
          </div>
        </div>
        HTML;
    }

    private function buildBookingEmailHtml(string $name, array $booking): string
    {
        $code  = htmlspecialchars($booking['booking_code'] ?? '');
        $movie = htmlspecialchars($booking['movie_title']  ?? '');
        $total = number_format($booking['total_amount']    ?? 0) . '₫';

        return <<<HTML
        <div style="font-family:Inter,sans-serif;max-width:480px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08)">
          <div style="background:linear-gradient(135deg,#3B82F6,#6366F1);padding:32px;text-align:center">
            <h1 style="color:#fff;margin:0;font-size:24px;font-weight:800">MovieFlex</h1>
          </div>
          <div style="padding:32px">
            <p style="color:#0F172A;font-size:16px;font-weight:600;margin-bottom:8px">Xin chào {$name},</p>
            <p style="color:#64748B;font-size:14px;line-height:1.6;margin-bottom:24px">
              Đặt vé của bạn đã được xác nhận thành công!
            </p>
            <div style="background:#F8FAFC;border-radius:10px;padding:20px;margin-bottom:24px">
              <p style="margin:0 0 8px;font-size:13px;color:#64748B">Mã đặt vé</p>
              <p style="margin:0 0 16px;font-size:20px;font-weight:800;color:#3B82F6">{$code}</p>
              <p style="margin:0 0 4px;font-size:13px;color:#64748B">Phim</p>
              <p style="margin:0 0 16px;font-size:15px;font-weight:600;color:#0F172A">{$movie}</p>
              <p style="margin:0 0 4px;font-size:13px;color:#64748B">Tổng tiền</p>
              <p style="margin:0;font-size:18px;font-weight:800;color:#10B981">{$total}</p>
            </div>
            <p style="color:#94A3B8;font-size:12px;text-align:center">
              Vui lòng xuất trình mã đặt vé khi đến rạp. Cảm ơn bạn đã sử dụng MovieFlex!
            </p>
          </div>
        </div>
        HTML;
    }
}