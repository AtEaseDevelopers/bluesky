<?php

namespace App\Services\RevenueMonster;

use App\Services\RevenueMonster\Exceptions\RevenueMonsterException;

/**
 * Builds and verifies Revenue Monster request signatures (SHA256withRSA).
 *
 * The canonicalisation rules mirror Revenue Monster's official SDK exactly:
 *   - request body keys are sorted alphabetically (recursively),
 *   - encoded to compact JSON with slashes/unicode kept literal and
 *     < > & ' hex-escaped, then base64 encoded,
 *   - the signing plaintext is the ampersand-joined, alphabetically ordered
 *     "data / method / nonceStr / requestUrl / signType / timestamp" pairs.
 *
 * @see https://doc.revenuemonster.my/docs/quickstart/signature-algorithm/
 */
class RevenueMonsterSignature
{
    private const SIGN_TYPE = 'sha256';

    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_HEX_APOS
        | JSON_HEX_AMP
        | JSON_HEX_TAG;

    private ?string $privateKey;

    private ?string $publicKey;

    public function __construct(?string $privateKey = null, ?string $publicKey = null)
    {
        $this->privateKey = $privateKey;
        $this->publicKey = $publicKey;
    }

    /**
     * Sort a payload's keys (recursively) and return the base64-encoded compact
     * JSON that forms the "data" segment of the signature. Empty payloads yield
     * an empty string.
     */
    public function canonicalize(array $payload): string
    {
        if ($payload === []) {
            return '';
        }

        $sorted = $this->ksortRecursive($payload);
        $json = json_encode($sorted, self::JSON_FLAGS);

        if ($json === false) {
            throw new RevenueMonsterException('Unable to encode Revenue Monster payload: ' . json_last_error_msg());
        }

        return base64_encode($json);
    }

    /**
     * Generate a base64 signature (without the "sha256 " header prefix).
     *
     * @param string     $method   lower-case HTTP verb (e.g. "post", "get")
     * @param string     $url      full request URL that will be called
     * @param array|null $payload  request body; null for requests without a body (GET)
     * @param int|string $timestamp
     */
    public function sign(string $method, string $url, string $nonceStr, $timestamp, ?array $payload = null): string
    {
        $key = openssl_pkey_get_private((string) $this->privateKey);
        if ($key === false) {
            throw new RevenueMonsterException('Invalid or missing Revenue Monster private key.');
        }

        $data = is_array($payload) ? $this->canonicalize($payload) : null;
        $plainText = $this->plainText($method, $url, $nonceStr, $timestamp, $data, is_array($payload));

        $signature = '';
        if (! openssl_sign($plainText, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RevenueMonsterException('Failed to generate Revenue Monster signature.');
        }

        return base64_encode($signature);
    }

    /**
     * Verify a base64 signature (typically from a callback or API response)
     * against a base64-encoded data segment using Revenue Monster's public key.
     *
     * @param int|string $timestamp
     */
    public function verify(string $signature, string $method, string $url, string $nonceStr, $timestamp, ?string $base64Payload = null): bool
    {
        $key = openssl_pkey_get_public((string) $this->publicKey);
        if ($key === false) {
            throw new RevenueMonsterException('Invalid or missing Revenue Monster public key.');
        }

        // The data segment is included only when non-empty (matches RM verification).
        $plainText = $this->plainText($method, $url, $nonceStr, $timestamp, $base64Payload, (string) $base64Payload !== '');

        return openssl_verify($plainText, base64_decode($signature), $key, OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * Assemble the alphabetically ordered plaintext that gets signed/verified.
     *
     * @param int|string $timestamp
     */
    private function plainText(string $method, string $url, string $nonceStr, $timestamp, ?string $base64Data, bool $includeData): string
    {
        $parts = [];

        if ($includeData) {
            $parts[] = 'data=' . (string) $base64Data;
        }

        $parts[] = 'method=' . $method;
        $parts[] = 'nonceStr=' . $nonceStr;
        // RM omits requestUrl entirely for some callback signatures; an empty
        // url means "leave the requestUrl segment out".
        if ($url !== '') {
            $parts[] = 'requestUrl=' . $url;
        }
        $parts[] = 'signType=' . self::SIGN_TYPE;
        $parts[] = 'timestamp=' . $timestamp;

        return implode('&', $parts);
    }

    /**
     * Recursively sort array keys alphabetically.
     */
    private function ksortRecursive(array $array): array
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->ksortRecursive($value);
            }
        }

        ksort($array);

        return $array;
    }
}
