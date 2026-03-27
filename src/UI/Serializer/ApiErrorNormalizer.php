<?php

declare(strict_types=1);

namespace App\UI\Serializer;

use ApiPlatform\State\ApiResource\Error as ApiError;
use ApiPlatform\Validator\Exception\ValidationException;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class ApiErrorNormalizer implements NormalizerInterface
{
    /**
     * @param array<string, mixed> $context
     *
     * @return array{message:string}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        if ($data instanceof ValidationException) {
            return ['message' => (string) $data];
        }

        if ($data instanceof ApiError) {
            $statusCode = $data->getStatus() ?? Response::HTTP_INTERNAL_SERVER_ERROR;
            $message = trim((string) $data->getDetail());

            if ($statusCode >= Response::HTTP_INTERNAL_SERVER_ERROR || $message === '') {
                $message = Response::$statusTexts[$statusCode] ?? 'Internal server error.';
            }

            return ['message' => $message];
        }

        \assert($data instanceof FlattenException);
        $statusCode = $data->getStatusCode();
        $message = trim((string) $data->getMessage());

        if ($statusCode >= Response::HTTP_INTERNAL_SERVER_ERROR || $message === '') {
            $message = Response::$statusTexts[$statusCode] ?? 'Internal server error.';
        }

        return ['message' => $message];
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        if ($format !== 'json') {
            return false;
        }

        return $data instanceof FlattenException || $data instanceof ApiError || $data instanceof ValidationException;
    }

    public function getSupportedTypes(?string $format): array
    {
        if ($format !== 'json') {
            return [];
        }

        return [
            FlattenException::class => true,
            ApiError::class => true,
            ValidationException::class => true,
        ];
    }
}
