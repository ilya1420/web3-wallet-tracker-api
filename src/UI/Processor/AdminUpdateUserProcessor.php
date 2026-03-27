<?php

declare(strict_types=1);

namespace App\UI\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\DTO\AdminUpdateUserInput;
use App\Application\DTO\UserOutput;
use App\Application\Service\UserOutputMapper;
use App\Domain\Repository\UserRepositoryInterface;
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
            $existing = $this->userRepository->findByEmail($data->email);
            if ($existing !== null && $existing->id()->toRfc4122() !== $user->id()->toRfc4122()) {
                throw new ConflictHttpException('User with this email already exists.');
            }

            $user->setEmail($data->email);
        }

        if ($data->password !== null) {
            $user->changePassword($this->passwordHasher->hashPassword($user, $data->password));
        }

        if ($data->roles !== null) {
            $user->setRoles($data->roles);
        }

        if ($data->isVerified !== null) {
            $user->setIsVerified($data->isVerified);
        }

        $this->userRepository->save($user);

        return $this->mapper->toUserOutput($user);
    }
}
