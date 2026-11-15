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
        $this->configureRateLimiting();
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

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Named rate limiters, used as route middleware like "throttle:widget-messages".
     */
    protected function configureRateLimiting(): void
    {
        // Widget questions are public, so two limits apply at once:
        // one visitor can't flood a widget, and all visitors together can't burn the team's AI quota.
        RateLimiter::for('widget-messages', function (Request $request): array {
            $key = (string) $request->route('key');

            $tooMany = fn (Request $request, array $headers) => response()->json([
                'message' => 'Too many questions. Please wait a minute and try again.',
            ], 429, $headers);

            return [
                // Each limit needs its own prefix, or the two would share one counter.
                Limit::perMinute((int) config('knowbase.widget.rate_limits.per_visitor'))
                    ->by('widget-visitor:'.$key.'|'.$request->ip())
                    ->response($tooMany),
                Limit::perMinute((int) config('knowbase.widget.rate_limits.per_widget'))
                    ->by('widget:'.$key)
                    ->response($tooMany),
            ];
        });
    }
}
