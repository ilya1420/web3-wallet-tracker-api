<?php

declare(strict_types=1);

namespace App\Infrastructure\Web3;

use App\Application\Exception\Web3ProviderException;
use App\Application\Service\Web3ProviderGatewayInterface;
use Web3\Providers\HttpProvider;

final readonly class Web3ProviderGateway implements Web3ProviderGatewayInterface
{
    public function __construct(private float $requestTimeout = 3.0)
    {
    }

    public function resolveNetworkId(string $rpcEndpoint): string
    {
        $networkId = $this->rpcCall($rpcEndpoint, 'net_version', []);
        $value = $this->normalizeResultToString($networkId, 'network id');
        if ($value === '') {
            throw new Web3ProviderException('Web3 provider returned empty network id.');
        }

        return $value;
    }

    public function fetchBalanceWei(string $rpcEndpoint, string $address): string
    {
        $balance = $this->rpcCall($rpcEndpoint, 'eth_getBalance', [$address, 'latest']);
        $balanceWei = $this->normalizeResultToString($balance, 'wallet balance');

        if ($balanceWei === '') {
            throw new Web3ProviderException('Web3 provider returned empty wallet balance.');
        }

        if (str_starts_with(strtolower($balanceWei), '0x')) {
            try {
                $balanceWei = $this->hexToDecimalString($balanceWei);
            } catch (\Throwable $e) {
                throw new Web3ProviderException('Web3 provider returned invalid hex wallet balance.', previous: $e);
            }
        }

        if (preg_match('/^\d+$/', $balanceWei) !== 1) {
            throw new Web3ProviderException('Web3 provider returned unsupported wallet balance format.');
        }

        return $balanceWei;
    }

    private function createClient(string $rpcEndpoint): HttpProvider
    {
        $endpoint = trim($rpcEndpoint);
        if ($endpoint === '') {
            throw new Web3ProviderException('RPC endpoint cannot be empty.');
        }

        if (filter_var($endpoint, FILTER_VALIDATE_URL) === false) {
            throw new Web3ProviderException('RPC endpoint must be a valid URL.');
        }

        try {
            return new HttpProvider($endpoint, $this->requestTimeout);
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

    /**
     * @param list<mixed> $params
     */
    private function rpcCall(string $rpcEndpoint, string $method, array $params): mixed
    {
        $provider = $this->createClient($rpcEndpoint);

        try {
            $payload = json_encode([
                'jsonrpc' => '2.0',
                'method' => $method,
                'params' => $params,
                'id' => 1,
            ], JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new Web3ProviderException('Unable to build web3 RPC payload.', previous: $e);
        }

        $result = null;
        $error = null;

        $provider->sendPayload($payload, static function (mixed $err, mixed $res) use (&$error, &$result): void {
            $error = $err;
            $result = $res;
        });

        if ($error !== null) {
            throw new Web3ProviderException(sprintf('Web3 RPC method "%s" failed.', $method), previous: $this->normalizeException($error));
        }

        return $result;
    }

    private function hexToDecimalString(string $hexValue): string
    {
        $normalized = strtolower(trim($hexValue));
        if (str_starts_with($normalized, '0x')) {
            $normalized = substr($normalized, 2);
        }

        if ($normalized === '') {
            return '0';
        }

        if (preg_match('/^[a-f0-9]+$/', $normalized) !== 1) {
            throw new \InvalidArgumentException('Hex value contains unsupported characters.');
        }

        $decimal = '0';

        foreach (str_split($normalized) as $char) {
            $digit = (int) hexdec($char);
            $decimal = $this->multiplyDecimalStringByInt($decimal, 16);
            $decimal = $this->addIntToDecimalString($decimal, $digit);
        }

        return ltrim($decimal, '0') ?: '0';
    }

    private function multiplyDecimalStringByInt(string $value, int $multiplier): string
    {
        $carry = 0;
        $result = '';

        for ($i = strlen($value) - 1; $i >= 0; --$i) {
            $product = ((int) $value[$i] * $multiplier) + $carry;
            $result = (string) ($product % 10) . $result;
            $carry = intdiv($product, 10);
        }

        while ($carry > 0) {
            $result = (string) ($carry % 10) . $result;
            $carry = intdiv($carry, 10);
        }

        return ltrim($result, '0') ?: '0';
    }

    private function addIntToDecimalString(string $value, int $addend): string
    {
        $carry = $addend;
        $result = '';

        for ($i = strlen($value) - 1; $i >= 0; --$i) {
            $sum = ((int) $value[$i]) + $carry;
            $result = (string) ($sum % 10) . $result;
            $carry = intdiv($sum, 10);
        }

        while ($carry > 0) {
            $result = (string) ($carry % 10) . $result;
            $carry = intdiv($carry, 10);
        }

        return ltrim($result, '0') ?: '0';
    }
}
