<?php

namespace Tests\Unit\RevenueMonster;

use App\Services\RevenueMonster\Exceptions\RevenueMonsterException;
use App\Services\RevenueMonster\RevenueMonsterSignature;
use PHPUnit\Framework\TestCase;

class RevenueMonsterSignatureTest extends TestCase
{
    private string $privateKey;
    private string $publicKey;

    protected function setUp(): void
    {
        parent::setUp();

        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        $privateKey = '';
        openssl_pkey_export($resource, $privateKey);

        $this->privateKey = $privateKey;
        $this->publicKey = openssl_pkey_get_details($resource)['key'];
    }

    private function signer(): RevenueMonsterSignature
    {
        return new RevenueMonsterSignature($this->privateKey, $this->publicKey);
    }

    /** Keys are sorted alphabetically (recursively) and encoded to compact JSON. */
    public function testCanonicalizeSortsKeysRecursively(): void
    {
        $canonical = $this->signer()->canonicalize([
            'charlie' => 'c',
            'alpha' => 'a',
            'bravo' => ['zulu' => 1, 'alpha' => 2],
        ]);

        $this->assertSame(
            '{"alpha":"a","bravo":{"alpha":2,"zulu":1},"charlie":"c"}',
            base64_decode($canonical)
        );
    }

    /** Special characters must be escaped exactly the way Revenue Monster expects. */
    public function testCanonicalizeEscapesSpecialCharacters(): void
    {
        $canonical = $this->signer()->canonicalize([
            'html' => '<b>&\'',
            'url' => 'https://example.com/path?x=1',
        ]);

        // Slashes stay unescaped; < > & ' become \u00xx (PHP emits uppercase hex).
        $this->assertSame(
            '{"html":"\u003Cb\u003E\u0026\u0027","url":"https://example.com/path?x=1"}',
            base64_decode($canonical)
        );
    }

    public function testCanonicalizeReturnsEmptyStringForEmptyPayload(): void
    {
        $this->assertSame('', $this->signer()->canonicalize([]));
    }

    /** A signature produced by the private key verifies against the public key. */
    public function testSignAndVerifyRoundTrip(): void
    {
        $signer = $this->signer();
        $payload = ['order' => ['id' => 'A1', 'amount' => 100], 'storeId' => 'S9'];
        $url = 'https://sb-open.revenuemonster.my/v3/payment/online';

        $signature = $signer->sign('post', $url, 'nonce123', 1_700_000_000, $payload);
        $base64Data = $signer->canonicalize($payload);

        $this->assertTrue(
            $signer->verify($signature, 'post', $url, 'nonce123', 1_700_000_000, $base64Data)
        );
    }

    public function testVerifyFailsOnTamperedPayload(): void
    {
        $signer = $this->signer();
        $url = 'https://sb-open.revenuemonster.my/v3/payment/online';

        $signature = $signer->sign('post', $url, 'nonce123', 1_700_000_000, ['amount' => 100]);
        $tampered = $signer->canonicalize(['amount' => 999]);

        $this->assertFalse(
            $signer->verify($signature, 'post', $url, 'nonce123', 1_700_000_000, $tampered)
        );
    }

    public function testVerifyFailsWhenTimestampChanged(): void
    {
        $signer = $this->signer();
        $url = 'https://sb-open.revenuemonster.my/v3/payment/online';
        $base64Data = $signer->canonicalize(['amount' => 100]);

        $signature = $signer->sign('post', $url, 'nonce123', 1_700_000_000, ['amount' => 100]);

        $this->assertFalse(
            $signer->verify($signature, 'post', $url, 'nonce123', 1_700_000_099, $base64Data)
        );
    }

    public function testSignThrowsOnInvalidPrivateKey(): void
    {
        $this->expectException(RevenueMonsterException::class);

        (new RevenueMonsterSignature('not-a-key', $this->publicKey))
            ->sign('post', 'https://example.com', 'n', 1, ['a' => 1]);
    }
}
