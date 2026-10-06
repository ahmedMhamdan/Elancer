<?php

namespace App\Providers;

use App\Events\WorkspaceSignal;
use App\Models\User;
use App\Notifications\WorkspaceEvent;
use App\Notifications\WorkspaceEventMail;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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
        // Every persisted notification also nudges the recipient's open pages.
        Event::listen(function (NotificationSent $event): void {
            if ($event->notification instanceof WorkspaceEvent && $event->channel === 'database' && $event->notifiable instanceof User) {
                WorkspaceSignal::send($event->notifiable->id, $event->notification->id, $event->notification->conversation);
                // Q30: email follows the recipient's category preference and never blocks the action.
                $stored = $event->notification;
                $recipient = $event->notifiable;
                if ($recipient->hasVerifiedEmail() && $recipient->hasDeliverableEmail() && $recipient->emailPreferences()[$stored->category()]) {
                    rescue(fn () => $recipient->notify(new WorkspaceEventMail($stored->kind, $stored->href, $stored->title, $stored->actor)));
                }
            }
        });
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
