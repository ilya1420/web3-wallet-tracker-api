<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\DTO\AuthTokenOutput;
use App\Application\Exception\InvalidLoginTokenException;
use App\Application\Service\AccessTokenIssuer;
use App\Application\Service\TokenManager;
use App\Domain\Repository\LoginTokenRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\Email;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ConfirmLoginTokenUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private LoginTokenRepositoryInterface $loginTokenRepository,
        private AccessTokenIssuer $accessTokenIssuer,
        private TokenManager $tokenManager,
        private EntityManagerInterface $entityManager,
        private string $accessTokenTtl = 'PT24H',
    ) {
    }

    public function execute(string $email, string $rawToken): AuthTokenOutput
    {
        $normalizedEmail = (new Email($email))->value();
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
            $authToken = $this->accessTokenIssuer->issueForUser($user, $this->accessTokenTtl, false, $usedAt);
            $this->entityManager->flush();

            return $authToken;
        });

        return $authToken;
    }
}
