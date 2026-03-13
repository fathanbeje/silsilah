<?php

namespace App\Providers;

use App\RegistrationRequest;
use App\Services\FamilyScopeResolver;
use App\UserEditRequest;
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

        // Let each deployment decide via APP_FORCE_HTTPS, regardless of APP_ENV.
        if (config('app.force_https')) {
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
            $pendingUserEditRequestCount = 0;

            if (Auth::check() && is_system_admin(Auth::user())) {
                $familyScopeResolver = app(FamilyScopeResolver::class);

                $pendingRegistrationRequestCount = RegistrationRequest::query()
                    ->forTenant($familyScopeResolver)
                    ->where('status', RegistrationRequest::STATUS_PENDING)
                    ->count();

                $pendingUserEditRequestCount = UserEditRequest::query()
                    ->forTenant($familyScopeResolver)
                    ->pending()
                    ->count();
            }

            $view->with([
                'pendingRegistrationRequestCount' => $pendingRegistrationRequestCount,
                'pendingUserEditRequestCount' => $pendingUserEditRequestCount,
                'pendingReviewCount' => $pendingRegistrationRequestCount + $pendingUserEditRequestCount,
            ]);
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->scoped(FamilyScopeResolver::class, function ($app) {
            return new FamilyScopeResolver($app['request']);
        });
    }
}
