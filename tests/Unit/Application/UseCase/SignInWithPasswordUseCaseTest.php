<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase;

use App\Application\Exception\InvalidCredentialsException;
use App\Application\Service\AccessTokenIssuer;
use App\Application\Service\LoginRateLimiterService;
use App\Application\Service\TokenManager;
use App\Application\UseCase\SignInWithPasswordUseCase;
use App\Domain\Entity\AccessToken;
use App\Domain\Entity\User;
use App\Domain\Repository\AccessTokenRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\Email;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class SignInWithPasswordUseCaseTest extends TestCase
{
    public function testIssuesAccessTokenForExistingUserWithValidPassword(): void
    {
        $user = new User(new Email('alice@example.com'), 'hash');
        $userRepository = new SignInUserRepository($user);
        $accessTokenRepository = new SignInAccessTokenRepository();

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher
            ->expects(self::once())
            ->method('isPasswordValid')
            ->with($user, 'CorrectPass123!')
            ->willReturn(true);

        $entityManager = $this->createTransactionalEntityManager();

        $useCase = $this->createUseCase($userRepository, $accessTokenRepository, $passwordHasher, $entityManager);
        $output = $useCase->execute('Alice@Example.com', 'CorrectPass123!');

        self::assertSame('Bearer', $output->tokenType);
        self::assertNotSame('', $output->accessToken);
        self::assertNotNull($user->lastLoginAt());
        self::assertSame($user, $userRepository->savedUser);
        self::assertCount(1, $accessTokenRepository->tokens);
    }

    public function testRejectsMissingEmailWithGenericCredentialsError(): void
    {
        $userRepository = new SignInUserRepository(null);
        $accessTokenRepository = new SignInAccessTokenRepository();

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->expects(self::never())->method('isPasswordValid');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $useCase = $this->createUseCase($userRepository, $accessTokenRepository, $passwordHasher, $entityManager);

        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Invalid email or password.');

        $useCase->execute('missing@example.com', 'CorrectPass123!');
    }

    public function testRejectsInvalidPasswordWithGenericCredentialsError(): void
    {
        $user = new User(new Email('alice@example.com'), 'hash');
        $userRepository = new SignInUserRepository($user);
        $accessTokenRepository = new SignInAccessTokenRepository();

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher
            ->expects(self::once())
            ->method('isPasswordValid')
            ->with($user, 'WrongPass123!')
            ->willReturn(false);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $useCase = $this->createUseCase($userRepository, $accessTokenRepository, $passwordHasher, $entityManager);

        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Invalid email or password.');

        $useCase->execute('alice@example.com', 'WrongPass123!');
    }

    private function createUseCase(
        SignInUserRepository $userRepository,
        SignInAccessTokenRepository $accessTokenRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
    ): SignInWithPasswordUseCase {
        return new SignInWithPasswordUseCase(
            userRepository: $userRepository,
            passwordHasher: $passwordHasher,
            rateLimiter: new LoginRateLimiterService(new RateLimiterFactory([
                'id' => 'login_request',
                'policy' => 'fixed_window',
                'limit' => 100,
                'interval' => '1 minute',
            ], new InMemoryStorage())),
            accessTokenIssuer: new AccessTokenIssuer($accessTokenRepository, new TokenManager()),
            entityManager: $entityManager,
            logger: new NullLogger(),
            accessTokenTtl: 'PT24H',
        );
    }

    private function createTransactionalEntityManager(): EntityManagerInterface
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(self::once())
            ->method('transactional')
            ->willReturnCallback(static fn (callable $func): mixed => $func());
        $entityManager->expects(self::once())->method('getConnection')->willReturn($connection);
        $entityManager->expects(self::once())->method('flush');

        return $entityManager;
    }
}

final class SignInUserRepository implements UserRepositoryInterface
{
    public ?User $savedUser = null;

    public function __construct(private readonly ?User $user)
    {
    }

    public function save(User $user, bool $flush = true): void
    {
        $this->savedUser = $user;
    }

    public function remove(User $user, bool $flush = true): void
    {
    }

    public function findByEmail(string $email): ?User
    {
        return $this->user !== null && $this->user->email() === mb_strtolower(trim($email)) ? $this->user : null;
    }

    public function findById(string $id): ?User
    {
        return null;
    }

    public function findAllUsers(): array
    {
        return $this->user !== null ? [$this->user] : [];
    }
}

final class SignInAccessTokenRepository implements AccessTokenRepositoryInterface
{
    /** @var list<AccessToken> */
    public array $tokens = [];

    public function save(AccessToken $token, bool $flush = true): void
    {
        $this->tokens[] = $token;
    }

    public function findActiveByHash(string $tokenHash, \DateTimeImmutable $at): ?AccessToken
    {
        foreach ($this->tokens as $token) {
            if ($token->tokenHash() === $tokenHash && $token->isValidAt($at)) {
                return $token;
            }
        }

        return null;
    }

    public function deleteExpired(\DateTimeImmutable $at): int
    {
        return 0;
    }
}
