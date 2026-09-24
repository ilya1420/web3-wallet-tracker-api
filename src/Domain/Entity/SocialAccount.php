<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\ValueObject\SocialAuthProfile;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'social_accounts')]
#[ORM\UniqueConstraint(name: 'uniq_social_account_provider_subject', columns: ['provider', 'provider_user_id'])]
#[ORM\Index(name: 'idx_social_account_user', columns: ['user_id'])]
class SocialAccount
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'string', length: 32)]
    private string $provider;

    #[ORM\Column(name: 'provider_user_id', type: 'string', length: 191)]
    private string $providerUserId;

    #[ORM\Column(type: 'string', length: 180, nullable: true)]
    private ?string $email;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $rawProfile;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(User $user, SocialAuthProfile $profile)
    {
        $now = new \DateTimeImmutable();

        $this->id = Uuid::v7();
        $this->user = $user;
        $this->provider = $profile->normalizedProvider();
        $this->providerUserId = trim($profile->providerUserId);
        $this->email = $profile->normalizedEmail();
        $this->rawProfile = $profile->rawProfile;
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function user(): User
    {
        return $this->user;
    }

    public function refresh(SocialAuthProfile $profile): void
    {
        $this->email = $profile->normalizedEmail();
        $this->rawProfile = $profile->rawProfile;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
