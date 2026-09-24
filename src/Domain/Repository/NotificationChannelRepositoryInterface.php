<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\NotificationChannel;

interface NotificationChannelRepositoryInterface
{
    public function save(NotificationChannel $channel, bool $flush = true): void;

    public function findOneByTypeAndDestination(string $type, string $destination): ?NotificationChannel;
}
