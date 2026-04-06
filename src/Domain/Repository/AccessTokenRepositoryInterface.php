<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\AccessToken;

interface AccessTokenRepositoryInterface
{
    public function save(AccessToken $token, bool $flush = true): void;

    public function findActiveByHash(string $tokenHash, \DateTimeImmutable $at): ?AccessToken;
}
