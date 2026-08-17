<?php
/**
 * FPL-BOT - Configuration Loader & Helpers
 */

// Set Timezone default (WIB / UTC)
if (!defined('FPL_TIMEZONE_SET')) {
    date_default_timezone_set('Asia/Jakarta');
    define('FPL_TIMEZONE_SET', true);
}

if (!function_exists('loadEnv')) {
    function loadEnv($path = __DIR__ . '/../.env') {
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }

            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                // Hapus tanda kutip jika ada
                $value = trim($value, "\"'");

                if (!array_key_exists($key, $_SERVER) && !array_key_exists($key, $_ENV)) {
                    putenv(sprintf('%s=%s', $key, $value));
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                }
            }
        }
    }
}

// Muat .env
loadEnv();

if (!function_exists('env')) {
    function env($key, $default = null) {
        $val = getenv($key);
        if ($val === false) {
            return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
        }
        return $val;
    }
}

// Global App Config Array
return [
    'db' => [
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '3306'),
        'name' => env('DB_NAME', 'fpl_bot'),
        'user' => env('DB_USER', 'root'),
        'pass' => env('DB_PASS', ''),
    ],
    'fpl' => [
        'email' => env('FPL_EMAIL', ''),
        'password' => env('FPL_PASSWORD', ''),
        'team_id' => env('FPL_TEAM_ID', ''),
    ],
    'cron' => [
        'secret_token' => env('CRON_SECRET_TOKEN', 'fpl_bot_secret_token_12345'),
        'fallback_minutes' => 30, // H-30 menit sebelum deadline
    ],
    'ai' => [
        'gemini_api_key' => env('GEMINI_API_KEY', ''),
    ]
];
