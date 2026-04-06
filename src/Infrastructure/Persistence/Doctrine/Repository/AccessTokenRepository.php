<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Entity\AccessToken;
use App\Domain\Repository\AccessTokenRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class AccessTokenRepository extends ServiceEntityRepository implements AccessTokenRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccessToken::class);
    }

    public function save(AccessToken $token, bool $flush = true): void
    {
        $this->getEntityManager()->persist($token);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findActiveByHash(string $tokenHash, \DateTimeImmutable $at): ?AccessToken
    {
        /** @var AccessToken|null $accessToken */
        $accessToken = $this->createQueryBuilder('at')
            ->andWhere('at.tokenHash = :tokenHash')
            ->andWhere('at.expiresAt > :at')
            ->setParameter('tokenHash', $tokenHash)
            ->setParameter('at', $at)
            ->getQuery()
            ->getOneOrNullResult();

        return $accessToken;
    }
}
