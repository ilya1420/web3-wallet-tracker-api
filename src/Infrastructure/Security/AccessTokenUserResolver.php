<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Application\Service\TokenManager;
use App\Domain\Entity\User;
use App\Domain\Repository\AccessTokenRepositoryInterface;

final readonly class AccessTokenUserResolver
{
    public function __construct(
        private AccessTokenRepositoryInterface $accessTokenRepository,
        private TokenManager $tokenManager,
    ) {
    }

    public function resolve(string $rawToken): ?User
    {
        $trimmedToken = trim($rawToken);
        if ($trimmedToken === '') {
            return null;
        }

        $tokenHash = $this->tokenManager->hashToken($trimmedToken);
        $accessToken = $this->accessTokenRepository->findActiveByHash($tokenHash, new \DateTimeImmutable());

        return $accessToken?->user();
    }
}
