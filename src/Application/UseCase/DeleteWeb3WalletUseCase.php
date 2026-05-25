<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Exception\Web3WalletNotFoundException;
use App\Domain\Entity\User;
use App\Domain\Repository\Web3WalletRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DeleteWeb3WalletUseCase
{
    public function __construct(
        private Web3WalletRepositoryInterface $walletRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function execute(User $user, string $walletId): void
    {
        $wallet = $this->walletRepository->findForUserById($user, $walletId);
        if ($wallet === null) {
            throw new Web3WalletNotFoundException('Wallet not found.');
        }

        $this->entityManager->getConnection()->transactional(function () use ($wallet): void {
            $this->walletRepository->remove($wallet, false);
            $this->entityManager->flush();
        });
    }
}
