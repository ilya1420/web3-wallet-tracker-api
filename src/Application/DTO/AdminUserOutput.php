<?php

declare(strict_types=1);

namespace App\Application\DTO;

final readonly class AdminUserOutput
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        public string $id,
        public ?string $email,
        public ?string $displayName,
        public ?string $phone,
        public array $roles,
        public bool $isVerified,
        public ?string $lastLoginAt,
        public string $createdAt,
        public ?string $deviceFingerprintHash,
        public ?string $registrationIpHash,
        public ?string $registrationIpCounterDate,
    ) {
    }
}
