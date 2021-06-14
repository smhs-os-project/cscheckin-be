<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->rebinding('request', function ($app, $request) {
            if ($request->is('api/*')) {
                $accept = $request->header('Accept');
                $accept = rtrim('application/json,' . $accept, ',');

                $request->headers->set('Accept', $accept);
                $request->server->set('HTTP_ACCEPT', $accept);
                $_SERVER['HTTP_ACCEPT'] = $accept;
            }
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
