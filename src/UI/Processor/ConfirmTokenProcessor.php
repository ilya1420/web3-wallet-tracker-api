<?php

declare(strict_types=1);

namespace App\UI\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\DTO\ApiDataResponse;
use App\Application\DTO\ConfirmTokenInput;
use App\Application\UseCase\ConfirmLoginTokenUseCase;
use App\UI\Input\DtoInputResolver;

/**
 * @implements ProcessorInterface<ConfirmTokenInput, ApiDataResponse>
 */
final readonly class ConfirmTokenProcessor implements ProcessorInterface
{
    public function __construct(
        private ConfirmLoginTokenUseCase $useCase,
        private DtoInputResolver $inputResolver,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ApiDataResponse
    {
        /** @var ConfirmTokenInput $input */
        $input = $this->inputResolver->resolveAndValidate($data, ConfirmTokenInput::class);

        return new ApiDataResponse($this->useCase->execute($input->email, $input->token));
    }
}
