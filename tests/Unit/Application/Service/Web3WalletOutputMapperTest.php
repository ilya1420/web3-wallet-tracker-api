<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Service;

use App\Application\Service\Web3WalletOutputMapper;
use App\Domain\Entity\User;
use App\Domain\Entity\Web3Wallet;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\EthereumAddress;
use PHPUnit\Framework\TestCase;

final class Web3WalletOutputMapperTest extends TestCase
{
    public function testMapsBalanceWithHumanReadableDisplay(): void
    {
        $user = new User(new Email('mapper@example.com'), 'hash');
        $wallet = new Web3Wallet(
            user: $user,
            address: new EthereumAddress('0x742d35cc6634c0532925a3b844bc454e4438f44e'),
            rpcEndpoint: 'https://base-rpc.publicnode.com',
            networkId: '8453',
        );
        $wallet->updateBalance('1234567890123456789');

        $mapper = new Web3WalletOutputMapper();
        $output = $mapper->toWalletOutput($wallet);

        self::assertSame('Base Mainnet', $output->networkName);
        self::assertSame('ETH', $output->nativeSymbol);
        self::assertSame('1.234567890123456789', $output->lastKnownBalanceEth);
        self::assertSame('1.234567', $output->lastKnownBalanceEthFormatted);
        self::assertSame('1.234567 ETH', $output->lastKnownBalanceDisplay);
    }
}
