<?php

declare(strict_types=1);

namespace App\UI\Controller;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Put;
use App\Application\DTO\ApiDataResponse;
use App\Application\DTO\AdminReplaceUserInput;
use App\Application\DTO\AdminUpdateUserInput;
use App\UI\Processor\AdminDeleteUserProcessor;
use App\UI\Processor\AdminReplaceUserProcessor;
use App\UI\Processor\AdminUpdateUserProcessor;
use App\UI\Provider\AdminUserItemProvider;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/users/{id}',
            output: ApiDataResponse::class,
            name: 'admin_users_get',
            provider: AdminUserItemProvider::class,
        ),
        new Patch(
            uriTemplate: '/admin/users/{id}',
            input: AdminUpdateUserInput::class,
            output: ApiDataResponse::class,
            read: false,
            name: 'admin_users_update',
            processor: AdminUpdateUserProcessor::class,
        ),
        new Put(
            uriTemplate: '/admin/users/{id}',
            input: AdminReplaceUserInput::class,
            output: ApiDataResponse::class,
            read: false,
            name: 'admin_users_replace',
            processor: AdminReplaceUserProcessor::class,
        ),
        new Delete(
            uriTemplate: '/admin/users/{id}',
            output: ApiDataResponse::class,
            read: false,
            status: 200,
            name: 'admin_users_delete',
            processor: AdminDeleteUserProcessor::class,
        ),
    ],
)]
final class AdminUserItemResource
{
}
