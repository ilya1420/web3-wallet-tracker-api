<?php

declare(strict_types=1);

namespace App\UI\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Application\DTO\ApiDataResponse;
use App\Application\UseCase\GetWeb3WalletUseCase;
use App\Domain\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProviderInterface<ApiDataResponse>
 */
final readonly class Web3WalletItemProvider implements ProviderInterface
{
    public function __construct(
        private GetWeb3WalletUseCase $useCase,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ApiDataResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('Authentication required.');
        }

        $walletId = (string) ($uriVariables['id'] ?? '');

        return new ApiDataResponse($this->useCase->execute($user, $walletId));
    }
}
