<?php

declare(strict_types=1);

namespace App\UI\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Application\DTO\ApiDataResponse;
use App\Application\Service\UserOutputMapper;
use App\Domain\Repository\UserRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class AdminUserItemProvider implements ProviderInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private UserOutputMapper $mapper,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ApiDataResponse
    {
        $id = (string) ($uriVariables['id'] ?? '');
        $user = $this->userRepository->findById($id);

        if ($user === null) {
            throw new NotFoundHttpException('User not found.');
        }

        return new ApiDataResponse($this->mapper->toUserOutput($user));
    }
}
