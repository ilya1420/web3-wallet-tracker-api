<?php

declare(strict_types=1);

namespace App\Tests\Integration\Api;

use App\Kernel;
use App\Application\Service\TokenManager;
use App\Domain\Entity\AccessToken;
use App\Domain\Entity\User;
use App\Domain\ValueObject\Email;
use App\Tests\Integration\Support\DatabaseResetter;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

abstract class ApiTestCase extends TestCase
{
    protected static ?KernelInterface $kernel = null;
    protected EntityManagerInterface $entityManager;
    protected Connection $connection;

    protected function setUp(): void
    {
        self::$kernel = new Kernel('test', true);
        self::$kernel->boot();

        /** @var ManagerRegistry $doctrine */
        $doctrine = self::$kernel->getContainer()->get('doctrine');
        $this->entityManager = $doctrine->getManager();
        $this->connection = $this->entityManager->getConnection();

        DatabaseResetter::reset($this->entityManager);
    }

    protected function tearDown(): void
    {
        $this->entityManager->close();
        self::$kernel?->shutdown();
        self::$kernel = null;

        parent::tearDown();
    }

    /**
     * @param array<string, mixed>|null $payload
     * @param array<string, string> $headers
     */
    protected function requestJson(string $method, string $uri, ?array $payload = null, array $headers = []): Response
    {
        $server = [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ];

        foreach ($headers as $name => $value) {
            $server[$name] = $value;
        }

        $request = Request::create(
            $uri,
            $method,
            [],
            [],
            [],
            $server,
            $payload !== null ? json_encode($payload, JSON_THROW_ON_ERROR) : null,
        );

        $response = self::$kernel->handle($request);
        self::$kernel->terminate($request, $response);

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeJson(Response $response): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($response->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    protected function createUser(
        string $email,
        array $roles = ['ROLE_USER'],
        bool $isVerified = false,
        string $password = 'Password123!'
    ): User {
        $user = new User(
            new Email($email),
            password_hash($password, PASSWORD_ARGON2ID),
            $roles,
        );

        if ($isVerified) {
            $user->verify();
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    protected function latestOutboxToken(): string
    {
        $body = $this->connection->fetchOne('SELECT body FROM outbox_messages ORDER BY created_at DESC LIMIT 1');
        self::assertIsString($body);

        /** @var array{payload?: array{token?: string}} $decoded */
        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        return (string) ($decoded['payload']['token'] ?? '');
    }

    protected function issueAccessToken(User $user, string $ttl = 'PT24H'): string
    {
        $managedUser = $this->entityManager->find(User::class, $user->id());
        self::assertInstanceOf(User::class, $managedUser);

        $rawToken = (new TokenManager())->generateRawToken(48);
        $accessToken = new AccessToken(
            $managedUser,
            (new TokenManager())->hashToken($rawToken),
            (new \DateTimeImmutable())->add(new \DateInterval($ttl)),
        );

        $this->entityManager->persist($accessToken);
        $this->entityManager->flush();

        return $rawToken;
    }

    /**
     * @return array<string, string>
     */
    protected function bearerHeaders(User $user): array
    {
        return [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->issueAccessToken($user),
        ];
    }
}
