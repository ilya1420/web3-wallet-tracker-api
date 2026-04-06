<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\LoginToken;

interface LoginTokenRepositoryInterface
{
    public function save(LoginToken $token, bool $flush = true): void;

    public function consumeValidToken(string $email, string $tokenHash, \DateTimeImmutable $usedAt): bool;

    public function deleteObsolete(\DateTimeImmutable $at): int;
}
