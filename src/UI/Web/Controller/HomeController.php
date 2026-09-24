<?php

declare(strict_types=1);

namespace App\UI\Web\Controller;

use App\Application\Exception\CurrencyConversionException;
use App\Application\Service\CurrencyConverterInterface;
use App\Application\DTO\Web3WalletOutput;
use App\Application\UseCase\ListWeb3WalletsUseCase;
use App\Domain\Enum\EvmRpcPreset;
use App\Domain\ValueObject\Currency;
use App\Domain\ValueObject\Money;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractWebController
{
    public function __construct(
        private readonly ListWeb3WalletsUseCase $listWeb3WalletsUseCase,
        private readonly CurrencyConverterInterface $currencyConverter,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route(path: '/', name: 'app_root', methods: ['GET'])]
    public function root(): RedirectResponse
    {
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route(path: '/app', name: 'app_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        $user = $this->currentWebUser();
        $wallets = $this->listWeb3WalletsUseCase->execute($user);

        return $this->render('web_app/dashboard.html.twig', [
            'currentUser' => $user,
            'wallets' => $wallets,
            'summary' => $this->buildWalletSummary($wallets),
            'walletUsdValues' => $this->buildWalletUsdValues($wallets, $user->id()->toRfc4122()),
        ]);
    }

    /**
     * @param list<Web3WalletOutput> $wallets
     *
     * @return array{total:int,networks:int,synced:int,withBalance:int}
     */
    private function buildWalletSummary(array $wallets): array
    {
        $networks = [];
        $synced = 0;
        $withBalance = 0;

        foreach ($wallets as $wallet) {
            $networks[$wallet->networkId] = true;
            $synced += $wallet->lastSyncedAt !== null ? 1 : 0;
            $withBalance += $wallet->lastKnownBalanceWei !== null ? 1 : 0;
        }

        return [
            'total' => count($wallets),
            'networks' => count($networks),
            'synced' => $synced,
            'withBalance' => $withBalance,
        ];
    }

    /**
     * @param list<Web3WalletOutput> $wallets
     *
     * @return array<string, array{amount: string, updatedAt: \DateTimeImmutable}>
     */
    private function buildWalletUsdValues(array $wallets, string $userId): array
    {
        $values = [];
        $unavailableSymbols = [];

        foreach ($wallets as $wallet) {
            if ($wallet->lastKnownBalanceEth === null) {
                continue;
            }

            $preset = EvmRpcPreset::fromNetworkId($wallet->networkId);
            if ($preset === null || isset($unavailableSymbols[$preset->coinMarketCapSymbol()])) {
                continue;
            }

            try {
                $conversion = $this->currencyConverter->convert(
                    new Money($wallet->lastKnownBalanceEth),
                    new Currency($preset->coinMarketCapSymbol()),
                    new Currency('USD'),
                );
                $values[$wallet->id] = [
                    'amount' => $this->formatDecimalForDisplay($conversion->convertedAmount),
                    'updatedAt' => $conversion->rateUpdatedAt,
                ];
            } catch (CurrencyConversionException $exception) {
                $unavailableSymbols[$preset->coinMarketCapSymbol()] = true;
                $this->logger->warning('web.wallet_usd_value.unavailable', [
                    'user_id' => $userId,
                    'asset' => $preset->coinMarketCapSymbol(),
                    'reason' => 'provider_unavailable',
                ]);
            }
        }

        return $values;
    }

    private function formatDecimalForDisplay(string $value): string
    {
        [$integer, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $integer = preg_replace('/\B(?=(\d{3})+(?!\d))/', ' ', $integer) ?? $integer;
        $fraction = rtrim(substr($fraction, 0, 8), '0');

        return $fraction === '' ? $integer : sprintf('%s.%s', $integer, $fraction);
    }

}
