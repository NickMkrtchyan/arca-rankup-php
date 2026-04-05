<?php

declare(strict_types=1);

namespace ArCa\Workers;

use ArCa\DB;
use ArCa\Logger;
use ArCa\Services\ArCa;

class Autocapture
{
    private const MAX_RETRIES = 10;

    public static function run(): void
    {
        $queue = DB::fetchAll(
            "SELECT * FROM process WHERE retry < ? ORDER BY id ASC",
            [self::MAX_RETRIES]
        );

        if (empty($queue)) {
            Logger::cron("Autocapture: queue empty");
            return;
        }

        Logger::cron("Autocapture: processing " . count($queue) . " item(s)");
        $arca = new ArCa();

        foreach ($queue as $job) {
            try {
                $status = $arca->getStatus($job['externaltrid']);
                $state  = $status['paymentState'] ?? '';

                if ($state === 'DEPOSITED') {
                    DB::update('transaction', ['trstatus' => 'captured', 'program' => 3], 'externaltrid = ?', [$job['externaltrid']]);
                    DB::update('orders', ['status' => 3], 'orderid = ?', [$job['shopify_id']]);
                    DB::query('DELETE FROM process WHERE id = ?', [$job['id']]);
                    Logger::cron("Autocapture: already deposited | arcaId={$job['externaltrid']}");
                    continue;
                }

                if ($state === 'APPROVED') {
                    $result = $arca->deposit($job['externaltrid'], (int) $job['amount']);
                    if (($result['errorCode'] ?? '1') === '0') {
                        DB::update('transaction', ['trstatus' => 'captured', 'program' => 3], 'externaltrid = ?', [$job['externaltrid']]);
                        DB::update('orders', ['status' => 3], 'orderid = ?', [$job['shopify_id']]);
                        DB::query('DELETE FROM process WHERE id = ?', [$job['id']]);
                        Logger::cron("Autocapture: deposited OK | arcaId={$job['externaltrid']}");
                    } else {
                        DB::update('process', ['retry' => $job['retry'] + 1], 'id = ?', [$job['id']]);
                        Logger::warn('cron', "Autocapture: deposit failed retry={$job['retry']} | arcaId={$job['externaltrid']}");
                    }
                } else {
                    DB::update('process', ['retry' => $job['retry'] + 1], 'id = ?', [$job['id']]);
                    Logger::warn('cron', "Autocapture: unexpected state={$state} | arcaId={$job['externaltrid']}");
                }

            } catch (\Throwable $e) {
                DB::update('process', ['retry' => $job['retry'] + 1], 'id = ?', [$job['id']]);
                Logger::error('cron', "Autocapture error | arcaId={$job['externaltrid']}: " . $e->getMessage());
            }
        }
    }
}
