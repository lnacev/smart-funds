<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

final class YahooFinanceProvider implements PriceProviderInterface
{
    public function fetchPrice(string $providerSymbol): ?array
    {
        $url = 'https://query1.finance.yahoo.com/v8/finance/chart/' . \urlencode($providerSymbol)
            . '?interval=1d&range=1d';

        $ctx = \stream_context_create([
            'http' => [
                'timeout' => 10,
                'header'  => "User-Agent: Mozilla/5.0\r\n",
            ],
        ]);
        $response = @\file_get_contents($url, false, $ctx);
        if ($response === false) {
            return null;
        }

        $data = \json_decode($response, true);
        $result = $data['chart']['result'][0] ?? null;
        if ($result === null) {
            return null;
        }

        $price = $result['meta']['regularMarketPrice'] ?? null;
        $currency = $result['meta']['currency'] ?? 'USD';

        if ($price === null || $price <= 0.0) {
            return null;
        }

        return ['price' => (float) $price, 'currency' => \strtoupper($currency)];
    }

    public function fetchBatch(array $symbols): array
    {
        $results = [];
        foreach ($symbols as $symbol) {
            $result = $this->fetchPrice($symbol);
            if ($result !== null) {
                $results[$symbol] = $result;
            }
            \usleep(100_000);
        }
        return $results;
    }
}
