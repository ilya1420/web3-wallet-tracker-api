<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Exception\SocialAuthException;
use App\Domain\ValueObject\SocialAuthProfile;

final readonly class SocialAuthService
{
    /** @var array<string, SocialAuthProvider> */
    private array $providers;

    /**
     * @param iterable<SocialAuthProvider> $providers
     */
    public function __construct(iterable $providers)
    {
        $indexed = [];
        foreach ($providers as $provider) {
            $indexed[$provider->name()] = $provider;
        }

        $this->providers = $indexed;
    }

    public function redirectUrl(string $providerName, string $state): string
    {
        $provider = $this->provider($providerName);
        if (!$provider->supportsRedirect()) {
            throw new SocialAuthException('Social provider does not support redirect flow.');
        }

        return $provider->redirectUrl($state);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function authenticate(string $providerName, array $payload): SocialAuthProfile
    {
        return $this->provider($providerName)->authenticate($payload);
    }

    private function provider(string $providerName): SocialAuthProvider
    {
        $normalized = mb_strtolower(trim($providerName));
        if (!isset($this->providers[$normalized])) {
            throw new SocialAuthException('Unsupported social auth provider.');
        }

        return $this->providers[$normalized];
    }
}
