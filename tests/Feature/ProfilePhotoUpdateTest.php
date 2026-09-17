<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ProfilePhotoUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['services.sightengine.user' => 'test-user', 'services.sightengine.secret' => 'test-secret', 'services.sightengine.workflow' => 'test-workflow']);
        Http::preventStrayRequests();
        $this->moderation('accept');
    }

    private function moderation(string $action): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['api.sightengine.com/*' => Http::response(['status' => 'success', 'summary' => ['action' => $action], 'workflow' => ['id' => 'test-workflow']])]);
    }

    private function owner(): User
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        $profile = $user->profile()->create(['headline' => 'Keep my headline']);
        $profile->forceFill(['photo_path' => 'profile-photos/'.$user->id.'/old.jpg'])->save();
        Storage::disk('local')->put($profile->photo_path, 'old image');

        return $user;
    }

    public function test_owner_replaces_photo_with_normalized_private_jpeg_without_changing_profile_fields(): void
    {
        $user = $this->owner();
        $old = $user->profile->photo_path;
        $png = UploadedFile::fake()->image('avatar.png', 900, 600)->getContent();
        $response = $this->actingAs($user)->postJson('/my-profile/photo', ['photo' => UploadedFile::fake()->createWithContent('avatar.png', $png.'private metadata')]);
        $response->assertOk()->assertJsonStructure(['avatar']);
        $profile = $user->profile->fresh();
        $this->assertSame('Keep my headline', $profile->headline);
        $this->assertNull($profile->published_at);
        Storage::disk('local')->assertMissing($old);
        $bytes = Storage::disk('local')->get($profile->photo_path);
        $this->assertStringNotContainsString('private metadata', $bytes);
        $this->assertSame('image/jpeg', getimagesizefromstring($bytes)['mime']);
        $this->assertSame(512, getimagesizefromstring($bytes)[0]);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.sightengine.com/1.0/check-workflow.json' && $request->hasFile('media', $bytes, 'profile.jpg'));
        // Refresh the guard's test user as a new HTTP request would.
        $this->actingAs($user->fresh())->get($response->json('avatar'))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $other = User::factory()->create(['onboarding_completed_at' => now()]);
        $this->actingAs($other)->get($response->json('avatar'))->assertNotFound();
    }

    public function test_photo_attempts_are_limited_per_user_per_minute_and_hour(): void
    {
        $user = $this->owner();
        $this->actingAs($user)->postJson('/my-profile/photo', ['photo' => UploadedFile::fake()->image('a.jpg')])->assertOk();
        $this->postJson('/my-profile/photo', ['photo' => UploadedFile::fake()->image('b.jpg')])->assertStatus(429);
        for ($i = 0; $i < 2; $i++) {
            $this->travel(61)->seconds();
            $this->postJson('/my-profile/photo', ['photo' => UploadedFile::fake()->image('c.jpg')])->assertOk();
        }
        $this->travel(61)->seconds();
        $this->postJson('/my-profile/photo', ['photo' => UploadedFile::fake()->image('d.jpg')])->assertStatus(429);
        Http::assertSentCount(3);
        $this->actingAs($this->owner())->postJson('/my-profile/photo', ['photo' => UploadedFile::fake()->image('other.jpg')])->assertOk();
    }

    public function test_unsafe_unknown_and_failed_checks_never_replace_the_previous_photo(): void
    {
        foreach (['reject', 'unknown'] as $action) {
            $user = $this->owner();
            $this->moderation($action);
            $old = $user->profile->photo_path;
            $this->actingAs($user)->postJson('/my-profile/photo', ['photo' => UploadedFile::fake()->image('unsafe.jpg')])->assertUnprocessable()->assertJsonValidationErrors('photo');
            $this->assertSame($old, $user->profile->fresh()->photo_path);
            Storage::disk('local')->assertExists($old);
        }
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['api.sightengine.com/*' => Http::failedConnection()]);
        $user = $this->owner();
        $this->actingAs($user)->postJson('/my-profile/photo', ['photo' => UploadedFile::fake()->image('timeout.jpg')])->assertUnprocessable()->assertJsonValidationErrors('photo');
        $this->assertSame($user->profile->photo_path, $user->profile->fresh()->photo_path);
    }

    public function test_malformed_wrong_workflow_and_http_failures_preserve_the_photo(): void
    {
        foreach ([
            [[], 200],
            [['status' => 'success', 'summary' => ['action' => 'accept'], 'workflow' => ['id' => 'wrong']], 200],
            [['status' => 'failure'], 200],
            [['status' => 'success', 'summary' => ['action' => 'accept'], 'workflow' => ['id' => 'test-workflow']], 503],
        ] as [$body, $status]) {
            Http::swap(new Factory);
            Http::preventStrayRequests();
            Http::fake(['api.sightengine.com/*' => Http::response($body, $status)]);
            $user = $this->owner();
            $old = $user->profile->photo_path;
            $this->actingAs($user)->postJson('/my-profile/photo', ['photo' => UploadedFile::fake()->image('photo.jpg')])->assertUnprocessable()->assertJsonValidationErrors('photo');
            $this->assertSame($old, $user->profile->fresh()->photo_path);
            $this->assertSame([$old], Storage::disk('local')->allFiles('profile-photos/'.$user->id));
        }
    }

    public function test_missing_configuration_fails_closed_without_sending_the_photo(): void
    {
        config(['services.sightengine.secret' => null]);
        $user = $this->owner();
        $this->actingAs($user)->postJson('/my-profile/photo', ['photo' => UploadedFile::fake()->image('photo.jpg')])->assertUnprocessable()->assertJsonValidationErrors('photo');
        Http::assertNothingSent();
        $this->assertSame($user->profile->photo_path, $user->profile->fresh()->photo_path);
    }

    public function test_unauthorized_or_forged_uploads_and_non_images_never_reach_provider(): void
    {
        $this->postJson('/my-profile/photo', ['photo' => UploadedFile::fake()->image('guest.jpg')])->assertUnauthorized();
        $user = $this->owner();
        $user->forceFill(['status' => 'suspended'])->save();
        $this->actingAs($user)->postJson('/my-profile/photo', ['photo' => UploadedFile::fake()->image('suspended.jpg')])->assertForbidden();
        $this->actingAs($this->owner())->postJson('/my-profile/photo', ['photo' => UploadedFile::fake()->image('forged.jpg'), 'user_id' => $user->id])->assertUnprocessable();
        $this->actingAs($this->owner())->postJson('/my-profile/photo', ['photo' => UploadedFile::fake()->createWithContent('fake.jpg', '<svg onload="alert(1)"></svg>')])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_database_failure_preserves_old_photo_and_cleans_new_file(): void
    {
        $user = $this->owner();
        $old = $user->profile->photo_path;
        Profile::saving(function (): void {
            throw new RuntimeException('Simulated write failure');
        });
        $this->actingAs($user)->postJson('/my-profile/photo', ['photo' => UploadedFile::fake()->image('rollback.jpg')])->assertServerError();
        $this->assertSame($old, $user->profile->fresh()->photo_path);
        $this->assertSame([$old], Storage::disk('local')->allFiles('profile-photos/'.$user->id));
    }
}
