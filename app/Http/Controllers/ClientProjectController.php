<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Project;
use App\Models\Skill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ClientProjectController extends Controller
{
    private function eligible(Request $request): void
    {
        abort_unless($request->user()?->canParticipateInMarketplace() && $request->user()->onboarding_completed_at !== null, 403);
    }

    private function owner(Request $request, Project $project): void
    {
        $this->eligible($request);
        abort_unless($project->user_id === $request->user()->id, 404);
    }

    public function index(Request $request): Response
    {
        $this->eligible($request);
        $trash = $request->query('status') === 'trash';
        $query = Project::query()->where('user_id', $request->user()->id);
        if ($trash) {
            $query->onlyTrashed();
        }

        return Inertia::render('projects/index', ['projects' => $query->orderByDesc('updated_at')->orderByDesc('id')->paginate(20)->withQueryString(), 'trash' => $trash]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->eligible($request);
        $project = new Project;
        $project->forceFill(['user_id' => $request->user()->id, 'application_closes_at' => now()->addDays(7)])->save();

        return to_route('projects.edit', $project);
    }

    public function edit(Request $request, Project $project): Response
    {
        $this->owner($request, $project);
        abort_unless($project->status === 'draft', 409);
        $project->load('skills');

        return Inertia::render('projects/edit', ['project' => $project, 'categories' => Category::query()->orderBy('categoryname')->get(['id', 'categoryname']), 'skills' => Skill::query()->orderBy('name')->get(['id', 'name'])]);
    }

    /** @return array<string, mixed> */
    private function fields(Request $request, bool $publish): array
    {
        $presence = $publish ? 'required' : 'nullable';
        $rules = [
            'version' => ['required', 'integer', 'min:1'],
            'title' => [$presence, 'string', 'max:160'],
            'description' => [$presence, 'string', 'max:20000'],
            'category_id' => [$presence, 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'skills' => [$presence, 'array', 'list', 'max:15'],
            'skills.*' => ['required', 'integer', 'distinct', Rule::exists('skills', 'id')],
            'budget_min' => [$presence, 'numeric', 'min:1', 'max:1000000', 'decimal:0,2'],
            'budget_max' => [$presence, 'numeric', 'min:1', 'max:1000000', 'decimal:0,2'],
            'application_closes_at' => [$presence, 'date'],
            'screening_questions' => ['nullable', 'array', 'list', 'max:3'],
            'screening_questions.*' => [$publish ? 'required' : 'nullable', 'string', 'max:300'],
            'user_id' => ['missing'], 'status' => ['missing'], 'published_at' => ['missing'],
        ];
        if ($publish) {
            $rules['title'][] = 'min:5';
            $rules['description'][] = 'min:50';
            $rules['skills'][] = 'min:1';
            $rules['application_closes_at'][] = 'after:now';
            $rules['screening_questions.*'][] = 'min:5';
        }
        $validator = Validator::make($request->all(), $rules, ['application_closes_at.after' => __('Choose a future application cutoff.')]);
        $validator->after(function ($validator) use ($request): void {
            if ($request->filled(['budget_min', 'budget_max']) && is_numeric($request->input('budget_min')) && is_numeric($request->input('budget_max')) && (float) $request->input('budget_max') < (float) $request->input('budget_min')) {
                $validator->errors()->add('budget_max', __('The maximum budget must be at least the minimum budget.'));
            }
        });

        return $validator->validate();
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        return $this->save($request, $project, false);
    }

    public function publish(Request $request, Project $project): JsonResponse
    {
        return $this->save($request, $project, true);
    }

    private function save(Request $request, Project $project, bool $publish): JsonResponse
    {
        $this->owner($request, $project);
        $data = $this->fields($request, $publish);

        return DB::transaction(function () use ($request, $project, $data, $publish): JsonResponse {
            $locked = Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();
            $this->owner($request, $locked);
            if ($locked->status !== 'draft' || $locked->version !== (int) $data['version']) {
                return response()->json(['conflict' => true, 'project' => $locked->load('skills')], 409);
            }
            $skills = $data['skills'] ?? [];
            $data['screening_questions'] = array_map(fn ($question) => $question ?? '', $data['screening_questions'] ?? []);
            unset($data['skills'], $data['version']);
            $locked->forceFill($data);
            $locked->version++;
            if ($publish) {
                $locked->status = 'published';
                $locked->published_at = now();
            }
            $locked->save();
            $locked->skills()->sync($skills);

            return response()->json(['project' => $locked->load('skills'), 'url' => $publish ? route('jobs.show', $locked) : null]);
        });
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        $this->owner($request, $project);
        DB::transaction(function () use ($project): void {
            $locked = Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'draft', 409);
            $locked->delete();
        });

        return to_route('projects.index');
    }

    public function restore(Request $request, Project $project): RedirectResponse
    {
        $this->owner($request, $project);
        DB::transaction(function () use ($project): void {
            $locked = Project::withTrashed()->whereKey($project->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->trashed() && $locked->status === 'draft', 409);
            $locked->restore();
        });

        return to_route('projects.index');
    }

    public function forceDestroy(Request $request, Project $project): RedirectResponse
    {
        $this->owner($request, $project);
        DB::transaction(function () use ($project): void {
            $locked = Project::withTrashed()->whereKey($project->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->trashed() && $locked->status === 'draft', 409);
            $locked->forceDelete();
        });

        return to_route('projects.index', ['status' => 'trash']);
    }
}
