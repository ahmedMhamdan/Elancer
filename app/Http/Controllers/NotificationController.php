<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    /** The bell asks for JSON; a page visit gets the full paginated list. */
    public function index(Request $request): JsonResponse|Response
    {
        $user = $request->user();
        if (! $request->expectsJson()) {
            return Inertia::render('notifications/index', ['items' => $user->notifications()->paginate(20)->through($this->item(...))]);
        }

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'notifications' => $user->notifications()->limit(20)->get()->map($this->item(...)),
        ]);
    }

    /** Marks the recipient's own notification read and opens its destination. */
    public function open(Request $request, string $notification): RedirectResponse
    {
        $record = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $record->markAsRead();
        $href = $record->data['href'] ?? null;

        // Destinations are written by the server; still refuse anything that is not a local path.
        return redirect(is_string($href) && preg_match('#^/[a-z0-9]#i', $href) ? $href : route('dashboard'));
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }

    public function preferences(Request $request): Response
    {
        return Inertia::render('settings/notifications', ['preferences' => $request->user()->emailPreferences()]);
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $data = $request->validate(['contracts' => ['required', 'boolean'], 'hiring' => ['required', 'boolean'], 'messages' => ['required', 'boolean']]);
        $request->user()->forceFill(['email_preferences' => array_map(boolval(...), $data)])->save();

        return back();
    }

    /** @return array<string, mixed> */
    private function item(DatabaseNotification $notification): array
    {
        return ['id' => $notification->id, 'kind' => $notification->data['kind'] ?? null, 'title' => $notification->data['title'] ?? null,
            'actor' => $notification->data['actor'] ?? null, 'read' => $notification->read_at !== null,
            'created_at' => $notification->created_at?->toISOString()];
    }
}
