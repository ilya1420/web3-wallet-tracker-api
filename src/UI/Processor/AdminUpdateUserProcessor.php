<?php

declare(strict_types=1);

namespace App\UI\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\DTO\AdminUpdateUserInput;
use App\Application\DTO\UserOutput;
use App\Application\Service\UserOutputMapper;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\Email;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class AdminUpdateUserProcessor implements ProcessorInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private UserOutputMapper $mapper,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): UserOutput
    {
        \assert($data instanceof AdminUpdateUserInput);

        $id = (string) ($uriVariables['id'] ?? '');
        $user = $this->userRepository->findById($id);

        if ($user === null) {
            throw new NotFoundHttpException('User not found.');
        }

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

        $this->userRepository->save($user);

        return $this->mapper->toUserOutput($user);
    }
}
