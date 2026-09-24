<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\SocialAuth;

use App\Infrastructure\SocialAuth\GoogleIdTokenVerifier;
use App\Infrastructure\SocialAuth\GoogleOneTapProvider;
use App\Infrastructure\SocialAuth\SocialHttpClientInterface;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

final class GoogleOneTapProviderTest extends TestCase
{
    public function testVerifiesCredentialJwtAndReturnsProfile(): void
    {
        $keyPair = $this->createRsaKeyPair();
        $jwt = $this->buildJwt($keyPair['privateKey'], [
            'iss' => 'https://accounts.google.com',
            'aud' => 'client-id',
            'sub' => 'google-one-tap-subject',
            'email' => 'alice@example.com',
            'email_verified' => true,
            'name' => 'Alice',
            'exp' => time() + 300,
        ]);

        $provider = new GoogleOneTapProvider(new GoogleIdTokenVerifier(
            new JwksHttpClientStub($keyPair['jwk']),
            'client-id',
        ));

        $profile = $provider->authenticate(['credential' => $jwt]);

        self::assertSame('google_one_tap', $profile->provider);
        self::assertSame('google-one-tap-subject', $profile->providerUserId);
        self::assertSame('alice@example.com', $profile->email);
    }

    /**
     * @return array{privateKey:string,jwk:array<string, string>}
     */
    private function createRsaKeyPair(): array
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        self::assertNotFalse($resource);

        openssl_pkey_export($resource, $privateKey);
        $details = openssl_pkey_get_details($resource);
        self::assertIsArray($details);

        return [
            'privateKey' => $privateKey,
            'jwk' => [
                'kty' => 'RSA',
                'alg' => 'RS256',
                'use' => 'sig',
                'kid' => 'test-key',
                'n' => $this->base64UrlEncode($details['rsa']['n']),
                'e' => $this->base64UrlEncode($details['rsa']['e']),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function buildJwt(string $privateKey, array $claims): string
    {
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT', 'kid' => 'test-key'], JSON_THROW_ON_ERROR));
        $payload = $this->base64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR));
        $signature = '';
        openssl_sign($header . '.' . $payload, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return $header . '.' . $payload . '.' . $this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

final readonly class JwksHttpClientStub implements SocialHttpClientInterface
{
    /**
     * @param array<string, string> $jwk
     */
    public function __construct(private array $jwk)
    {
    }

    public function getJson(string $url): array
    {
        Assert::assertSame('https://www.googleapis.com/oauth2/v3/certs', $url);

        return ['keys' => [$this->jwk]];
    }

    public function postFormJson(string $url, array $fields): array
    {
        Assert::fail('Google One Tap should not perform form POST requests.');
    }
}
