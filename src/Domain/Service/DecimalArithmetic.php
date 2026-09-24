<?php

declare(strict_types=1);

namespace App\Domain\Service;

final class DecimalArithmetic
{
    public static function multiply(string $left, string $right): string
    {
        [$leftDigits, $leftScale] = self::digitsAndScale($left);
        [$rightDigits, $rightScale] = self::digitsAndScale($right);
        $leftDigits = ltrim($leftDigits, '0') ?: '0';
        $rightDigits = ltrim($rightDigits, '0') ?: '0';

        if ($leftDigits === '0' || $rightDigits === '0') {
            return '0';
        }

        $result = array_fill(0, strlen($leftDigits) + strlen($rightDigits), 0);
        for ($leftIndex = strlen($leftDigits) - 1; $leftIndex >= 0; --$leftIndex) {
            for ($rightIndex = strlen($rightDigits) - 1; $rightIndex >= 0; --$rightIndex) {
                $position = $leftIndex + $rightIndex + 1;
                $result[$position] += ((int) $leftDigits[$leftIndex]) * ((int) $rightDigits[$rightIndex]);
            }
        }

        for ($index = count($result) - 1; $index > 0; --$index) {
            $result[$index - 1] += intdiv($result[$index], 10);
            $result[$index] %= 10;
        }

        $digits = ltrim(implode('', $result), '0') ?: '0';
        $scale = $leftScale + $rightScale;

        if ($scale === 0) {
            return $digits;
        }

        $digits = str_pad($digits, $scale + 1, '0', STR_PAD_LEFT);
        $value = sprintf('%s.%s', substr($digits, 0, -$scale), substr($digits, -$scale));

        return rtrim(rtrim($value, '0'), '.');
    }

    /** @return array{0: string, 1: int} */
    private static function digitsAndScale(string $value): array
    {
        if (preg_match('/^(\d+)(?:\.(\d+))?$/', $value, $matches) !== 1) {
            throw new \InvalidArgumentException('Decimal arithmetic requires non-negative decimal strings.');
        }

        return [$matches[1] . ($matches[2] ?? ''), strlen($matches[2] ?? '')];
    }
}
