<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Repository\AccessTokenRepositoryInterface;
use App\Domain\Repository\LoginTokenRepositoryInterface;

final readonly class AuthTokenCleanupService
{
    public function __construct(
        private LoginTokenRepositoryInterface $loginTokenRepository,
        private AccessTokenRepositoryInterface $accessTokenRepository,
    ) {
    }

    /**
     * @return array{loginTokens:int,accessTokens:int,total:int}
     */
    public function cleanup(\DateTimeImmutable $now): array
    {
        $deletedLoginTokens = $this->loginTokenRepository->deleteObsolete($now);
        $deletedAccessTokens = $this->accessTokenRepository->deleteExpired($now);

        return [
            'loginTokens' => $deletedLoginTokens,
            'accessTokens' => $deletedAccessTokens,
            'total' => $deletedLoginTokens + $deletedAccessTokens,
        ];
    }
}
