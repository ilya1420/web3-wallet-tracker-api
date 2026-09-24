<?php

declare(strict_types=1);

namespace App\Application\DTO;

final readonly class MeOutput
{
    public function __construct(
        public string $id,
        public ?string $email,
        public ?string $displayName,
        public array $roles,
        public bool $isVerified,
        public ?string $lastLoginAt,
        public string $createdAt,
    ) {
    }
}
