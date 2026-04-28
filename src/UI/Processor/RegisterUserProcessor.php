<?php

declare(strict_types=1);

namespace App\UI\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\DTO\ApiDataResponse;
use App\Application\DTO\RegisterUserInput;
use App\Application\UseCase\RegisterUserUseCase;
use App\UI\Input\DtoInputResolver;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements ProcessorInterface<RegisterUserInput, ApiDataResponse>
 */
final readonly class RegisterUserProcessor implements ProcessorInterface
{
    public function __construct(
        private RegisterUserUseCase $useCase,
        private RequestStack $requestStack,
        private DtoInputResolver $inputResolver,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ApiDataResponse
    {
        /** @var RegisterUserInput $input */
        $input = $this->inputResolver->resolveAndValidate($data, RegisterUserInput::class);

        $ip = $this->requestStack->getCurrentRequest()?->getClientIp();

        return new ApiDataResponse(
            $this->useCase->execute($input->email, $input->password, $input->deviceFingerprint, $ip),
        );
    }
}
