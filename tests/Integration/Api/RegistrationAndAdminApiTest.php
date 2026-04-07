<?php

declare(strict_types=1);

namespace App\Tests\Integration\Api;

final class RegistrationAndAdminApiTest extends ApiTestCase
{
    public function testRegisterCreatesUserContextAndOutboxMessage(): void
    {
        $response = $this->requestJson('POST', '/api/auth/register', [
            'email' => 'new-user@example.com',
            'password' => 'StrongPass123!',
            'deviceFingerprint' => 'device-4f95bca6d8f64a93',
        ], [
            'REMOTE_ADDR' => '10.20.30.40',
        ]);

        self::assertSame(201, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame('new-user@example.com', $payload['data']['email'] ?? null);
        self::assertFalse((bool) ($payload['data']['isVerified'] ?? true));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM users'));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_registration_context'));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM outbox_messages'));
    }

    public function testRegisterRejectsDuplicateEmail(): void
    {
        $this->createUser('duplicate@example.com');

        $response = $this->requestJson('POST', '/api/auth/register', [
            'email' => 'duplicate@example.com',
            'password' => 'StrongPass123!',
            'deviceFingerprint' => 'device-4f95bca6d8f64a93',
        ], [
            'REMOTE_ADDR' => '10.20.30.40',
        ]);

        self::assertSame(409, $response->getStatusCode());
        self::assertSame(['message' => 'User with this email already exists.'], $this->decodeJson($response));
    }

    public function testRegisterRejectsMultiAccountingForSameFingerprint(): void
    {
        $firstResponse = $this->requestJson('POST', '/api/auth/register', [
            'email' => 'first@example.com',
            'password' => 'StrongPass123!',
            'deviceFingerprint' => 'shared-device-4f95bca6',
        ], [
            'REMOTE_ADDR' => '10.20.30.40',
        ]);

        self::assertSame(201, $firstResponse->getStatusCode());

        $secondResponse = $this->requestJson('POST', '/api/auth/register', [
            'email' => 'second@example.com',
            'password' => 'StrongPass123!',
            'deviceFingerprint' => 'shared-device-4f95bca6',
        ], [
            'REMOTE_ADDR' => '10.20.30.41',
        ]);

        self::assertSame(422, $secondResponse->getStatusCode());
        self::assertSame(
            ['message' => 'Multi-accounting is not allowed for this device.'],
            $this->decodeJson($secondResponse),
        );
    }

    public function testAdminEndpointsRequireAdminRole(): void
    {
        $user = $this->createUser('user@example.com');

        $response = $this->requestJson('GET', '/api/admin/users', null, $this->bearerHeaders($user));

        self::assertSame(403, $response->getStatusCode());
    }

    public function testAdminCanListGetCreateUpdateAndDeleteUsers(): void
    {
        $admin = $this->createUser('admin@example.com', ['ROLE_ADMIN'], true);

        $registerResponse = $this->requestJson('POST', '/api/auth/register', [
            'email' => 'context-user@example.com',
            'password' => 'StrongPass123!',
            'deviceFingerprint' => 'device-for-admin-view-1234',
        ], [
            'REMOTE_ADDR' => '10.20.30.40',
        ]);
        self::assertSame(201, $registerResponse->getStatusCode());

        $listResponse = $this->requestJson('GET', '/api/admin/users', null, $this->bearerHeaders($admin));
        self::assertSame(200, $listResponse->getStatusCode());
        $listPayload = $this->decodeJson($listResponse);
        self::assertCount(2, $listPayload['data']);

        $contextUser = null;
        foreach ($listPayload['data'] as $item) {
            if (($item['email'] ?? null) === 'context-user@example.com') {
                $contextUser = $item;
                break;
            }
        }

        self::assertIsArray($contextUser);
        self::assertNotNull($contextUser['deviceFingerprintHash'] ?? null);
        self::assertNotNull($contextUser['registrationIpHash'] ?? null);

        $createResponse = $this->requestJson('POST', '/api/admin/users', [
            'email' => 'managed@example.com',
            'password' => 'StrongPass123!',
            'roles' => ['ROLE_MANAGER'],
            'isVerified' => true,
        ], $this->bearerHeaders($admin));

        self::assertSame(201, $createResponse->getStatusCode());
        $createdPayload = $this->decodeJson($createResponse);
        self::assertSame('managed@example.com', $createdPayload['data']['email'] ?? null);
        self::assertSame(['ROLE_MANAGER', 'ROLE_USER'], $createdPayload['data']['roles'] ?? null);
        self::assertTrue((bool) ($createdPayload['data']['isVerified'] ?? false));

        $managedId = (string) ($createdPayload['data']['id'] ?? '');
        self::assertNotSame('', $managedId);

        $getResponse = $this->requestJson('GET', '/api/admin/users/'.$managedId, null, $this->bearerHeaders($admin));
        self::assertSame(200, $getResponse->getStatusCode());
        self::assertSame('managed@example.com', $this->decodeJson($getResponse)['data']['email'] ?? null);

        $patchResponse = $this->requestJson('PATCH', '/api/admin/users/'.$managedId, [
            'roles' => ['ROLE_ADMIN'],
        ], array_merge($this->bearerHeaders($admin), [
            'CONTENT_TYPE' => 'application/merge-patch+json',
        ]));

        self::assertSame(200, $patchResponse->getStatusCode());
        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $this->decodeJson($patchResponse)['data']['roles'] ?? null);

        $deleteResponse = $this->requestJson('DELETE', '/api/admin/users/'.$managedId, null, $this->bearerHeaders($admin));
        self::assertSame(200, $deleteResponse->getStatusCode());
        self::assertSame(['data' => ['deleted' => true]], $this->decodeJson($deleteResponse));
        self::assertSame(0, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM users WHERE email = :email',
            ['email' => 'managed@example.com'],
        ));
    }
}
