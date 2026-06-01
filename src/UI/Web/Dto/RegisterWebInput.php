<?php

declare(strict_types=1);

namespace App\UI\Web\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class RegisterWebInput
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 8, max: 255)]
    #[Assert\Regex(
        pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).+$/',
        message: 'validation.password.complexity',
    )]
    public string $password = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 16, max: 255)]
    public string $deviceFingerprint = '';
}
