<?php

declare(strict_types=1);

namespace ArCa\Routes;

use ArCa\Config;
use ArCa\DB;

class Api
{
    public static function stats(): void
    {
        header('Content-Type: application/json');
        try {
            $totals = DB::fetch(
                "SELECT
                    COUNT(*) AS total,
                    SUM(status = 0) AS pending,
                    SUM(status IN (2,3)) AS captured,
                    SUM(status = 4) AS expired
                 FROM orders"
            );

            $revenue = DB::fetch("SELECT COALESCE(SUM(price),0) AS total_amd FROM transaction WHERE trstatus IN ('authorized','captured')");

            $recent = DB::fetchAll(
                "SELECT t.externaltrid, t.trstatus, t.price, t.created, o.orderid, o.email
                 FROM transaction t
                 JOIN orders o ON o.orderid = t.orderid
                 ORDER BY t.id DESC
                 LIMIT 20"
            );

            echo json_encode(['totals' => $totals, 'revenue' => $revenue, 'recent' => $recent]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public static function config(): void
    {
        header('Content-Type: application/json');
        $arca = Config::arca();
        $shop = Config::shopify();
        echo json_encode([
            'arca'               => ['authMode' => $arca['auth_mode'], 'baseUrl' => $arca['base_url']],
            'currency'           => Config::currency(),
            'shopify'            => ['store' => $shop['store'], 'apiVersion' => $shop['api_version']],
            'cancelAfterMinutes' => Config::app()['cancel_after_minutes'],
        ]);
    }

    public static function health(): void
    {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok', 'ts' => date('c'), 'env' => Config::app()['env']]);
    }
}
