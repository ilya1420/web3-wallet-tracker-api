<?php

declare(strict_types=1);

namespace App\UI\Controller;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Application\DTO\ApiDataResponse;
use App\Application\DTO\CreateWeb3WalletInput;
use App\UI\Processor\CreateWeb3WalletProcessor;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/web3/wallets',
            input: CreateWeb3WalletInput::class,
            output: ApiDataResponse::class,
            read: false,
            name: 'web3_wallets_create',
            processor: CreateWeb3WalletProcessor::class,
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
        ),
    ],
)]
final class Web3WalletCollectionResource
{
}
