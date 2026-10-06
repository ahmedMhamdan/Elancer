<?php

namespace App\Http\Controllers;

use App\Actions\Contracts\ContractWork;
use App\Models\ContractReview;
use App\Models\PortfolioCase;
use App\Models\Profile;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class FreelancerController extends Controller
{
    public function index(Request $request): Response
    {
        $validator = Validator::make($request->query(), [
            'q' => ['nullable', 'string', 'max:100'],
            'skill' => ['nullable', 'integer', Rule::exists('skills', 'id')],
            'availability' => ['nullable', Rule::in(['available', 'busy'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);
        abort_if($validator->fails(), 422, __('The search filters are invalid.'));
        $filters = $validator->validated();
        $query = Profile::query()->publiclyVisible()->with(['user', 'skillTags']);
        if (! empty($filters['q'])) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower(trim($filters['q']))).'%';
            $query->where(fn (Builder $q) => $q->whereRaw("LOWER(headline) LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("LOWER(bio) LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereHas('user', fn (Builder $u) => $u->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern])));
        }
        if (! empty($filters['skill'])) {
            $query->whereHas('skillTags', fn (Builder $q) => $q->where('skills.id', $filters['skill']));
        }
        if (! empty($filters['availability'])) {
            $query->where('availability', $filters['availability']);
        }

        return Inertia::render('discovery/freelancers', [
            'freelancers' => $query->orderByDesc('published_at')->orderBy('id')->paginate(12)->withQueryString()->through(fn (Profile $profile) => $profile->publicDetails()),
            'filters' => ['q' => $filters['q'] ?? '', 'skill' => $filters['skill'] ?? '', 'availability' => $filters['availability'] ?? ''],
            'skills' => Skill::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Request $request, Profile $profile): Response
    {
        abort_unless(Profile::query()->publiclyVisible()->whereKey($profile->id)->exists(), 404);

        // Q18/Q82: published reviews only, with the client's first name and nothing that links to an account.
        $reviews = ContractReview::query()->with('contract')->where('subject_id', $profile->user_id)->orderByDesc('id')->limit(50)->get()
            ->filter(fn (ContractReview $review) => $review->contract->freelancer_id === $profile->user_id && ContractWork::reviewsPublished($review->contract))
            ->take(10)->values()->map(fn (ContractReview $review) => [
                'id' => $review->id, 'rating' => $review->rating, 'body' => $review->body, 'created_at' => $review->created_at,
                'author' => Str::before(trim((string) ($review->contract->agreement['client_name'] ?? '')), ' '),
                'project_title' => $review->contract->agreement['project_title'] ?? null,
            ]);

        $cases = PortfolioCase::query()->publiclyVisible()->where('user_id', $profile->user_id)->orderByDesc('published_at')->orderByDesc('id')->get()->map(PortfolioController::published(...));

        return Inertia::render('discovery/freelancer', ['freelancer' => $profile->load(['user', 'skillTags'])->publicDetails(), 'canContact' => $request->user()?->id !== $profile->user_id, 'reviews' => $reviews, 'cases' => $cases]);
    }

    public function photo(Profile $profile): HttpResponse
    {
        abort_unless(Profile::query()->publiclyVisible()->whereKey($profile->id)->exists() && $profile->photo_path && Storage::disk('local')->exists($profile->photo_path), 404);

        return Storage::disk('local')->response($profile->photo_path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function publication(Request $request): RedirectResponse
    {
        $data = $request->validate(['published' => ['required', 'boolean']]);
        abort_unless($request->user()?->canParticipateInMarketplace(), 403);
        DB::transaction(function () use ($request, $data): void {
            $user = $request->user()->newQuery()->lockForUpdate()->findOrFail($request->user()->id);
            abort_unless($user->canParticipateInMarketplace(), 403);
            $profile = $user->profile()->lockForUpdate()->firstOrFail();
            if ($data['published'] && (! $user->onboarding_completed_at || ! $profile->readyForPublication())) {
                throw ValidationException::withMessages(['publication' => __('Add a headline, bio and at least one catalog skill before publishing.')]);
            }
            $profile->forceFill(['published_at' => $data['published'] ? ($profile->published_at ?? now()) : null]);
            $profile->save();
        });

        return back()->with('success', $data['published'] ? __('Your freelancer profile is public.') : __('Your freelancer profile is private.'));
    }
}
