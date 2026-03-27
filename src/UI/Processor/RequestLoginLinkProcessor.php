<?php

declare(strict_types=1);

namespace App\UI\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\DTO\ApiDataResponse;
use App\Application\DTO\OperationStatusOutput;
use App\Application\DTO\RequestLoginLinkInput;
use App\Application\Exception\RateLimitExceededException;
use App\Application\UseCase\RequestLoginLinkUseCase;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

final readonly class RequestLoginLinkProcessor implements ProcessorInterface
{
    public function __construct(private RequestLoginLinkUseCase $useCase)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ApiDataResponse
    {
        \assert($data instanceof RequestLoginLinkInput);

        try {
            $this->useCase->execute($data->email);
        } catch (RateLimitExceededException $e) {
            throw new TooManyRequestsHttpException(null, $e->getMessage(), $e);
        }

        return new ApiDataResponse(new OperationStatusOutput('ok'));
    }
}
