<?php

declare(strict_types=1);

namespace App\UI\Web\Controller;

use App\Application\Exception\CurrencyConversionException;
use App\Application\Service\CurrencyConverterInterface;
use App\Domain\ValueObject\Currency;
use App\Domain\ValueObject\Money;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MarketConverterController extends AbstractWebController
{
    /** @var array<string, string> */
    private const BASE_CURRENCIES = [
        'ETH' => 'Ethereum',
        'BTC' => 'Bitcoin',
        'SOL' => 'Solana',
        'BNB' => 'BNB Chain',
        'POL' => 'Polygon',
        'AVAX' => 'Avalanche',
        'USD' => 'US Dollar',
        'EUR' => 'Euro',
        'RUB' => 'Russian Ruble',
        'USDT' => 'Tether',
    ];

    /** @var array<string, string> */
    private const QUOTE_CURRENCIES = [
        'USD' => 'US Dollar',
        'EUR' => 'Euro',
        'RUB' => 'Russian Ruble',
        'USDT' => 'Tether',
        'BTC' => 'Bitcoin',
        'ETH' => 'Ethereum',
        'SOL' => 'Solana',
        'BNB' => 'BNB Chain',
        'POL' => 'Polygon',
        'AVAX' => 'Avalanche',
    ];

    public function __construct(
        private readonly CurrencyConverterInterface $currencyConverter,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route(path: '/app/converter', name: 'app_market_converter', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $user = $this->currentWebUser();
        $market = $this->convert($request, $user->id()->toRfc4122());

        return $this->renderWithStatus('web_app/market_converter.html.twig', [
            'market' => $market,
            'baseCurrencies' => self::BASE_CURRENCIES,
            'quoteCurrencies' => self::QUOTE_CURRENCIES,
        ], $market['submitted'] && $market['error'] !== null ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);
    }

    /**
     * @return array{amount:string, from:string, to:string, submitted:bool, result:?\App\Application\DTO\ConversionResult, convertedAmount:?string, rate:?string, error:?string}
     */
    private function convert(Request $request, string $userId): array
    {
        $submitted = $request->query->getBoolean('convert');
        $amount = trim((string) $request->query->get('amount', '1'));
        $from = strtoupper(trim((string) $request->query->get('from', 'ETH')));
        $to = strtoupper(trim((string) $request->query->get('to', 'USD')));
        $state = ['amount' => $amount, 'from' => $from, 'to' => $to, 'submitted' => $submitted, 'result' => null, 'convertedAmount' => null, 'rate' => null, 'error' => null];

        if (!$submitted) {
            return $state;
        }

        try {
            if (!array_key_exists($from, self::BASE_CURRENCIES) || !array_key_exists($to, self::QUOTE_CURRENCIES)) {
                throw new \InvalidArgumentException('Unsupported market pair.');
            }

            $state['result'] = $this->currencyConverter->convert(new Money($amount), new Currency($from), new Currency($to));
            $state['convertedAmount'] = $this->formatDecimalForDisplay($state['result']->convertedAmount);
            $state['rate'] = $this->formatDecimalForDisplay($state['result']->rate);
        } catch (CurrencyConversionException|\InvalidArgumentException $exception) {
            $this->logger->warning('web.market_converter.unavailable', [
                'user_id' => $userId,
                'from' => $from,
                'to' => $to,
                'reason' => $exception instanceof CurrencyConversionException ? 'provider_unavailable' : 'invalid_input',
            ]);
            $state['error'] = $exception instanceof CurrencyConversionException ? 'market.page.unavailable' : 'market.page.invalid_input';
        }

        return $state;
    }

    private function formatDecimalForDisplay(string $value): string
    {
        [$integer, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $integer = preg_replace('/\B(?=(\d{3})+(?!\d))/', ' ', $integer) ?? $integer;
        $fraction = rtrim(substr($fraction, 0, 8), '0');

        return $fraction === '' ? $integer : sprintf('%s.%s', $integer, $fraction);
    }
}
