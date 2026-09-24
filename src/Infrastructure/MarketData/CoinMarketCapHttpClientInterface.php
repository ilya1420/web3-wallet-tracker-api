<?php

declare(strict_types=1);

namespace App\Infrastructure\MarketData;

interface CoinMarketCapHttpClientInterface
{
    /**
     * @param array<string, string> $headers
     *
     * @return array<string, mixed>
     */
    public function getJson(string $url, array $headers): array;
}
