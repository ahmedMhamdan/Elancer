<?php

namespace App\Providers;

use App\Events\WorkspaceSignal;
use App\Http\Middleware\ThrottleRequestsPerRoute;
use App\Http\Responses\OAuthConsentPage;
use App\Jobs\SendMessageDigest;
use App\Mcp\AgentAccess;
use App\Models\User;
use App\Notifications\WorkspaceEvent;
use App\Notifications\WorkspaceEventMail;
use Carbon\CarbonImmutable;
use DateInterval;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\Passport;
use Laravel\Sanctum\PersonalAccessToken;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Plain throttle:N,M rules count per route instead of sharing one counter per member.
        $this->app->bind(ThrottleRequests::class, ThrottleRequestsPerRoute::class);
        // Plan 033: routes/ai.php registers the part of Passport the Connect flow uses; no device codes.
        Passport::ignoreRoutes();
        Passport::$deviceCodeGrantEnabled = false;
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        // Every persisted notification also nudges the recipient's open pages.
        Event::listen(function (NotificationSent $event): void {
            if ($event->notification instanceof WorkspaceEvent && $event->channel === 'database' && $event->notifiable instanceof User) {
                WorkspaceSignal::send($event->notifiable->id, $event->notification->id, $event->notification->conversation);
                // Q30: email follows the recipient's category preference and never blocks the action.
                $stored = $event->notification;
                $recipient = $event->notifiable;
                if ($recipient->hasVerifiedEmail() && $recipient->hasDeliverableEmail() && $recipient->emailPreferences()[$stored->category()]) {
                    // Plan 031: a conversation's email waits, so it can stand for every message sent meanwhile or be dropped once read.
                    if ($stored->kind === 'message_received' && $stored->conversation !== null) {
                        rescue(fn () => SendMessageDigest::schedule($recipient->id, $stored->conversation, (string) $stored->id));
                    } else {
                        rescue(fn () => $recipient->notify(new WorkspaceEventMail($stored->kind, $stored->href, $stored->title, $stored->actor)));
                    }
                }
            }
        });
        // Plan 028: the API counts by token, so one program cannot use up another's allowance.
        foreach (['api-read' => 60, 'api-write' => 20] as $name => $perMinute) {
            RateLimiter::for($name, function (Request $request) use ($name, $perMinute) {
                $token = $request->user()?->currentAccessToken();

                return Limit::perMinute($perMinute)->by($name.':'.($token instanceof PersonalAccessToken ? $token->getKey() : $request->ip()));
            });
        }
        // Plan 033: an agent's calls count by its token, as the API's do; signing in counts by address.
        RateLimiter::for('mcp', fn (Request $request) => Limit::perMinute(60)->by('mcp:'.($request->user() instanceof User ? AgentAccess::key($request->user()) : $request->ip())));
        RateLimiter::for('oauth-register', fn (Request $request) => $request->isMethod('post')
            ? Limit::perMinute(10)->by('oauth-register:'.$request->ip()) : Limit::perMinute(60)->by('oauth-discovery:'.$request->ip()));
        RateLimiter::for('oauth-token', fn (Request $request) => Limit::perMinute(30)->by('oauth-token:'.$request->ip()));
        // Access tokens are short; the refresh token renews them while the app is used.
        Passport::tokensExpireIn(new DateInterval('PT1H'));
        Passport::refreshTokensExpireIn(new DateInterval('P30D'));
        Passport::authorizationView(OAuthConsentPage::render(...));
        // laravel/mcp's one scope is the only one there is, so an app that asks for none still gets it.
        Passport::setDefaultScope([Registrar::OAUTH_SCOPE]);
        RateLimiter::for('profile-photos', function (Request $request) {
            if (! $request->hasFile('photo')) {
                return Limit::none();
            }
            $user = $request->user()->id ?? $request->ip();

            return [Limit::perMinute(1)->by('photo-minute:'.$user), Limit::perHour(3)->by('photo-hour:'.$user)];
        });
        // Each portfolio image costs one content check, like a profile photo.
        RateLimiter::for('portfolio-images', function (Request $request) {
            $user = $request->user()->id ?? $request->ip();

            return [Limit::perMinute(6)->by('portfolio-image-minute:'.$user), Limit::perHour(30)->by('portfolio-image-hour:'.$user)];
        });
        // A report reaches a person, so a member cannot flood the queue.
        RateLimiter::for('reports', function (Request $request) {
            $user = $request->user()->id ?? $request->ip();

            return [Limit::perMinute(5)->by('report-minute:'.$user), Limit::perDay(30)->by('report-day:'.$user)];
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
