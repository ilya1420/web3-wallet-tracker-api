<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\DTO\Web3WalletBalanceOutput;
use App\Application\Exception\CurrencyConversionException;
use App\Application\Service\CurrencyConverterInterface;
use App\Application\Exception\Web3WalletNotFoundException;
use App\Application\Service\Web3ProviderGatewayInterface;
use App\Application\Service\Web3WalletOutputMapper;
use App\Domain\Enum\EvmRpcPreset;
use App\Domain\Entity\User;
use App\Domain\Repository\Web3WalletRepositoryInterface;
use App\Domain\ValueObject\Currency;
use App\Domain\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final readonly class GetWeb3WalletBalanceUseCase
{
    public function __construct(
        private Web3WalletRepositoryInterface $walletRepository,
        private Web3ProviderGatewayInterface $web3Gateway,
        private Web3WalletOutputMapper $mapper,
        private EntityManagerInterface $entityManager,
        private CurrencyConverterInterface $currencyConverter,
        private LoggerInterface $logger,
        private string $defaultQuoteCurrency = 'USD',
    ) {
    }

    public function execute(User $user, string $walletId, ?string $quoteCurrency = null): Web3WalletBalanceOutput
    {
        $wallet = $this->walletRepository->findForUserById($user, $walletId);
        if ($wallet === null) {
            throw new Web3WalletNotFoundException('Wallet not found.');
        }

        $balanceWei = $this->web3Gateway->fetchBalanceWei($wallet->rpcEndpoint(), $wallet->address());

        $this->entityManager->getConnection()->transactional(function () use ($wallet, $balanceWei): void {
            $wallet->updateBalance($balanceWei);
            $this->walletRepository->save($wallet, false);
            $this->entityManager->flush();
        });

        $marketValue = $this->convertMarketValue($wallet, $quoteCurrency);

        return $this->mapper->toBalanceOutput($wallet, $marketValue);
    }

    private function convertMarketValue(\App\Domain\Entity\Web3Wallet $wallet, ?string $quoteCurrency): ?\App\Application\DTO\ConversionResult
    {
        $preset = EvmRpcPreset::fromNetworkId($wallet->networkId());
        if ($preset === null || $wallet->lastKnownBalanceWei() === null || $wallet->lastKnownBalanceWei() === '0') {
            return null;
        }

        try {
            $balance = $this->mapper->toBalanceOutput($wallet)->balanceEth;

            return $this->currencyConverter->convert(
                new Money($balance),
                new Currency($preset->coinMarketCapSymbol()),
                new Currency($quoteCurrency ?? $this->defaultQuoteCurrency),
            );
        } catch (CurrencyConversionException $exception) {
            $this->logger->warning('web3.wallet.market_value_unavailable', [
                'use_case' => self::class,
                'user_id' => $wallet->user()->id()->toRfc4122(),
                'wallet_id' => $wallet->id()->toRfc4122(),
                'network_id' => $wallet->networkId(),
                'reason' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
