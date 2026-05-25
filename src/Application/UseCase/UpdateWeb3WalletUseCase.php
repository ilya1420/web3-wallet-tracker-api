<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\DTO\Web3WalletOutput;
use App\Application\Exception\Web3WalletAlreadyExistsException;
use App\Application\Exception\Web3WalletNotFoundException;
use App\Application\Service\Web3ProviderGatewayInterface;
use App\Application\Service\Web3WalletOutputMapper;
use App\Domain\Entity\User;
use App\Domain\Repository\Web3WalletRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class UpdateWeb3WalletUseCase
{
    public function __construct(
        private Web3WalletRepositoryInterface $walletRepository,
        private Web3ProviderGatewayInterface $web3Gateway,
        private Web3WalletOutputMapper $mapper,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function execute(User $user, string $walletId, string $rpcEndpoint): Web3WalletOutput
    {
        $wallet = $this->walletRepository->findForUserById($user, $walletId);
        if ($wallet === null) {
            throw new Web3WalletNotFoundException('Wallet not found.');
        }

        $networkId = $this->web3Gateway->resolveNetworkId($rpcEndpoint);
        $existing = $this->walletRepository->findOneByUserAddressAndNetwork($user, $wallet->address(), $networkId);
        if ($existing !== null && !$existing->id()->equals($wallet->id())) {
            throw new Web3WalletAlreadyExistsException('Wallet already exists for this network.');
        }

        $this->entityManager->getConnection()->transactional(function () use ($wallet, $rpcEndpoint, $networkId): void {
            $wallet->updateConnection($rpcEndpoint, $networkId);
            $this->walletRepository->save($wallet, false);
            $this->entityManager->flush();
        });

        return $this->mapper->toWalletOutput($wallet);
    }
}
