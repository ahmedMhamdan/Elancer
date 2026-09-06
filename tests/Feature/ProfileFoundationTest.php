<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProfileFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_does_not_require_a_marketplace_profile(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->profile);
        $this->assertDatabaseCount('profiles', 0);
    }

    public function test_owner_can_have_an_incomplete_unpublished_profile(): void
    {
        $user = User::factory()->create();
        $profile = $user->profile()->create([])->refresh();

        $this->assertTrue($profile->user->is($user));
        $this->assertTrue($user->profile->is($profile));
        $this->assertNull($profile->headline);
        $this->assertNull($profile->bio);
        $this->assertNull($profile->location);
        $this->assertNull($profile->published_at);
    }

    public function test_database_rejects_a_second_profile_for_the_same_user(): void
    {
        $user = User::factory()->create();
        $user->profile()->create([]);

        $this->expectException(QueryException::class);

        $user->profile()->create([]);
    }

    public function test_database_rejects_a_profile_without_an_existing_owner(): void
    {
        $this->expectException(QueryException::class);

        DB::table('profiles')->insert(['user_id' => 999]);
    }

    public function test_mass_assignment_cannot_transfer_or_publish_a_profile(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $profile = $owner->profile()->create(['headline' => 'Laravel developer']);

        $profile->fill([
            'user_id' => $otherUser->id,
            'published_at' => now(),
            'bio' => 'I build web applications.',
        ])->save();
        $profile->refresh();

        $this->assertSame($owner->id, $profile->user_id);
        $this->assertNull($profile->published_at);
        $this->assertSame('I build web applications.', $profile->bio);
    }

    public function test_deleting_an_account_removes_its_public_profile_details(): void
    {
        $user = User::factory()->create();
        $profile = $user->profile()->create([]);

        $user->delete();

        $this->assertModelMissing($profile);
        $this->assertSame(0, Profile::query()->count());
    }
}
