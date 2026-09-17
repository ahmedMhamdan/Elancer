<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        RateLimiter::for('profile-photos', function (Request $request) {
            if (! $request->hasFile('photo')) {
                return Limit::none();
            }
            $user = $request->user()->id ?? $request->ip();

            return [Limit::perMinute(1)->by('photo-minute:'.$user), Limit::perHour(3)->by('photo-hour:'.$user)];
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): Password => Password::min(12)
            ->mixedCase()->letters()->numbers()->symbols()
            ->when(app()->isProduction(), fn (Password $rule) => $rule->uncompromised())
        );
    }
}
