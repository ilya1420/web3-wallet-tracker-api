<?php

declare(strict_types=1);

namespace App\Application\DTO;

final readonly class ApiDataResponse
{
    public function __construct(public mixed $data)
    {
    }
}
