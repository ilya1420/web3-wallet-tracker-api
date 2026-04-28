<?php

declare(strict_types=1);

namespace App\UI\Input;

use App\UI\Exception\DtoValidationException;
use App\UI\Exception\UnexpectedInputTypeException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class DtoInputResolver
{
    public function __construct(private ValidatorInterface $validator)
    {
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $expectedClass
     *
     * @return T
     */
    public function resolveAndValidate(mixed $data, string $expectedClass): object
    {
        if (!$data instanceof $expectedClass) {
            $actual = is_object($data) ? $data::class : get_debug_type($data);

            throw new UnexpectedInputTypeException(sprintf('Expected payload of type %s, got %s.', $expectedClass, $actual));
        }

        $violations = $this->validator->validate($data);
        if ($violations->count() > 0) {
            throw new DtoValidationException($violations);
        }

        return $data;
    }
}
