<?php

declare(strict_types=1);

namespace App\UI\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\DTO\ApiDataResponse;
use App\Application\DTO\CreateWeb3WalletInput;
use App\Application\Exception\Web3ProviderException;
use App\Application\Exception\Web3WalletAlreadyExistsException;
use App\Application\UseCase\CreateWeb3WalletUseCase;
use App\Domain\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadGatewayHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final readonly class CreateWeb3WalletProcessor implements ProcessorInterface
{
    public function __construct(
        private CreateWeb3WalletUseCase $useCase,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ApiDataResponse
    {
        \assert($data instanceof CreateWeb3WalletInput);

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new UnauthorizedHttpException('Bearer', 'Authentication required.');
        }

        try {
            return new ApiDataResponse($this->useCase->execute($user, $data->address, $data->rpcEndpoint));
        } catch (Web3WalletAlreadyExistsException $e) {
            throw new ConflictHttpException($e->getMessage(), $e);
        } catch (Web3ProviderException $e) {
            throw new BadGatewayHttpException($e->getMessage(), $e);
        } catch (\InvalidArgumentException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }
    }
}
