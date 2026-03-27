<?php

declare(strict_types=1);

namespace App\Application\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final class AdminCreateUserInput
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email;

    #[Assert\NotBlank]
    #[Assert\Length(min: 8, max: 255)]
    public string $password;

    /** @var list<string>|null */
    #[Assert\Count(min: 1)]
    #[Assert\All([
        new Assert\Type('string'),
        new Assert\Regex('/^ROLE_[A-Z0-9_]+$/'),
    ])]
    public ?array $roles = null;

    #[Assert\Type('bool')]
    public bool $isVerified = false;
}
