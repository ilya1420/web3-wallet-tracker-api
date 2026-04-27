<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\DTO\Web3WalletBalanceOutput;
use App\Application\DTO\Web3WalletOutput;
use App\Domain\Entity\Web3Wallet;

final class Web3WalletOutputMapper
{
    public function toWalletOutput(Web3Wallet $wallet): Web3WalletOutput
    {
        $balanceWei = $wallet->lastKnownBalanceWei();

        return new Web3WalletOutput(
            id: $wallet->id()->toRfc4122(),
            address: $wallet->address(),
            rpcEndpoint: $wallet->rpcEndpoint(),
            networkId: $wallet->networkId(),
            lastKnownBalanceWei: $balanceWei,
            lastKnownBalanceEth: $balanceWei !== null ? $this->formatWeiToEth($balanceWei) : null,
            lastSyncedAt: $wallet->lastSyncedAt()?->format(DATE_ATOM),
            createdAt: $wallet->createdAt()->format(DATE_ATOM),
        );
    }

    public function toBalanceOutput(Web3Wallet $wallet): Web3WalletBalanceOutput
    {
        $balanceWei = $wallet->lastKnownBalanceWei() ?? '0';

        return new Web3WalletBalanceOutput(
            walletId: $wallet->id()->toRfc4122(),
            address: $wallet->address(),
            networkId: $wallet->networkId(),
            balanceWei: $balanceWei,
            balanceEth: $this->formatWeiToEth($balanceWei),
            syncedAt: ($wallet->lastSyncedAt() ?? new \DateTimeImmutable())->format(DATE_ATOM),
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
}
