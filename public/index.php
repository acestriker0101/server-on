<?php
session_set_cookie_params(0, '/');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../lib/config.php';
$env = Config::get();

// URIの取得と整形
$uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

// 1. ログアウト処理
if ($uri === 'attendance_mgmt/logout') {
    $_SESSION = [];
    session_destroy();
    header("Location: /attendance_mgmt/login");
    exit;
}

if ($uri === 'portal/logout') {
    $_SESSION = [];
    session_destroy();
    header("Location: /");
    exit;
}

// 2. ログイン済みユーザーがトップ（/）に来た場合、/portal へリダイレクト
if ($uri === '' && isset($_SESSION['user_id'])) {
    header("Location: /portal/");
    exit;
}

// 3. 認証不要なページリスト
$public_pages = ['',
                 'tokushoho',
                 'privacy',
                 'portal/login',
                 'portal/register',
                 'portal/forgot',
                 'portal/activate',
                 'portal/reset',
                 'portal/webhook',
                 'portal/inventory-app',
                 'portal/equipment-app',
                 'portal/attendance-app',
                 'equipment_mgmt',
                 'attendance_mgmt',
                 'attendance_mgmt/login',
                 'attendance_mgmt/logout'];

//error_log("DEBUG: URI is [" . $uri . "]");

// 4. 未ログイン時のリダイレクト
if (!isset($_SESSION['user_id']) && !in_array($uri, $public_pages)) {
    header("Location: /portal/login");
    exit;
}

// 5. ルーティング定義 (URLパス => 物理ファイル)
$routes = [
    ''                    => 'landing.php',
    'tokushoho'           => 'tokushoho.php',
    'privacy'             => 'privacy.php',
    'portal'              => 'portal/index.php',
    'portal/admin'        => 'portal/admin.php',
    'portal/login'        => 'portal/login.php',
    'portal/register'     => 'portal/register.php',
    'portal/forgot'       => 'portal/forgot.php',
    'portal/activate'     => 'portal/activate.php',
    'portal/subscribe'    => 'portal/subscribe.php',
    'portal/reset'        => 'portal/reset.php',
    'portal/webhook'      => 'portal/webhook.php',
    'portal/inventory-app'=> 'portal/inventory-app.php',
    'portal/equipment-app'=> 'portal/equipment-app.php',
    'portal/attendance-app'=> 'portal/attendance-app.php',
    'equipment_mgmt'      => 'equipment_mgmt/index.php',
    'equipment_mgmt/details' => 'equipment_mgmt/details.php',
    'equipment_mgmt/consumables' => 'equipment_mgmt/consumables.php',
    'equipment_mgmt/analysis' => 'equipment_mgmt/analysis.php',
    'attendance_mgmt'     => 'attendance_mgmt/index.php',
    'attendance_mgmt/login'   => 'attendance_mgmt/login.php',
    'attendance_mgmt/staff'   => 'attendance_mgmt/staff_mgmt.php',
    'attendance_mgmt/requests' => 'attendance_mgmt/requests.php',
    'attendance_mgmt/settings' => 'attendance_mgmt/settings.php',
    'attendance_mgmt/leave' => 'attendance_mgmt/leave.php',
    'attendance_mgmt/reports' => 'attendance_mgmt/reports.php',
    'attendance_mgmt/shifts' => 'attendance_mgmt/shifts.php',
    'inventory'           => 'inventory/index.php',
    'inventory/status'    => 'inventory/status.php',
    'inventory/items'     => 'inventory/items.php',
    'inventory/suppliers' => 'inventory/suppliers.php',
    'inventory/analysis'  => 'inventory/analysis.php',
];

// 6. ルート分岐実行
if (array_key_exists($uri, $routes)) {
    $file = __DIR__ . '/' . $routes[$uri];
    if (file_exists($file)) {
        require_once $file;
    } else {
        // portal_view.php が直下にない場合、portal/ 内を探す
        $alt_file = __DIR__ . '/portal/' . $routes[$uri];
        if (file_exists($alt_file)) {
            require_once $alt_file;
        } else {
            echo "Error: 404 Not Found ($routes[$uri])";
        }
    }
} else {
    header("Location: /");
    exit;
}
