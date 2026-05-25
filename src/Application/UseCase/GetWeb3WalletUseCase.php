<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\DTO\Web3WalletOutput;
use App\Application\Exception\Web3WalletNotFoundException;
use App\Application\Service\Web3WalletOutputMapper;
use App\Domain\Entity\User;
use App\Domain\Repository\Web3WalletRepositoryInterface;

final readonly class GetWeb3WalletUseCase
{
    public function __construct(
        private Web3WalletRepositoryInterface $walletRepository,
        private Web3WalletOutputMapper $mapper,
    ) {
    }

    public function execute(User $user, string $walletId): Web3WalletOutput
    {
        $wallet = $this->walletRepository->findForUserById($user, $walletId);
        if ($wallet === null) {
            throw new Web3WalletNotFoundException('Wallet not found.');
        }

        return $this->mapper->toWalletOutput($wallet);
    }
}
