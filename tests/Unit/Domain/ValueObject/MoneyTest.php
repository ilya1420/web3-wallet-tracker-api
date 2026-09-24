<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testNormalizesDecimalWithoutLosingPrecision(): void
    {
        $money = new Money('0001.23000000000000000100');

        self::assertSame('1.230000000000000001', $money->amount());
        self::assertFalse($money->isZero());
    }

    public function testRejectsFloatNotationAndNegativeAmounts(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Money('-0.01');
    }
}
