<?php

declare(strict_types=1);

namespace App\UI\Exception;

use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

final class DtoValidationException extends \RuntimeException
{
    public function __construct(private readonly ConstraintViolationListInterface $violations)
    {
        parent::__construct(self::buildMessage($violations));
    }

    public function violations(): ConstraintViolationListInterface
    {
        return $this->violations;
    }

    private static function buildMessage(ConstraintViolationListInterface $violations): string
    {
        $messages = [];

        /** @var ConstraintViolationInterface $violation */
        foreach ($violations as $violation) {
            $propertyPath = trim($violation->getPropertyPath());
            $prefix = $propertyPath !== '' ? sprintf('%s: ', $propertyPath) : '';
            $messages[] = $prefix . $violation->getMessage();
        }

        if ($messages === []) {
            return 'Input validation failed.';
        }

        return implode('; ', $messages);
    }
}
