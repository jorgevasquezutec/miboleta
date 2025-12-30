<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Configurar autenticación para Horizon
        Horizon::auth(function ($request) {
            // En desarrollo, permitir acceso libre
            if (app()->environment('local')) {
                return true;
            }

            // En producción, usar Basic Auth
            return $this->checkBasicAuth($request);
        });
    }

    /**
     * Check Basic Auth credentials for Horizon access.
     */
    protected function checkBasicAuth($request): bool
    {
        $user = $request->getUser();
        $password = $request->getPassword();

        // Credenciales desde .env
        $expectedUser = config('horizon.basic_auth.user', 'horizon');
        $expectedPassword = config('horizon.basic_auth.password');

        // Si no hay password configurado, denegar acceso
        if (empty($expectedPassword)) {
            return false;
        }

        // Verificar credenciales
        if ($user === $expectedUser && $password === $expectedPassword) {
            return true;
        }

        // Si las credenciales no coinciden, pedir autenticación
        if (!$user || !$password) {
            header('WWW-Authenticate: Basic realm="Horizon Dashboard"');
            header('HTTP/1.0 401 Unauthorized');
            echo 'Acceso no autorizado.';
            exit;
        }

        return false;
    }

    /**
     * Register the Horizon gate.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            // Permitir acceso en desarrollo
            if (app()->environment('local')) {
                return true;
            }

            // En producción, el acceso se controla via Basic Auth
            return false;
        });
    }
}
