<?php

declare(strict_types=1);

namespace App\Infrastructure\SocialAuth;

use App\Application\Exception\SocialAuthException;
use App\Application\Service\SocialAuthProvider;
use App\Domain\ValueObject\SocialAuthProfile;

final readonly class GoogleOneTapProvider implements SocialAuthProvider
{
    public function __construct(private GoogleIdTokenVerifier $idTokenVerifier)
    {
    }

    public function name(): string
    {
        return 'google_one_tap';
    }

    public function supportsRedirect(): bool
    {
        return false;
    }

    public function redirectUrl(string $state): string
    {
        throw new SocialAuthException('Google One Tap does not support redirect flow.');
    }

    public function authenticate(array $payload): SocialAuthProfile
    {
        $credential = trim((string) ($payload['credential'] ?? ''));
        if ($credential === '') {
            throw new SocialAuthException('Google One Tap credential is missing.');
        }

        $claims = $this->idTokenVerifier->verify($credential);

        return new SocialAuthProfile(
            provider: $this->name(),
            providerUserId: $claims['sub'],
            email: is_string($claims['email'] ?? null) && ($claims['email_verified'] ?? false) === true ? $claims['email'] : null,
            displayName: is_string($claims['name'] ?? null) ? $claims['name'] : null,
            rawProfile: $claims,
        );
    }
}
