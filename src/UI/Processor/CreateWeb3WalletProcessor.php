<?php

declare(strict_types=1);

namespace App\UI\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\DTO\ApiDataResponse;
use App\Application\DTO\CreateWeb3WalletInput;
use App\Application\UseCase\CreateWeb3WalletUseCase;
use App\Domain\Entity\User;
use App\UI\Input\DtoInputResolver;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProcessorInterface<CreateWeb3WalletInput, ApiDataResponse>
 */
final readonly class CreateWeb3WalletProcessor implements ProcessorInterface
{
    public function __construct(
        private CreateWeb3WalletUseCase $useCase,
        private Security $security,
        private DtoInputResolver $inputResolver,
        private LoggerInterface $logger,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ApiDataResponse
    {
        /** @var CreateWeb3WalletInput $input */
        $input = $this->inputResolver->resolveAndValidate($data, CreateWeb3WalletInput::class);

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            $this->logger->warning('web3.wallet.create.unauthorized');
            throw new AccessDeniedException('Authentication required.');
        }

        $this->logger->info('web3.wallet.create.requested', [
            'userId' => $user->id()->toRfc4122(),
            'rpcPreset' => $input->rpcPreset,
            'hasCustomRpcEndpoint' => trim((string) $input->rpcEndpoint) !== '',
        ]);

        $output = $this->useCase->execute($user, $input->address, $input->rpcEndpoint, $input->rpcPreset);

        $this->logger->info('web3.wallet.create.succeeded', [
            'userId' => $user->id()->toRfc4122(),
            'walletId' => $output->id,
            'networkId' => $output->networkId,
            'rpcEndpoint' => $output->rpcEndpoint,
        ]);

        return new ApiDataResponse($output);
    }
}
