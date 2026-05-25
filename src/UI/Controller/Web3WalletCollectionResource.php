<?php

declare(strict_types=1);

namespace App\UI\Controller;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Application\DTO\ApiDataResponse;
use App\Application\DTO\CreateWeb3WalletInput;
use App\UI\Processor\CreateWeb3WalletProcessor;
use App\UI\Provider\Web3WalletsProvider;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/web3/wallets',
            output: ApiDataResponse::class,
            name: 'web3_wallets_list',
            provider: Web3WalletsProvider::class,
        ),
        new Post(
            uriTemplate: '/web3/wallets',
            input: CreateWeb3WalletInput::class,
            output: ApiDataResponse::class,
            read: false,
            name: 'web3_wallets_create',
            processor: CreateWeb3WalletProcessor::class,
        ),
    ],
    paginationEnabled: false,
)]
final class Web3WalletCollectionResource
{
}
