<?php

namespace App\Providers;

use App\RegistrationRequest;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        require_once app_path() . '/Helpers/functions.php';

        // Only force HTTPS when the deployment explicitly enables it.
        if ($this->app->environment() === 'production' && env('APP_FORCE_HTTPS', false)) {
            $this->app['request']->server->set('HTTPS', true);
            URL::forceRootUrl(config('app.url'));
            URL::forceScheme('https');
        }

        \Validator::extend('current_password', function ($attribute, $value, $parameters, $validator) {
            $user = \Auth::user();

            return $user && \Hash::check($value, $user->password);
        });

        \Validator::extend('same_password', function ($attribute, $value, $parameters, $validator) {
            $user = \Auth::user();

            return $user && !\Hash::check($value, $user->password);
        });

        View::composer('layouts.partials.nav', function ($view) {
            $pendingRegistrationRequestCount = 0;

            if (Auth::check() && is_system_admin(Auth::user())) {
                $pendingRegistrationRequestCount = RegistrationRequest::query()
                    ->where('status', RegistrationRequest::STATUS_PENDING)
                    ->count();
            }

            $view->with('pendingRegistrationRequestCount', $pendingRegistrationRequestCount);
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
