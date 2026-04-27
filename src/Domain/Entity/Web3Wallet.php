<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\ValueObject\EthereumAddress;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'web3_wallets')]
#[ORM\UniqueConstraint(name: 'uniq_web3_wallet_user_address_network', columns: ['user_id', 'address', 'network_id'])]
#[ORM\Index(name: 'idx_web3_wallet_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_web3_wallet_updated_at', columns: ['updated_at'])]
class Web3Wallet
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'string', length: 42)]
    private string $address;

    #[ORM\Column(type: 'string', length: 255)]
    private string $rpcEndpoint;

    #[ORM\Column(type: 'string', length: 32)]
    private string $networkId;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $lastKnownBalanceWei;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastSyncedAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(User $user, EthereumAddress $address, string $rpcEndpoint, string $networkId)
    {
        $normalizedRpcEndpoint = trim($rpcEndpoint);
        $normalizedNetworkId = trim($networkId);

        if ($normalizedRpcEndpoint === '') {
            throw new \InvalidArgumentException('RPC endpoint cannot be empty.');
        }

        if ($normalizedNetworkId === '') {
            throw new \InvalidArgumentException('Network id cannot be empty.');
        }

        $this->id = Uuid::v7();
        $this->user = $user;
        $this->address = $address->value();
        $this->rpcEndpoint = $normalizedRpcEndpoint;
        $this->networkId = $normalizedNetworkId;
        $this->lastKnownBalanceWei = null;
        $this->lastSyncedAt = null;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function user(): User
    {
        return $this->user;
    }

    public function address(): string
    {
        return $this->address;
    }

    public function rpcEndpoint(): string
    {
        return $this->rpcEndpoint;
    }

    public function networkId(): string
    {
        return $this->networkId;
    }

    public function lastKnownBalanceWei(): ?string
    {
        return $this->lastKnownBalanceWei;
    }

    public function lastSyncedAt(): ?\DateTimeImmutable
    {
        return $this->lastSyncedAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function updateBalance(string $balanceWei): void
    {
        if (preg_match('/^\d+$/', $balanceWei) !== 1) {
            throw new \InvalidArgumentException('Balance must be an unsigned integer in wei.');
        }

        $now = new \DateTimeImmutable();
        $this->lastKnownBalanceWei = $balanceWei;
        $this->lastSyncedAt = $now;
        $this->updatedAt = $now;
    }
}
