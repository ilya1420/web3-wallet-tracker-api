<?php

declare(strict_types=1);

namespace App\UI\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\DTO\AdminReplaceUserInput;
use App\Application\DTO\ApiDataResponse;
use App\Application\Service\MultiAccountGuardService;
use App\Application\Service\UserOutputMapper;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\Email;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class AdminReplaceUserProcessor implements ProcessorInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private UserOutputMapper $mapper,
        private UserPasswordHasherInterface $passwordHasher,
        private MultiAccountGuardService $multiAccountGuard,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ApiDataResponse
    {
        \assert($data instanceof AdminReplaceUserInput);

        $id = (string) ($uriVariables['id'] ?? '');
        $user = $this->userRepository->findById($id);

        if ($user === null) {
            throw new NotFoundHttpException('User not found.');
        }

        $newEmail = new Email($data->email);
        $existing = $this->userRepository->findByEmail($newEmail->value());
        if ($existing !== null && !$existing->id()->equals($user->id())) {
            throw new ConflictHttpException('User with this email already exists.');
        }

        $user->changeEmail($newEmail);
        $user->changeRoles($data->roles);
        $user->setVerified($data->isVerified);
        $user->changeCreatedAt(new \DateTimeImmutable($data->createdAt));
        $user->changeLastLoginAt($data->lastLoginAt !== null ? new \DateTimeImmutable($data->lastLoginAt) : null);

        if ($data->password !== null) {
            $user->changePassword($this->passwordHasher->hashPassword($user, $data->password));
        }

        $this->entityManager->getConnection()->transactional(function () use ($user): void {
            $this->userRepository->save($user, false);
            $this->entityManager->flush();
        });

        $counterDate = $data->registrationIpCounterDate !== null
            ? new \DateTimeImmutable($data->registrationIpCounterDate)
            : null;
        $this->multiAccountGuard->upsertRegistrationContext(
            $user,
            $data->deviceFingerprint,
            $data->registrationIp,
            $counterDate,
        );

        return new ApiDataResponse($this->mapper->toAdminUserOutput($user, $this->multiAccountGuard->findContext($user)));
    }
}
