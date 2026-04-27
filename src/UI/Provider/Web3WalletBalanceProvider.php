<?php

declare(strict_types=1);

namespace App\UI\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Application\DTO\ApiDataResponse;
use App\Application\Exception\Web3ProviderException;
use App\Application\Exception\Web3WalletNotFoundException;
use App\Application\UseCase\GetWeb3WalletBalanceUseCase;
use App\Domain\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadGatewayHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

final readonly class Web3WalletBalanceProvider implements ProviderInterface
{
    public function __construct(
        private GetWeb3WalletBalanceUseCase $useCase,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ApiDataResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new UnauthorizedHttpException('Bearer', 'Authentication required.');
        }

        $walletId = (string) ($uriVariables['id'] ?? '');

        try {
            return new ApiDataResponse($this->useCase->execute($user, $walletId));
        } catch (Web3WalletNotFoundException $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        } catch (Web3ProviderException $e) {
            throw new BadGatewayHttpException($e->getMessage(), $e);
        }
    }
}
