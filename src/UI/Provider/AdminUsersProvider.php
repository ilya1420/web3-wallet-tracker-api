<?php

declare(strict_types=1);

namespace App\UI\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Application\DTO\ApiDataResponse;
use App\Application\Service\UserOutputMapper;
use App\Domain\Repository\UserRepositoryInterface;

final readonly class AdminUsersProvider implements ProviderInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private UserOutputMapper $mapper,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ApiDataResponse
    {
        $users = $this->userRepository->findAllUsers();
        $items = array_map($this->mapper->toUserOutput(...), $users);

        return new ApiDataResponse($items);
    }
}
