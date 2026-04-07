<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Entity;

use App\Domain\Entity\User;
use App\Domain\ValueObject\Email;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testConstructorAndRoleChangesAlwaysKeepRoleUser(): void
    {
        $user = new User(new Email('admin@example.com'), 'hashed-password', ['ROLE_ADMIN']);

        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $user->getRoles());

        $user->changeRoles(['ROLE_MANAGER']);

        self::assertSame(['ROLE_MANAGER', 'ROLE_USER'], $user->getRoles());
    }

    public function testVerifyAndMarkLoggedInMutateState(): void
    {
        $user = new User(new Email('member@example.com'), 'hashed-password');

        self::assertFalse($user->isVerified());
        self::assertNull($user->lastLoginAt());

        $user->verify();
        $user->markLoggedIn();

        self::assertTrue($user->isVerified());
        self::assertInstanceOf(\DateTimeImmutable::class, $user->lastLoginAt());
    }
}
