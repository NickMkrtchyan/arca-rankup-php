<?php

declare(strict_types=1);

namespace ArCa;

class Config
{
    private static array $cache = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }
        $value = $_ENV[$key] ?? getenv($key) ?: $default;
        self::$cache[$key] = $value;
        return $value;
    }

    public static function require(string $key): string
    {
        $value = self::get($key);
        if ($value === null || $value === '') {
            throw new \RuntimeException("Missing required config: {$key}");
        }
        return (string) $value;
    }

    // ── Grouped accessors ──────────────────────────────────────────────────

    public static function db(): array
    {
        return [
            'host'     => self::get('DB_HOST', 'localhost'),
            'port'     => (int) self::get('DB_PORT', 3306),
            'name'     => self::get('DB_NAME', 'arca_gateway'),
            'user'     => self::get('DB_USER', 'root'),
            'password' => self::get('DB_PASSWORD', ''),
        ];
    }

    public static function shopify(): array
    {
        return [
            'store'          => self::require('SHOPIFY_STORE'),
            'access_token'   => self::require('SHOPIFY_ACCESS_TOKEN'),
            'webhook_secret' => self::require('SHOPIFY_WEBHOOK_SECRET'),
            'api_version'    => self::get('SHOPIFY_API_VERSION', '2024-01'),
        ];
    }

    public static function arca(): array
    {
        return [
            'username'  => self::require('ARCA_USERNAME'),
            'password'  => self::require('ARCA_PASSWORD'),
            'base_url'  => self::get('ARCA_BASE_URL', 'https://ipay.arca.am/payment/rest'),
            'auth_mode' => (int) self::get('ARCA_AUTH_MODE', 1),
        ];
    }

    public static function currency(): array
    {
        return [
            'default'       => self::get('DEFAULT_CURRENCY', 'AMD'),
            'default_code'  => self::get('DEFAULT_CURRENCY_CODE', '051'),
            'shop_currency' => self::get('SHOP_CURRENCY', 'EUR'),
            'eur_rate'      => (float) self::get('EUR_RATE', 430),
            'usd_rate'      => (float) self::get('USD_RATE', 420),
        ];
    }

    public static function app(): array
    {
        return [
            'env'                  => self::get('APP_ENV', 'production'),
            'url'                  => self::get('APP_URL', ''),
            'cancel_after_minutes' => (int) self::get('CANCEL_AFTER_MINUTES', 30),
        ];
    }
}
