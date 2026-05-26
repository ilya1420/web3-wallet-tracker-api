<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\DTO\Web3WalletOutput;
use App\Application\Exception\Web3WalletAlreadyExistsException;
use App\Application\Service\Web3ProviderGatewayInterface;
use App\Application\Service\Web3WalletOutputMapper;
use App\Domain\Enum\EvmRpcPreset;
use App\Domain\Entity\User;
use App\Domain\Entity\Web3Wallet;
use App\Domain\Repository\Web3WalletRepositoryInterface;
use App\Domain\ValueObject\EthereumAddress;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final readonly class CreateWeb3WalletUseCase
{
    public function __construct(
        private Web3WalletRepositoryInterface $walletRepository,
        private Web3ProviderGatewayInterface $web3Gateway,
        private Web3WalletOutputMapper $mapper,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private string $defaultRpcEndpoint = 'http://localhost:8545',
    ) {
    }

    public function execute(User $user, string $address, ?string $rpcEndpoint = null, ?string $rpcPreset = null): Web3WalletOutput
    {
        $addressValueObject = new EthereumAddress($address);
        $walletAddress = $addressValueObject->value();
        [$endpoint, $networkId] = $this->resolveRpcConnection($rpcEndpoint, $rpcPreset);
        $this->logger->info('web3.wallet.create.rpc_resolved', [
            'userId' => $user->id()->toRfc4122(),
            'walletAddress' => $walletAddress,
            'rpcPreset' => $rpcPreset,
            'rpcEndpoint' => $endpoint,
            'networkId' => $networkId,
        ]);

        if ($this->walletRepository->findOneByUserAddressAndNetwork($user, $walletAddress, $networkId) !== null) {
            $this->logger->warning('web3.wallet.create.duplicate', [
                'userId' => $user->id()->toRfc4122(),
                'walletAddress' => $walletAddress,
                'networkId' => $networkId,
            ]);
            throw new Web3WalletAlreadyExistsException('Wallet already exists for this network.');
        }

        $wallet = new Web3Wallet($user, $addressValueObject, $endpoint, $networkId);

        $this->entityManager->getConnection()->transactional(function () use ($wallet): void {
            $this->walletRepository->save($wallet, false);
            $this->entityManager->flush();
        });

        return $this->mapper->toWalletOutput($wallet);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveRpcConnection(?string $rpcEndpoint, ?string $rpcPreset): array
    {
        $endpoint = trim((string) $rpcEndpoint);
        $presetRaw = trim((string) $rpcPreset);
        $preset = $presetRaw !== '' ? EvmRpcPreset::from($presetRaw) : null;

        if ($endpoint !== '') {
            if (filter_var($endpoint, FILTER_VALIDATE_URL) === false) {
                throw new \InvalidArgumentException('RPC endpoint must be a valid URL.');
            }

            $networkId = $this->web3Gateway->resolveNetworkId($endpoint);
            if ($preset !== null && $preset->networkId() !== $networkId) {
                throw new \InvalidArgumentException(sprintf(
                    'RPC endpoint network mismatch: preset "%s" expects networkId "%s", got "%s".',
                    $preset->value,
                    $preset->networkId(),
                    $networkId,
                ));
            }

            return [$endpoint, $networkId];
        }

        if ($preset !== null) {
            return [$preset->endpoint(), $preset->networkId()];
        }

        $defaultEndpoint = trim($this->defaultRpcEndpoint);
        if ($defaultEndpoint === '') {
            throw new \InvalidArgumentException('Either rpcEndpoint or rpcPreset is required.');
        }

        if (filter_var($defaultEndpoint, FILTER_VALIDATE_URL) === false) {
            throw new \RuntimeException('Default web3 RPC endpoint is invalid.');
        }

        return [$defaultEndpoint, $this->web3Gateway->resolveNetworkId($defaultEndpoint)];
    }
}
