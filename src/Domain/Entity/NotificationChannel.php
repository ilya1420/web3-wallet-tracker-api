<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'notification_channels')]
#[ORM\UniqueConstraint(name: 'uniq_notification_channel_type_destination', columns: ['type', 'destination'])]
#[ORM\Index(name: 'idx_notification_channel_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_notification_channel_user_verified', columns: ['user_id', 'verified'])]
class NotificationChannel
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'string', length: 32)]
    private string $type;

    #[ORM\Column(type: 'string', length: 191)]
    private string $destination;

    #[ORM\Column(type: 'boolean')]
    private bool $verified;

    #[ORM\Column(name: 'is_primary', type: 'boolean')]
    private bool $primary;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(User $user, string $type, string $destination, bool $verified = false, bool $primary = false)
    {
        $now = new \DateTimeImmutable();

        $this->id = Uuid::v7();
        $this->user = $user;
        $this->type = mb_strtolower(trim($type));
        $this->destination = trim($destination);
        $this->verified = $verified;
        $this->primary = $primary;
        $this->createdAt = $now;
        $this->updatedAt = $now;

        if ($this->type === '' || $this->destination === '') {
            throw new \InvalidArgumentException('Notification channel type and destination are required.');
        }
    }

    public function verify(bool $primary = false): void
    {
        $this->verified = true;
        $this->primary = $this->primary || $primary;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
