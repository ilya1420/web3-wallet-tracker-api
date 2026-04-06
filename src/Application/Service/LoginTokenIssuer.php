<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Entity\LoginToken;
use App\Domain\Repository\LoginTokenRepositoryInterface;

final readonly class LoginTokenIssuer
{
    public function __construct(
        private LoginTokenRepositoryInterface $loginTokenRepository,
        private TokenManager $tokenManager,
    ) {
    }

    public function issueForEmail(
        string $normalizedEmail,
        string $ttl,
        bool $flush = true,
    ): string {
        $rawToken = $this->tokenManager->generateRawToken();
        $expiresAt = (new \DateTimeImmutable())->add(new \DateInterval($ttl));

        $token = new LoginToken(
            $normalizedEmail,
            $this->tokenManager->hashToken($rawToken),
            $expiresAt,
        );

        $this->loginTokenRepository->save($token, $flush);

        return $rawToken;
    }
}
