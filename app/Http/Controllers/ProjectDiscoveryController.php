<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Project;
use App\Models\ProjectClarification;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectDiscoveryController extends Controller
{
    /** Q75 received-proposal presets (plan 003 section 15): the lowest count and, where bounded, the first count above the range. */
    private const RECEIVED = ['0-4' => [0, 5], '5-9' => [5, 10], '10-19' => [10, 20], '20' => [20, null]];

    public function filters(): JsonResponse
    {
        return response()->json([
            'categories' => Category::query()->orderBy('categoryname')->get(['id', 'slug', 'categoryname']),
            'skills' => Skill::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

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
            'skill_mode' => ['nullable', Rule::in(['any', 'all'])],
            'budget_min' => ['nullable', 'numeric', 'min:1', 'max:1000000', 'decimal:0,2'],
            'budget_max' => ['nullable', 'numeric', 'min:1', 'max:1000000', 'decimal:0,2'],
            'posted' => ['nullable', Rule::in(['any', '1', '7', '30'])],
            'proposals' => ['nullable', Rule::in(['any', ...array_keys(self::RECEIVED)])],
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
            'skills' => array_map('intval', $data['skills'] ?? []), 'skill_mode' => $data['skill_mode'] ?? 'any',
            'budget_min' => $data['budget_min'] ?? '', 'budget_max' => $data['budget_max'] ?? '',
            'posted' => $data['posted'] ?? 'any', 'proposals' => $data['proposals'] ?? 'any', 'status' => $data['status'] ?? 'open',
            'sort' => $data['sort'] ?? ($profileSkills ? 'match' : 'newest'),
        ];
        // Q60: the public number counts submitted proposals only; the Q75 filter below uses the same count.
        $received = fn (Builder $q) => $q->whereNotNull('submitted_at');
        $query = Project::query()->visible()->with(['category', 'skills'])->withCount(['proposals as proposals_received' => $received]);
        if ($filters['q'] !== '') {
            $pattern = $this->pattern($filters['q']);
            $query->where(fn (Builder $q) => $q->whereRaw("LOWER(title) LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("LOWER(description) LIKE ? ESCAPE '!'", [$pattern]));
        }
        if ($filters['category'] !== '') {
            $query->whereHas('category', fn (Builder $q) => $q->where('slug', $filters['category']));
        }
        // Q58: any selected skill matches unless the All switch requires every one.
        if ($filters['skill_mode'] === 'all') {
            foreach ($filters['skills'] as $id) {
                $query->whereHas('skills', fn (Builder $q) => $q->where('skills.id', $id));
            }
        } elseif ($filters['skills']) {
            $query->whereHas('skills', fn (Builder $q) => $q->whereIn('skills.id', $filters['skills']));
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
        if ($filters['proposals'] !== 'any') {
            [$from, $below] = self::RECEIVED[$filters['proposals']];
            if ($from > 0) {
                $query->whereHas('proposals', $received, '>=', $from);
            }
            if ($below !== null) {
                $query->whereHas('proposals', $received, '<', $below);
            }
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
        // Q64: a suspended client's projects leave public pages, but the client still reads their own brief.
        $own = $request->user()?->id === $project->user_id && $request->user()->keepsExistingAccess()
            && in_array($project->status, ['published', 'closed', 'hired'], true) && $project->category !== null;
        abort_unless($own || Project::query()->visible()->whereKey($project->id)->exists(), 404);
        $project->load(['category', 'skills', 'user.profile']);
        $project->loadCount(['proposals as proposals_received' => fn (Builder $q) => $q->whereNotNull('submitted_at')]);
        $clarifications = $project->clarifications()->orderBy('id')->get();
        // Q06/Q61: the owner changes a published project only while it is still hiring and not hidden.
        $changeable = $request->user()?->id === $project->user_id && $request->user()->canParticipateInMarketplace()
            && $project->status === 'published' && $project->moderated_at === null;

        return Inertia::render('discovery/job', [
            'project' => [...$this->summary($project), 'description' => $project->description, 'screening_questions' => $project->screening_questions ?? []],
            // Q61: dated public notes under the brief, oldest first; the brief itself is never rewritten.
            'clarifications' => $clarifications->map(fn (ProjectClarification $note) => ['id' => $note->id, 'body' => $note->body, 'created_at' => $note->created_at->toIso8601String()]),
            'can' => ['extend' => $changeable, 'clarify' => $changeable && $clarifications->count() < ProjectClarification::LIMIT],
            // Only the owner can be here while moderation hides the project.
            'moderated' => $project->moderated_at !== null,
            'returnUrl' => '/jobs'.(is_string($request->query('search')) && strlen($request->query('search')) <= 4000 && $request->query('search') !== '' ? '?'.$request->query('search') : ''),
            'application' => [
                'owner' => $request->user()?->id === $project->user_id,
                'proposal' => $request->user() ? $project->proposals()->where('user_id', $request->user()->id)->first()?->only(['id', 'status']) : null,
            ],
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
            'proposals_received' => (int) $project->getAttribute('proposals_received'),
            // A project hidden by moderation takes no new applications, so its owner is not told it is open.
            'open' => $project->moderated_at === null && $project->status === 'published' && $project->application_closes_at?->isFuture(),
        ];
    }

    private function pattern(string $text): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($text)).'%';
    }
}
