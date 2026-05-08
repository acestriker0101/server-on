<?php
require_once __DIR__ . '/config.php';

class DB {
    private static $instance = null;
    public static function get() {
        if (self::$instance === null) {
            $env = Config::get();
            $dsn = "mysql:host={$env['DB_HOST']};dbname={$env['DB_NAME']};charset=utf8mb4";
            
            try {
                date_default_timezone_set('Asia/Tokyo');
                self::$instance = new PDO($dsn, $env['DB_USER'], $env['DB_PASS'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT => 3 // 早めにタイムアウト
                ]);
                self::$instance->exec("SET time_zone = '+09:00'");
            } catch (PDOException $e) {
                // ローカル（server-db-1等）へ繋がらない場合は、SSHトンネル経由へフォールバック
                try {
                    $fallbackHost = '127.0.0.1';
                    $fallbackPort = 3306; // SSHトンネルのローカルポートに合わせて 3306 に変更
                    $dsnFallback = "mysql:host={$fallbackHost};port={$fallbackPort};dbname={$env['DB_NAME']};charset=utf8mb4";
                    
                    self::$instance = new PDO($dsnFallback, $env['DB_USER'], $env['DB_PASS'], [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                    ]);
                    self::$instance->exec("SET time_zone = '+09:00'");
                } catch (PDOException $e2) {
                    throw new Exception("Database Connection Error (Local & Remote Failed): " . $e2->getMessage());
                }
            }
        }
        return self::$instance;
    }
}
