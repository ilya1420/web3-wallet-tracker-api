<?php

declare(strict_types=1);

namespace App\UI\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\DTO\ApiDataResponse;
use App\Application\UseCase\DeleteWeb3WalletUseCase;
use App\Domain\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProcessorInterface<mixed, ApiDataResponse>
 */
final readonly class DeleteWeb3WalletProcessor implements ProcessorInterface
{
    public function __construct(
        private DeleteWeb3WalletUseCase $useCase,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ApiDataResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('Authentication required.');
        }

        $walletId = (string) ($uriVariables['id'] ?? '');
        $this->useCase->execute($user, $walletId);

        return new ApiDataResponse(['deleted' => true]);
    }
}
