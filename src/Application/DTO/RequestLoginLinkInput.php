<?php

declare(strict_types=1);

namespace App\Application\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final class RequestLoginLinkInput
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email;
}
