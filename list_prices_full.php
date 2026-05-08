<?php
require_once __DIR__ . '/lib/config.php';
$env = Config::get();
$secret_key = $env['STRIPE_SECRET_KEY'];

function listStripePrices($secret_key) {
    $ch = curl_init("https://api.stripe.com/v1/prices?expand[]=data.product&limit=100");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, $secret_key . ':');
    $response = curl_exec($ch);
    $data = json_decode($response, true);
    curl_close($ch);
    return $data;
}

$prices = listStripePrices($secret_key);
foreach ($prices['data'] as $price) {
    $product_name = $price['product']['name'] ?? 'Unknown';
    $nickname = $price['nickname'] ?? 'No Nickname';
    $amount = $price['unit_amount'] ?? 0;
    echo "ID: {$price['id']} | Product: {$product_name} | Amount: {$amount}\n";
}
