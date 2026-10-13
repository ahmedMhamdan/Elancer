<?php

namespace App\Http\Controllers;

use App\Models\Skill;
use App\Models\User;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Plan 026: administrators keep the skill catalog members choose from. A skill is retired, never
 * deleted, so every profile and project that carries it stays readable.
 */
class AdminSkillController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', Rule::in(['active', 'retired', 'all'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);
        $search = trim($filters['q'] ?? '');
        $status = $filters['status'] ?? 'active';
        $query = Skill::query()->withCount(['profiles', 'projects']);
        if ($search !== '') {
            // Literal substring search: SQL wildcards typed by the administrator are not patterns.
            $query->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($search)).'%']);
        }
        if ($status !== 'all') {
            $status === 'active' ? $query->whereNull('retired_at') : $query->whereNotNull('retired_at');
        }
        $events = DB::table('moderation_events')->where('target_type', 'skill')->orderByDesc('id')->limit(10)->get();
        $people = User::query()->whereIn('id', $events->pluck('actor_id')->filter()->unique())->pluck('name', 'id');
        $names = Skill::query()->whereIn('id', $events->pluck('target_id'))->pluck('name', 'id');

        return Inertia::render('admin/skills/index', [
            'skills' => $query->orderBy('name')->paginate(20)->withQueryString()->through(fn (Skill $skill) => [
                'id' => $skill->id, 'name' => $skill->name, 'retired' => $skill->retired_at !== null,
                'profiles' => (int) $skill->getAttribute('profiles_count'), 'projects' => (int) $skill->getAttribute('projects_count'),
            ]),
            'search' => $search,
            'status' => $status,
            'events' => $events->map(fn ($event) => ['id' => $event->id, 'action' => $event->action, 'actor' => $people->get($event->actor_id),
                'skill' => $names->get($event->target_id), 'previous' => $event->reason, 'created_at' => Carbon::parse((string) $event->created_at)->toIso8601String()]),
            'notice' => $request->session()->get('skill_notice'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/skills/form', ['skill' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $name = $this->name($request, null);
        $skill = new Skill;
        $this->write($skill, ['name' => $name]);
        $this->record($request, 'skill_created', $skill);

        return to_route('admin.skills.index')->with('skill_notice', 'created');
    }

    public function edit(Skill $skill): Response
    {
        return Inertia::render('admin/skills/form', ['skill' => $skill->only(['id', 'name'])]);
    }

    public function update(Request $request, Skill $skill): RedirectResponse
    {
        $name = $this->name($request, $skill);
        $previous = $skill->name;
        if ($name !== $previous) {
            $this->write($skill, ['name' => $name]);
            $this->record($request, 'skill_renamed', $skill, $previous);
        }

        return to_route('admin.skills.index')->with('skill_notice', 'renamed');
    }

    public function destroy(Request $request, Skill $skill): RedirectResponse
    {
        if ($skill->retired_at === null) {
            $skill->forceFill(['retired_at' => now()])->save();
            $this->record($request, 'skill_retired', $skill);
        }

        return back(303, [], route('admin.skills.index'))->with('skill_notice', 'retired');
    }

    public function restore(Request $request, Skill $skill): RedirectResponse
    {
        if ($skill->retired_at !== null) {
            $skill->forceFill(['retired_at' => null])->save();
            $this->record($request, 'skill_restored', $skill);
        }

        return back(303, [], route('admin.skills.index'))->with('skill_notice', 'restored');
    }

    /** One name per skill whatever the letter case, on every database. */
    private function name(Request $request, ?Skill $skill): string
    {
        $request->merge(['name' => is_string($request->input('name')) ? trim((string) preg_replace('/\s+/u', ' ', $request->input('name'))) : $request->input('name')]);
        $taken = __('A skill with this name already exists.');
        $data = $request->validate(['name' => ['bail', 'required', 'string', 'min:2', 'max:50', 'not_regex:/\p{C}/u',
            function (string $attribute, string $value, Closure $fail) use ($skill, $taken): void {
                if (Skill::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($value)])->when($skill, fn ($query) => $query->whereKeyNot($skill->id))->exists()) {
                    $fail($taken);
                }
            }]]);

        return $data['name'];
    }

    /** @param  array<string, mixed>  $values */
    private function write(Skill $skill, array $values): void
    {
        try {
            $skill->forceFill($values)->save();
        } catch (UniqueConstraintViolationException) {
            // Two administrators saving the same name at once: the second is told, not shown an error page.
            throw ValidationException::withMessages(['name' => __('A skill with this name already exists.')]);
        }
    }

    private function record(Request $request, string $action, Skill $skill, ?string $previous = null): void
    {
        DB::table('moderation_events')->insert(['actor_id' => $request->user()->id, 'actor_email' => $request->user()->email, 'action' => $action,
            'target_type' => 'skill', 'target_id' => $skill->id, 'reason' => $previous, 'created_at' => now()]);
    }
}
