<?php

declare(strict_types=1);

namespace App\UI\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\DTO\ApiDataResponse;
use App\Application\DTO\AdminCreateUserInput;
use App\Application\Exception\UserAlreadyExistsException;
use App\UI\Input\DtoInputResolver;
use App\Application\Service\UserOutputMapper;
use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * @implements ProcessorInterface<AdminCreateUserInput, ApiDataResponse>
 */
final readonly class AdminCreateUserProcessor implements ProcessorInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private UserOutputMapper $mapper,
        private UserPasswordHasherInterface $passwordHasher,
        private DtoInputResolver $inputResolver,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ApiDataResponse
    {
        /** @var AdminCreateUserInput $input */
        $input = $this->inputResolver->resolveAndValidate($data, AdminCreateUserInput::class);

        $email = new Email($input->email);

        if ($this->userRepository->findByEmail($email->value()) !== null) {
            throw new UserAlreadyExistsException('User with this email already exists.');
        }

        $roles = $input->roles ?? ['ROLE_USER'];
        $user = new User($email, bin2hex(random_bytes(32)), $roles);
        $user->changePassword($this->passwordHasher->hashPassword($user, $input->password));

        if ($input->isVerified) {
            $user->verify();
        }

        $this->userRepository->save($user);

        return new ApiDataResponse($this->mapper->toAdminUserOutput($user, null));
    }
}
