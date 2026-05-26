<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Exception\Web3WalletNotFoundException;
use App\Domain\Entity\User;
use App\Domain\Repository\Web3WalletRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final readonly class DeleteWeb3WalletUseCase
{
    public function __construct(
        private Web3WalletRepositoryInterface $walletRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    public function execute(User $user, string $walletId): void
    {
        $wallet = $this->walletRepository->findForUserById($user, $walletId);
        if ($wallet === null) {
            $this->logger->warning('web3.wallet.delete.not_found', [
                'userId' => $user->id()->toRfc4122(),
                'walletId' => $walletId,
            ]);
            throw new Web3WalletNotFoundException('Wallet not found.');
        }

        $this->entityManager->getConnection()->transactional(function () use ($wallet): void {
            $this->walletRepository->remove($wallet, false);
            $this->entityManager->flush();
        });

        $this->logger->info('web3.wallet.delete.persisted', [
            'userId' => $user->id()->toRfc4122(),
            'walletId' => $walletId,
        ]);
    }
}
