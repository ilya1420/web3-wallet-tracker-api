<?php

declare(strict_types=1);

namespace App\UI\Web\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class LoginLinkWebInput
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email = '';
}
