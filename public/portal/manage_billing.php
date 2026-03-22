<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/config.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: /portal/login");
    exit;
}

$env = Config::get();
\Stripe\Stripe::setApiKey($env['STRIPE_SECRET_KEY']);

try {
    $db = DB::get();
    $stmt = $db->prepare("SELECT stripe_customer_id FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $customer_id = $stmt->fetchColumn();

    if (!$customer_id) {
        die("決済情報が見つかりません。プランに加入してからお試しください。");
    }

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $return_url = $protocol . $host . '/portal/';

    $session = \Stripe\BillingPortal\Session::create([
        'customer' => $customer_id,
        'return_url' => $return_url,
    ]);

    header("Location: " . $session->url);
    exit;

} catch (Exception $e) {
    echo "エラー: " . htmlspecialchars($e->getMessage());
}
