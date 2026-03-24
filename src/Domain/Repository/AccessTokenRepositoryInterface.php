<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\AccessToken;

interface AccessTokenRepositoryInterface
{
    public function save(AccessToken $token): void;

    public function findValidByHash(string $tokenHash): ?AccessToken;
}
