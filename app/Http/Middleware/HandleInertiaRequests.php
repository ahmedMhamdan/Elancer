<?php

namespace App\Http\Middleware;

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

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'locale' => app()->getLocale(),
            'auth' => [
                'user' => $user === null ? null : [...$user->attributesToArray(), 'avatar' => $avatar, 'has_password' => $user->password !== null],
            ],
            // The recipient's own count only; the list loads when the bell opens.
            'notifications' => ['unread' => $user?->unreadNotifications()->count() ?? 0],
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
