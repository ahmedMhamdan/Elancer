<?php

namespace App\Http\Controllers;

use App\Models\Skill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SkillController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()->canParticipateInMarketplace(), 403);
        $data = $request->validate(['q' => ['nullable', 'string', 'max:50']]);
        $query = trim($data['q'] ?? '');
        // Literal substring search: SQL wildcards supplied by users are not patterns.
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($query)).'%';
        $matches = Skill::query()->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern])
            ->orderBy('name')->limit(20)->get(['id', 'name']);

        return response()->json(['data' => $matches]);
    }
}
