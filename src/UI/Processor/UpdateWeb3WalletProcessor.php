<?php

declare(strict_types=1);

namespace App\UI\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\DTO\ApiDataResponse;
use App\Application\DTO\UpdateWeb3WalletInput;
use App\Application\UseCase\UpdateWeb3WalletUseCase;
use App\Domain\Entity\User;
use App\UI\Input\DtoInputResolver;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProcessorInterface<UpdateWeb3WalletInput, ApiDataResponse>
 */
final readonly class UpdateWeb3WalletProcessor implements ProcessorInterface
{
    public function __construct(
        private UpdateWeb3WalletUseCase $useCase,
        private Security $security,
        private DtoInputResolver $inputResolver,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ApiDataResponse
    {
        /** @var UpdateWeb3WalletInput $input */
        $input = $this->inputResolver->resolveAndValidate($data, UpdateWeb3WalletInput::class);

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('Authentication required.');
        }

        $rpcEndpoint = trim((string) $input->rpcEndpoint);
        if ($rpcEndpoint === '') {
            throw new UnprocessableEntityHttpException('rpcEndpoint is required.');
        }

        $walletId = (string) ($uriVariables['id'] ?? '');

        return new ApiDataResponse($this->useCase->execute($user, $walletId, $rpcEndpoint));
    }
}
