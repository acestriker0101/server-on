<?php
class Config {
    private static $settings = null;

    public static function get() {
        if (self::$settings === null) {
            $paths = [
                '/var/www/.env',
                '/var/www/html/.env',
                '/opt/server/.env',
                realpath(__DIR__ . '/../.env'),
                realpath(__DIR__ . '/../../.env')
            ];

            foreach ($paths as $path) {
                if ($path && file_exists($path)) {
                    self::$settings = parse_ini_file($path);
                    break;
                }
            }

            if (!self::$settings) {
                throw new Exception("Config file (.env) not found.");
            }
        }
        return self::$settings;
    }
}
