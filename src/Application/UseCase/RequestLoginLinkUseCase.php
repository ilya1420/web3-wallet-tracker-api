<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Service\LoginTokenIssuer;
use App\Application\Service\LoginRateLimiterService;
use App\Application\Service\OutboxMessageRecorder;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\Email;
use App\Infrastructure\Messaging\Message\SendLoginLinkEmailMessage;
use Doctrine\ORM\EntityManagerInterface;

final readonly class RequestLoginLinkUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private LoginTokenIssuer $loginTokenIssuer,
        private OutboxMessageRecorder $outboxMessageRecorder,
        private LoginRateLimiterService $rateLimiter,
        private EntityManagerInterface $entityManager,
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

        $this->entityManager->getConnection()->transactional(function () use ($normalizedEmail): void {
            $rawToken = $this->loginTokenIssuer->issueForEmail($normalizedEmail, $this->loginTokenTtl, false);
            $this->outboxMessageRecorder->record(new SendLoginLinkEmailMessage($normalizedEmail, $rawToken));
            $this->entityManager->flush();
        });
    }
}
