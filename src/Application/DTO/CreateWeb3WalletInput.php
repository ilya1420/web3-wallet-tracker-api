<?php

declare(strict_types=1);

namespace App\Application\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateWeb3WalletInput
{
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^0x[a-fA-F0-9]{40}$/', message: 'Address must be a valid EVM address.')]
    public string $address;

    #[Assert\Length(max: 255)]
    #[Assert\Url(protocols: ['http', 'https'], requireTld: false)]
    public ?string $rpcEndpoint = null;
}
