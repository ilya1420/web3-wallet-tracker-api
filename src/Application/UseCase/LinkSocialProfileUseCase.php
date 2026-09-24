<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Domain\Entity\User;
use App\Domain\Service\SocialUserResolver;
use App\Domain\ValueObject\SocialAuthProfile;
use Doctrine\ORM\EntityManagerInterface;

final readonly class LinkSocialProfileUseCase
{
    public function __construct(
        private SocialUserResolver $socialUserResolver,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function execute(User $user, SocialAuthProfile $profile): void
    {
        $this->entityManager->getConnection()->transactional(function () use ($user, $profile): void {
            $this->socialUserResolver->link($user, $profile);
            $this->entityManager->flush();
        });
    }
}
