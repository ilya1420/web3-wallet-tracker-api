<?php

declare(strict_types=1);

namespace App\Infrastructure\MarketData;

use App\Application\Exception\CurrencyConversionException;

final readonly class NativeCoinMarketCapHttpClient implements CoinMarketCapHttpClientInterface
{
    public function __construct(private float $requestTimeout = 3.0)
    {
    }

    public function getJson(string $url, array $headers): array
    {
        $response = @file_get_contents($url, false, stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => $this->requestTimeout,
                'ignore_errors' => true,
            ],
        ]));

        $statusLine = $http_response_header[0] ?? '';
        if ($response === false || preg_match('/\s(\d{3})\s/', $statusLine, $matches) !== 1) {
            throw new CurrencyConversionException('CoinMarketCap request failed.');
        }

        $statusCode = (int) $matches[1];
        try {
            $payload = json_decode($response, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (\JsonException $exception) {
            throw new CurrencyConversionException('CoinMarketCap returned invalid JSON.', previous: $exception);
        }

        if (!is_array($payload)) {
            throw new CurrencyConversionException('CoinMarketCap returned an unsupported response.');
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            $message = is_array($payload['status'] ?? null) ? ($payload['status']['error_message'] ?? null) : null;
            throw new CurrencyConversionException(is_string($message) && $message !== '' ? $message : 'CoinMarketCap conversion is unavailable.');
        }

        return $payload;
    }
}
