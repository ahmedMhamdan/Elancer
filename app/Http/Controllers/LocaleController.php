<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['locale' => ['required', Rule::in(['en', 'ar'])]]);
        $locale = $data['locale'];
        $request->session()->put('locale', $locale);
        if ($request->user() !== null && $request->user()->locale !== $locale) {
            $request->user()->forceFill(['locale' => $locale])->save();
        }
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['locale' => $locale])->withCookie(cookie('locale', $locale, 60 * 24 * 365));
        }

        return back()->withCookie(cookie('locale', $locale, 60 * 24 * 365));
    }
}
