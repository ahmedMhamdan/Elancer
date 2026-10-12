<?php

namespace App\Http\Controllers;

use App\Actions\Contracts\PublicReviews;
use App\Models\Category;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use stdClass;

/**
 * Q08, Q17 and Q36: a member's private saved projects, saved job searches and saved freelancers.
 * Nothing here is visible to anyone else, and saving notifies no one.
 */
class SavedItemController extends Controller
{
    private const PROJECTS = 200;

    private const PROFILES = 200;

    private const SEARCHES = 25;

    /** Filter values that mean "not chosen"; they are left out of a saved search's link. */
    private const UNSET = ['q' => '', 'category' => '', 'skills' => [], 'skill_mode' => 'any', 'budget_min' => '', 'budget_max' => '', 'posted' => 'any', 'proposals' => 'any', 'status' => 'open'];

    public function projects(Request $request): Response
    {
        $user = $request->user()->id;
        $page = DB::table('saved_projects')->where('user_id', $user)->orderByDesc('id')->paginate(20, ['project_id', 'created_at']);
        $ids = $page->pluck('project_id');
        // Q73: closed and hired work stays readable; a project that left public pages shows no content here either.
        $projects = Project::query()->visible()->with(['category', 'skills'])->withCount(['proposals as proposals_received' => fn (Builder $q) => $q->whereNotNull('submitted_at')])
            ->whereIn('id', $ids)->get()->keyBy('id');
        $proposals = Proposal::query()->where('user_id', $user)->whereIn('project_id', $ids)->get(['id', 'project_id', 'status'])->keyBy('project_id');

        return Inertia::render('saved/projects', ['saves' => $page->through(function (stdClass $save) use ($projects, $proposals): array {
            $project = $projects->get($save->project_id);

            return [
                'id' => $save->project_id, 'saved_at' => Carbon::parse($save->created_at)->toIso8601String(),
                'project' => $project === null ? null : [...ProjectDiscoveryController::summary($project), 'hired' => $project->status === 'hired'],
                'proposal' => $project === null ? null : $proposals->get($save->project_id)?->only(['id', 'status']),
            ];
        })]);
    }

    public function saveProject(Request $request, Project $project): RedirectResponse
    {
        $this->eligible($request);
        abort_unless(Project::query()->visible()->whereKey($project->id)->exists(), 404);
        abort_if($project->user_id === $request->user()->id, 403);
        $this->save('saved_projects', 'project_id', $project->id, $request->user()->id, self::PROJECTS, __('You can save up to :count projects. Remove one to save another.', ['count' => self::PROJECTS]));

        return back();
    }

    public function unsaveProject(Request $request, int $project): RedirectResponse
    {
        DB::table('saved_projects')->where('user_id', $request->user()->id)->where('project_id', $project)->delete();

        return back();
    }

    public function freelancers(Request $request): Response
    {
        $page = DB::table('saved_profiles')->where('user_id', $request->user()->id)->orderByDesc('id')->paginate(20, ['profile_id', 'created_at']);
        $profiles = Profile::query()->publiclyVisible()->with(['user', 'skillTags'])->whereIn('id', $page->pluck('profile_id'))->get()->keyBy('id');
        $records = PublicReviews::records($profiles->map(fn (Profile $profile): int => $profile->user_id)->all(), 'freelancer');

        return Inertia::render('saved/freelancers', ['saves' => $page->through(function (stdClass $save) use ($profiles, $records): array {
            $profile = $profiles->get($save->profile_id);

            return ['id' => $save->profile_id, 'saved_at' => Carbon::parse($save->created_at)->toIso8601String(),
                // A profile that is no longer public shows none of its earlier details.
                'freelancer' => $profile === null ? null : [...$profile->publicDetails(), 'rating' => $records[$profile->user_id]['rating']]];
        })]);
    }

    public function saveFreelancer(Request $request, Profile $profile): RedirectResponse
    {
        $this->eligible($request);
        abort_unless(Profile::query()->publiclyVisible()->whereKey($profile->id)->exists(), 404);
        abort_if($profile->user_id === $request->user()->id, 403);
        $this->save('saved_profiles', 'profile_id', $profile->id, $request->user()->id, self::PROFILES, __('You can save up to :count freelancers. Remove one to save another.', ['count' => self::PROFILES]));

        return back();
    }

    public function unsaveFreelancer(Request $request, int $profile): RedirectResponse
    {
        DB::table('saved_profiles')->where('user_id', $request->user()->id)->where('profile_id', $profile)->delete();

        return back();
    }

    public function searches(Request $request): Response
    {
        $page = DB::table('saved_searches')->where('user_id', $request->user()->id)->orderByDesc('id')->paginate(25, ['id', 'name', 'filters']);
        $stored = $page->getCollection()->mapWithKeys(fn (stdClass $search) => [$search->id => json_decode($search->filters, true)]);
        $categories = Category::query()->whereIn('slug', $stored->pluck('category')->filter()->unique())->pluck('categoryname', 'slug');
        $skills = Skill::query()->whereIn('id', $stored->pluck('skills')->flatten()->unique())->pluck('name', 'id');

        return Inertia::render('saved/searches', ['searches' => $page->through(function (stdClass $search) use ($stored, $categories, $skills): array {
            /** @var array{category: string, skills: list<int>} $filters */
            $filters = $stored[$search->id];
            $names = array_values(array_filter(array_map(fn (int $id) => $skills->get($id), $filters['skills'])));
            // /jobs refuses a category or skill that no longer exists, so such a search is not offered as a link.
            $available = ($filters['category'] === '' || $categories->has($filters['category'])) && count($names) === count($filters['skills']);

            return [
                'id' => $search->id, 'name' => $search->name, 'available' => $available,
                'href' => $available ? '/jobs?'.http_build_query(array_filter($filters, fn (mixed $value, string $key) => ! array_key_exists($key, self::UNSET) || $value !== self::UNSET[$key], ARRAY_FILTER_USE_BOTH)) : null,
                'filters' => [...$filters, 'category' => $categories->get($filters['category']), 'skills' => $names],
            ];
        })]);
    }

    public function storeSearch(Request $request): RedirectResponse
    {
        $this->eligible($request);
        $user = $request->user()->id;
        $name = $this->name($request, $user);
        $validator = Validator::make((array) $request->input('filters'), ProjectDiscoveryController::rules());
        $data = $validator->fails() ? null : $validator->validated();
        if ($data === null || (isset($data['budget_min'], $data['budget_max']) && $data['budget_max'] < $data['budget_min'])) {
            throw ValidationException::withMessages(['name' => __('The search filters are invalid.')]);
        }
        if (DB::table('saved_searches')->where('user_id', $user)->count() >= self::SEARCHES) {
            throw ValidationException::withMessages(['name' => __('You can keep up to :count saved searches. Delete one to save another.', ['count' => self::SEARCHES])]);
        }
        DB::table('saved_searches')->insert(['user_id' => $user, 'name' => $name, 'filters' => json_encode(ProjectDiscoveryController::normalise($data)), 'created_at' => now(), 'updated_at' => now()]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Search saved.')]);

        return back();
    }

    public function renameSearch(Request $request, int $search): RedirectResponse
    {
        $user = $request->user()->id;
        abort_unless(DB::table('saved_searches')->where('id', $search)->where('user_id', $user)->exists(), 404);
        DB::table('saved_searches')->where('id', $search)->where('user_id', $user)->update(['name' => $this->name($request, $user, $search), 'updated_at' => now()]);

        return back();
    }

    public function deleteSearch(Request $request, int $search): RedirectResponse
    {
        abort_unless(DB::table('saved_searches')->where('id', $search)->where('user_id', $request->user()->id)->delete() > 0, 404);

        return back();
    }

    /** Q64: a suspended member keeps and prunes their lists but starts nothing new. */
    private function eligible(Request $request): void
    {
        abort_unless($request->user()->canParticipateInMarketplace(), 403);
    }

    private function save(string $table, string $column, int $target, int $user, int $limit, string $full): void
    {
        if (DB::table($table)->where('user_id', $user)->where($column, $target)->exists()) {
            return;
        }
        if (DB::table($table)->where('user_id', $user)->count() >= $limit) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $full]);

            return;
        }
        DB::table($table)->insertOrIgnore(['user_id' => $user, $column => $target, 'created_at' => now()]);
    }

    private function name(Request $request, int $user, ?int $except = null): string
    {
        return $request->validate(['name' => ['required', 'string', 'max:80', Rule::unique('saved_searches', 'name')->where('user_id', $user)->ignore($except)]],
            ['name.unique' => __('You already have a saved search with this name.')])['name'];
    }
}
