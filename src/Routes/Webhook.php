<?php

declare(strict_types=1);

namespace ArCa\Routes;

use ArCa\DB;
use ArCa\Logger;
use ArCa\Services\ArCa;
use ArCa\Services\Shopify;

class Webhook
{
    public static function handleCancel(): void
    {
        $rawBody = file_get_contents('php://input');
        $hmac    = $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'] ?? '';
        $topic   = $_SERVER['HTTP_X_SHOPIFY_TOPIC']       ?? '';

        if (!(new Shopify())->verifyWebhook($rawBody, $hmac)) {
            Logger::error('cancel', 'HMAC verification failed');
            http_response_code(401);
            return;
        }

        $data = json_decode($rawBody, true) ?? [];
        http_response_code(200);
        echo 'ok';
        fastcgi_finish_request_safe();

        try {
            $shopifyId = (string) ($data['id'] ?? '');
            $kind      = $data['kind'] ?? '';

            Logger::cancel("Webhook | topic={$topic} | shopifyId={$shopifyId}");

            $tx = DB::fetch(
                'SELECT t.* FROM transaction t WHERE t.orderid = ? AND t.trstatus IN (\'authorized\',\'captured\') ORDER BY t.id DESC LIMIT 1',
                [$shopifyId]
            );

            if (!$tx) {
                Logger::warn('cancel', "No active transaction for shopifyId={$shopifyId}");
                return;
            }

            $arca        = new ArCa();
            $arcaOrderId = $tx['externaltrid'];
            $status      = $arca->getStatus($arcaOrderId);
            $state       = $status['paymentState'] ?? '';

            if ($topic === 'refunds/create') {
                // Refund captured payment
                $refundAmount = (float) ($data['refund_line_items'][0]['subtotal'] ?? $data['transactions'][0]['amount'] ?? 0);
                $amtKopeks    = (int) round($refundAmount * 100);
                $arca->refund($arcaOrderId, $amtKopeks);
                DB::update('transaction', ['trstatus' => 'canceled', 'program' => 4], 'externaltrid = ?', [$arcaOrderId]);
                DB::update('orders', ['status' => 4], 'orderid = ?', [$shopifyId]);
                Logger::cancel("Refunded | arcaId={$arcaOrderId} | amt={$refundAmount}");

            } else {
                // Reverse authorized payment
                if ($state === 'APPROVED') {
                    $arca->reverse($arcaOrderId);
                } else {
                    $arca->refund($arcaOrderId, (int) round($tx['price'] * 100));
                }
                DB::update('transaction', ['trstatus' => 'canceled', 'program' => 4], 'externaltrid = ?', [$arcaOrderId]);
                DB::update('orders', ['status' => 4], 'orderid = ?', [$shopifyId]);
                Logger::cancel("Reversed | arcaId={$arcaOrderId}");
            }

        } catch (\Throwable $e) {
            Logger::error('cancel', 'Webhook cancel failed: ' . $e->getMessage());
        }
    }

    public static function handleCapture(): void
    {
        $rawBody = file_get_contents('php://input');
        $hmac    = $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'] ?? '';

        if (!(new Shopify())->verifyWebhook($rawBody, $hmac)) {
            Logger::error('capture', 'HMAC verification failed');
            http_response_code(401);
            return;
        }

        $data = json_decode($rawBody, true) ?? [];
        http_response_code(200);
        echo 'ok';
        fastcgi_finish_request_safe();

        try {
            $shopifyId = (string) ($data['id'] ?? '');
            Logger::info('capture', "Webhook capture | shopifyId={$shopifyId}");

            $tx = DB::fetch(
                'SELECT * FROM transaction WHERE orderid = ? AND trstatus = \'authorized\' ORDER BY id DESC LIMIT 1',
                [$shopifyId]
            );

            if (!$tx) {
                Logger::warn('capture', "No authorized transaction for shopifyId={$shopifyId}");
                return;
            }

            $arca   = new ArCa();
            $result = $arca->deposit($tx['externaltrid'], (int) round($tx['price'] * 100));

            if (($result['errorCode'] ?? '1') === '0') {
                DB::update('transaction', ['trstatus' => 'captured', 'program' => 3], 'id = ?', [$tx['id']]);
                DB::update('orders', ['status' => 3], 'orderid = ?', [$shopifyId]);
                Logger::info('capture', "Deposited OK | arcaId={$tx['externaltrid']}");
            } else {
                // Queue for retry
                $existing = DB::fetch('SELECT id FROM process WHERE externaltrid = ?', [$tx['externaltrid']]);
                if (!$existing) {
                    DB::insert('process', [
                        'externaltrid' => $tx['externaltrid'],
                        'shopify_id'   => $shopifyId,
                        'amount'       => (int) round($tx['price'] * 100),
                        'retry'        => 0,
                        'created'      => date('Y-m-d H:i:s'),
                    ]);
                }
                Logger::warn('capture', "Deposit failed, queued for retry | arcaId={$tx['externaltrid']}");
            }

        } catch (\Throwable $e) {
            Logger::error('capture', 'Webhook capture failed: ' . $e->getMessage());
        }
    }
}

// Graceful finish for non-FPM environments
function fastcgi_finish_request_safe(): void
{
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
}
