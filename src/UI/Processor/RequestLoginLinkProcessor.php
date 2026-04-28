<?php

declare(strict_types=1);

namespace App\UI\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\DTO\ApiDataResponse;
use App\Application\DTO\OperationStatusOutput;
use App\Application\DTO\RequestLoginLinkInput;
use App\Application\UseCase\RequestLoginLinkUseCase;
use App\UI\Input\DtoInputResolver;

/**
 * @implements ProcessorInterface<RequestLoginLinkInput, ApiDataResponse>
 */
final readonly class RequestLoginLinkProcessor implements ProcessorInterface
{
    public function __construct(
        private RequestLoginLinkUseCase $useCase,
        private DtoInputResolver $inputResolver,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ApiDataResponse
    {
        /** @var RequestLoginLinkInput $input */
        $input = $this->inputResolver->resolveAndValidate($data, RequestLoginLinkInput::class);

        $this->useCase->execute($input->email);

        return new ApiDataResponse(new OperationStatusOutput('ok'));
    }
}
