#!/usr/bin/env php
<?php

declare(strict_types=1);

// Použití: php bin/fetch-prices.php [--force]
// --force přeskočí cooldown 23h a vynutí fetch všech securities

$force = \in_array('--force', $argv ?? [], true);

require __DIR__ . '/../vendor/autoload.php';

$container = App\Bootstrap::boot()->createContainer();

/** @var App\Application\Prices\PriceFetcherService $fetcher */
$fetcher = $container->getByType(App\Application\Prices\PriceFetcherService::class);

$start = \microtime(true);
$result = $fetcher->fetchAll($force);
$elapsed = \round(\microtime(true) - $start, 2);

echo \sprintf(
    "[%s] Fetch dokončen za %.2fs — OK: %d, Chyby: %d, Přeskočeno: %d\n",
    \date('Y-m-d H:i:s'),
    $elapsed,
    $result['ok'],
    $result['errors'],
    $result['skipped'],
);
