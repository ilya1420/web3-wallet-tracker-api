<?php

declare(strict_types=1);

namespace App\UI\Web\Dto;

use App\Domain\Enum\EvmRpcPreset;
use Symfony\Component\Validator\Constraints as Assert;

final class Web3WalletCreateWebInput
{
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^0x[a-fA-F0-9]{40}$/', message: 'Address must be a valid EVM address.')]
    public string $address = '';

    #[Assert\NotBlank]
    #[Assert\Choice(callback: [EvmRpcPreset::class, 'values'])]
    public string $rpcPreset = EvmRpcPreset::ETHEREUM->value;

    #[Assert\Length(max: 255)]
    #[Assert\Url(protocols: ['http', 'https'], requireTld: false)]
    public ?string $rpcEndpoint = null;
}
