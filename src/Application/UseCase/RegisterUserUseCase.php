<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\DTO\UserOutput;
use App\Application\Exception\MultiAccountingDetectedException;
use App\Application\Exception\UserAlreadyExistsException;
use App\Application\Service\MultiAccountGuardService;
use App\Application\Service\TokenManager;
use App\Application\Service\UserOutputMapper;
use App\Domain\Entity\LoginToken;
use App\Domain\Entity\User;
use App\Domain\Repository\LoginTokenRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\Email;
use App\Infrastructure\Messaging\Message\SendLoginLinkEmailMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class RegisterUserUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private LoginTokenRepositoryInterface $loginTokenRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private MultiAccountGuardService $multiAccountGuard,
        private MessageBusInterface $messageBus,
        private TokenManager $tokenManager,
        private UserOutputMapper $mapper,
        private EntityManagerInterface $entityManager,
        private string $loginTokenTtl = 'PT15M',
    ) {
    }

    /**
     * @throws MultiAccountingDetectedException
     * @throws UserAlreadyExistsException
     */
    public function execute(string $email, string $password, string $deviceFingerprint, ?string $ip): UserOutput
    {
        $emailVo = new Email($email);
        $normalizedEmail = $emailVo->value();

        if ($this->userRepository->findByEmail($normalizedEmail) !== null) {
            throw new UserAlreadyExistsException('User with this email already exists.');
        }

        $this->multiAccountGuard->assertCanRegister($normalizedEmail, $deviceFingerprint, $ip);

        /** @var array{user:User,rawToken:string} $registrationResult */
        $registrationResult = $this->entityManager->getConnection()->transactional(function () use ($emailVo, $normalizedEmail, $password): array {
            $user = new User($emailVo, bin2hex(random_bytes(32)), ['ROLE_USER']);
            $user->changePassword($this->passwordHasher->hashPassword($user, $password));
            $rawToken = $this->persistLoginToken($normalizedEmail);

            $this->userRepository->save($user, false);
            $this->entityManager->flush();

            return [
                'user' => $user,
                'rawToken' => $rawToken,
            ];
        });

        $user = $registrationResult['user'];
        $this->multiAccountGuard->markRegistered($normalizedEmail, $deviceFingerprint, $ip);
        $this->messageBus->dispatch(new SendLoginLinkEmailMessage($normalizedEmail, $registrationResult['rawToken']));

        return $this->mapper->toUserOutput($user);
    }

    private function persistLoginToken(string $normalizedEmail): string
    {
        $rawToken = $this->tokenManager->generateRawToken();
        $token = new LoginToken(
            $normalizedEmail,
            $this->tokenManager->hashToken($rawToken),
            (new \DateTimeImmutable())->add(new \DateInterval($this->loginTokenTtl)),
        );

        $this->loginTokenRepository->save($token, false);

        return $rawToken;
    }
}
