<?php

declare(strict_types=1);

namespace ArCa\Services;

use ArCa\Config;
use ArCa\Logger;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class Shopify
{
    private Client $http;
    private string $store;
    private string $token;
    private string $apiVersion;

    public function __construct()
    {
        $cfg = Config::shopify();
        $this->store      = $cfg['store'];
        $this->token      = $cfg['access_token'];
        $this->apiVersion = $cfg['api_version'];
        $this->http       = new Client([
            'base_uri' => "https://{$this->store}/admin/api/{$this->apiVersion}/",
            'timeout'  => 20,
            'headers'  => [
                'X-Shopify-Access-Token' => $this->token,
                'Content-Type'           => 'application/json',
                'Accept'                 => 'application/json',
            ],
        ]);
    }

    public function getOrder(string $orderId): array
    {
        try {
            $res  = $this->http->get("orders/{$orderId}.json");
            $data = $this->decode($res);
            return $data['order'] ?? [];
        } catch (GuzzleException $e) {
            Logger::error('event', "getOrder#{$orderId}: " . $e->getMessage());
            throw new \RuntimeException("Shopify getOrder failed: " . $e->getMessage(), 0, $e);
        }
    }

    public function createTransaction(string $orderId, string $kind, string $status, float $amount, string $currency, string $gateway = 'ArCa', ?string $authCode = null): array
    {
        $body = ['transaction' => [
            'kind'     => $kind,
            'status'   => $status,
            'amount'   => number_format($amount, 2, '.', ''),
            'currency' => $currency,
            'gateway'  => $gateway,
        ]];

        if ($authCode) {
            $body['transaction']['authorization'] = $authCode;
        }

        try {
            $res  = $this->http->post("orders/{$orderId}/transactions.json", ['json' => $body]);
            $data = $this->decode($res);
            Logger::event("createTransaction | order#{$orderId} | kind={$kind} status={$status}", ['id' => $data['transaction']['id'] ?? null]);
            return $data['transaction'] ?? [];
        } catch (GuzzleException $e) {
            Logger::error('event', "createTransaction#{$orderId}: " . $e->getMessage());
            throw new \RuntimeException("Shopify createTransaction failed: " . $e->getMessage(), 0, $e);
        }
    }

    public function cancelOrder(string $orderId): bool
    {
        try {
            $this->http->post("orders/{$orderId}/cancel.json");
            Logger::cancel("cancelOrder#{$orderId} OK");
            return true;
        } catch (GuzzleException $e) {
            Logger::error('cancel', "cancelOrder#{$orderId}: " . $e->getMessage());
            return false;
        }
    }

    public function deleteOrder(string $orderId): bool
    {
        try {
            $this->http->delete("orders/{$orderId}.json");
            Logger::cancel("deleteOrder#{$orderId} OK");
            return true;
        } catch (GuzzleException $e) {
            Logger::error('cancel', "deleteOrder#{$orderId}: " . $e->getMessage());
            return false;
        }
    }

    public function verifyWebhook(string $rawBody, string $hmacHeader): bool
    {
        $secret = Config::shopify()['webhook_secret'];
        $digest = base64_encode(hash_hmac('sha256', $rawBody, $secret, true));
        return hash_equals($digest, $hmacHeader);
    }

    private function decode($response): array
    {
        return json_decode((string) $response->getBody(), true) ?? [];
    }
}
