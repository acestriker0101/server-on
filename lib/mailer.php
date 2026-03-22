<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer {
    public static function send($to, $subject, $body) {
        $settings = Config::get();
        $mail = new PHPMailer(true);

        try {
            // SMTP設定
            $mail->isSMTP();
            $mail->Host       = $settings['SMTP_HOST'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $settings['SMTP_USER']; // 認証用(lightning1200...)
            $mail->Password   = $settings['SMTP_PASS'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $settings['SMTP_PORT'];
            $mail->CharSet    = 'UTF-8';

            // --- 修正ポイント ---
            // 第一引数を認証用ユーザー名ではなく、.envのSMTP_FROMに変更します
            $fromEmail = !empty($settings['SMTP_FROM']) ? $settings['SMTP_FROM'] : $settings['SMTP_USER'];
            $fromName  = !empty($settings['SMTP_FROM_NAME']) ? $settings['SMTP_FROM_NAME'] : 'SERVER-ON';
            
            $mail->setFrom($fromEmail, $fromName);
            // --------------------

            $mail->addAddress($to);

            // コンテンツ
            $mail->isHTML(false);
            $mail->Subject = $subject;
            $mail->Body    = $body;

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("PHPMailer Error: {$mail->ErrorInfo}");
            return false;
        }
    }
}
