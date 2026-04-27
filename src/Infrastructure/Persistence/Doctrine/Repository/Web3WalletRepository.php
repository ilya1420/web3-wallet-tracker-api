<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Entity\User;
use App\Domain\Entity\Web3Wallet;
use App\Domain\Repository\Web3WalletRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Web3Wallet>
 */
final class Web3WalletRepository extends ServiceEntityRepository implements Web3WalletRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Web3Wallet::class);
    }

    public function save(Web3Wallet $wallet, bool $flush = true): void
    {
        $this->getEntityManager()->persist($wallet);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findForUserById(User $user, string $id): ?Web3Wallet
    {
        /** @var Web3Wallet|null $wallet */
        $wallet = $this->findOneBy([
            'id' => $id,
            'user' => $user,
        ]);

        return $wallet;
    }

    public function findOneByUserAddressAndNetwork(User $user, string $address, string $networkId): ?Web3Wallet
    {
        /** @var Web3Wallet|null $wallet */
        $wallet = $this->findOneBy([
            'user' => $user,
            'address' => $address,
            'networkId' => $networkId,
        ]);

        return $wallet;
    }
}
