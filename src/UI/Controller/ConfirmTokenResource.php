<?php

declare(strict_types=1);

namespace App\UI\Controller;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Application\DTO\AuthTokenOutput;
use App\Application\DTO\ConfirmTokenInput;
use App\UI\Processor\ConfirmTokenProcessor;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/auth/confirm-token',
            input: ConfirmTokenInput::class,
            output: AuthTokenOutput::class,
            processor: ConfirmTokenProcessor::class,
            read: false,
            name: 'confirm_token',
        ),
    ],
)]
final class ConfirmTokenResource
{
}
