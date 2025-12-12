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

        // Desactivar autenticación en desarrollo
        Horizon::auth(function ($request) {
            return app()->environment('local') ||
                   Gate::check('viewHorizon', [$request->user()]);
        });

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            // Permitir acceso en desarrollo
            if (app()->environment('local')) {
                return true;
            }

            // En producción, solo permitir a estos emails
            return in_array(optional($user)->email, [
                //
            ]);
        });
    }
}
