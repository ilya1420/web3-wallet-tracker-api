<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\DTO\ConversionResult;
use App\Application\DTO\Web3WalletBalanceOutput;
use App\Application\DTO\Web3WalletOutput;
use App\Domain\Enum\EvmRpcPreset;
use App\Domain\Entity\Web3Wallet;

final class Web3WalletOutputMapper
{
    public function toWalletOutput(Web3Wallet $wallet): Web3WalletOutput
    {
        $balanceWei = $wallet->lastKnownBalanceWei();
        $preset = EvmRpcPreset::fromNetworkId($wallet->networkId());
        $nativeSymbol = $preset?->nativeSymbol() ?? 'ETH';
        $balanceEth = $balanceWei !== null ? $this->formatWeiToEth($balanceWei) : null;
        $balanceEthFormatted = $balanceEth !== null ? $this->formatDecimalForDisplay($balanceEth) : null;

        return new Web3WalletOutput(
            id: $wallet->id()->toRfc4122(),
            address: $wallet->address(),
            rpcEndpoint: $wallet->rpcEndpoint(),
            networkId: $wallet->networkId(),
            networkName: $preset?->networkName() ?? sprintf('EVM Network #%s', $wallet->networkId()),
            nativeSymbol: $nativeSymbol,
            lastKnownBalanceWei: $balanceWei,
            lastKnownBalanceEth: $balanceEth,
            lastKnownBalanceEthFormatted: $balanceEthFormatted,
            lastKnownBalanceDisplay: $balanceEthFormatted !== null ? sprintf('%s %s', $balanceEthFormatted, $nativeSymbol) : null,
            lastSyncedAt: $wallet->lastSyncedAt()?->format(DATE_ATOM),
            createdAt: $wallet->createdAt()->format(DATE_ATOM),
        );
    }

    public function toBalanceOutput(Web3Wallet $wallet, ?ConversionResult $marketValue = null): Web3WalletBalanceOutput
    {
        $balanceWei = $wallet->lastKnownBalanceWei() ?? '0';
        $balanceEth = $this->formatWeiToEth($balanceWei);
        $preset = EvmRpcPreset::fromNetworkId($wallet->networkId());
        $nativeSymbol = $preset?->nativeSymbol() ?? 'ETH';
        $balanceEthFormatted = $this->formatDecimalForDisplay($balanceEth);

        return new Web3WalletBalanceOutput(
            walletId: $wallet->id()->toRfc4122(),
            address: $wallet->address(),
            networkId: $wallet->networkId(),
            networkName: $preset?->networkName() ?? sprintf('EVM Network #%s', $wallet->networkId()),
            nativeSymbol: $nativeSymbol,
            balanceWei: $balanceWei,
            balanceEth: $balanceEth,
            balanceEthFormatted: $balanceEthFormatted,
            balanceDisplay: sprintf('%s %s', $balanceEthFormatted, $nativeSymbol),
            syncedAt: ($wallet->lastSyncedAt() ?? new \DateTimeImmutable())->format(DATE_ATOM),
            marketValue: $marketValue,
        );
    }

    private function formatWeiToEth(string $wei): string
    {
        $normalized = ltrim($wei, '0');
        if ($normalized === '') {
            return '0';
        }

        $decimals = 18;
        $length = strlen($normalized);

        if ($length <= $decimals) {
            $fraction = str_pad($normalized, $decimals, '0', STR_PAD_LEFT);
            $fraction = rtrim($fraction, '0');

            return $fraction === '' ? '0' : sprintf('0.%s', $fraction);
        }

        $whole = substr($normalized, 0, $length - $decimals);
        $fraction = rtrim(substr($normalized, -$decimals), '0');

        return $fraction === '' ? $whole : sprintf('%s.%s', $whole, $fraction);
    }

    private function formatDecimalForDisplay(string $value): string
    {
        $parts = explode('.', $value, 2);
        $integer = preg_replace('/\B(?=(\d{3})+(?!\d))/', ' ', $parts[0]) ?? $parts[0];
        $fraction = $parts[1] ?? '';
        $fraction = rtrim(substr($fraction, 0, 6), '0');

        if ($fraction === '') {
            return $integer;
        }

        return sprintf('%s.%s', $integer, $fraction);
    }
}
