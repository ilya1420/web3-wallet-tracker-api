<?php

declare(strict_types=1);

namespace App\UI\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\DTO\AuthTokenOutput;
use App\Application\DTO\ConfirmTokenInput;
use App\Application\UseCase\ConfirmLoginTokenUseCase;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

final readonly class ConfirmTokenProcessor implements ProcessorInterface
{
    public function __construct(private ConfirmLoginTokenUseCase $useCase)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AuthTokenOutput
    {
        \assert($data instanceof ConfirmTokenInput);

        try {
            return $this->useCase->execute($data->email, $data->token);
        } catch (\RuntimeException $e) {
            throw new UnauthorizedHttpException('Bearer', $e->getMessage(), $e);
        }
    }
}
