<?php

declare(strict_types=1);

namespace ArCa\Routes;

use ArCa\Config;
use ArCa\DB;
use ArCa\Logger;
use ArCa\Services\ArCa;
use ArCa\Services\Shopify;

class Payment
{
    public static function handle(): void
    {
        $orderId   = trim($_GET['orderid'] ?? '');
        $lang      = preg_replace('/[^a-z]/i', '', $_GET['lang'] ?? 'en');
        $statusUrl = $_GET['statusurl'] ?? '';

        if (!preg_match('/^\d+$/', $orderId)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid orderid']);
            return;
        }

        try {
            $shopify = new Shopify();
            $order   = $shopify->getOrder($orderId);

            if (empty($order)) {
                http_response_code(404);
                echo json_encode(['error' => 'Order not found']);
                return;
            }

            if (($order['financial_status'] ?? '') === 'paid') {
                http_response_code(409);
                echo json_encode(['error' => 'Order already paid']);
                return;
            }

            // ── Amount conversion ───────────────────────────────────────────
            $rawAmount   = (float) ($order['total_price'] ?? 0);
            $shopCurrency = strtoupper($order['currency'] ?? 'AMD');
            $cfg          = Config::currency();

            $amdAmount = $rawAmount;
            if ($shopCurrency !== 'AMD') {
                $amdAmount = match($shopCurrency) {
                    'EUR'   => $rawAmount * $cfg['eur_rate'],
                    'USD'   => $rawAmount * $cfg['usd_rate'],
                    default => $rawAmount,
                };
            }
            $amountKopeks = (int) round($amdAmount * 100);

            // ── Save to DB ──────────────────────────────────────────────────
            $email = $order['email'] ?? ($order['customer']['email'] ?? '');
            $phone = $order['phone'] ?? ($order['billing_address']['phone'] ?? '');

            $dbOrderId = DB::insert('orders', [
                'status'     => 0,
                'orderid'    => $orderId,
                'clientid'   => (string) ($order['customer']['id'] ?? ''),
                'email'      => $email,
                'phone'      => $phone,
                'created'    => date('Y-m-d H:i:s'),
                'price'      => round($amdAmount, 2),
                'currency'   => $cfg['default'],
                'status_url' => $statusUrl,
            ]);

            Logger::order("New order | shopifyId={$orderId} | amd={$amdAmount} | dbId={$dbOrderId}");

            // ── Register with ArCa ──────────────────────────────────────────
            $appUrl    = rtrim(Config::app()['url'], '/');
            $returnUrl = "{$appUrl}/result";
            $desc      = "Shopify Order #{$order['order_number']}";

            $arca   = new ArCa();
            $result = $arca->register(
                orderNumber:  (string) $dbOrderId,
                amountKopeks: $amountKopeks,
                returnUrl:    $returnUrl,
                lang:         $lang,
                description:  $desc,
                email:        $email ?: null
            );

            $arcaOrderId = $result['orderId']  ?? null;
            $formUrl     = $result['formUrl']  ?? null;

            if (!$arcaOrderId || !$formUrl) {
                throw new \RuntimeException('ArCa did not return orderId/formUrl');
            }

            // ── Save transaction ────────────────────────────────────────────
            DB::insert('transaction', [
                'status'       => 0,
                'orderid'      => $orderId,
                'externaltrid' => $arcaOrderId,
                'redirect'     => $formUrl,
                'price'        => round($amdAmount, 2),
                'trstatus'     => 'pending',
                'gateway'      => 'ArCa',
                'program'      => 1,
                'lang'         => $lang,
            ]);

            // Mark order registered
            DB::update('orders', ['status' => 1], 'id = ?', [$dbOrderId]);

            Logger::order("Registered | arcaId={$arcaOrderId} | redirect to ArCa");

            header("Location: {$formUrl}");
            exit;

        } catch (\Throwable $e) {
            Logger::error('order', "Payment init failed | order#{$orderId}: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Payment initialization failed. Please try again.']);
        }
    }
}
