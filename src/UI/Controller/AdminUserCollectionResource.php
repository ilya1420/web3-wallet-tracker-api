<?php

declare(strict_types=1);

namespace App\UI\Controller;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Application\DTO\AdminCreateUserInput;
use App\Application\DTO\UserOutput;
use App\UI\Processor\AdminCreateUserProcessor;
use App\UI\Provider\AdminUsersProvider;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/admin/users',
            output: UserOutput::class,
            provider: AdminUsersProvider::class,
            security: "is_granted('ROLE_ADMIN')",
            name: 'admin_users_list',
        ),
        new Post(
            uriTemplate: '/admin/users',
            input: AdminCreateUserInput::class,
            output: UserOutput::class,
            processor: AdminCreateUserProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
            read: false,
            name: 'admin_users_create',
        ),
    ],
    paginationEnabled: false,
)]
final class AdminUserCollectionResource
{
}
