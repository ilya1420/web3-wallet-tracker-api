<?php

declare(strict_types=1);

namespace App\UI\Web\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class LoginPasswordWebInput
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 8, max: 255)]
    public string $password = '';
}
