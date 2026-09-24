<?php

declare(strict_types=1);

namespace App\Infrastructure\SocialAuth;

use App\Application\Exception\SocialAuthException;
use App\Application\Service\SocialAuthProvider;
use App\Domain\ValueObject\SocialAuthProfile;

final readonly class GoogleOAuth2Provider implements SocialAuthProvider
{
    private const AUTHORIZE_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const TOKEN_INFO_URL = 'https://oauth2.googleapis.com/tokeninfo';

    public function __construct(
        private SocialHttpClientInterface $httpClient,
        private string $clientId,
        private string $clientSecret,
        private string $redirectUri,
    ) {
    }

    public function name(): string
    {
        return 'google_oauth2';
    }

    public function supportsRedirect(): bool
    {
        return true;
    }

    public function redirectUrl(string $state): string
    {
        return self::AUTHORIZE_URL . '?' . http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'access_type' => 'online',
            'prompt' => 'select_account',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function authenticate(array $payload): SocialAuthProfile
    {
        $code = trim((string) ($payload['code'] ?? ''));
        if ($code === '') {
            throw new SocialAuthException('Google authorization code is missing.');
        }

        $token = $this->httpClient->postFormJson(self::TOKEN_URL, [
            'code' => $code,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'grant_type' => 'authorization_code',
        ]);

        $idToken = $token['id_token'] ?? null;
        if (!is_string($idToken) || trim($idToken) === '') {
            throw new SocialAuthException('Google token response did not include an id token.');
        }

        $claims = $this->httpClient->getJson(self::TOKEN_INFO_URL . '?' . http_build_query(['id_token' => $idToken]));
        if (($claims['aud'] ?? null) !== $this->clientId || !is_string($claims['sub'] ?? null)) {
            throw new SocialAuthException('Google token verification failed.');
        }

        return new SocialAuthProfile(
            provider: $this->name(),
            providerUserId: $claims['sub'],
            email: is_string($claims['email'] ?? null) && $this->isEmailVerified($claims['email_verified'] ?? null) ? $claims['email'] : null,
            displayName: is_string($claims['name'] ?? null) ? $claims['name'] : null,
            rawProfile: $claims,
        );
    }

    private function isEmailVerified(mixed $value): bool
    {
        return $value === true || $value === 'true' || $value === '1' || $value === 1;
    }
}
