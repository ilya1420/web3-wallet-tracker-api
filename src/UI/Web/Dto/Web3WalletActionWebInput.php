<?php

declare(strict_types=1);

namespace App\UI\Web\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class Web3WalletActionWebInput
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $id = '';
}
