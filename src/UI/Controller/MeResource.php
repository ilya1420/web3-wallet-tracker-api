<?php

declare(strict_types=1);

namespace App\UI\Controller;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Application\DTO\ApiDataResponse;
use App\UI\Provider\MeProvider;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/me',
            output: ApiDataResponse::class,
            name: 'me',
            provider: MeProvider::class,
        ),
    ],
)]
final class MeResource
{
}
