<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

final class CoinGeckoProvider implements PriceProviderInterface
{
    private const BASE_URL = 'https://api.coingecko.com/api/v3';

    public function fetchPrice(string $providerSymbol): ?array
    {
        $result = $this->fetchBatch([$providerSymbol]);
        return $result[$providerSymbol] ?? null;
    }

    public function fetchBatch(array $symbols): array
    {
        if (empty($symbols)) {
            return [];
        }

        $ids = \implode(',', $symbols);
        $url = self::BASE_URL . '/simple/price?' . \http_build_query([
            'ids'           => $ids,
            'vs_currencies' => 'usd',
        ]);

        $ctx = \stream_context_create(['http' => ['timeout' => 10]]);
        $response = @\file_get_contents($url, false, $ctx);
        if ($response === false) {
            return [];
        }

        $data = \json_decode($response, true);
        if (!\is_array($data)) {
            return [];
        }

        $results = [];
        foreach ($symbols as $symbol) {
            $price = $data[$symbol]['usd'] ?? null;
            if ($price !== null) {
                $results[$symbol] = ['price' => (float) $price, 'currency' => 'USD'];
            }
        }
        return $results;
    }
}
