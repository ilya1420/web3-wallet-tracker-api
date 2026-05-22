<?php

declare(strict_types=1);

namespace App\UI\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\DTO\ApiDataResponse;
use App\Application\Exception\UserNotFoundException;
use App\Application\Service\MultiAccountGuardService;
use App\Domain\Repository\UserRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements ProcessorInterface<mixed, ApiDataResponse>
 */
final readonly class AdminDeleteUserProcessor implements ProcessorInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private MultiAccountGuardService $multiAccountGuard,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ApiDataResponse
    {
        $id = (string) ($uriVariables['id'] ?? '');
        $user = $this->userRepository->findById($id);

        if ($user === null) {
            throw new UserNotFoundException('User not found.');
        }

        $this->entityManager->getConnection()->transactional(function () use ($user): void {
            $this->multiAccountGuard->clearUserRegistrationContext($user, false);
            $this->userRepository->remove($user, false);
            $this->entityManager->flush();
        });

        return new ApiDataResponse(['deleted' => true]);
    }
}
