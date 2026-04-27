<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\DTO\Web3WalletBalanceOutput;
use App\Application\Exception\Web3WalletNotFoundException;
use App\Application\Service\Web3ProviderGatewayInterface;
use App\Application\Service\Web3WalletOutputMapper;
use App\Domain\Entity\User;
use App\Domain\Repository\Web3WalletRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class GetWeb3WalletBalanceUseCase
{
    public function __construct(
        private Web3WalletRepositoryInterface $walletRepository,
        private Web3ProviderGatewayInterface $web3Gateway,
        private Web3WalletOutputMapper $mapper,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function execute(User $user, string $walletId): Web3WalletBalanceOutput
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

        return $this->mapper->toBalanceOutput($wallet);
    }
}
