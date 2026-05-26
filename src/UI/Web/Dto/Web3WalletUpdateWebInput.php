<?php

declare(strict_types=1);

namespace App\UI\Web\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class Web3WalletUpdateWebInput
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $id = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Assert\Url(protocols: ['http', 'https'], requireTld: false)]
    public string $rpcEndpoint = '';
}
