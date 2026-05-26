<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase;

use App\Application\Service\Web3ProviderGatewayInterface;
use App\Application\Service\Web3WalletOutputMapper;
use App\Application\UseCase\CreateWeb3WalletUseCase;
use App\Domain\Entity\User;
use App\Domain\Entity\Web3Wallet;
use App\Domain\Repository\Web3WalletRepositoryInterface;
use App\Domain\ValueObject\Email;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class CreateWeb3WalletUseCaseTest extends TestCase
{
    public function testUsesRpcPresetWithoutNetworkResolutionCall(): void
    {
        $repo = new InMemoryWeb3WalletRepository();

        $gateway = $this->createMock(Web3ProviderGatewayInterface::class);
        $gateway->expects(self::never())->method('resolveNetworkId');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(self::once())
            ->method('transactional')
            ->willReturnCallback(static fn (callable $func): mixed => $func());
        $entityManager->expects(self::once())->method('getConnection')->willReturn($connection);
        $entityManager->expects(self::once())->method('flush');

        $useCase = new CreateWeb3WalletUseCase(
            walletRepository: $repo,
            web3Gateway: $gateway,
            mapper: new Web3WalletOutputMapper(),
            entityManager: $entityManager,
            logger: $this->createMock(LoggerInterface::class),
            defaultRpcEndpoint: 'https://ethereum-rpc.publicnode.com',
        );

        $user = new User(new Email('preset@example.com'), 'hash');

        $output = $useCase->execute(
            user: $user,
            address: '0x742d35cc6634c0532925a3b844bc454e4438f44e',
            rpcEndpoint: null,
            rpcPreset: 'base',
        );

        self::assertSame('https://base-rpc.publicnode.com', $output->rpcEndpoint);
        self::assertSame('8453', $output->networkId);
        self::assertSame('Base Mainnet', $output->networkName);
    }
}

final class InMemoryWeb3WalletRepository implements Web3WalletRepositoryInterface
{
    /** @var list<Web3Wallet> */
    private array $wallets = [];

    public function save(Web3Wallet $wallet, bool $flush = true): void
    {
        $this->wallets[] = $wallet;
    }

    public function remove(Web3Wallet $wallet, bool $flush = true): void
    {
        foreach ($this->wallets as $index => $savedWallet) {
            if ($savedWallet->id()->equals($wallet->id())) {
                unset($this->wallets[$index]);
            }
        }

        $this->wallets = array_values($this->wallets);
    }

    public function findForUserById(User $user, string $id): ?Web3Wallet
    {
        foreach ($this->wallets as $wallet) {
            if ($wallet->user()->id()->equals($user->id()) && $wallet->id()->toRfc4122() === $id) {
                return $wallet;
            }
        }

        return null;
    }

    public function findAllForUser(User $user): array
    {
        return array_values(array_filter(
            $this->wallets,
            static fn (Web3Wallet $wallet): bool => $wallet->user()->id()->equals($user->id()),
        ));
    }

    public function findOneByUserAddressAndNetwork(User $user, string $address, string $networkId): ?Web3Wallet
    {
        foreach ($this->wallets as $wallet) {
            if (
                $wallet->user()->id()->equals($user->id())
                && $wallet->address() === $address
                && $wallet->networkId() === $networkId
            ) {
                return $wallet;
            }
        }

        return null;
    }
}
