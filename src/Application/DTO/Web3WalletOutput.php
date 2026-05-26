<?php

declare(strict_types=1);

namespace App\Application\DTO;

final readonly class Web3WalletOutput
{
    public function __construct(
        public string $id,
        public string $address,
        public string $rpcEndpoint,
        public string $networkId,
        public string $networkName,
        public string $nativeSymbol,
        public ?string $lastKnownBalanceWei,
        public ?string $lastKnownBalanceEth,
        public ?string $lastKnownBalanceEthFormatted,
        public ?string $lastKnownBalanceDisplay,
        public ?string $lastSyncedAt,
        public string $createdAt,
    ) {
    }
}
