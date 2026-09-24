<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\SocialAccount;

interface SocialAccountRepositoryInterface
{
    public function save(SocialAccount $socialAccount, bool $flush = true): void;

    public function findOneByProviderSubject(string $provider, string $providerUserId): ?SocialAccount;
}
