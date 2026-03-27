<?php

declare(strict_types=1);

namespace App\UI\Controller;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Application\DTO\ApiDataResponse;
use App\Application\DTO\RequestLoginLinkInput;
use App\UI\Processor\RequestLoginLinkProcessor;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/auth/request-login-link',
            input: RequestLoginLinkInput::class,
            output: ApiDataResponse::class,
            read: false,
            name: 'request_login_link',
            processor: RequestLoginLinkProcessor::class,
        ),
    ],
)]
final class RequestLoginLinkResource
{
}
