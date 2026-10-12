<?php

namespace App\Http\Controllers;

use App\Actions\Contracts\PublicReviews;
use App\Models\Category;
use App\Models\Profile;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Q33: the homepage shows real open projects, categories and public freelancer profiles.
 * It reads through the same scopes as /jobs, /freelancers and /categories, so it can never
 * show a record those lists hide, and it publishes no field they do not.
 */
class HomeController extends Controller
{
    private const PROJECTS = 6;

    private const FREELANCERS = 6;

    private const CATEGORIES = 8;

    public function __invoke(): Response
    {
        $open = Project::query()->visible()->where('status', 'published')->where('application_closes_at', '>', now());
        $profiles = Profile::query()->publiclyVisible();
        // A profile with a photo leads; after that the most recently published.
        $freelancers = (clone $profiles)->with(['user', 'skillTags'])
            ->orderByRaw('CASE WHEN photo_path IS NULL THEN 1 ELSE 0 END')->orderByDesc('published_at')->orderBy('id')
            ->limit(self::FREELANCERS)->get();
        $records = PublicReviews::records($freelancers->map(fn (Profile $profile): int => $profile->user_id)->all(), 'freelancer');

        return Inertia::render('welcome', [
            'projects' => (clone $open)->with(['category', 'skills'])
                ->withCount(['proposals as proposals_received' => fn (Builder $query) => $query->whereNotNull('submitted_at')])
                ->orderByDesc('published_at')->orderByDesc('id')->limit(self::PROJECTS)->get()
                ->map(fn (Project $project) => Arr::except(ProjectDiscoveryController::summary($project), ['excerpt'])),
            'categories' => Category::query()->select(['id', 'slug', 'categoryname'])
                ->withCount(['projects as open_projects_count' => fn (Builder $query) => $query->whereIn('projects.id', (clone $open)->select('id'))])
                ->orderByDesc('open_projects_count')->orderBy('categoryname')->orderBy('id')->limit(self::CATEGORIES)->get(),
            'freelancers' => $freelancers->map(fn (Profile $profile) => [...Arr::except($profile->publicDetails(), ['links']),
                'bio' => Str::limit($profile->bio ?? '', 180), 'rating' => $records[$profile->user_id]['rating']]),
            'totals' => ['projects' => (clone $open)->count(), 'freelancers' => (clone $profiles)->count(), 'categories' => Category::query()->count()],
        ]);
    }
}
