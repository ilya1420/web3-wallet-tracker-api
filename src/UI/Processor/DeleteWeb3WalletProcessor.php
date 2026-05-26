<?php

declare(strict_types=1);

namespace App\UI\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\UseCase\DeleteWeb3WalletUseCase;
use App\Domain\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProcessorInterface<mixed, void>
 */
final readonly class DeleteWeb3WalletProcessor implements ProcessorInterface
{
    public function __construct(
        private DeleteWeb3WalletUseCase $useCase,
        private Security $security,
        private LoggerInterface $logger,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            $this->logger->warning('web3.wallet.delete.unauthorized');
            throw new AccessDeniedException('Authentication required.');
        }

        $walletId = (string) ($uriVariables['id'] ?? '');
        $this->logger->info('web3.wallet.delete.requested', [
            'userId' => $user->id()->toRfc4122(),
            'walletId' => $walletId,
        ]);

        $this->useCase->execute($user, $walletId);

        $this->logger->info('web3.wallet.delete.succeeded', [
            'userId' => $user->id()->toRfc4122(),
            'walletId' => $walletId,
        ]);

        return;
    }
}
