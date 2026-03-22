<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Composerのオートロード読み込み
require_once __DIR__ . '/vendor/autoload.php';

/**
 * .envファイルをパースする簡易関数
 */
function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

// .envの読み込み
loadEnv(__DIR__ . '/.env');

$mail = new PHPMailer(true);

try {
    // サーバー設定
    $mail->isSMTP();
    $mail->Host       = $_ENV['SMTP_HOST'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $_ENV['SMTP_USER'];
    $mail->Password   = $_ENV['SMTP_PASS'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = $_ENV['SMTP_PORT'];
    $mail->CharSet    = 'UTF-8';

    // 送信元・宛先設定
    $mail->setFrom($_ENV['SMTP_FROM'], $_ENV['SMTP_FROM_NAME']);
    $mail->addAddress('ace_striker_0101@icloud.com'); // テスト用宛先

    // コンテンツ設定
    $mail->isHTML(true);
    $mail->Subject = '【SERVER-ON】SMTP送信テスト';
    $mail->Body    = 'このメールは .env の設定を利用して Gmail SMTP 経由で送信されました。';

    $mail->send();
    echo "✅ 送信成功しました！\n";
} catch (Exception $e) {
    echo "❌ 送信失敗: {$mail->ErrorInfo}\n";
}
