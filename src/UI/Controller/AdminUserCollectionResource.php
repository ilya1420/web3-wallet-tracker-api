<?php

declare(strict_types=1);

namespace App\UI\Controller;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Application\DTO\ApiDataResponse;
use App\Application\DTO\AdminCreateUserInput;
use App\UI\Processor\AdminCreateUserProcessor;
use App\UI\Provider\AdminUsersProvider;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/admin/users',
            security: "is_granted('ROLE_ADMIN')",
            output: ApiDataResponse::class,
            name: 'admin_users_list',
            provider: AdminUsersProvider::class,
        ),
        new Post(
            uriTemplate: '/admin/users',
            security: "is_granted('ROLE_ADMIN')",
            input: AdminCreateUserInput::class,
            output: ApiDataResponse::class,
            read: false,
            name: 'admin_users_create',
            processor: AdminCreateUserProcessor::class,
        ),
    ],
    paginationEnabled: false,
)]
final class AdminUserCollectionResource
{
}
