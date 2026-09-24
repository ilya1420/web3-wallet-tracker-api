<?php

declare(strict_types=1);

namespace App\Infrastructure\MarketData;

use App\Application\DTO\ConversionResult;
use App\Application\Exception\CurrencyConversionException;
use App\Application\Service\CurrencyConverterInterface;
use App\Domain\Service\DecimalArithmetic;
use App\Domain\ValueObject\Currency;
use App\Domain\ValueObject\Money;
use Psr\Cache\CacheItemInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;

final readonly class CoinMarketCapCurrencyConverter implements CurrencyConverterInterface
{
    private const ENDPOINT = 'https://pro-api.coinmarketcap.com/v2/tools/price-conversion';

    public function __construct(
        private CoinMarketCapHttpClientInterface $httpClient,
        private CacheInterface $cache,
        private LoggerInterface $logger,
        private string $apiKey,
        private int $cacheTtl = 55,
    ) {
    }

    public function convert(Money $amount, Currency $from, Currency $to): ConversionResult
    {
        if ($amount->isZero()) {
            return new ConversionResult($from->code(), $to->code(), '0', '0', '0', new \DateTimeImmutable());
        }

        if ($from->code() === $to->code()) {
            return new ConversionResult($from->code(), $to->code(), $amount->amount(), $amount->amount(), '1', new \DateTimeImmutable());
        }

        if (trim($this->apiKey) === '') {
            throw new CurrencyConversionException('CoinMarketCap API key is not configured.');
        }

        $cacheKey = sprintf('market.cmc.rate.%s', hash('sha256', $from->code() . ':' . $to->code()));
        /** @var array{rate: string, rateUpdatedAt: string} $rateData */
        $rateData = $this->cache->get($cacheKey, function (CacheItemInterface $item) use ($from, $to): array {
            $item->expiresAfter($this->cacheTtl);

            return $this->fetchRate($from, $to);
        });

        $rateUpdatedAt = $this->parseTimestamp($rateData['rateUpdatedAt']);

        return new ConversionResult(
            from: $from->code(),
            to: $to->code(),
            sourceAmount: $amount->amount(),
            convertedAmount: DecimalArithmetic::multiply($amount->amount(), $rateData['rate']),
            rate: $rateData['rate'],
            rateUpdatedAt: $rateUpdatedAt,
        );
    }

    /** @return array{rate: string, rateUpdatedAt: string} */
    private function fetchRate(Currency $from, Currency $to): array
    {
        $url = self::ENDPOINT . '?' . http_build_query([
            'amount' => '1',
            'symbol' => $from->code(),
            'convert' => $to->code(),
        ], '', '&', PHP_QUERY_RFC3986);

        $payload = $this->httpClient->getJson($url, [
            'Accept: application/json',
            'X-CMC_PRO_API_KEY: ' . $this->apiKey,
        ]);

        $data = $payload['data'] ?? null;
        $source = is_array($data) && array_is_list($data) ? ($data[0] ?? null) : $data;
        $quote = is_array($source) ? ($source['quote'][$to->code()] ?? null) : null;
        $price = is_array($quote) ? ($quote['price'] ?? null) : null;
        $lastUpdated = is_array($quote) ? ($quote['last_updated'] ?? null) : null;

        if (!is_numeric($price) || (float) $price < 0 || !is_string($lastUpdated) || $lastUpdated === '') {
            throw new CurrencyConversionException('CoinMarketCap returned an incomplete conversion quote.');
        }

        $rate = $this->normalizeApiDecimal($price);
        $this->logger->info('market.cmc.rate_fetched', [
            'provider' => 'coinmarketcap',
            'from' => $from->code(),
            'to' => $to->code(),
            'rate_updated_at' => $lastUpdated,
        ]);

        return ['rate' => $rate, 'rateUpdatedAt' => $lastUpdated];
    }

    private function normalizeApiDecimal(mixed $value): string
    {
        $normalized = is_float($value) ? sprintf('%.18F', $value) : trim((string) $value);
        if (str_contains($normalized, '.')) {
            $normalized = rtrim(rtrim($normalized, '0'), '.');
        }

        if (preg_match('/^\d+(?:\.\d+)?$/', $normalized) !== 1) {
            throw new CurrencyConversionException('CoinMarketCap returned an invalid conversion rate.');
        }

        return $normalized;
    }

    private function parseTimestamp(string $timestamp): \DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable($timestamp);
        } catch (\Exception $exception) {
            throw new CurrencyConversionException('CoinMarketCap returned an invalid quote timestamp.', previous: $exception);
        }
    }
}
