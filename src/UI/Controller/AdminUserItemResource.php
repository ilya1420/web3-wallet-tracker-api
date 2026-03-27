<?php

declare(strict_types=1);

namespace App\UI\Controller;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use App\Application\DTO\AdminUpdateUserInput;
use App\Application\DTO\UserOutput;
use App\UI\Processor\AdminDeleteUserProcessor;
use App\UI\Processor\AdminUpdateUserProcessor;
use App\UI\Provider\AdminUserItemProvider;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/users/{id}',
            security: "is_granted('ROLE_ADMIN')",
            output: UserOutput::class,
            name: 'admin_users_get',
            provider: AdminUserItemProvider::class,
        ),
        new Patch(
            uriTemplate: '/admin/users/{id}',
            security: "is_granted('ROLE_ADMIN')",
            input: AdminUpdateUserInput::class,
            output: UserOutput::class,
            read: false,
            name: 'admin_users_update',
            processor: AdminUpdateUserProcessor::class,
        ),
        new Delete(
            uriTemplate: '/admin/users/{id}',
            security: "is_granted('ROLE_ADMIN')",
            output: false,
            read: false,
            name: 'admin_users_delete',
            processor: AdminDeleteUserProcessor::class,
        ),
    ],
)]
final class AdminUserItemResource
{
}
