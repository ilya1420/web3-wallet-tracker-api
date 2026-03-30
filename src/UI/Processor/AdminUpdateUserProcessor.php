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
use App\Domain\ValueObject\Email;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class AdminUpdateUserProcessor implements ProcessorInterface
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
        \assert($data instanceof AdminUpdateUserInput);

        $id = (string) ($uriVariables['id'] ?? '');
        $user = $this->userRepository->findById($id);

        if ($user === null) {
            throw new NotFoundHttpException('User not found.');
        }

        $context = $this->multiAccountGuard->findContext($user);

        if ($data->email !== null) {
            $newEmail = new Email($data->email);
            $existing = $this->userRepository->findByEmail($newEmail->value());
            if ($existing !== null && !$existing->id()->equals($user->id())) {
                throw new ConflictHttpException('User with this email already exists.');
            }

            $user->changeEmail($newEmail);
        }

        if ($data->password !== null) {
            $user->changePassword($this->passwordHasher->hashPassword($user, $data->password));
        }

        if ($data->roles !== null) {
            $user->changeRoles($data->roles);
        }

        if ($data->isVerified !== null) {
            $user->setVerified($data->isVerified);
        }

        if ($data->lastLoginAt !== null) {
            $user->changeLastLoginAt(new \DateTimeImmutable($data->lastLoginAt));
        }

        if ($data->createdAt !== null) {
            $user->changeCreatedAt(new \DateTimeImmutable($data->createdAt));
        }

        $this->entityManager->getConnection()->transactional(function () use ($user): void {
            $this->userRepository->save($user, false);
            $this->entityManager->flush();
        });

        if ($data->deviceFingerprint !== null || $data->registrationIp !== null || $data->registrationIpCounterDate !== null) {
            $counterDate = $data->registrationIpCounterDate !== null
                ? new \DateTimeImmutable($data->registrationIpCounterDate)
                : $context?->registrationIpCounterDate();

            $deviceFingerprintHash = $data->deviceFingerprint !== null
                ? $this->multiAccountGuard->hashDeviceFingerprint($data->deviceFingerprint)
                : $context?->deviceFingerprintHash();
            $registrationIpHash = $data->registrationIp !== null
                ? $this->multiAccountGuard->hashRegistrationIp($data->registrationIp)
                : $context?->registrationIpHash();

            $this->multiAccountGuard->upsertRegistrationContextHashes(
                $user,
                $deviceFingerprintHash,
                $registrationIpHash,
                $counterDate,
            );
            $context = $this->multiAccountGuard->findContext($user);
        }

        return new ApiDataResponse($this->mapper->toAdminUserOutput($user, $context));
    }
}
