<?php

declare(strict_types=1);

namespace App\UI\Controller;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use App\Application\DTO\ApiDataResponse;
use App\Application\DTO\AdminUpdateUserInput;
use App\UI\Processor\AdminDeleteUserProcessor;
use App\UI\Processor\AdminUpdateUserProcessor;
use App\UI\Provider\AdminUserItemProvider;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/users/{id}',
            output: ApiDataResponse::class,
            name: 'admin_users_get',
            provider: AdminUserItemProvider::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Patch(
            uriTemplate: '/admin/users/{id}',
            input: AdminUpdateUserInput::class,
            output: ApiDataResponse::class,
            read: false,
            name: 'admin_users_update',
            processor: AdminUpdateUserProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Delete(
            uriTemplate: '/admin/users/{id}',
            status: 200,
            output: ApiDataResponse::class,
            read: false,
            name: 'admin_users_delete',
            processor: AdminDeleteUserProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
    ],
)]
final class AdminUserItemResource
{
}
