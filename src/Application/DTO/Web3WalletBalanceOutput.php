<?php

declare(strict_types=1);

namespace App\Application\DTO;

final readonly class Web3WalletBalanceOutput
{
    public function __construct(
        public string $walletId,
        public string $address,
        public string $networkId,
        public string $networkName,
        public string $nativeSymbol,
        public string $balanceWei,
        public string $balanceEth,
        public string $balanceEthFormatted,
        public string $balanceDisplay,
        public string $syncedAt,
    ) {
    }
}
