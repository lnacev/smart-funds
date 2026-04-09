<?php

declare(strict_types=1);

namespace App\Infrastructure;

use Nette\Application\Routers\RouteList;

final class RouterFactory
{
    public static function createRouter(): RouteList
    {
        $router = new RouteList();

        // Admin module
        $router->addRoute('admin/<presenter>[/<action>[/<id>]]', [
            'module' => 'Admin',
            'presenter' => 'Dashboard',
            'action' => 'default',
        ]);

        // Investor module
        $router->addRoute('investor/<presenter>[/<action>[/<id>]]', [
            'module' => 'Investor',
            'presenter' => 'Dashboard',
            'action' => 'default',
        ]);

        // Front module (public)
        $router->addRoute('<presenter>[/<action>[/<id>]]', [
            'module' => 'Front',
            'presenter' => 'Home',
            'action' => 'default',
        ]);

        return $router;
    }
}
