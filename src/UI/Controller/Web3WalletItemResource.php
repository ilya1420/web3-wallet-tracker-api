<?php

declare(strict_types=1);

namespace App\UI\Controller;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use App\Application\DTO\ApiDataResponse;
use App\Application\DTO\UpdateWeb3WalletInput;
use App\UI\Processor\DeleteWeb3WalletProcessor;
use App\UI\Processor\UpdateWeb3WalletProcessor;
use App\UI\Provider\Web3WalletItemProvider;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/web3/wallets/{id}',
            output: ApiDataResponse::class,
            name: 'web3_wallet_item_get',
            provider: Web3WalletItemProvider::class,
        ),
        new Patch(
            uriTemplate: '/web3/wallets/{id}',
            input: UpdateWeb3WalletInput::class,
            output: ApiDataResponse::class,
            read: false,
            name: 'web3_wallet_item_patch',
            processor: UpdateWeb3WalletProcessor::class,
        ),
        new Delete(
            uriTemplate: '/web3/wallets/{id}',
            output: ApiDataResponse::class,
            read: false,
            name: 'web3_wallet_item_delete',
            processor: DeleteWeb3WalletProcessor::class,
        ),
    ],
)]
final class Web3WalletItemResource
{
}
