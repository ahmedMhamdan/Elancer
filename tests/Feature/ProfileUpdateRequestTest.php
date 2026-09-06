<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Http\Requests\Profiles\UpdateProfileRequest;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProfileUpdateRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Exercise routing, model binding, policy discovery, and validation without
        // introducing a production endpoint before the controller slice.
        Route::middleware(['web', 'auth'])->patch(
            '/_test/profiles/{profile}',
            fn (UpdateProfileRequest $request, Profile $profile) => response()->json($request->validated()),
        );
    }

    public function test_verified_active_owner_can_submit_profile_text(): void
    {
        $owner = User::factory()->create();
        $profile = $owner->profile()->create([]);
        $data = [
            'headline' => 'Laravel developer',
            'bio' => 'أطوّر تطبيقات الويب باستخدام Laravel.',
            'location' => 'Hebron',
        ];

        $this->actingAs($owner)->patchJson('/_test/profiles/'.$profile->id, $data)
            ->assertOk()
            ->assertExactJson($data);
    }

    public function test_partial_edits_allow_clearing_draft_fields_and_exclude_unknown_input(): void
    {
        $owner = User::factory()->create();
        $profile = $owner->profile()->create(['headline' => 'Existing headline']);

        $this->actingAs($owner)->patchJson('/_test/profiles/'.$profile->id, [
            'bio' => '',
            'is_admin' => true,
            'email' => 'ignored@example.test',
        ])->assertOk()->assertExactJson(['bio' => null]);
    }

    public function test_guests_cannot_submit_profile_edits(): void
    {
        $profile = User::factory()->create()->profile()->create([]);

        $this->patchJson('/_test/profiles/'.$profile->id, ['headline' => 'Changed'])
            ->assertUnauthorized();
    }

    #[DataProvider('deniedEditors')]
    public function test_ineligible_or_non_owner_accounts_cannot_edit(
        AccountStatus $status,
        bool $verified,
        bool $admin,
        bool $ownsProfile,
    ): void {
        $editor = User::factory()->create([
            'status' => $status,
            'email_verified_at' => $verified ? now() : null,
            'is_admin' => $admin,
        ]);
        $owner = $ownsProfile ? $editor : User::factory()->create();
        $profile = $owner->profile()->create([]);

        // Authorization runs before validation, even for malformed input.
        $this->actingAs($editor)->patchJson('/_test/profiles/'.$profile->id, ['headline' => []])
            ->assertForbidden();
    }

    public static function deniedEditors(): iterable
    {
        yield 'another user' => [AccountStatus::Active, true, false, false];
        yield 'another user who is an admin' => [AccountStatus::Active, true, true, false];
        foreach ([false, true] as $admin) {
            yield 'unverified owner admin-'.(int) $admin => [AccountStatus::Active, false, $admin, true];
            yield 'suspended owner admin-'.(int) $admin => [AccountStatus::Suspended, true, $admin, true];
            yield 'deactivated owner admin-'.(int) $admin => [AccountStatus::Deactivated, true, $admin, true];
        }
    }

    #[DataProvider('invalidFields')]
    public function test_invalid_fields_are_rejected(string $field, mixed $value): void
    {
        $owner = User::factory()->create();
        $profile = $owner->profile()->create([]);

        $this->actingAs($owner)->patchJson('/_test/profiles/'.$profile->id, [$field => $value])
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    }

    public static function invalidFields(): iterable
    {
        yield 'long headline' => ['headline', str_repeat('a', 121)];
        yield 'long bio' => ['bio', str_repeat('a', 5001)];
        yield 'long location' => ['location', str_repeat('a', 121)];
        yield 'array headline' => ['headline', ['invalid']];
        yield 'numeric bio' => ['bio', 123];
        yield 'boolean location' => ['location', true];
        yield 'owner override' => ['user_id', 999];
        yield 'null owner override' => ['user_id', null];
        yield 'publication override' => ['published_at', '2026-09-06'];
        yield 'null publication override' => ['published_at', null];
    }

    public function test_exact_character_limits_accept_arabic_text(): void
    {
        $owner = User::factory()->create();
        $profile = $owner->profile()->create([]);
        $data = [
            'headline' => str_repeat('ع', 120),
            'bio' => str_repeat('ع', 5000),
            'location' => str_repeat('ع', 120),
        ];

        $this->actingAs($owner)->patchJson('/_test/profiles/'.$profile->id, $data)
            ->assertOk()->assertExactJson($data);
    }

    #[DataProvider('validationLocales')]
    public function test_validation_messages_follow_the_application_locale(string $locale, string $message): void
    {
        app()->setLocale($locale);
        $owner = User::factory()->create();
        $profile = $owner->profile()->create([]);

        $this->actingAs($owner)->patchJson('/_test/profiles/'.$profile->id, [
            'headline' => str_repeat('a', 121),
        ])->assertUnprocessable()->assertJsonPath('errors.headline.0', $message);
    }

    public static function validationLocales(): iterable
    {
        yield 'English' => ['en', 'The headline must not exceed 120 characters.'];
        yield 'Arabic' => ['ar', 'يجب ألا يتجاوز حقل العنوان المهني عدد 120 حرفًا.'];
    }

    public function test_unknown_profile_returns_not_found(): void
    {
        $this->actingAs(User::factory()->create())
            ->patchJson('/_test/profiles/999', ['headline' => 'Changed'])
            ->assertNotFound();
    }
}
