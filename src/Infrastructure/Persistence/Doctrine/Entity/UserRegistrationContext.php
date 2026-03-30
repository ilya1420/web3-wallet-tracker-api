<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use App\Domain\Entity\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Infrastructure\Persistence\Doctrine\Repository\UserRegistrationContextRepository::class)]
#[ORM\Table(name: 'user_registration_context')]
#[ORM\UniqueConstraint(name: 'uniq_user_registration_context_fingerprint_hash', columns: ['device_fingerprint_hash'])]
#[ORM\Index(name: 'idx_user_registration_context_ip_date', columns: ['registration_ip_hash', 'registration_ip_counter_date'])]
#[ORM\Index(name: 'idx_user_registration_context_updated_at', columns: ['updated_at'])]
class UserRegistrationContext
{
    #[ORM\Id]
    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $deviceFingerprintHash;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $registrationIpHash;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $registrationIpCounterDate;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(User $user)
    {
        $now = new \DateTimeImmutable();

        $this->user = $user;
        $this->deviceFingerprintHash = null;
        $this->registrationIpHash = null;
        $this->registrationIpCounterDate = null;
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function user(): User
    {
        return $this->user;
    }

    public function deviceFingerprintHash(): ?string
    {
        return $this->deviceFingerprintHash;
    }

    public function registrationIpHash(): ?string
    {
        return $this->registrationIpHash;
    }

    public function registrationIpCounterDate(): ?\DateTimeImmutable
    {
        return $this->registrationIpCounterDate;
    }

    public function update(?string $deviceFingerprintHash, ?string $registrationIpHash, ?\DateTimeImmutable $registrationIpCounterDate): void
    {
        $this->deviceFingerprintHash = $deviceFingerprintHash;
        $this->registrationIpHash = $registrationIpHash;
        $this->registrationIpCounterDate = $registrationIpCounterDate;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
