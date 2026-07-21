<?php

namespace Tests\Unit\RevenueMonster;

use App\Services\RevenueMonster\Exceptions\RevenueMonsterException;
use App\Services\RevenueMonster\RevenueMonsterClient;
use App\Services\RevenueMonster\RevenueMonsterSignature;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RevenueMonsterClientTest extends TestCase
{
    private string $privateKey;
    private string $publicKey;

    /** @var array<int, array> */
    private array $history = [];

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $privateKey = '';
        openssl_pkey_export($resource, $privateKey);

        $this->privateKey = $privateKey;
        $this->publicKey = openssl_pkey_get_details($resource)['key'];
    }

    /**
     * Build a client whose Guzzle transport replays the given queued responses,
     * recording every outbound request into $this->history.
     *
     * @param  Response[]  $responses
     */
    private function makeClient(array $responses, array $configOverrides = []): RevenueMonsterClient
    {
        $this->history = [];
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));

        $config = array_merge([
            'client_id' => 'CID',
            'client_secret' => 'SECRET',
            'sandbox' => true,
            'timeout' => 5,
        ], $configOverrides);

        return new RevenueMonsterClient(
            $config,
            new HttpClient(['handler' => $stack, 'http_errors' => false]),
            new RevenueMonsterSignature($this->privateKey, $this->publicKey)
        );
    }

    private function jsonResponse(array $data): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode($data));
    }

    public function testRequestSignsHeadersAndUnwrapsItem(): void
    {
        $client = $this->makeClient([
            $this->jsonResponse(['accessToken' => 'TOKEN123', 'expiresIn' => 7200]),
            $this->jsonResponse(['item' => ['transactionId' => 'T-1', 'status' => 'SUCCESS']]),
        ]);

        $item = $client->request('post', 'v3', '/payment/online', ['amount' => 100]);

        $this->assertSame('T-1', $item->transactionId);

        // Second request in history is the signed API call.
        $apiRequest = $this->history[1]['request'];
        $this->assertSame('Bearer TOKEN123', $apiRequest->getHeaderLine('Authorization'));
        $this->assertStringStartsWith('sha256 ', $apiRequest->getHeaderLine('X-Signature'));
        $this->assertNotEmpty($apiRequest->getHeaderLine('X-Nonce-Str'));
        $this->assertNotEmpty($apiRequest->getHeaderLine('X-Timestamp'));
        $this->assertSame(
            'https://sb-open.revenuemonster.my/v3/payment/online',
            (string) $apiRequest->getUri()
        );
    }

    public function testAccessTokenIsCachedAcrossRequests(): void
    {
        $client = $this->makeClient([
            $this->jsonResponse(['accessToken' => 'TOKEN123', 'expiresIn' => 7200]),
            $this->jsonResponse(['item' => ['ok' => true]]),
            $this->jsonResponse(['item' => ['ok' => true]]),
        ]);

        $client->request('post', 'v3', '/payment/online', ['amount' => 1]);
        $client->request('post', 'v3', '/payment/online', ['amount' => 2]);

        // Token endpoint hit once; two API calls -> 3 requests total (not 4).
        $this->assertCount(3, $this->history);
        $this->assertStringContainsString('/v1/token', (string) $this->history[0]['request']->getUri());
    }

    public function testErrorEnvelopeThrowsWithRmCode(): void
    {
        $client = $this->makeClient([
            $this->jsonResponse(['accessToken' => 'TOKEN123', 'expiresIn' => 7200]),
            new Response(400, ['Content-Type' => 'application/json'], json_encode([
                'error' => ['code' => 'invalid_amount', 'message' => 'Amount is invalid'],
            ])),
        ]);

        try {
            $client->request('post', 'v3', '/payment/online', ['amount' => -1]);
            $this->fail('Expected RevenueMonsterException was not thrown.');
        } catch (RevenueMonsterException $e) {
            $this->assertSame('Amount is invalid', $e->getMessage());
            $this->assertSame('invalid_amount', $e->getRmErrorCode());
            $this->assertSame(400, $e->getHttpStatus());
        }
    }

    public function testMissingAccessTokenThrows(): void
    {
        $client = $this->makeClient([
            $this->jsonResponse(['expiresIn' => 7200]), // no accessToken
        ]);

        $this->expectException(RevenueMonsterException::class);
        $client->getAccessToken();
    }

    public function testVerifySignatureAcceptsPrefixedHeader(): void
    {
        $client = $this->makeClient([]);
        $signer = new RevenueMonsterSignature($this->privateKey, $this->publicKey);

        $url = 'https://merchant.test/rm/callback';
        $base64Data = $signer->canonicalize(['status' => 'SUCCESS', 'order' => ['id' => 'A1']]);
        $signature = $signer->sign('post', $url, 'nonceZ', 1_700_000_000, ['status' => 'SUCCESS', 'order' => ['id' => 'A1']]);

        $this->assertTrue(
            $client->verifySignature('sha256 ' . $signature, 'post', $url, 'nonceZ', 1_700_000_000, $base64Data)
        );
        $this->assertFalse(
            $client->verifySignature('sha256 ' . $signature, 'post', $url, 'WRONG', 1_700_000_000, $base64Data)
        );
    }
}
