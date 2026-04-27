<?php

declare(strict_types=1);

namespace App\Application\Service;

interface Web3ProviderGatewayInterface
{
    public function resolveNetworkId(string $rpcEndpoint): string;

    public function fetchBalanceWei(string $rpcEndpoint, string $address): string;
}
