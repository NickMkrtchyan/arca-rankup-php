<?php

declare(strict_types=1);

namespace ArCa\Workers;

use ArCa\Config;
use ArCa\DB;
use ArCa\Logger;
use ArCa\Services\Shopify;

class Autopurge
{
    public static function run(): void
    {
        $minutes = Config::app()['cancel_after_minutes'];
        $cutoff  = date('Y-m-d H:i:s', strtotime("-{$minutes} minutes"));

        $stale = DB::fetchAll(
            "SELECT o.id AS db_id, o.orderid
             FROM orders o
             JOIN transaction t ON t.orderid = o.orderid
             WHERE o.status = 0
               AND t.trstatus = 'pending'
               AND o.created < ?",
            [$cutoff]
        );

        if (empty($stale)) {
            Logger::cron("Autopurge: nothing to clean");
            return;
        }

        Logger::cron("Autopurge: found " . count($stale) . " stale orders");
        $shopify = new Shopify();

        foreach ($stale as $row) {
            $shopifyId = $row['orderid'];
            try {
                DB::update('transaction', ['trstatus' => 'deleted'],  'orderid = ? AND trstatus = ?', [$shopifyId, 'pending']);
                DB::update('orders',     ['status'   => 4],           'orderid = ?',                  [$shopifyId]);

                $shopify->cancelOrder($shopifyId);
                $shopify->deleteOrder($shopifyId);

                Logger::cron("Autopurge: purged order#{$shopifyId}");
            } catch (\Throwable $e) {
                Logger::error('cron', "Autopurge failed for order#{$shopifyId}: " . $e->getMessage());
            }
        }
    }
}
