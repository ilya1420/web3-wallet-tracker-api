<?php

declare(strict_types=1);

namespace App\Infrastructure\Web3;

use App\Application\Exception\Web3ProviderException;
use App\Application\Service\Web3ProviderGatewayInterface;
use Web3\Utils;
use Web3\Web3;

final readonly class Web3ProviderGateway implements Web3ProviderGatewayInterface
{
    public function __construct(private float $requestTimeout = 3.0)
    {
    }

    public function resolveNetworkId(string $rpcEndpoint): string
    {
        $web3 = $this->createClient($rpcEndpoint);

        $networkId = null;
        $error = null;

        $web3->net->version(function ($err, $result) use (&$networkId, &$error): void {
            $error = $err;
            $networkId = $result;
        });

        if ($error !== null) {
            throw new Web3ProviderException('Unable to resolve network id from web3 provider.', previous: $this->normalizeException($error));
        }

        $value = $this->normalizeResultToString($networkId, 'network id');
        if ($value === '') {
            throw new Web3ProviderException('Web3 provider returned empty network id.');
        }

        return $value;
    }

    public function fetchBalanceWei(string $rpcEndpoint, string $address): string
    {
        $web3 = $this->createClient($rpcEndpoint);

        $balance = null;
        $error = null;

        $web3->eth->getBalance($address, 'latest', function ($err, $result) use (&$balance, &$error): void {
            $error = $err;
            $balance = $result;
        });

        if ($error !== null) {
            throw new Web3ProviderException('Unable to fetch wallet balance from web3 provider.', previous: $this->normalizeException($error));
        }

        $balanceWei = $this->normalizeResultToString($balance, 'wallet balance');
        if ($balanceWei === '') {
            throw new Web3ProviderException('Web3 provider returned empty wallet balance.');
        }

        if (str_starts_with(strtolower($balanceWei), '0x')) {
            try {
                $balanceWei = Utils::toBn($balanceWei)->toString();
            } catch (\Throwable $e) {
                throw new Web3ProviderException('Web3 provider returned invalid hex wallet balance.', previous: $e);
            }
        }

        if (preg_match('/^\d+$/', $balanceWei) !== 1) {
            throw new Web3ProviderException('Web3 provider returned unsupported wallet balance format.');
        }

        return $balanceWei;
    }

    private function createClient(string $rpcEndpoint): Web3
    {
        $endpoint = trim($rpcEndpoint);
        if ($endpoint === '') {
            throw new Web3ProviderException('RPC endpoint cannot be empty.');
        }

        if (filter_var($endpoint, FILTER_VALIDATE_URL) === false) {
            throw new Web3ProviderException('RPC endpoint must be a valid URL.');
        }

        try {
            return new Web3($endpoint, $this->requestTimeout);
        } catch (\Throwable $e) {
            throw new Web3ProviderException('Unable to initialize web3 provider client.', previous: $e);
        }
    }

    private function normalizeException(mixed $error): \Throwable
    {
        if ($error instanceof \Throwable) {
            return $error;
        }

        if (is_array($error)) {
            $messages = array_map(
                static fn (mixed $item): string => $item instanceof \Throwable ? $item->getMessage() : (string) $item,
                $error,
            );

            return new \RuntimeException(implode('; ', $messages));
        }

        return new \RuntimeException((string) $error);
    }

    private function normalizeResultToString(mixed $result, string $context): string
    {
        if (is_string($result) || is_int($result) || is_float($result)) {
            return trim((string) $result);
        }

        if (is_object($result) && method_exists($result, 'toString')) {
            return trim((string) $result->toString());
        }

        if (is_object($result) && method_exists($result, '__toString')) {
            return trim((string) $result);
        }

        throw new Web3ProviderException(sprintf('Web3 provider returned unsupported %s format.', $context));
    }
}
