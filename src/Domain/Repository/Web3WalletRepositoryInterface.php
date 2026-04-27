<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\User;
use App\Domain\Entity\Web3Wallet;

interface Web3WalletRepositoryInterface
{
    public function save(Web3Wallet $wallet, bool $flush = true): void;

    public function findForUserById(User $user, string $id): ?Web3Wallet;

    public function findOneByUserAddressAndNetwork(User $user, string $address, string $networkId): ?Web3Wallet;
}
