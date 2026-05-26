<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Module Route Auto-Loader
|--------------------------------------------------------------------------
| Charge automatiquement le fichier Routes/api.php de chaque module.
| Tous les modules sont préfixés /v1 et passent par le middleware 'api'.
|--------------------------------------------------------------------------
*/

$modulesPath = app_path('Modules');

if (is_dir($modulesPath)) {
    foreach (glob($modulesPath . '/*/Routes/api.php') as $routeFile) {
        Route::middleware(['api', 'cors', 'secure.headers', 'validate.json'])
            ->prefix('v1')
            ->group($routeFile);
    }
}
