<?php

declare(strict_types=1);

namespace App\UI\Controller;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Application\DTO\ApiDataResponse;
use App\UI\Provider\Web3WalletBalanceProvider;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/web3/wallets/{id}/balance',
            output: ApiDataResponse::class,
            name: 'web3_wallet_balance_get',
            provider: Web3WalletBalanceProvider::class,
        ),
    ],
)]
final class Web3WalletBalanceResource
{
}
