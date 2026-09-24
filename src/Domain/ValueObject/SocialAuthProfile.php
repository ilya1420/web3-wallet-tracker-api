<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

final readonly class SocialAuthProfile
{
    /**
     * @param array<string, mixed> $rawProfile
     */
    public function __construct(
        public string $provider,
        public string $providerUserId,
        public ?string $email = null,
        public ?string $displayName = null,
        public array $rawProfile = [],
    ) {
        if (trim($provider) === '') {
            throw new \InvalidArgumentException('Social provider cannot be empty.');
        }

        if (trim($providerUserId) === '') {
            throw new \InvalidArgumentException('Social provider user id cannot be empty.');
        }

        if ($email !== null) {
            new Email($email);
        }
    }

    public function normalizedProvider(): string
    {
        return mb_strtolower(trim($this->provider));
    }

    public function normalizedEmail(): ?string
    {
        return $this->email !== null ? (new Email($this->email))->value() : null;
    }
}
