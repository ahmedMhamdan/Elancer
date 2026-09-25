<?php

namespace App\Http\Controllers;

use App\Actions\Invitations\InvitationLifecycle;
use App\Models\Conversation;
use App\Models\Invitation;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ContactBlockController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('invitations/blocked', ['blocks' => DB::table('user_blocks')->join('users', 'users.id', '=', 'user_blocks.blocked_user_id')
            ->where('user_blocks.user_id', $request->user()->id)->orderByDesc('user_blocks.id')->paginate(20, ['user_blocks.id', 'users.name', 'user_blocks.created_at'])]);
    }

    public function store(Request $request, Profile $profile): RedirectResponse
    {
        abort_unless(Profile::query()->publiclyVisible()->whereKey($profile->id)->exists(), 404);
        abort_if($profile->user_id === $request->user()->id, 403);

        return $this->block($request, $profile->user_id);
    }

    public function fromInvitation(Request $request, Invitation $invitation): RedirectResponse
    {
        $owner = $invitation->project->user_id;
        abort_unless($request->user()->id === $owner || $request->user()->id === $invitation->recipient_id, 404);

        return $this->block($request, $request->user()->id === $owner ? $invitation->recipient_id : $owner);
    }

    public function fromConversation(Request $request, Conversation $conversation): RedirectResponse
    {
        abort_unless($conversation->contains($request->user()->id), 404);

        return $this->block($request, $request->user()->id === $conversation->client_id ? $conversation->freelancer_id : $conversation->client_id);
    }

    private function block(Request $request, int $target): RedirectResponse
    {
        DB::transaction(function () use ($request, $target): void {
            User::query()->whereIn('id', [$request->user()->id, $target])->orderBy('id')->lockForUpdate()->get();
            DB::table('user_blocks')->insertOrIgnore(['user_id' => $request->user()->id, 'blocked_user_id' => $target, 'created_at' => now()]);
            $invitations = Invitation::query()->where('status', 'pending')->where(function ($q) use ($request, $target): void {
                $q->where(fn ($q) => $q->where('recipient_id', $request->user()->id)->whereHas('project', fn ($p) => $p->where('user_id', $target)))
                    ->orWhere(fn ($q) => $q->where('recipient_id', $target)->whereHas('project', fn ($p) => $p->where('user_id', $request->user()->id)));
            })->orderBy('id')->lockForUpdate()->get();
            foreach ($invitations as $invitation) {
                $invitation->forceFill(['status' => 'cancelled', 'version' => $invitation->version + 1])->save();
                InvitationLifecycle::event($invitation, 'cancelled');
            }
        }, 3);

        return to_route('contacts.blocked');
    }

    public function destroy(Request $request, int $block): RedirectResponse
    {
        $deleted = DB::table('user_blocks')->where('id', $block)->where('user_id', $request->user()->id)->delete();
        abort_unless($deleted > 0, 404);

        return back();
    }
}
