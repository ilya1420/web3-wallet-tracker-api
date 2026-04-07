<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\Email;
use PHPUnit\Framework\TestCase;

final class EmailTest extends TestCase
{
    public function testNormalizesEmail(): void
    {
        $email = new Email(' User@Example.COM ');

        self::assertSame('user@example.com', $email->value());
    }

    public function testRejectsInvalidEmail(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Email('not-an-email');
    }
}
