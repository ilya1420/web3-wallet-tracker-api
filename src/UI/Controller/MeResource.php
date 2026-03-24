<?php

declare(strict_types=1);

namespace App\UI\Controller;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Application\DTO\MeOutput;
use App\UI\Provider\MeProvider;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/me',
            output: MeOutput::class,
            provider: MeProvider::class,
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            name: 'me',
        ),
    ],
)]
final class MeResource
{
}
