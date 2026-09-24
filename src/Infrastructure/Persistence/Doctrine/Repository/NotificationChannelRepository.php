<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Entity\NotificationChannel;
use App\Domain\Repository\NotificationChannelRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationChannel>
 */
final class NotificationChannelRepository extends ServiceEntityRepository implements NotificationChannelRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationChannel::class);
    }

    public function save(NotificationChannel $channel, bool $flush = true): void
    {
        $this->getEntityManager()->persist($channel);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByTypeAndDestination(string $type, string $destination): ?NotificationChannel
    {
        $channel = $this->findOneBy([
            'type' => mb_strtolower(trim($type)),
            'destination' => trim($destination),
        ]);

        return $channel instanceof NotificationChannel ? $channel : null;
    }
}
