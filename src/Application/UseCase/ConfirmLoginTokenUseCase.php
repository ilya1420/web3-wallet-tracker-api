<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\DTO\AuthTokenOutput;
use App\Application\Service\TokenManager;
use App\Domain\Entity\AccessToken;
use App\Domain\Repository\AccessTokenRepositoryInterface;
use App\Domain\Repository\LoginTokenRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;

final readonly class ConfirmLoginTokenUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private LoginTokenRepositoryInterface $loginTokenRepository,
        private AccessTokenRepositoryInterface $accessTokenRepository,
        private TokenManager $tokenManager,
        private string $accessTokenTtl = 'PT24H',
    ) {
    }

    public function execute(string $email, string $rawToken): AuthTokenOutput
    {
        $normalizedEmail = mb_strtolower(trim($email));
        $tokenHash = $this->tokenManager->hashToken($rawToken);

        $loginToken = $this->loginTokenRepository->findValidByEmailAndHash($normalizedEmail, $tokenHash);
        if ($loginToken === null) {
            throw new \RuntimeException('Invalid or expired login token.');
        }

        $user = $this->userRepository->findByEmail($normalizedEmail);
        if ($user === null) {
            throw new \RuntimeException('User not found.');
        }

        $loginToken->markUsed();
        $this->loginTokenRepository->save($loginToken);

        $user->markLoggedIn();
        $user->verify();
        $this->userRepository->save($user);

        $rawAccessToken = $this->tokenManager->generateRawToken(48);
        $expiresAt = (new \DateTimeImmutable())->add(new \DateInterval($this->accessTokenTtl));

        $accessToken = new AccessToken($user, $this->tokenManager->hashToken($rawAccessToken), $expiresAt);
        $this->accessTokenRepository->save($accessToken);

        return new AuthTokenOutput(
            accessToken: $rawAccessToken,
            tokenType: 'Bearer',
            expiresAt: $expiresAt->format(DATE_ATOM),
        );
    }
}
