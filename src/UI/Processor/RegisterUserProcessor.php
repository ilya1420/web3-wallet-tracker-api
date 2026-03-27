<?php

declare(strict_types=1);

namespace App\UI\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\DTO\ApiDataResponse;
use App\Application\DTO\RegisterUserInput;
use App\Application\Exception\MultiAccountingDetectedException;
use App\Application\Exception\UserAlreadyExistsException;
use App\Application\UseCase\RegisterUserUseCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final readonly class RegisterUserProcessor implements ProcessorInterface
{
    public function __construct(
        private RegisterUserUseCase $useCase,
        private RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ApiDataResponse
    {
        \assert($data instanceof RegisterUserInput);

        $ip = $this->requestStack->getCurrentRequest()?->getClientIp();

        try {
            return new ApiDataResponse(
                $this->useCase->execute($data->email, $data->password, $data->deviceFingerprint, $ip),
            );
        } catch (MultiAccountingDetectedException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        } catch (UserAlreadyExistsException $e) {
            throw new ConflictHttpException($e->getMessage(), $e);
        }
    }
}
