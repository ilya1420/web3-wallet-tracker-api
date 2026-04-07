<?php

declare(strict_types=1);

namespace App\Tests\Integration\Api;

final class AuthFlowTest extends ApiTestCase
{
    public function testRequestLoginLinkCreatesTokenAndOutboxMessageForExistingUser(): void
    {
        $this->createUser('alice@example.com');

        $response = $this->requestJson('POST', '/api/auth/login-links', [
            'email' => 'alice@example.com',
        ]);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame(['data' => ['status' => 'ok']], $this->decodeJson($response));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM login_tokens'));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM outbox_messages'));
        self::assertNotSame('', $this->latestOutboxToken());
    }

    public function testRequestLoginLinkForUnknownUserDoesNotLeakAccountExistence(): void
    {
        $response = $this->requestJson('POST', '/api/auth/login-links', [
            'email' => 'missing@example.com',
        ]);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame(['data' => ['status' => 'ok']], $this->decodeJson($response));
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM login_tokens'));
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM outbox_messages'));
    }

    public function testConfirmTokenReturnsBearerTokenAndAllowsAccessToMeEndpoint(): void
    {
        $this->createUser('bob@example.com');

        $this->requestJson('POST', '/api/auth/login-links', [
            'email' => 'bob@example.com',
        ]);

        $rawLoginToken = $this->latestOutboxToken();
        $confirmResponse = $this->requestJson('POST', '/api/auth/confirm-token', [
            'email' => 'bob@example.com',
            'token' => $rawLoginToken,
        ]);

        self::assertSame(201, $confirmResponse->getStatusCode());
        $confirmPayload = $this->decodeJson($confirmResponse);
        self::assertSame('Bearer', $confirmPayload['data']['tokenType'] ?? null);
        self::assertIsString($confirmPayload['data']['accessToken'] ?? null);
        self::assertNotSame('', $confirmPayload['data']['accessToken'] ?? '');
        self::assertIsString($confirmPayload['data']['expiresAt'] ?? null);

        $meResponse = $this->requestJson('GET', '/api/me', null, [
            'HTTP_AUTHORIZATION' => 'Bearer '.$confirmPayload['data']['accessToken'],
        ]);

        self::assertSame(200, $meResponse->getStatusCode());
        self::assertSame('bob@example.com', $this->decodeJson($meResponse)['data']['email'] ?? null);
        self::assertTrue((bool) ($this->decodeJson($meResponse)['data']['isVerified'] ?? false));

        $userState = $this->connection->fetchAssociative(
            'SELECT is_verified, last_login_at FROM users WHERE email = :email',
            ['email' => 'bob@example.com'],
        );

        self::assertSame('1', (string) ($userState['is_verified'] ?? '0'));
        self::assertNotNull($userState['last_login_at'] ?? null);
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM access_tokens'));
    }

    public function testConfirmTokenRejectsInvalidToken(): void
    {
        $this->createUser('carol@example.com');

        $response = $this->requestJson('POST', '/api/auth/confirm-token', [
            'email' => 'carol@example.com',
            'token' => str_repeat('a', 32),
        ]);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame(
            ['message' => 'Invalid or expired login token.'],
            $this->decodeJson($response),
        );
    }

    public function testMeEndpointRequiresBearerToken(): void
    {
        $response = $this->requestJson('GET', '/api/me');

        self::assertSame(401, $response->getStatusCode());
        self::assertSame(['message' => 'Full authentication is required to access this resource.'], $this->decodeJson($response));
    }
}
