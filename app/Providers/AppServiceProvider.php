<?php

namespace App\Providers;

use App\Events\WorkspaceSignal;
use App\Http\Middleware\ThrottleRequestsPerRoute;
use App\Jobs\SendMessageDigest;
use App\Models\User;
use App\Notifications\WorkspaceEvent;
use App\Notifications\WorkspaceEventMail;
use Carbon\CarbonImmutable;
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
