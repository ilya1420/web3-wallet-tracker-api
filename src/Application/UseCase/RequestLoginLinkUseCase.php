<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Service\LoginRateLimiterService;
use App\Application\Service\TokenManager;
use App\Domain\Entity\LoginToken;
use App\Domain\Repository\LoginTokenRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Infrastructure\Messaging\Message\SendLoginLinkEmailMessage;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class RequestLoginLinkUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private LoginTokenRepositoryInterface $loginTokenRepository,
        private MessageBusInterface $messageBus,
        private TokenManager $tokenManager,
        private LoginRateLimiterService $rateLimiter,
        private string $loginTokenTtl = 'PT15M',
    ) {
    }

    public function execute(string $email): void
    {
        $normalizedEmail = mb_strtolower(trim($email));
        $this->rateLimiter->assertCanAttempt($normalizedEmail);

        $user = $this->userRepository->findByEmail($normalizedEmail);
        if ($user === null) {
            return;
        }

        $rawToken = $this->tokenManager->generateRawToken();
        $token = new LoginToken(
            $normalizedEmail,
            $this->tokenManager->hashToken($rawToken),
            (new \DateTimeImmutable())->add(new \DateInterval($this->loginTokenTtl)),
        );

        $this->loginTokenRepository->save($token);

        $this->messageBus->dispatch(new SendLoginLinkEmailMessage($normalizedEmail, $rawToken));
    }
}
