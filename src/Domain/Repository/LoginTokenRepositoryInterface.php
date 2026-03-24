<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\LoginToken;

interface LoginTokenRepositoryInterface
{
    public function save(LoginToken $token): void;

    public function findValidByEmailAndHash(string $email, string $tokenHash): ?LoginToken;
}
