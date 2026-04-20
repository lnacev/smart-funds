<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

final class AlphaVantageProvider implements PriceProviderInterface
{
    private const BASE_URL = 'https://www.alphavantage.co/query';

    public function __construct(
        private readonly string $apiKey,
    ) {
    }

    private bool $rateLimited = false;

    public function isRateLimited(): bool
    {
        return $this->rateLimited;
    }

    public function fetchPrice(string $providerSymbol): ?array
    {
        $url = self::BASE_URL . '?' . \http_build_query([
            'function' => 'GLOBAL_QUOTE',
            'symbol'   => $providerSymbol,
            'apikey'   => $this->apiKey,
        ]);

        $data = $this->httpGet($url);
        if ($data === null) {
            return null;
        }

        if (isset($data['Information']) || isset($data['Note'])) {
            $this->rateLimited = true;
            \error_log('AlphaVantage rate limit: ' . ($data['Information'] ?? $data['Note']));
            return null;
        }

        $quote = $data['Global Quote'] ?? [];
        $price = isset($quote['05. price']) ? (float) $quote['05. price'] : null;

        if ($price === null || $price <= 0.0) {
            return null;
        }

        return ['price' => $price, 'currency' => 'USD'];
    }

    public function fetchBatch(array $symbols): array
    {
        $results = [];
        foreach ($symbols as $symbol) {
            if ($this->rateLimited) {
                break;
            }
            $result = $this->fetchPrice($symbol);
            if ($result !== null) {
                $results[$symbol] = $result;
            }
            \usleep(200_000);
        }
        return $results;
    }

    /**
     * Fetch kurzů USD→CZK a EUR→CZK.
     * @return array<string, float>
     */
    public function fetchExchangeRates(): array
    {
        $rates = [];
        foreach (['USD', 'EUR'] as $from) {
            $url = self::BASE_URL . '?' . \http_build_query([
                'function'      => 'CURRENCY_EXCHANGE_RATE',
                'from_currency' => $from,
                'to_currency'   => 'CZK',
                'apikey'        => $this->apiKey,
            ]);
            $data = $this->httpGet($url);
            $rate = $data['Realtime Currency Exchange Rate']['5. Exchange Rate'] ?? null;
            if ($rate !== null) {
                $rates[$from] = (float) $rate;
            }
            \usleep(200_000);
        }
        return $rates;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function searchSymbols(string $query): array
    {
        $url = self::BASE_URL . '?' . \http_build_query([
            'function' => 'SYMBOL_SEARCH',
            'keywords' => $query,
            'apikey'   => $this->apiKey,
        ]);

        $data = $this->httpGet($url);
        return $data['bestMatches'] ?? [];
    }

    private function httpGet(string $url): ?array
    {
        $ctx = \stream_context_create(['http' => ['timeout' => 10]]);
        $response = @\file_get_contents($url, false, $ctx);
        if ($response === false) {
            return null;
        }
        $decoded = \json_decode($response, true);
        return \is_array($decoded) ? $decoded : null;
    }
}
