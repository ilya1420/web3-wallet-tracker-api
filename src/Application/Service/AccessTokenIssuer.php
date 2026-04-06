<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\DTO\AuthTokenOutput;
use App\Domain\Entity\AccessToken;
use App\Domain\Entity\User;
use App\Domain\Repository\AccessTokenRepositoryInterface;

final readonly class AccessTokenIssuer
{
    public function __construct(
        private AccessTokenRepositoryInterface $accessTokenRepository,
        private TokenManager $tokenManager,
    ) {
    }

    public function issueForUser(
        User $user,
        string $ttl,
        bool $flush = true,
        ?\DateTimeImmutable $issuedAt = null,
    ): AuthTokenOutput {
        $issuedAt ??= new \DateTimeImmutable();

        $rawToken = $this->tokenManager->generateRawToken(48);
        $expiresAt = $issuedAt->add(new \DateInterval($ttl));

        $accessToken = new AccessToken(
            $user,
            $this->tokenManager->hashToken($rawToken),
            $expiresAt,
        );

        $this->accessTokenRepository->save($accessToken, $flush);

        return new AuthTokenOutput(
            accessToken: $rawToken,
            tokenType: 'Bearer',
            expiresAt: $expiresAt->format(DATE_ATOM),
        );
    }
}
