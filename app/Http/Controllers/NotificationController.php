<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'notifications' => $user->notifications()->limit(20)->get()->map(fn (DatabaseNotification $notification) => [
                'id' => $notification->id, 'kind' => $notification->data['kind'] ?? null, 'title' => $notification->data['title'] ?? null,
                'actor' => $notification->data['actor'] ?? null, 'read' => $notification->read_at !== null,
                'created_at' => $notification->created_at?->toISOString(),
            ]),
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
}
