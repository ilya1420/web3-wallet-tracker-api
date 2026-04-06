<?php

declare(strict_types=1);

namespace App\UI\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\DTO\AdminUpdateUserInput;
use App\Application\DTO\ApiDataResponse;
use App\Application\Service\MultiAccountGuardService;
use App\Application\Service\UserOutputMapper;
use App\Domain\Repository\UserRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class AdminUpdateUserProcessor implements ProcessorInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private UserOutputMapper $mapper,
        private MultiAccountGuardService $multiAccountGuard,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ApiDataResponse
    {
        \assert($data instanceof AdminUpdateUserInput);

        $id = (string) ($uriVariables['id'] ?? '');
        $user = $this->userRepository->findById($id);

        if ($user === null) {
            throw new NotFoundHttpException('User not found.');
        }

        $context = $this->multiAccountGuard->findContext($user);

        if ($data->roles !== null) {
            $user->changeRoles($data->roles);
        }

        $this->entityManager->getConnection()->transactional(function () use ($user): void {
            $this->userRepository->save($user, false);
            $this->entityManager->flush();
        });

        return new ApiDataResponse($this->mapper->toAdminUserOutput($user, $context));
    }
}
