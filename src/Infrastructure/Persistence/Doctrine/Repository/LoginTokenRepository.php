<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Entity\LoginToken;
use App\Domain\Repository\LoginTokenRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class LoginTokenRepository extends ServiceEntityRepository implements LoginTokenRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoginToken::class);
    }

    public function save(LoginToken $token, bool $flush = true): void
    {
        $this->getEntityManager()->persist($token);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function consumeValidToken(string $email, string $tokenHash, \DateTimeImmutable $usedAt): bool
    {
        $affectedRows = $this->createQueryBuilder('lt')
            ->update()
            ->set('lt.usedAt', ':usedAt')
            ->where('lt.email = :email')
            ->andWhere('lt.tokenHash = :tokenHash')
            ->andWhere('lt.usedAt IS NULL')
            ->andWhere('lt.expiresAt > :now')
            ->setParameter('usedAt', $usedAt)
            ->setParameter('now', $usedAt)
            ->setParameter('email', mb_strtolower(trim($email)))
            ->setParameter('tokenHash', $tokenHash)
            ->getQuery()
            ->execute();

        return $affectedRows === 1;
    }
}
