<?php
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/config.php';

$payload = @file_get_contents('php://input');
$event = json_decode($payload, true);

if (!$event || !isset($event['type'])) {
    http_response_code(400);
    exit;
}

$env = Config::get();
$db = DB::get();

// Price ID -> Column/Rank mapping
function getPriceMap($env) {
    return [
        $env['STRIPE_PRICE_INVENTORY_BASIC']    => ['col' => 'plan_rank', 'rank' => 1],
        $env['STRIPE_PRICE_INVENTORY_STANDARD'] => ['col' => 'plan_rank', 'rank' => 2],
        $env['STRIPE_PRICE_INVENTORY_PRO']      => ['col' => 'plan_rank', 'rank' => 3],

        $env['STRIPE_PRICE_ATTENDANCE_BASIC']    => ['col' => 'attendance_plan_rank', 'rank' => 1],
        $env['STRIPE_PRICE_ATTENDANCE_STANDARD'] => ['col' => 'attendance_plan_rank', 'rank' => 2],
        $env['STRIPE_PRICE_ATTENDANCE_PRO']      => ['col' => 'attendance_plan_rank', 'rank' => 3],

        $env['STRIPE_PRICE_EQUIPMENT_STANDARD']  => ['col' => 'equipment_plan_rank', 'rank' => 2],
    ];
}

switch ($event['type']) {
    // 1. 初回決済完了時
    case 'checkout.session.completed':
        $session = $event['data']['object'];
        $user_id = $session['client_reference_id'] ?? null;
        $customer_id = $session['customer'] ?? null;
        $plan_rank = isset($session['metadata']['plan_rank']) ? (int)$session['metadata']['plan_rank'] : 0;
        $app_name = $session['metadata']['app_name'] ?? 'inventory';

        if ($user_id && $plan_rank > 0) {
            $col = 'plan_rank';
            if ($app_name === 'attendance') $col = 'attendance_plan_rank';
            if ($app_name === 'equipment') $col = 'equipment_plan_rank';

            $stmt = $db->prepare("UPDATE users SET {$col} = ?, stripe_customer_id = ?, trial_ends_at = NULL WHERE id = ?");
            $stmt->execute([$plan_rank, $customer_id, $user_id]);
            error_log("Webhook[completed]: UserID {$user_id} updated {$col} to Rank {$plan_rank}");
        }
        break;

    // 2. プラン変更（移行）時
    case 'customer.subscription.updated':
        $subscription = $event['data']['object'];
        $customer_id = $subscription['customer'];
        
        $price_map = getPriceMap($env);
        $price_id = $subscription['items']['data'][0]['price']['id'] ?? '';
        $map = $price_map[$price_id] ?? null;

        if ($map !== null) {
            $col = $map['col'];
            $new_rank = $map['rank'];
            $stmt = $db->prepare("UPDATE users SET {$col} = ? WHERE stripe_customer_id = ?");
            $stmt->execute([$new_rank, $customer_id]);
            error_log("Webhook[updated]: Customer {$customer_id} changed {$col} to Rank {$new_rank}");
        }
        break;

    // 3. 解約（サブスクリプション終了）時
    case 'customer.subscription.deleted':
        $subscription = $event['data']['object'];
        $customer_id = $subscription['customer'];

        $price_map = getPriceMap($env);
        $price_id = $subscription['items']['data'][0]['price']['id'] ?? '';
        $map = $price_map[$price_id] ?? null;

        if ($map !== null) {
            $col = $map['col'];
            $stmt = $db->prepare("UPDATE users SET {$col} = 0 WHERE stripe_customer_id = ?");
            $stmt->execute([$customer_id]);
            error_log("Webhook[deleted]: Subscription ended for Customer {$customer_id}, {$col} reset to 0");
        }
        break;
}

http_response_code(200);
