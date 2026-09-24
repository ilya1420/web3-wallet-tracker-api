<?php

declare(strict_types=1);

namespace App\Infrastructure\SocialAuth;

use App\Application\Exception\SocialAuthException;

final readonly class GoogleIdTokenVerifier
{
    private const JWKS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

    public function __construct(
        private SocialHttpClientInterface $httpClient,
        private string $clientId,
    ) {
    }

    /**
     * @return array{sub:string,email?:string,email_verified?:bool,name?:string}
     */
    public function verify(string $idToken): array
    {
        [$encodedHeader, $encodedPayload, $encodedSignature] = $this->splitJwt($idToken);
        $header = $this->decodeJwtPart($encodedHeader);
        $payload = $this->decodeJwtPart($encodedPayload);

        if (($header['alg'] ?? null) !== 'RS256' || !is_string($header['kid'] ?? null)) {
            throw new SocialAuthException('Invalid Google credential.');
        }

        $key = $this->findKey($header['kid']);
        $signature = $this->base64UrlDecode($encodedSignature);
        $verified = openssl_verify($encodedHeader . '.' . $encodedPayload, $signature, $this->jwkToPem($key), OPENSSL_ALGO_SHA256);
        if ($verified !== 1) {
            throw new SocialAuthException('Invalid Google credential signature.');
        }

        $this->assertClaims($payload);

        /** @var array{sub:string,email?:string,email_verified?:bool,name?:string} $payload */
        return $payload;
    }

    /**
     * @return array{0:string,1:string,2:string}
     */
    private function splitJwt(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new SocialAuthException('Invalid Google credential.');
        }

        return [$parts[0], $parts[1], $parts[2]];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJwtPart(string $encoded): array
    {
        try {
            $decoded = json_decode($this->base64UrlDecode($encoded), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new SocialAuthException('Invalid Google credential payload.', previous: $e);
        }

        if (!is_array($decoded)) {
            throw new SocialAuthException('Invalid Google credential payload.');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private function base64UrlDecode(string $encoded): string
    {
        $padded = str_pad($encoded, strlen($encoded) + (4 - strlen($encoded) % 4) % 4, '=');
        $decoded = base64_decode(strtr($padded, '-_', '+/'), true);
        if ($decoded === false) {
            throw new SocialAuthException('Invalid Google credential encoding.');
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    private function findKey(string $kid): array
    {
        $jwks = $this->httpClient->getJson(self::JWKS_URL);
        $keys = $jwks['keys'] ?? null;
        if (!is_array($keys)) {
            throw new SocialAuthException('Google JWKS response is invalid.');
        }

        foreach ($keys as $key) {
            if (is_array($key) && ($key['kid'] ?? null) === $kid) {
                /** @var array<string, mixed> $key */
                return $key;
            }
        }

        throw new SocialAuthException('Google signing key was not found.');
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function assertClaims(array $claims): void
    {
        $aud = $claims['aud'] ?? null;
        if ($aud !== $this->clientId) {
            throw new SocialAuthException('Google credential audience is invalid.');
        }

        if (($claims['iss'] ?? null) !== 'https://accounts.google.com' && ($claims['iss'] ?? null) !== 'accounts.google.com') {
            throw new SocialAuthException('Google credential issuer is invalid.');
        }

        if (!is_int($claims['exp'] ?? null) || $claims['exp'] <= time()) {
            throw new SocialAuthException('Google credential has expired.');
        }

        if (!is_string($claims['sub'] ?? null) || trim($claims['sub']) === '') {
            throw new SocialAuthException('Google credential subject is missing.');
        }
    }

    /**
     * @param array<string, mixed> $jwk
     */
    private function jwkToPem(array $jwk): string
    {
        if (($jwk['kty'] ?? null) !== 'RSA' || !is_string($jwk['n'] ?? null) || !is_string($jwk['e'] ?? null)) {
            throw new SocialAuthException('Google signing key is invalid.');
        }

        $modulus = $this->base64UrlDecode($jwk['n']);
        $exponent = $this->base64UrlDecode($jwk['e']);

        $components = $this->asn1Sequence(
            $this->asn1Integer($modulus) .
            $this->asn1Integer($exponent),
        );

        return "-----BEGIN RSA PUBLIC KEY-----\n"
            . chunk_split(base64_encode($components), 64, "\n")
            . "-----END RSA PUBLIC KEY-----\n";
    }

    private function asn1Sequence(string $payload): string
    {
        return "\x30" . $this->asn1Length(strlen($payload)) . $payload;
    }

    private function asn1Integer(string $payload): string
    {
        if ($payload !== '' && (ord($payload[0]) & 0x80) !== 0) {
            $payload = "\x00" . $payload;
        }

        return "\x02" . $this->asn1Length(strlen($payload)) . $payload;
    }

    private function asn1Length(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }

        $encoded = '';
        while ($length > 0) {
            $encoded = chr($length & 0xff) . $encoded;
            $length >>= 8;
        }

        return chr(0x80 | strlen($encoded)) . $encoded;
    }
}
