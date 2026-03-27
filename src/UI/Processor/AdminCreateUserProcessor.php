<?php

declare(strict_types=1);

namespace App\UI\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\DTO\AdminCreateUserInput;
use App\Application\DTO\UserOutput;
use App\Application\Service\UserOutputMapper;
use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\Email;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class AdminCreateUserProcessor implements ProcessorInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private UserOutputMapper $mapper,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): UserOutput
    {
        \assert($data instanceof AdminCreateUserInput);

        if ($this->userRepository->findByEmail($data->email) !== null) {
            throw new ConflictHttpException('User with this email already exists.');
        }

        $roles = $data->roles ?? ['ROLE_USER'];
        $user = new User(new Email($data->email), 'placeholder_hash', $roles);
        $user->changePassword($this->passwordHasher->hashPassword($user, $data->password));

        if ($data->isVerified) {
            $user->verify();
        }

        $this->userRepository->save($user);

        return $this->mapper->toUserOutput($user);
    }
}
