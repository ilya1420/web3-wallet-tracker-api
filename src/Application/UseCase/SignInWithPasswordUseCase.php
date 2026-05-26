<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\DTO\AuthTokenOutput;
use App\Application\Exception\InvalidCredentialsException;
use App\Application\Service\AccessTokenIssuer;
use App\Application\Service\LoginRateLimiterService;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\Email;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class SignInWithPasswordUseCase
{
    private const INVALID_CREDENTIALS_MESSAGE = 'Invalid email or password.';

    public function __construct(
        private UserRepositoryInterface $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private LoginRateLimiterService $rateLimiter,
        private AccessTokenIssuer $accessTokenIssuer,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private string $accessTokenTtl = 'PT24H',
    ) {
    }

    public function execute(string $email, string $password): AuthTokenOutput
    {
        $normalizedEmail = (new Email($email))->value();
        $emailHash = hash('sha256', $normalizedEmail);

        $this->rateLimiter->assertCanAttempt($normalizedEmail);

        $user = $this->userRepository->findByEmail($normalizedEmail);
        if ($user === null || !$this->passwordHasher->isPasswordValid($user, $password)) {
            $this->logger->warning('Password sign-in failed.', [
                'use_case' => self::class,
                'email_hash' => $emailHash,
                'reason' => $user === null ? 'email_not_found' : 'invalid_password',
            ]);

            throw new InvalidCredentialsException(self::INVALID_CREDENTIALS_MESSAGE);
        }

        /** @var AuthTokenOutput $authToken */
        $authToken = $this->entityManager->getConnection()->transactional(function () use ($user, $emailHash): AuthTokenOutput {
            $now = new \DateTimeImmutable();

            $user->markLoggedIn();
            $this->userRepository->save($user, false);
            $authToken = $this->accessTokenIssuer->issueForUser($user, $this->accessTokenTtl, false, $now);
            $this->entityManager->flush();

            $this->logger->info('Password sign-in succeeded.', [
                'use_case' => self::class,
                'user_id' => $user->id()->toRfc4122(),
                'email_hash' => $emailHash,
            ]);

            return $authToken;
        });

        return $authToken;
    }
}
