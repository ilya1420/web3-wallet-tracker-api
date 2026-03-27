<?php

declare(strict_types=1);

namespace App\UI\Controller;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Application\DTO\ApiDataResponse;
use App\Application\DTO\RegisterUserInput;
use App\UI\Processor\RegisterUserProcessor;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/auth/register',
            input: RegisterUserInput::class,
            output: ApiDataResponse::class,
            read: false,
            name: 'register_user',
            processor: RegisterUserProcessor::class,
        ),
    ],
)]
final class RegisterUserResource
{
}
