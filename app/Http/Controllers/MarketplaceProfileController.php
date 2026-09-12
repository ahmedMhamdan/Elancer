<?php

namespace App\Http\Controllers;

use App\Enums\WorkspaceRole;
use App\Http\Requests\Profiles\SaveProfileRequest;
use Illuminate\Http\RedirectResponse;

class MarketplaceProfileController extends Controller
{
    public function __invoke(SaveProfileRequest $request): RedirectResponse
    {
        $data = $request->validated();
        if ($request->user()->workspace_role === WorkspaceRole::Client) {
            unset($data['headline'], $data['skills']);
        } else {
            unset($data['company']);
        }
        if (array_key_exists('country', $data) || array_key_exists('city', $data)) {
            $data['location'] = implode(', ', array_filter([
                array_key_exists('city', $data) ? $data['city'] : $request->user()->profile?->city,
                array_key_exists('country', $data) ? $data['country'] : $request->user()->profile?->country,
            ]));
        }
        $profile = $request->user()->profile()->firstOrNew();
        $profile->forceFill($data)->save();

        return to_route($request->routeIs('marketplace-profile.update') ? 'marketplace-profile.edit' : 'dashboard');
    }
}
