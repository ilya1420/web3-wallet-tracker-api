<?php

declare(strict_types=1);

namespace App\Application\DTO;

final readonly class OperationStatusOutput
{
    public function __construct(public string $status)
    {
    }
}
