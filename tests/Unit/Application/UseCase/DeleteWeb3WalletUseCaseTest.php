<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase;

use App\Application\Exception\Web3WalletNotFoundException;
use App\Application\UseCase\DeleteWeb3WalletUseCase;
use App\Domain\Entity\User;
use App\Domain\Entity\Web3Wallet;
use App\Domain\Repository\Web3WalletRepositoryInterface;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\EthereumAddress;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class DeleteWeb3WalletUseCaseTest extends TestCase
{
    public function testDeletesWalletOwnedByUser(): void
    {
        $user = new User(new Email('owner@example.com'), 'hash');
        $wallet = new Web3Wallet($user, new EthereumAddress('0x742d35cc6634c0532925a3b844bc454e4438f44e'), 'https://base-rpc.publicnode.com', '8453');

        $repo = new SingleWalletRepository($wallet);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(self::once())
            ->method('transactional')
            ->willReturnCallback(static fn (callable $func): mixed => $func());
        $entityManager->expects(self::once())->method('getConnection')->willReturn($connection);
        $entityManager->expects(self::once())->method('flush');

        $useCase = new DeleteWeb3WalletUseCase($repo, $entityManager, $this->createMock(LoggerInterface::class));
        $useCase->execute($user, $wallet->id()->toRfc4122());

        self::assertNull($repo->storedWallet);
    }

    public function testThrowsIfWalletDoesNotExistForUser(): void
    {
        $user = new User(new Email('owner2@example.com'), 'hash');
        $repo = new SingleWalletRepository(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $useCase = new DeleteWeb3WalletUseCase($repo, $entityManager, $this->createMock(LoggerInterface::class));

        $this->expectException(Web3WalletNotFoundException::class);
        $useCase->execute($user, 'missing-id');
    }
}

final class SingleWalletRepository implements Web3WalletRepositoryInterface
{
    public ?Web3Wallet $storedWallet;

    public function __construct(?Web3Wallet $storedWallet)
    {
        $this->storedWallet = $storedWallet;
    }

    public function save(Web3Wallet $wallet, bool $flush = true): void
    {
        $this->storedWallet = $wallet;
    }

    public function remove(Web3Wallet $wallet, bool $flush = true): void
    {
        if ($this->storedWallet !== null && $this->storedWallet->id()->equals($wallet->id())) {
            $this->storedWallet = null;
        }
    }

    public function findForUserById(User $user, string $id): ?Web3Wallet
    {
        if ($this->storedWallet === null) {
            return null;
        }

        if (!$this->storedWallet->user()->id()->equals($user->id())) {
            return null;
        }

        return $this->storedWallet->id()->toRfc4122() === $id ? $this->storedWallet : null;
    }

    public function findAllForUser(User $user): array
    {
        if ($this->storedWallet === null) {
            return [];
        }

        return $this->storedWallet->user()->id()->equals($user->id()) ? [$this->storedWallet] : [];
    }

    public function findOneByUserAddressAndNetwork(User $user, string $address, string $networkId): ?Web3Wallet
    {
        return null;
    }
}
