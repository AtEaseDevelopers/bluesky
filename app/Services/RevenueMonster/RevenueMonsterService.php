<?php

namespace App\Services\RevenueMonster;

/**
 * High-level Revenue Monster payment API.
 *
 * Thin, intention-revealing wrappers over {@see RevenueMonsterClient} covering
 * the payment endpoints (v3). Each method returns the unwrapped response item.
 *
 * @see https://doc.revenuemonster.my/docs/
 */
class RevenueMonsterService
{
    private RevenueMonsterClient $client;

    public function __construct(?RevenueMonsterClient $client = null)
    {
        $this->client = $client ?? new RevenueMonsterClient();
    }

    /**
     * Create a hosted web / mobile checkout payment.
     * POST /v3/payment/online
     */
    public function createWebPayment(array $payload)
    {
        return $this->client->request('post', 'v3', '/payment/online', $this->withDefaultStore($payload));
    }

    /**
     * Generate a dynamic QR code for the customer to scan.
     * POST /v3/payment/transaction/qrcode
     */
    public function createQrPay(array $payload)
    {
        if (isset($payload['redirectUrl'])) {
            $payload['redirectUrl'] = $this->escapeUrl($payload['redirectUrl']);
        }

        return $this->client->request('post', 'v3', '/payment/transaction/qrcode', $this->withDefaultStore($payload));
    }

    /**
     * Merchant-scans-customer flow (customer presents a wallet barcode).
     * POST /v3/payment/quickpay
     */
    public function quickPay(array $payload)
    {
        return $this->client->request('post', 'v3', '/payment/quickpay', $this->withDefaultStore($payload));
    }

    /**
     * Refund a settled transaction.
     * POST /v3/payment/refund
     */
    public function refund(array $payload)
    {
        return $this->client->request('post', 'v3', '/payment/refund', $payload);
    }

    /**
     * Reverse (void) an authorised-but-unsettled transaction.
     * POST /v3/payment/reverse
     */
    public function reverse(array $payload)
    {
        return $this->client->request('post', 'v3', '/payment/reverse', $payload);
    }

    /**
     * Fetch a transaction by Revenue Monster transaction id.
     * GET /v3/payment/transaction/{transactionId}
     */
    public function getTransaction(string $transactionId)
    {
        return $this->client->request('get', 'v3', '/payment/transaction/' . rawurlencode($transactionId));
    }

    /**
     * Fetch a transaction by your own order id.
     * GET /v3/payment/transaction/order/{orderId}
     */
    public function getTransactionByOrderId(string $orderId)
    {
        return $this->client->request('get', 'v3', '/payment/transaction/order/' . rawurlencode($orderId));
    }

    /**
     * Fetch order details linked to a QR reference code.
     * GET /v3/payment/transaction/qrcode/{qrCode}
     */
    public function getQrCode(string $qrCode)
    {
        return $this->client->request('get', 'v3', '/payment/transaction/qrcode/' . rawurlencode($qrCode));
    }

    /**
     * List transactions bound to a QR reference code.
     * GET /v3/payment/transaction/qrcode/{qrCode}/transactions
     */
    public function getQrCodeTransactions(string $qrCode, int $limit = 10)
    {
        return $this->client->request('get', 'v3', '/payment/transaction/qrcode/' . rawurlencode($qrCode) . '/transactions?limit=' . $limit);
    }

    /**
     * Verify an incoming notifyUrl callback signature.
     *
     * @param  int|string  $timestamp
     */
    public function verifyCallback(string $signature, string $method, string $requestUrl, string $nonceStr, $timestamp, ?string $base64Data = null): bool
    {
        return $this->client->verifySignature($signature, $method, $requestUrl, $nonceStr, $timestamp, $base64Data);
    }

    public function client(): RevenueMonsterClient
    {
        return $this->client;
    }

    /**
     * Inject the configured default store id when the payload omits one.
     */
    private function withDefaultStore(array $payload): array
    {
        $storeId = trim((string) config('revenuemonster.store_id', ''));

        if ($storeId !== '' && empty($payload['storeId'])) {
            $payload['storeId'] = $storeId;
        }

        return $payload;
    }

    /**
     * Re-encode a URL's query string the way Revenue Monster expects (mirrors
     * the SDK's escape_url helper).
     */
    private function escapeUrl(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false) {
            return $url;
        }

        $result = '';
        if (isset($parts['scheme'])) {
            $result .= $parts['scheme'] . '://';
        }
        if (isset($parts['host'])) {
            $result .= $parts['host'];
        }
        if (isset($parts['port'])) {
            $result .= ':' . $parts['port'];
        }
        if (isset($parts['path'])) {
            $result .= $parts['path'];
        }
        if (isset($parts['query'])) {
            $result .= '?' . urlencode(urldecode($parts['query']));
        }

        return $result;
    }
}
