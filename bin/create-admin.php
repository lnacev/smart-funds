#!/usr/bin/env php
<?php

declare(strict_types=1);

// Usage: php bin/create-admin.php email@example.com heslo

if ($argc !== 3) {
    fwrite(STDERR, "Použití: php bin/create-admin.php <email> <heslo>\n");
    exit(1);
}

$email = $argv[1];
$password = $argv[2];

require __DIR__ . '/../vendor/autoload.php';

$container = App\Bootstrap::boot()->createContainer();

/** @var App\Application\User\UserService $userService */
$userService = $container->getByType(App\Application\User\UserService::class);
$userService->createAdmin($email, $password);

echo "Admin $email byl úspěšně vytvořen.\n";
