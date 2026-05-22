<?php

declare(strict_types=1);

namespace App\UI\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Application\DTO\ApiDataResponse;
use App\Application\Exception\UserNotFoundException;
use App\Application\Service\MultiAccountGuardService;
use App\Application\Service\UserOutputMapper;
use App\Domain\Repository\UserRepositoryInterface;

/**
 * @implements ProviderInterface<ApiDataResponse>
 */
final readonly class AdminUserItemProvider implements ProviderInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private UserOutputMapper $mapper,
        private MultiAccountGuardService $multiAccountGuard,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ApiDataResponse
    {
        $id = (string) ($uriVariables['id'] ?? '');
        $user = $this->userRepository->findById($id);

        if ($user === null) {
            throw new UserNotFoundException('User not found.');
        }

        return new ApiDataResponse($this->mapper->toAdminUserOutput($user, $this->multiAccountGuard->findContext($user)));
    }
}
