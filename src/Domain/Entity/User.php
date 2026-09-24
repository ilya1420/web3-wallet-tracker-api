<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\ValueObject\Email;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'uniq_user_email', columns: ['email'])]
#[ORM\UniqueConstraint(name: 'uniq_user_phone', columns: ['phone'])]
#[ORM\Index(name: 'idx_user_created_at', columns: ['created_at'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(type: 'string', length: 180, nullable: true)]
    private ?string $email;

    #[ORM\Column(type: 'string', length: 32, nullable: true)]
    private ?string $phone;

    #[ORM\Column(type: 'string', length: 120, nullable: true)]
    private ?string $displayName;

    #[ORM\Column(type: 'string')]
    private string $password;

    #[ORM\Column(type: 'boolean')]
    private bool $isVerified;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $roles;

    public function __construct(?Email $email, string $passwordHash, array $roles = ['ROLE_USER'], ?string $displayName = null)
    {
        $this->id = Uuid::v7();
        $this->email = $email?->value();
        $this->phone = null;
        $this->displayName = $this->normalizeOptionalString($displayName);
        $this->password = $passwordHash;
        $this->isVerified = false;
        $this->lastLoginAt = null;
        $this->createdAt = new \DateTimeImmutable();
        $this->roles = array_values(array_unique(array_merge($roles, ['ROLE_USER'])));
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function email(): ?string
    {
        return $this->email;
    }

    public function changeEmail(Email $email): void
    {
        $this->email = $email->value();
    }

    public function clearEmail(): void
    {
        $this->email = null;
    }

    public function phone(): ?string
    {
        return $this->phone;
    }

    public function changePhone(?string $phone): void
    {
        $this->phone = $this->normalizeOptionalString($phone);
    }

    public function displayName(): ?string
    {
        return $this->displayName;
    }

    public function changeDisplayName(?string $displayName): void
    {
        $this->displayName = $this->normalizeOptionalString($displayName);
    }

    public function verify(): void
    {
        $this->isVerified = true;
    }

    public function markLoggedIn(): void
    {
        $this->lastLoginAt = new \DateTimeImmutable();
    }

    public function changeLastLoginAt(?\DateTimeImmutable $lastLoginAt): void
    {
        $this->lastLoginAt = $lastLoginAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function changeCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function lastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setVerified(bool $isVerified): void
    {
        $this->isVerified = $isVerified;
    }

    public function getUserIdentifier(): string
    {
        return $this->id->toRfc4122();
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    #[\Deprecated('User does not keep transient sensitive state.')]
    public function eraseCredentials(): void
    {
    }

    public function changePassword(string $passwordHash): void
    {
        $this->password = $passwordHash;
    }

    public function changeRoles(array $roles): void
    {
        $this->roles = array_values(array_unique(array_merge($roles, ['ROLE_USER'])));
    }

    private function normalizeOptionalString(?string $value): ?string
    {
        $normalized = $value !== null ? trim($value) : '';

        return $normalized !== '' ? $normalized : null;
    }
}
