<?php

declare(strict_types=1);

namespace App\UI\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Application\DTO\UserOutput;
use App\Application\Service\UserOutputMapper;
use App\Domain\Repository\UserRepositoryInterface;

final readonly class AdminUsersProvider implements ProviderInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private UserOutputMapper $mapper,
    ) {
    }

    /** @return list<UserOutput> */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $users = $this->userRepository->findAllUsers();

        return array_map($this->mapper->toUserOutput(...), $users);
    }
}
