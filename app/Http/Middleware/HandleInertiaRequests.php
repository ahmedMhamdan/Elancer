<?php

namespace App\Http\Middleware;

use App\Actions\Auth\DemoAccess;
use App\Actions\Conversations\ConversationThread;
use App\Actions\Demo\DemoWorld;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $avatar = $user?->profile?->photo_path !== null
            ? route('profile.photo', ['v' => hash('sha256', $user->profile->photo_path)])
            : null;

        $demo = DemoAccess::active($request);

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'locale' => app()->getLocale(),
            'auth' => [
                // A demo session reads in the visitor's language, not the shared account's saved one.
                'user' => $user === null ? null : [...$user->attributesToArray(), ...($demo ? ['locale' => app()->getLocale()] : []), 'avatar' => $avatar, 'has_password' => $user->password !== null],
                'demo' => $demo,
                // Plan 027: whether this demo session may change things, and the other prepared account it can move to.
                'demoActing' => $demo && DemoWorld::acting($request),
                'demoOther' => $demo ? DemoAccess::other($user) : null,
            ],
            // The recipient's own count only; the list loads when the bell opens. Counted when the page is answered, after
            // the controller, so opening a conversation (which reads it, plan 032 part 13) shows at once.
            'notifications' => fn () => ['unread' => $user?->unreadNotifications()->count() ?? 0],
            // Plan 032: the count beside Messages in the public bar ("messages" is a page prop of the conversation pages).
            'inbox' => fn () => ['unread' => $user === null ? 0 : ConversationThread::unreadConversations($user->id)],
            'realtime' => $user === null ? null : $this->realtime(),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * Public connection details for the member's live-update channel; null when broadcasting is off.
     *
     * @return array<string, mixed>|null
     */
    private function realtime(): ?array
    {
        $driver = config('broadcasting.default');
        $connection = config('broadcasting.connections.'.$driver);
        if (! in_array($driver, ['reverb', 'pusher'], true) || blank($connection['key'] ?? null)) {
            return null;
        }
        $reverb = $driver === 'reverb';

        return ['driver' => $driver, 'key' => $connection['key'], 'host' => $reverb ? $connection['options']['host'] : null,
            'port' => $reverb ? (int) $connection['options']['port'] : null, 'secure' => $connection['options']['scheme'] === 'https',
            'cluster' => $reverb ? null : $connection['options']['cluster']];
    }
}
