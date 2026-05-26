<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Enum;

use App\Domain\Enum\EvmRpcPreset;
use PHPUnit\Framework\TestCase;

final class EvmRpcPresetTest extends TestCase
{
    public function testPresetHasStableMetadata(): void
    {
        self::assertSame('https://base-rpc.publicnode.com', EvmRpcPreset::BASE->endpoint());
        self::assertSame('8453', EvmRpcPreset::BASE->networkId());
        self::assertSame('Base Mainnet', EvmRpcPreset::BASE->networkName());
        self::assertSame('ETH', EvmRpcPreset::BASE->nativeSymbol());
    }

    public function testFromNetworkIdReturnsMatchingPreset(): void
    {
        self::assertSame(EvmRpcPreset::BSC, EvmRpcPreset::fromNetworkId('56'));
        self::assertNull(EvmRpcPreset::fromNetworkId('999999'));
    }
}
