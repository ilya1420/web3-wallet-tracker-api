<?php

declare(strict_types=1);

namespace App\Domain\Enum;

enum EvmRpcPreset: string
{
    case ETHEREUM = 'ethereum';
    case ARBITRUM = 'arbitrum';
    case OPTIMISM = 'optimism';
    case BASE = 'base';
    case POLYGON = 'polygon';
    case BSC = 'bsc';
    case AVALANCHE = 'avalanche';

    public function endpoint(): string
    {
        return match ($this) {
            self::ETHEREUM => 'https://ethereum-rpc.publicnode.com',
            self::ARBITRUM => 'https://arbitrum-one-rpc.publicnode.com',
            self::OPTIMISM => 'https://optimism-rpc.publicnode.com',
            self::BASE => 'https://base-rpc.publicnode.com',
            self::POLYGON => 'https://polygon-bor-rpc.publicnode.com',
            self::BSC => 'https://bsc-rpc.publicnode.com',
            self::AVALANCHE => 'https://avalanche-c-chain-rpc.publicnode.com',
        };
    }

    public function networkId(): string
    {
        return match ($this) {
            self::ETHEREUM => '1',
            self::ARBITRUM => '42161',
            self::OPTIMISM => '10',
            self::BASE => '8453',
            self::POLYGON => '137',
            self::BSC => '56',
            self::AVALANCHE => '43114',
        };
    }

    public function networkName(): string
    {
        return match ($this) {
            self::ETHEREUM => 'Ethereum Mainnet',
            self::ARBITRUM => 'Arbitrum One',
            self::OPTIMISM => 'OP Mainnet',
            self::BASE => 'Base Mainnet',
            self::POLYGON => 'Polygon PoS',
            self::BSC => 'BNB Smart Chain',
            self::AVALANCHE => 'Avalanche C-Chain',
        };
    }

    public function nativeSymbol(): string
    {
        return match ($this) {
            self::BSC => 'BNB',
            self::POLYGON => 'POL',
            self::AVALANCHE => 'AVAX',
            default => 'ETH',
        };
    }

    public function coinMarketCapSymbol(): string
    {
        return match ($this) {
            self::BSC => 'BNB',
            self::POLYGON => 'POL',
            self::AVALANCHE => 'AVAX',
            default => 'ETH',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public static function fromNetworkId(string $networkId): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->networkId() === $networkId) {
                return $case;
            }
        }

        return null;
    }
}
