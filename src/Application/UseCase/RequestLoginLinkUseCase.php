<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Service\LoginTokenIssuer;
use App\Application\Service\LoginRateLimiterService;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\Email;
use App\Infrastructure\Messaging\Message\SendLoginLinkEmailMessage;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class RequestLoginLinkUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private MessageBusInterface $messageBus,
        private LoginTokenIssuer $loginTokenIssuer,
        private LoginRateLimiterService $rateLimiter,
        private string $loginTokenTtl = 'PT15M',
    ) {
    }

    public function execute(string $email): void
    {
        $normalizedEmail = (new Email($email))->value();
        $this->rateLimiter->assertCanAttempt($normalizedEmail);

        $user = $this->userRepository->findByEmail($normalizedEmail);
        if ($user === null) {
            return;
        }

        $rawToken = $this->loginTokenIssuer->issueForEmail($normalizedEmail, $this->loginTokenTtl);
        $this->messageBus->dispatch(new SendLoginLinkEmailMessage($normalizedEmail, $rawToken));
    }
}
