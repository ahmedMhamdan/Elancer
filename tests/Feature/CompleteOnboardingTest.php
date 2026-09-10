<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\WorkspaceRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class CompleteOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /** @return array<string, mixed> */
    private function freelancerData(): array
    {
        return [
            'role' => 'freelancer', 'name' => 'Review User',
            'headline' => 'Laravel developer', 'bio' => 'I build dependable websites.',
            'skills' => ['Laravel', 'PHP'], 'country' => 'Palestine', 'city' => 'Hebron',
        ];
    }

    public function test_freelancer_profile_photo_and_completion_persist_and_dashboard_reloads(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('onboarding.store'), [
            ...$this->freelancerData(),
            'photo' => UploadedFile::fake()->image('photo.jpg', 160, 160),
        ])->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertSame('Review User', $user->name);
        $this->assertSame(WorkspaceRole::Freelancer, $user->workspace_role);
        $this->assertNotNull($user->onboarding_completed_at);
        $profile = $user->profile;
        $this->assertSame('Laravel developer', $profile->headline);
        $this->assertSame(['Laravel', 'PHP'], $profile->skills);
        $this->assertSame('Hebron, Palestine', $profile->location);
        $this->assertNull($profile->published_at);
        $this->assertNull($profile->company);
        Storage::disk('local')->assertExists($profile->photo_path);
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('profile.skills', ['Laravel', 'PHP'])
            ->where('auth.user.workspace_role', 'freelancer')
            ->where('auth.user.avatar', route('profile.photo'))
            ->missing('profile.photo_path')->missing('auth.user.profile'));
        $this->get(route('profile.photo'))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->get(route('onboarding'))->assertRedirect(route('dashboard'));
    }

    public function test_client_saves_without_freelancer_fields_and_discards_stale_role_fields(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('onboarding.store'), [
            ...$this->freelancerData(), 'role' => 'client', 'company' => 'Small Studio',
        ])->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertSame(WorkspaceRole::Client, $user->workspace_role);
        $this->assertSame('Small Studio', $user->profile->company);
        $this->assertNull($user->profile->headline);
        $this->assertSame([], $user->profile->skills);
        $this->assertNull($user->profile->photo_path);
        $this->assertNotNull($user->onboarding_completed_at);
    }

    public function test_client_company_is_optional_and_long_locations_fit_without_truncation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('onboarding.store'), [
            'role' => 'client', 'name' => 'Client', 'bio' => 'My project',
            'city' => str_repeat('a', 100), 'country' => str_repeat('b', 100),
        ])->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));
        $this->assertSame(202, strlen($user->fresh()->profile->location));
        $this->assertNull($user->fresh()->profile->company);
    }

    public function test_invalid_fields_do_not_change_the_account_or_create_profile_or_photo(): void
    {
        $user = User::factory()->create();
        $name = $user->name;
        $this->actingAs($user)->from(route('onboarding'))->post(route('onboarding.store'), [
            ...$this->freelancerData(), 'name' => ' ', 'skills' => ['PHP', 'php'],
            'country' => str_repeat('x', 101), 'photo' => UploadedFile::fake()->create('fake.png', 10, 'text/plain'),
        ])->assertSessionHasErrors(['name', 'skills.0', 'country', 'photo']);
        $this->assertSame($name, $user->fresh()->name);
        $this->assertNull($user->fresh()->onboarding_completed_at);
        $this->assertDatabaseCount('profiles', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    #[DataProvider('protectedFields')]
    public function test_cannot_submit_server_controlled_fields(string $field): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('onboarding.store'), [
            ...$this->freelancerData(), $field => null,
        ])->assertSessionHasErrors($field);
        $this->assertNull($user->fresh()->onboarding_completed_at);
        $this->assertFalse($user->fresh()->is_admin);
        $this->assertDatabaseCount('profiles', 0);
    }

    /** @return iterable<string, array{string}> */
    public static function protectedFields(): iterable
    {
        foreach (['user_id', 'profile_id', 'photo_path', 'is_admin', 'status', 'workspace_role', 'published_at', 'onboarding_completed_at'] as $field) {
            yield $field => [$field];
        }
    }

    public function test_photo_size_and_dimensions_are_validated(): void
    {
        $user = User::factory()->create();
        foreach ([
            UploadedFile::fake()->image('large.jpg')->size(2049),
            UploadedFile::fake()->image('wide.jpg', 6001, 1),
        ] as $photo) {
            $this->actingAs($user)->post(route('onboarding.store'), [
                ...$this->freelancerData(), 'photo' => $photo,
            ])->assertSessionHasErrors('photo');
        }
        $this->assertNull($user->fresh()->onboarding_completed_at);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_guests_unverified_and_suspended_accounts_cannot_save(): void
    {
        $this->post(route('onboarding.store'), $this->freelancerData())->assertRedirect(route('login'));
        $this->actingAs(User::factory()->unverified()->create())
            ->post(route('onboarding.store'), $this->freelancerData())->assertRedirect(route('verification.notice'));
        $this->actingAs(User::factory()->create(['status' => AccountStatus::Suspended]))
            ->post(route('onboarding.store'), $this->freelancerData())->assertForbidden();
        $this->assertDatabaseCount('profiles', 0);
    }

    public function test_repeated_submission_does_not_overwrite_completed_details_or_store_another_photo(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('onboarding.store'), $this->freelancerData())
            ->assertRedirect(route('dashboard'));
        $completed = $user->fresh()->onboarding_completed_at;
        $this->post(route('onboarding.store'), [
            ...$this->freelancerData(), 'name' => 'Overwrite', 'role' => 'client',
            'photo' => UploadedFile::fake()->image('retry.jpg'),
        ])->assertRedirect(route('dashboard'));
        $this->assertSame('Review User', $user->fresh()->name);
        $this->assertSame(WorkspaceRole::Freelancer, $user->fresh()->workspace_role);
        $this->assertTrue($completed->equalTo($user->fresh()->onboarding_completed_at));
        $this->assertDatabaseCount('profiles', 1);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_failed_account_save_rolls_back_profile_and_removes_new_photo(): void
    {
        $user = User::factory()->create();
        $name = $user->name;
        Event::listen('eloquent.saving: '.User::class, function (User $account): void {
            if ($account->onboarding_completed_at !== null) {
                throw new RuntimeException('Simulated failed account save');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($user)->post(route('onboarding.store'), [
                ...$this->freelancerData(), 'photo' => UploadedFile::fake()->image('rollback.jpg'),
            ]);
            $this->fail('Expected simulated failure');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated failed account save', $exception->getMessage());
        }
        $this->assertSame($name, $user->fresh()->name);
        $this->assertNull($user->fresh()->onboarding_completed_at);
        $this->assertDatabaseCount('profiles', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_replaced_photo_is_removed_only_after_success_and_photo_is_owner_scoped(): void
    {
        $user = User::factory()->create();
        Storage::disk('local')->put('profile-photos/old.jpg', 'old');
        $profile = $user->profile()->create([]);
        $profile->photo_path = 'profile-photos/old.jpg';
        $profile->save();
        $this->actingAs($user)->post(route('onboarding.store'), [
            ...$this->freelancerData(), 'photo' => UploadedFile::fake()->image('new.jpg'),
        ])->assertRedirect(route('dashboard'));
        Storage::disk('local')->assertMissing('profile-photos/old.jpg');
        Storage::disk('local')->assertExists($profile->fresh()->photo_path);
        $this->actingAs(User::factory()->create())
            ->get(route('profile.photo', ['user_id' => $user->id]))->assertNotFound();
    }

    public function test_deleting_account_removes_its_photo(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('onboarding.store'), [
            ...$this->freelancerData(), 'photo' => UploadedFile::fake()->image('avatar.jpg'),
        ])->assertRedirect(route('dashboard'));
        $photo = $user->fresh()->profile->photo_path;
        $this->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect('/');
        Storage::disk('local')->assertMissing($photo);
    }
}
