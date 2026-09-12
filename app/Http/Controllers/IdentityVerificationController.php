<?php

namespace App\Http\Controllers;

use App\Models\IdentityVerification;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class IdentityVerificationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canParticipateInMarketplace(), 403);
        $request->validate([
            'government_id' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048', 'dimensions:max_width=6000,max_height=6000'],
            'selfie' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048', 'dimensions:max_width=6000,max_height=6000'],
            'consent' => ['accepted'],
            'status' => ['missing'], 'user_id' => ['missing'],
        ]);
        $paths = [];
        try {
            DB::transaction(function () use ($request, &$paths): void {
                User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                $verification = IdentityVerification::where('user_id', $request->user()->id)->lockForUpdate()->first();
                abort_if($verification && $verification->status !== 'rejected', 409, 'This submission is already pending or approved.');
                $data = ['user_id' => $request->user()->id, 'status' => 'pending', 'reason' => null, 'reviewed_by' => null, 'reviewed_at' => null, 'consented_at' => now()];
                foreach (['government_id' => 'id', 'selfie' => 'selfie'] as $field => $key) {
                    $file = $request->file($field);
                    $path = Str::uuid().'.enc';
                    Storage::disk('identity')->put($path, Crypt::encryptString($file->getContent()));
                    $paths[] = $path;
                    $data[$key.'_path'] = $path;
                    $data[$key.'_mime'] = $file->getMimeType();
                }
                $oldPaths = $verification ? array_filter([$verification->id_path, $verification->selfie_path]) : [];
                ($verification ?? new IdentityVerification)->forceFill($data)->save();
                if ($oldPaths) {
                    Storage::disk('identity')->delete($oldPaths);
                }
            });
        } catch (\Throwable $error) {
            Storage::disk('identity')->delete($paths);
            throw $error;
        }

        return to_route('marketplace-profile.edit');
    }

    public function index(): Response
    {
        return Inertia::render('admin/identity/index', [
            'submissions' => IdentityVerification::query()->join('users', 'users.id', '=', 'identity_verifications.user_id')
                ->select('identity_verifications.id', 'identity_verifications.status', 'identity_verifications.reason', 'identity_verifications.created_at', 'identity_verifications.updated_at', 'users.name', 'users.email')
                ->orderByRaw("case when identity_verifications.status = 'pending' then 0 else 1 end")
                ->orderByDesc('identity_verifications.updated_at')->paginate(10),
        ]);
    }

    public function image(IdentityVerification $verification, string $kind): HttpResponse
    {
        abort_unless($verification->status === 'pending' && in_array($kind, ['id', 'selfie'], true), 404);
        $path = $kind === 'id' ? $verification->id_path : $verification->selfie_path;
        abort_unless($path && Storage::disk('identity')->exists($path), 404);

        return response(Crypt::decryptString(Storage::disk('identity')->get($path)), 200, [
            'Content-Type' => $kind === 'id' ? $verification->id_mime : $verification->selfie_mime,
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache', 'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline', 'Referrer-Policy' => 'no-referrer',
        ]);
    }

    public function review(Request $request, IdentityVerification $verification): RedirectResponse
    {
        abort_if($verification->user_id === $request->user()->id, 403, 'You cannot review your own identity.');
        $data = $request->validate(['status' => ['required', 'in:approved,rejected'], 'reason' => ['required', 'string', 'max:1000']]);
        DB::transaction(function () use ($verification, $data, $request): void {
            $locked = IdentityVerification::lockForUpdate()->findOrFail($verification->id);
            abort_unless($locked->status === 'pending', 409);
            $locked->forceFill($data + ['reviewed_by' => $request->user()->id, 'reviewed_at' => now()])->save();
            Storage::disk('identity')->delete(array_filter([$locked->id_path, $locked->selfie_path]));
            $locked->forceFill(['id_path' => null, 'selfie_path' => null])->save();
        });

        return to_route('admin.identity.index');
    }
}
