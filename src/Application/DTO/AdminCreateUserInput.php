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
    #[Assert\All([
        new Assert\Type('string'),
    ])]
    public ?array $roles = null;

    #[Assert\Type('bool')]
    public bool $isVerified = false;
}
