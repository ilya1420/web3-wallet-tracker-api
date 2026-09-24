<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\ValueObject\SocialAuthProfile;

interface SocialAuthProvider
{
    public function name(): string;

    public function supportsRedirect(): bool;

    public function redirectUrl(string $state): string;

    /**
     * @param array<string, mixed> $payload
     */
    public function authenticate(array $payload): SocialAuthProfile;
}
