<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Project;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectDiscoveryController extends Controller
{
    public function categories(Request $request): Response
    {
        $q = $request->query('q', '');
        abort_unless(is_string($q) && mb_strlen($q) <= 100, 422);
        $pattern = $this->pattern(trim($q));

        return Inertia::render('discovery/categories', [
            'q' => $q,
            'categories' => Category::query()->whereRaw("LOWER(categoryname) LIKE ? ESCAPE '!'", [$pattern])
                ->withCount(['projects as open_projects_count' => fn (Builder $query) => $query->whereIn('projects.id', Project::query()->visible()->where('status', 'published')->where('application_closes_at', '>', now())->select('id'))])
                ->orderBy('categoryname')->orderBy('id')->paginate(24)->withQueryString(),
        ]);
    }

    public function index(Request $request): Response
    {
        $validator = Validator::make($request->query(), [
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', Rule::exists('categories', 'slug')->whereNull('deleted_at')],
            'skills' => ['nullable', 'array', 'max:15'],
            'skills.*' => ['required', 'integer', 'distinct', Rule::exists('skills', 'id')],
            'budget_min' => ['nullable', 'numeric', 'min:1', 'max:1000000', 'decimal:0,2'],
            'budget_max' => ['nullable', 'numeric', 'min:1', 'max:1000000', 'decimal:0,2'],
            'posted' => ['nullable', Rule::in(['any', '1', '7', '30'])],
            'status' => ['nullable', Rule::in(['open', 'all'])],
            'sort' => ['nullable', Rule::in(['match', 'newest'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);
        // Invalid shared URLs must never silently broaden a search or redirect in a loop.
        abort_if($validator->fails(), 422, __('The search filters are invalid.'));
        $data = $validator->validated();
        abort_if(isset($data['budget_min'], $data['budget_max']) && $data['budget_max'] < $data['budget_min'], 422, __('The maximum budget must be at least the minimum budget.'));
        $profileSkills = $request->user()?->profile?->skillTags()->pluck('skills.id')->all() ?? [];
        $filters = [
            'q' => trim($data['q'] ?? ''), 'category' => $data['category'] ?? '',
            'skills' => array_map('intval', $data['skills'] ?? []),
            'budget_min' => $data['budget_min'] ?? '', 'budget_max' => $data['budget_max'] ?? '',
            'posted' => $data['posted'] ?? 'any', 'status' => $data['status'] ?? 'open',
            'sort' => $data['sort'] ?? ($profileSkills ? 'match' : 'newest'),
        ];
        $query = Project::query()->visible()->with(['category', 'skills']);
        if ($filters['q'] !== '') {
            $pattern = $this->pattern($filters['q']);
            $query->where(fn (Builder $q) => $q->whereRaw("LOWER(title) LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("LOWER(description) LIKE ? ESCAPE '!'", [$pattern]));
        }
        if ($filters['category'] !== '') {
            $query->whereHas('category', fn (Builder $q) => $q->where('slug', $filters['category']));
        }
        foreach ($filters['skills'] as $id) {
            $query->whereHas('skills', fn (Builder $q) => $q->where('skills.id', $id));
        }
        if ($filters['budget_min'] !== '') {
            $query->where('budget_max', '>=', $filters['budget_min']);
        }
        if ($filters['budget_max'] !== '') {
            $query->where('budget_min', '<=', $filters['budget_max']);
        }
        if ($filters['posted'] !== 'any') {
            $query->where('published_at', '>=', now()->subDays((int) $filters['posted']));
        }
        if ($filters['status'] === 'open') {
            $query->where('status', 'published')->where('application_closes_at', '>', now());
        }
        if ($filters['sort'] === 'match' && $profileSkills) {
            $query->withCount(['skills as matching_skills_count' => fn (Builder $q) => $q->whereIn('skills.id', $profileSkills)])->orderByDesc('matching_skills_count');
        }

        return Inertia::render('discovery/jobs', [
            'projects' => $query->orderByDesc('published_at')->orderByDesc('id')->paginate(20)->withQueryString()->through(fn (Project $project) => $this->summary($project)),
            'filters' => $filters,
            'categories' => Category::query()->orderBy('categoryname')->get(['id', 'slug', 'categoryname']),
            'skills' => Skill::query()->orderBy('name')->get(['id', 'name']),
            'canMatchSkills' => count($profileSkills) > 0,
        ]);
    }

    public function show(Request $request, Project $project): Response
    {
        abort_unless(Project::query()->visible()->whereKey($project->id)->exists(), 404);
        $project->load(['category', 'skills', 'user.profile']);

        return Inertia::render('discovery/job', [
            'project' => [...$this->summary($project), 'description' => $project->description, 'screening_questions' => $project->screening_questions ?? []],
            'returnUrl' => '/jobs'.(is_string($request->query('search')) && strlen($request->query('search')) <= 4000 && $request->query('search') !== '' ? '?'.$request->query('search') : ''),
            'client' => [
                'name' => $project->user->profile?->company ?: Str::before($project->user->name, ' '),
                'country' => $project->user->profile?->country,
                'member_since' => $project->user->created_at?->format('Y'),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function summary(Project $project): array
    {
        return [
            'id' => $project->id, 'title' => $project->title, 'excerpt' => Str::limit($project->description ?? '', 260),
            'category' => $project->category?->only(['categoryname', 'slug']),
            'skills' => $project->skills->map(fn (Skill $skill) => $skill->only(['id', 'name'])),
            'budget_min' => $project->budget_min, 'budget_max' => $project->budget_max,
            'published_at' => $project->published_at?->toIso8601String(),
            'application_closes_at' => $project->application_closes_at?->toIso8601String(),
            'open' => $project->status === 'published' && $project->application_closes_at?->isFuture(),
        ];
    }

    private function pattern(string $text): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($text)).'%';
    }
}
