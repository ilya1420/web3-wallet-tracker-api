<?php

declare(strict_types=1);

namespace App\UI\Web\Controller;

use App\Application\DTO\Web3WalletOutput;
use App\Application\UseCase\ListWeb3WalletsUseCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractWebController
{
    public function __construct(
        private readonly ListWeb3WalletsUseCase $listWeb3WalletsUseCase,
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
}
