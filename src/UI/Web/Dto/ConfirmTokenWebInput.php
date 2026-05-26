<?php

declare(strict_types=1);

namespace App\UI\Web\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class ConfirmTokenWebInput
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 32, max: 512)]
    public string $token = '';
}
