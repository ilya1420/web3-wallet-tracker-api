<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Entity\User;
use App\Domain\Entity\Web3Wallet;
use App\Domain\Repository\Web3WalletRepositoryInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
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

    public function remove(Web3Wallet $wallet, bool $flush = true): void
    {
        $this->getEntityManager()->remove($wallet);

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

    public function findAllForUser(User $user): array
    {
        /** @var list<Web3Wallet> $wallets */
        $wallets = $this->createQueryBuilder('wallet')
            ->innerJoin('wallet.user', 'owner')
            ->andWhere('owner.id = :ownerId')
            ->setParameter('ownerId', $user->id(), UuidType::NAME)
            ->orderBy('wallet.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $wallets;
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
