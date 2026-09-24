<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\MarketData;

use App\Domain\ValueObject\Currency;
use App\Domain\ValueObject\Money;
use App\Infrastructure\MarketData\CoinMarketCapCurrencyConverter;
use App\Infrastructure\MarketData\CoinMarketCapHttpClientInterface;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\Cache\CacheInterface;

final class CoinMarketCapCurrencyConverterTest extends TestCase
{
    public function testConvertsUsingCachedOneUnitMarketRateWithoutFloatAmountMath(): void
    {
        $httpClient = new CoinMarketCapHttpClientStub([
            'data' => [
                'quote' => [
                    'USD' => [
                        'price' => 2490.5,
                        'last_updated' => '2026-09-24T10:00:00.000Z',
                    ],
                ],
            ],
        ]);
        $converter = new CoinMarketCapCurrencyConverter(
            $httpClient,
            new CallbackCache(),
            new NullLogger(),
            'test-key',
        );

        $result = $converter->convert(new Money('1.230000000000000001'), new Currency('ETH'), new Currency('USD'));

        self::assertSame('ETH', $result->from);
        self::assertSame('USD', $result->to);
        self::assertSame('2490.5', $result->rate);
        self::assertSame('3063.3150000000000024905', $result->convertedAmount);
        self::assertSame('2026-09-24T10:00:00+00:00', $result->rateUpdatedAt->format(DATE_ATOM));
        self::assertStringContainsString('amount=1', $httpClient->url);
        self::assertStringNotContainsString('test-key', $httpClient->url);
        self::assertContains('X-CMC_PRO_API_KEY: test-key', $httpClient->headers);
    }
}

final class CoinMarketCapHttpClientStub implements CoinMarketCapHttpClientInterface
{
    /** @var array<string, mixed> */
    private array $payload;

    public string $url = '';

    /** @var array<string, string> */
    public array $headers = [];

    /** @param array<string, mixed> $payload */
    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function getJson(string $url, array $headers): array
    {
        $this->url = $url;
        $this->headers = $headers;

        return $this->payload;
    }
}

final class CallbackCache implements CacheInterface
{
    public function get(string $key, callable $callback, float $beta = null, ?array &$metadata = null): mixed
    {
        return $callback(new CacheItem());
    }

    public function delete(string $key): bool
    {
        return true;
    }
}

final class CacheItem implements CacheItemInterface
{
    public function getKey(): string
    {
        return 'test';
    }

    public function get(): mixed
    {
        return null;
    }

    public function isHit(): bool
    {
        return false;
    }

    public function set(mixed $value): static
    {
        return $this;
    }

    public function expiresAt(?\DateTimeInterface $expiration): static
    {
        return $this;
    }

    public function expiresAfter(\DateInterval|int|null $time): static
    {
        return $this;
    }
}
