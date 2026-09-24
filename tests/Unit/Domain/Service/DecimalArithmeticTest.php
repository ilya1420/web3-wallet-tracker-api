<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Service;

use App\Domain\Service\DecimalArithmetic;
use PHPUnit\Framework\TestCase;

final class DecimalArithmeticTest extends TestCase
{
    public function testMultipliesLargeDecimalAmountsWithoutFloatPrecisionLoss(): void
    {
        self::assertSame(
            '307407404640740.74047334567890123456789',
            DecimalArithmetic::multiply('123456789012.3456789', '2490.0000000000000001'),
        );
    }
}
