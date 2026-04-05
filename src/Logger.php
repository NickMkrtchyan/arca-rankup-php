<?php

declare(strict_types=1);

namespace ArCa;

class Logger
{
    private static string $logDir = '';

    private static function dir(): string
    {
        if (self::$logDir === '') {
            self::$logDir = dirname(__DIR__) . '/logs';
            if (!is_dir(self::$logDir)) {
                mkdir(self::$logDir, 0755, true);
            }
        }
        return self::$logDir;
    }

    private static function write(string $channel, string $level, string $message, array $context = []): void
    {
        $ts   = date('Y-m-d H:i:s');
        $ctx  = empty($context) ? '' : ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        $line = "[{$ts}] [{$level}] {$message}{$ctx}" . PHP_EOL;

        $file = self::dir() . "/{$channel}.log";
        file_put_contents($file, $line, FILE_APPEND | LOCK_EX);

        if (Config::get('APP_ENV') !== 'production') {
            echo $line;
        }
    }

    public static function info(string $channel, string $msg, array $ctx = []): void
    {
        self::write($channel, 'INFO', $msg, $ctx);
    }

    public static function error(string $channel, string $msg, array $ctx = []): void
    {
        self::write($channel, 'ERROR', $msg, $ctx);
        self::write('error', 'ERROR', "[{$channel}] {$msg}", $ctx);
    }

    public static function warn(string $channel, string $msg, array $ctx = []): void
    {
        self::write($channel, 'WARN', $msg, $ctx);
    }

    // Convenience channel shortcuts
    public static function arca(string $msg, array $ctx = []): void   { self::info('arca', $msg, $ctx); }
    public static function order(string $msg, array $ctx = []): void  { self::info('order', $msg, $ctx); }
    public static function event(string $msg, array $ctx = []): void  { self::info('event', $msg, $ctx); }
    public static function cancel(string $msg, array $ctx = []): void { self::info('cancel', $msg, $ctx); }
    public static function cron(string $msg, array $ctx = []): void   { self::info('cron', $msg, $ctx); }
}
