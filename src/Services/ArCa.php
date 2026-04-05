<?php

declare(strict_types=1);

namespace ArCa\Services;

use ArCa\Config;
use ArCa\Logger;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class ArCa
{
    private Client $http;
    private string $baseUrl;
    private string $username;
    private string $password;
    private int    $authMode;

    public function __construct()
    {
        $cfg = Config::arca();
        $this->baseUrl   = rtrim($cfg['base_url'], '/');
        $this->username  = $cfg['username'];
        $this->password  = $cfg['password'];
        $this->authMode  = $cfg['auth_mode'];
        $this->http      = new Client(['timeout' => 30, 'verify' => true]);
    }

    /**
     * Register a payment with ArCa.
     * Returns ['orderId' => uuid, 'formUrl' => redirect_url] on success.
     */
    public function register(
        string $orderNumber,
        int    $amountKopeks,
        string $returnUrl,
        string $lang = 'en',
        ?string $description = null,
        ?string $email = null
    ): array {
        $endpoint = $this->authMode === 1 ? 'registerPreAuth.do' : 'register.do';

        $params = [
            'userName'    => $this->username,
            'password'    => $this->password,
            'orderNumber' => $orderNumber,
            'amount'      => $amountKopeks,
            'currency'    => Config::get('DEFAULT_CURRENCY_CODE', '051'),
            'returnUrl'   => $returnUrl,
            'language'    => $lang,
        ];

        if ($description) $params['description'] = $description;
        if ($email)       $params['email']       = $email;

        $result = $this->call($endpoint, $params);
        Logger::arca("register.do | order#{$orderNumber} | amt={$amountKopeks}", $result);

        if (!empty($result['errorCode']) && $result['errorCode'] !== '0') {
            throw new \RuntimeException("ArCa register error {$result['errorCode']}: " . ($result['errorMessage'] ?? 'unknown'));
        }

        return $result;
    }

    /**
     * Get extended status for a registered transaction.
     */
    public function getStatus(string $arcaOrderId, string $lang = 'en'): array
    {
        $params = [
            'userName' => $this->username,
            'password' => $this->password,
            'orderId'  => $arcaOrderId,
            'language' => $lang,
        ];

        $result = $this->call('getOrderStatusExtended.do', $params);
        Logger::arca("getStatus | arcaId={$arcaOrderId}", ['paymentState' => $result['paymentState'] ?? null, 'errorCode' => $result['errorCode'] ?? null]);
        return $result;
    }

    /**
     * Capture (deposit) an authorized payment.
     */
    public function deposit(string $arcaOrderId, int $amountKopeks): array
    {
        $params = [
            'userName' => $this->username,
            'password' => $this->password,
            'orderId'  => $arcaOrderId,
            'amount'   => $amountKopeks,
        ];

        $result = $this->call('deposit.do', $params);
        Logger::arca("deposit.do | arcaId={$arcaOrderId} | amt={$amountKopeks}", $result);
        return $result;
    }

    /**
     * Reverse (void) an authorized payment before capture.
     */
    public function reverse(string $arcaOrderId): array
    {
        $params = [
            'userName' => $this->username,
            'password' => $this->password,
            'orderId'  => $arcaOrderId,
            'language' => 'en',
        ];

        $result = $this->call('reverse.do', $params);
        Logger::arca("reverse.do | arcaId={$arcaOrderId}", $result);
        return $result;
    }

    /**
     * Refund a deposited/captured payment.
     */
    public function refund(string $arcaOrderId, int $amountKopeks): array
    {
        $params = [
            'userName' => $this->username,
            'password' => $this->password,
            'orderId'  => $arcaOrderId,
            'amount'   => $amountKopeks,
        ];

        $result = $this->call('refund.do', $params);
        Logger::arca("refund.do | arcaId={$arcaOrderId} | amt={$amountKopeks}", $result);
        return $result;
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function call(string $endpoint, array $params): array
    {
        $url = "{$this->baseUrl}/{$endpoint}";

        try {
            $response = $this->http->get($url, ['query' => $params]);
            $body     = (string) $response->getBody();
            $data     = json_decode($body, true) ?? [];
            return $data;
        } catch (GuzzleException $e) {
            Logger::error('arca', "HTTP error calling {$endpoint}: " . $e->getMessage());
            throw new \RuntimeException("ArCa network error: " . $e->getMessage(), 0, $e);
        }
    }
}
