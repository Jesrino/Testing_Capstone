<?php
// Lightweight SMTP wrapper with retry using PHPMailer if installed, otherwise fallback to mail().

class SMTPMailer {
    private $settings;

    public function __construct($settings = []) {
        $this->settings = $settings;
    }

    // Send with retries (exponential backoff)
    public function send($to, $subject, $body, $fromEmail = null, $fromName = null, $attempts = 3) {
        $delay = 1; // seconds
        for ($i = 0; $i < $attempts; $i++) {
            $ok = $this->trySend($to, $subject, $body, $fromEmail, $fromName);
            if ($ok) return true;
            sleep($delay);
            $delay *= 2;
        }
        return false;
    }

    private function trySend($to, $subject, $body, $fromEmail = null, $fromName = null) {
        // Prefer PHPMailer if available
        if (class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
            try {
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                // Server settings
                $cfg = $this->settings;
                if (!empty($cfg['host'])) {
                    $mail->isSMTP();
                    $mail->Host = $cfg['host'];
                    $mail->Port = $cfg['port'] ?? 587;
                    $mail->SMTPAuth = !empty($cfg['username']);
                    if ($mail->SMTPAuth) {
                        $mail->Username = $cfg['username'];
                        $mail->Password = $cfg['password'];
                    }
                    if (!empty($cfg['encryption'])) {
                        $mail->SMTPSecure = $cfg['encryption'];
                    }
                }

                $mail->setFrom($fromEmail ?? ($cfg['from_email'] ?? 'no-reply@localhost'), $fromName ?? ($cfg['from_name'] ?? 'Dents-City'));
                $mail->addAddress($to);
                $mail->Subject = $subject;
                $mail->Body = $body;
                $mail->isHTML(false);
                $mail->CharSet = 'UTF-8';

                $mail->send();
                return true;
            } catch (Exception $e) {
                error_log('PHPMailer send failed: ' . $e->getMessage());
                return false;
            }
        }

        // Fallback to PHP mail()
        $headers = 'From: ' . ($fromEmail ?? 'no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost')) . "\r\n" .
                   'Reply-To: ' . ($fromEmail ?? 'no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost')) . "\r\n" .
                   'X-Mailer: PHP/' . phpversion();
        return @mail($to, $subject, $body, $headers);
    }
}
