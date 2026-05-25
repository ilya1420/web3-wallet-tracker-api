<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\DTO\Web3WalletOutput;
use App\Application\Service\Web3WalletOutputMapper;
use App\Domain\Entity\User;
use App\Domain\Repository\Web3WalletRepositoryInterface;

final readonly class ListWeb3WalletsUseCase
{
    public function __construct(
        private Web3WalletRepositoryInterface $walletRepository,
        private Web3WalletOutputMapper $mapper,
    ) {
    }

    /** @return list<Web3WalletOutput> */
    public function execute(User $user): array
    {
        return array_map(
            fn ($wallet) => $this->mapper->toWalletOutput($wallet),
            $this->walletRepository->findAllForUser($user),
        );
    }
}
