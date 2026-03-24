<?php

declare(strict_types=1);

namespace App\UI\Controller;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Application\DTO\UserOutput;
use App\UI\Provider\UsersProvider;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/users',
            output: UserOutput::class,
            provider: UsersProvider::class,
            security: "is_granted('ROLE_ADMIN')",
            name: 'users_list',
        ),
    ],
    paginationEnabled: false,
)]
final class UserCollectionResource
{
}
