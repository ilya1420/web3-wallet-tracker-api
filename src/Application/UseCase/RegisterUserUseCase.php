<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\DTO\UserOutput;
use App\Application\Exception\MultiAccountingDetectedException;
use App\Application\Exception\UserAlreadyExistsException;
use App\Application\Service\LoginTokenIssuer;
use App\Application\Service\MultiAccountGuardService;
use App\Application\Service\OutboxMessageRecorder;
use App\Application\Service\UserOutputMapper;
use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\Email;
use App\Infrastructure\Messaging\Message\SendLoginLinkEmailMessage;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class RegisterUserUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private MultiAccountGuardService $multiAccountGuard,
        private LoginTokenIssuer $loginTokenIssuer,
        private OutboxMessageRecorder $outboxMessageRecorder,
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
        $counterDate = new \DateTimeImmutable('today');

        if ($this->userRepository->findByEmail($normalizedEmail) !== null) {
            throw new UserAlreadyExistsException('User with this email already exists.');
        }

        $this->multiAccountGuard->assertCanRegister($normalizedEmail, $deviceFingerprint, $ip);

        try {
            /** @var array{user:User,rawToken:string} $registrationResult */
            $registrationResult = $this->entityManager->getConnection()->transactional(function () use ($emailVo, $normalizedEmail, $password, $deviceFingerprint, $ip, $counterDate): array {
                $user = new User($emailVo, bin2hex(random_bytes(32)), ['ROLE_USER']);
                $user->changePassword($this->passwordHasher->hashPassword($user, $password));
                $rawToken = $this->loginTokenIssuer->issueForEmail($normalizedEmail, $this->loginTokenTtl, false);

                $this->userRepository->save($user, false);
                $this->multiAccountGuard->upsertRegistrationContext($user, $deviceFingerprint, $ip, $counterDate, false);
                $this->outboxMessageRecorder->record(new SendLoginLinkEmailMessage($normalizedEmail, $rawToken));
                $this->entityManager->flush();

                return [
                    'user' => $user,
                    'rawToken' => $rawToken,
                ];
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw $this->mapRegistrationConstraintViolation($exception);
        }

        $user = $registrationResult['user'];

        return $this->mapper->toUserOutput($user);
    }

    private function mapRegistrationConstraintViolation(UniqueConstraintViolationException $exception): \RuntimeException
    {
        $message = mb_strtolower($exception->getMessage());

        if (str_contains($message, 'uniq_user_email')) {
            return new UserAlreadyExistsException('User with this email already exists.', 0, $exception);
        }

        if (str_contains($message, 'uniq_user_registration_context_fingerprint_hash')) {
            return new MultiAccountingDetectedException('Multi-accounting is not allowed for this device.', 0, $exception);
        }

        return new MultiAccountingDetectedException('Registration constraints were violated.', 0, $exception);
    }
}
