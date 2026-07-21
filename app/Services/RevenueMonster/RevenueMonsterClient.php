<?php

namespace App\Services\RevenueMonster;

use App\Services\RevenueMonster\Exceptions\RevenueMonsterException;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Low-level Revenue Monster HTTP client: handles OAuth token retrieval /
 * caching, request signing and the response envelope ({item}/{items}/{error}).
 *
 * High-level payment helpers live in {@see RevenueMonsterService}.
 */
class RevenueMonsterClient
{
    private const OAUTH_HOST = 'oauth.revenuemonster.my';
    private const API_HOST = 'open.revenuemonster.my';
    private const TOKEN_CACHE_KEY = 'revenuemonster.access_token';

    private array $config;

    private HttpClient $http;

    private RevenueMonsterSignature $signature;

    public function __construct(?array $config = null, ?HttpClient $http = null, ?RevenueMonsterSignature $signature = null)
    {
        $this->config = $config ?? (array) config('revenuemonster');

        $this->http = $http ?? new HttpClient([
            'timeout' => $this->config['timeout'] ?? 30,
            'http_errors' => false,
        ]);

        $this->signature = $signature ?? new RevenueMonsterSignature(
            $this->resolveKey('private_key', 'private_key_path'),
            $this->resolveKey('public_key', 'public_key_path')
        );
    }

    /**
     * Perform a signed API request and return the unwrapped item / items.
     *
     * @param  array|null  $payload  request body (null for GET / bodyless calls)
     * @return mixed  stdClass (item) | array (items) | stdClass (raw body)
     */
    public function request(string $method, string $version, string $path, ?array $payload = null)
    {
        $method = strtolower($method);
        $url = $this->url($version, $path);

        $nonceStr = Str::random(32);
        $timestamp = time();
        $signature = $this->signature->sign($method, $url, $nonceStr, $timestamp, $payload);

        $body = $this->send(strtoupper($method), $url, $payload, [
            'Authorization' => 'Bearer ' . $this->getAccessToken(),
            'X-Signature' => 'sha256 ' . $signature,
            'X-Nonce-Str' => $nonceStr,
            'X-Timestamp' => (string) $timestamp,
        ]);

        return $this->unwrap($body);
    }

    /**
     * Retrieve (and cache) an OAuth access token via the client_credentials grant.
     */
    public function getAccessToken(bool $forceFresh = false): string
    {
        if (! $forceFresh) {
            $cached = Cache::get(self::TOKEN_CACHE_KEY);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }

        $hash = base64_encode(($this->config['client_id'] ?? '') . ':' . ($this->config['client_secret'] ?? ''));

        $body = $this->send('POST', $this->url('v1', '/token', 'oauth'), [
            'grantType' => 'client_credentials',
        ], [
            'Authorization' => 'Basic ' . $hash,
        ]);

        $token = $body->accessToken ?? null;
        if (! is_string($token) || $token === '') {
            throw new RevenueMonsterException('Revenue Monster did not return an access token.');
        }

        $expiresIn = (int) ($body->expiresIn ?? 7200);
        Cache::put(self::TOKEN_CACHE_KEY, $token, max(60, $expiresIn - 60));

        return $token;
    }

    /**
     * Verify a Revenue Monster callback / response signature using RM's public key.
     *
     * @param  string|null  $base64Data  base64-encoded JSON body sent by RM
     * @param  int|string   $timestamp
     */
    public function verifySignature(string $signature, string $method, string $requestUrl, string $nonceStr, $timestamp, ?string $base64Data = null): bool
    {
        // Callbacks send the header as "sha256 <base64>" — strip the prefix if present.
        $signature = trim(Str::replaceFirst('sha256', '', $signature));

        return $this->signature->verify($signature, strtolower($method), $requestUrl, $nonceStr, $timestamp, $base64Data);
    }

    public function forgetAccessToken(): void
    {
        Cache::forget(self::TOKEN_CACHE_KEY);
    }

    /**
     * Base64-encoded canonical form of a payload (for callback verification).
     */
    public function canonicalizeData(array $data): string
    {
        return $this->signature->canonicalize($data);
    }

    /**
     * Send a JSON request and decode the body, throwing on transport failure or
     * an {error} envelope.
     *
     * @param  array|null  $payload
     * @return object  decoded JSON body
     */
    private function send(string $method, string $url, ?array $payload, array $headers)
    {
        $options = [
            'headers' => array_merge([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ], $headers),
        ];

        if ($payload !== null && $method !== 'GET') {
            $options['json'] = $payload;
        }

        try {
            $response = $this->http->request($method, $url, $options);
        } catch (ConnectException $e) {
            throw new RevenueMonsterException('Unable to reach Revenue Monster: ' . $e->getMessage(), null, 0, $e);
        } catch (RequestException $e) {
            $response = $e->getResponse();
            if ($response === null) {
                throw new RevenueMonsterException('Revenue Monster request failed: ' . $e->getMessage(), null, 0, $e);
            }
        } catch (GuzzleException $e) {
            throw new RevenueMonsterException('Revenue Monster request failed: ' . $e->getMessage(), null, 0, $e);
        }

        $status = $response->getStatusCode();
        $body = json_decode((string) $response->getBody());

        if (! is_object($body)) {
            throw new RevenueMonsterException('Unexpected Revenue Monster response (HTTP ' . $status . ').', null, $status);
        }

        if (isset($body->error)) {
            $message = (string) ($body->error->message ?? 'Revenue Monster API error');

            // RM validation failures carry per-field detail (description/details/errors);
            // append them so the cause is visible in logs / the error response.
            $detail = $body->error->description ?? $body->error->details ?? $body->error->errors ?? null;
            if ($detail !== null) {
                $message .= ' — ' . json_encode($detail, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }

            throw new RevenueMonsterException(
                $message,
                isset($body->error->code) ? (string) $body->error->code : null,
                $status
            );
        }

        return $body;
    }

    /**
     * Unwrap Revenue Monster's response envelope.
     *
     * @return mixed
     */
    private function unwrap(object $body)
    {
        if (isset($body->item)) {
            return $body->item;
        }

        if (isset($body->items)) {
            return $body->items;
        }

        return $body;
    }

    private function url(string $version, string $path, string $usage = 'api'): string
    {
        $host = $usage === 'oauth' ? self::OAUTH_HOST : self::API_HOST;

        if (! empty($this->config['sandbox'])) {
            $host = 'sb-' . $host;
        }

        return 'https://' . $host . '/' . $version . '/' . ltrim($path, '/');
    }

    /**
     * Resolve a PEM key: inline value first, otherwise read the configured path.
     */
    private function resolveKey(string $inlineKey, string $pathKey): ?string
    {
        $inline = trim((string) ($this->config[$inlineKey] ?? ''));
        if ($inline !== '') {
            return $inline;
        }

        $path = trim((string) ($this->config[$pathKey] ?? ''));
        if ($path !== '' && is_file($path)) {
            return (string) file_get_contents($path);
        }

        return null;
    }
}
