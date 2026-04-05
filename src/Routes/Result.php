<?php

declare(strict_types=1);

namespace ArCa\Routes;

use ArCa\DB;
use ArCa\Logger;
use ArCa\Services\ArCa;
use ArCa\Services\Shopify;

class Result
{
    public static function handle(): void
    {
        $arcaOrderId = trim($_GET['orderId'] ?? '');
        $lang        = preg_replace('/[^a-z]/i', '', $_GET['lang'] ?? 'en');

        if (!$arcaOrderId) {
            http_response_code(400);
            self::renderResult('error', 'Missing orderId parameter.', null);
            return;
        }

        $tx = DB::fetch(
            'SELECT t.*, o.status_url, o.orderid AS shopify_orderid
             FROM transaction t
             JOIN orders o ON o.orderid = t.orderid
             WHERE t.externaltrid = ?
             LIMIT 1',
            [$arcaOrderId]
        );

        if (!$tx) {
            Logger::error('order', "Result: transaction not found | arcaId={$arcaOrderId}");
            self::renderResult('error', 'Transaction not found.', null);
            return;
        }

        if ($tx['trstatus'] === 'captured' || $tx['trstatus'] === 'authorized') {
            self::renderResult('success', 'Payment already processed.', $tx['status_url']);
            return;
        }

        try {
            $arca   = new ArCa();
            $status = $arca->getStatus($arcaOrderId, $lang);

            $paymentState = $status['paymentState'] ?? 'UNKNOWN';
            $errorCode    = $status['errorCode']    ?? '1';
            $shopifyId    = $tx['shopify_orderid'];
            $currency     = $status['currency']     ?? 'AMD';
            $amount       = (float) (($status['amount'] ?? 0) / 100);

            $authCode = $status['authCode']
                ?? ($status['cardAuthInfo']['approvalCode'] ?? null);

            Logger::order("Result | arcaId={$arcaOrderId} | state={$paymentState}");

            // ── Save transaction details ────────────────────────────────────
            if (!empty($status['cardAuthInfo'])) {
                $card = $status['cardAuthInfo'];
                $existing = DB::fetch('SELECT id FROM transaction_details WHERE externaltrid = ?', [$arcaOrderId]);
                if (!$existing) {
                    DB::insert('transaction_details', [
                        'externaltrid'  => $arcaOrderId,
                        'pan'           => $card['pan']            ?? '',
                        'cardholderName'=> $card['cardholderName'] ?? '',
                        'approvalCode'  => $card['approvalCode']   ?? '',
                        'cardBrand'     => $card['cardBrand']      ?? '',
                        'bankName'      => $status['merchantOrderParams'][0]['value'] ?? '',
                        'ip'            => $_SERVER['REMOTE_ADDR'] ?? '',
                    ]);
                }
            }

            // ── Process by payment state ────────────────────────────────────
            switch ($paymentState) {

                case 'APPROVED':
                    // PreAuth: authorized, awaiting capture
                    DB::update('transaction', ['trstatus' => 'authorized', 'program' => 2], 'externaltrid = ?', [$arcaOrderId]);
                    DB::update('orders', ['status' => 2], 'orderid = ?', [$shopifyId]);

                    (new Shopify())->createTransaction(
                        $shopifyId, 'authorization', 'success',
                        $amount, $currency, 'ArCa', $authCode
                    );

                    Logger::order("APPROVED | arcaId={$arcaOrderId} | shopifyId={$shopifyId}");
                    self::renderResult('success', 'Payment authorized successfully!', $tx['status_url']);
                    break;

                case 'DEPOSITED':
                    // Sale mode: charged immediately
                    DB::update('transaction', ['trstatus' => 'captured', 'program' => 3], 'externaltrid = ?', [$arcaOrderId]);
                    DB::update('orders', ['status' => 3], 'orderid = ?', [$shopifyId]);

                    (new Shopify())->createTransaction(
                        $shopifyId, 'sale', 'success',
                        $amount, $currency, 'ArCa', $authCode
                    );

                    Logger::order("DEPOSITED | arcaId={$arcaOrderId} | shopifyId={$shopifyId}");
                    self::renderResult('success', 'Payment completed successfully!', $tx['status_url']);
                    break;

                case 'DECLINED':
                    DB::update('transaction', ['trstatus' => 'declined', 'program' => 3], 'externaltrid = ?', [$arcaOrderId]);
                    DB::update('orders', ['status' => 4], 'orderid = ?', [$shopifyId]);

                    (new Shopify())->createTransaction(
                        $shopifyId, 'authorization', 'failure',
                        $amount, $currency, 'ArCa'
                    );

                    Logger::order("DECLINED | arcaId={$arcaOrderId}");
                    self::renderResult('declined', 'Payment was declined by your bank.', $tx['status_url']);
                    break;

                default:
                    Logger::warn('order', "Unknown paymentState={$paymentState} | arcaId={$arcaOrderId}");
                    self::renderResult('pending', 'Payment status is pending. Please check your order.', $tx['status_url']);
                    break;
            }

        } catch (\Throwable $e) {
            Logger::error('order', "Result error | arcaId={$arcaOrderId}: " . $e->getMessage());
            self::renderResult('error', 'An error occurred processing your payment.', null);
        }
    }

    private static function renderResult(string $type, string $message, ?string $statusUrl): void
    {
        require dirname(__DIR__, 2) . '/templates/result.php';
    }
}
