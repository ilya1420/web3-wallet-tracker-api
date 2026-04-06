<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Entity\User;
use App\Infrastructure\Persistence\Doctrine\Entity\UserRegistrationContext;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class UserRegistrationContextRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserRegistrationContext::class);
    }

    public function save(UserRegistrationContext $context, bool $flush = true): void
    {
        $this->getEntityManager()->persist($context);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(UserRegistrationContext $context, bool $flush = true): void
    {
        $this->getEntityManager()->remove($context);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByUser(User $user): ?UserRegistrationContext
    {
        return $this->find($user);
    }

    public function existsByDeviceFingerprintHash(string $deviceFingerprintHash): bool
    {
        return $this->count(['deviceFingerprintHash' => $deviceFingerprintHash]) > 0;
    }
}
