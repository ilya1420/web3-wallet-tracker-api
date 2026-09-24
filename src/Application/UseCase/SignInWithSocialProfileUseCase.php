<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\DTO\AuthTokenOutput;
use App\Application\Service\AccessTokenIssuer;
use App\Domain\Service\SocialUserResolver;
use App\Domain\ValueObject\SocialAuthProfile;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final readonly class SignInWithSocialProfileUseCase
{
    public function __construct(
        private SocialUserResolver $socialUserResolver,
        private AccessTokenIssuer $accessTokenIssuer,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private string $accessTokenTtl = 'PT24H',
    ) {
    }

    public function execute(SocialAuthProfile $profile): AuthTokenOutput
    {
        /** @var AuthTokenOutput $authToken */
        $authToken = $this->entityManager->getConnection()->transactional(function () use ($profile): AuthTokenOutput {
            $now = new \DateTimeImmutable();
            $user = $this->socialUserResolver->resolve($profile);
            $user->verify();
            $user->markLoggedIn();

            $authToken = $this->accessTokenIssuer->issueForUser($user, $this->accessTokenTtl, false, $now);
            $this->entityManager->flush();

            $this->logger->info('Social sign-in succeeded.', [
                'use_case' => self::class,
                'provider' => $profile->normalizedProvider(),
                'provider_user_hash' => hash('sha256', $profile->providerUserId),
                'user_id' => $user->id()->toRfc4122(),
                'email_hash' => $profile->normalizedEmail() !== null ? hash('sha256', $profile->normalizedEmail()) : null,
            ]);

            return $authToken;
        });

        return $authToken;
    }
}
