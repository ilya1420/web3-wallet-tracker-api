<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\DTO\AuthTokenOutput;
use App\Application\Exception\InvalidLoginTokenException;
use App\Application\Service\TokenManager;
use App\Domain\Entity\AccessToken;
use App\Domain\Repository\AccessTokenRepositoryInterface;
use App\Domain\Repository\LoginTokenRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ConfirmLoginTokenUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private LoginTokenRepositoryInterface $loginTokenRepository,
        private AccessTokenRepositoryInterface $accessTokenRepository,
        private TokenManager $tokenManager,
        private EntityManagerInterface $entityManager,
        private string $accessTokenTtl = 'PT24H',
    ) {
    }

    public function execute(string $email, string $rawToken): AuthTokenOutput
    {
        $normalizedEmail = mb_strtolower(trim($email));
        $tokenHash = $this->tokenManager->hashToken($rawToken);

        /** @var AuthTokenOutput $authToken */
        $authToken = $this->entityManager->getConnection()->transactional(function () use ($normalizedEmail, $tokenHash): AuthTokenOutput {
            $user = $this->userRepository->findByEmail($normalizedEmail);
            if ($user === null) {
                throw new InvalidLoginTokenException('Invalid or expired login token.');
            }

            $usedAt = new \DateTimeImmutable();
            $isConsumed = $this->loginTokenRepository->consumeValidToken($normalizedEmail, $tokenHash, $usedAt);
            if (!$isConsumed) {
                throw new InvalidLoginTokenException('Invalid or expired login token.');
            }

            $user->markLoggedIn();
            $user->verify();
            $this->userRepository->save($user, false);

            $rawAccessToken = $this->tokenManager->generateRawToken(48);
            $expiresAt = $usedAt->add(new \DateInterval($this->accessTokenTtl));

            $accessToken = new AccessToken($user, $this->tokenManager->hashToken($rawAccessToken), $expiresAt);
            $this->accessTokenRepository->save($accessToken, false);
            $this->entityManager->flush();

            return new AuthTokenOutput(
                accessToken: $rawAccessToken,
                tokenType: 'Bearer',
                expiresAt: $expiresAt->format(DATE_ATOM),
            );
        });

        return $authToken;
    }
}
