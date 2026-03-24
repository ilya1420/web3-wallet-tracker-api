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

    public function save(LoginToken $token): void
    {
        $this->getEntityManager()->persist($token);
        $this->getEntityManager()->flush();
    }

    public function findValidByEmailAndHash(string $email, string $tokenHash): ?LoginToken
    {
        $candidate = $this->findOneBy([
            'email' => mb_strtolower(trim($email)),
            'tokenHash' => $tokenHash,
        ]);

        if ($candidate === null || !$candidate->isValidAt(new \DateTimeImmutable())) {
            return null;
        }

        return $candidate;
    }
}
