<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Entity\SocialAccount;
use App\Domain\Repository\SocialAccountRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SocialAccount>
 */
final class SocialAccountRepository extends ServiceEntityRepository implements SocialAccountRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SocialAccount::class);
    }

    public function save(SocialAccount $socialAccount, bool $flush = true): void
    {
        $this->getEntityManager()->persist($socialAccount);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByProviderSubject(string $provider, string $providerUserId): ?SocialAccount
    {
        $socialAccount = $this->findOneBy([
            'provider' => mb_strtolower(trim($provider)),
            'providerUserId' => trim($providerUserId),
        ]);

        return $socialAccount instanceof SocialAccount ? $socialAccount : null;
    }
}
