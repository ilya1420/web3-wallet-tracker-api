<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\DTO\Web3WalletOutput;
use App\Application\Exception\Web3WalletAlreadyExistsException;
use App\Application\Service\Web3ProviderGatewayInterface;
use App\Application\Service\Web3WalletOutputMapper;
use App\Domain\Entity\User;
use App\Domain\Entity\Web3Wallet;
use App\Domain\Repository\Web3WalletRepositoryInterface;
use App\Domain\ValueObject\EthereumAddress;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CreateWeb3WalletUseCase
{
    public function __construct(
        private Web3WalletRepositoryInterface $walletRepository,
        private Web3ProviderGatewayInterface $web3Gateway,
        private Web3WalletOutputMapper $mapper,
        private EntityManagerInterface $entityManager,
        private string $defaultRpcEndpoint = 'http://localhost:8545',
    ) {
    }

    public function execute(User $user, string $address, ?string $rpcEndpoint = null): Web3WalletOutput
    {
        $addressValueObject = new EthereumAddress($address);
        $walletAddress = $addressValueObject->value();
        $endpoint = $this->resolveRpcEndpoint($rpcEndpoint);
        $networkId = $this->web3Gateway->resolveNetworkId($endpoint);

        if ($this->walletRepository->findOneByUserAddressAndNetwork($user, $walletAddress, $networkId) !== null) {
            throw new Web3WalletAlreadyExistsException('Wallet already exists for this network.');
        }

        $wallet = new Web3Wallet($user, $addressValueObject, $endpoint, $networkId);

        $this->entityManager->getConnection()->transactional(function () use ($wallet): void {
            $this->walletRepository->save($wallet, false);
            $this->entityManager->flush();
        });

        return $this->mapper->toWalletOutput($wallet);
    }

    private function resolveRpcEndpoint(?string $rpcEndpoint): string
    {
        $endpoint = trim((string) $rpcEndpoint);
        if ($endpoint !== '') {
            if (filter_var($endpoint, FILTER_VALIDATE_URL) === false) {
                throw new \InvalidArgumentException('RPC endpoint must be a valid URL.');
            }

            return $endpoint;
        }

        $defaultEndpoint = trim($this->defaultRpcEndpoint);
        if ($defaultEndpoint === '') {
            throw new \InvalidArgumentException('RPC endpoint is required.');
        }

        if (filter_var($defaultEndpoint, FILTER_VALIDATE_URL) === false) {
            throw new \RuntimeException('Default web3 RPC endpoint is invalid.');
        }

        return $defaultEndpoint;
    }
}
