<?php

namespace Tests\Feature;

use App\Enums\WorkspaceRole;
use App\Models\IdentityVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class IdentityVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function owner(WorkspaceRole $role = WorkspaceRole::Freelancer): User
    {
        return User::factory()->create(['workspace_role' => $role, 'onboarding_completed_at' => now()]);
    }

    /** @return array<string, mixed> */
    private function files(): array
    {
        return ['government_id' => UploadedFile::fake()->image('id.jpg'), 'selfie' => UploadedFile::fake()->image('selfie.png'), 'consent' => true];
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create(['is_super_admin' => true, 'is_admin' => true, 'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_confirmed_at' => now()]);
        $this->actingAs($user)->withSession(['admin.two_factor_proof' => $user->id.':'.hash('sha256', (string) $user->two_factor_secret)]);

        return $user;
    }

    public function test_both_roles_submit_encrypted_images_and_only_receive_safe_status(): void
    {
        Storage::fake('identity');
        foreach ([WorkspaceRole::Client, WorkspaceRole::Freelancer] as $role) {
            $owner = $this->owner($role);
            $files = $this->files();
            $original = $files['government_id']->getContent();
            $this->actingAs($owner)->post('/my-profile/identity', $files)->assertRedirect('/my-profile');
            $verification = IdentityVerification::where('user_id', $owner->id)->firstOrFail();
            $encrypted = Storage::disk('identity')->get($verification->id_path);
            $this->assertNotSame($original, $encrypted);
            $this->assertSame($original, Crypt::decryptString($encrypted));
            $this->get('/my-profile')->assertInertia(fn (Assert $page) => $page->component('marketplace-profile')->where('identity.status', 'pending')->missing('identity.id_path')->missing('identity.selfie_path'));
            $this->post('/my-profile/identity', $this->files())->assertConflict();
        }
        $this->assertCount(4, Storage::disk('identity')->allFiles());
    }

    public function test_only_current_session_super_admin_can_read_or_review_documents(): void
    {
        Storage::fake('identity');
        $owner = $this->owner();
        $this->actingAs($owner)->post('/my-profile/identity', $this->files());
        $verification = IdentityVerification::firstOrFail();
        $image = "/admin/identity/{$verification->id}/image/id";
        foreach ([$owner, User::factory()->create(['is_admin' => true])] as $user) {
            $this->actingAs($user)->get('/admin/identity')->assertForbidden();
            $this->get($image)->assertForbidden();
            $this->put("/admin/identity/{$verification->id}", ['status' => 'approved', 'reason' => 'Matches'])->assertForbidden();
        }
        $super = $this->superAdmin();
        $this->get($image)->assertOk()->assertHeader('Content-Type', 'image/jpeg')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get('/admin/identity')->assertInertia(fn (Assert $page) => $page->missing('submissions.data.0.id_path'));
        $this->withSession(['admin.two_factor_proof' => null])->get($image)->assertStatus(200)->assertInertia(fn (Assert $page) => $page->component('admin/categories/security'));
        $this->put("/admin/identity/{$verification->id}", ['status' => 'approved', 'reason' => 'Matches'])->assertForbidden();
    }

    public function test_approval_purges_images_and_cannot_be_overwritten_or_restored(): void
    {
        Storage::fake('identity');
        $owner = $this->owner();
        $this->actingAs($owner)->post('/my-profile/identity', $this->files());
        $verification = IdentityVerification::firstOrFail();
        $super = $this->superAdmin();
        $this->put("/admin/identity/{$verification->id}", ['status' => 'approved', 'reason' => 'ID and selfie reviewed'])->assertRedirect('/admin/identity');
        $this->assertDatabaseHas('identity_verifications', ['id' => $verification->id, 'status' => 'approved', 'reviewed_by' => $super->id, 'id_path' => null, 'selfie_path' => null]);
        $this->assertSame([], Storage::disk('identity')->allFiles());
        $this->get("/admin/identity/{$verification->id}/image/id")->assertNotFound();
        $this->put("/admin/identity/{$verification->id}", ['status' => 'rejected', 'reason' => 'Second review'])->assertConflict();
        $this->actingAs($owner)->post('/my-profile/identity', $this->files())->assertConflict();
    }

    public function test_rejected_user_can_resubmit_and_account_deletion_purges_pending_files(): void
    {
        Storage::fake('identity');
        $owner = $this->owner();
        $this->actingAs($owner)->post('/my-profile/identity', $this->files());
        $verification = IdentityVerification::firstOrFail();
        $this->superAdmin();
        $this->put("/admin/identity/{$verification->id}", ['status' => 'rejected', 'reason' => 'Please upload a clearer photo'])->assertRedirect();
        $this->assertSame([], Storage::disk('identity')->allFiles());
        $this->actingAs($owner)->post('/my-profile/identity', $this->files())->assertRedirect();
        $this->assertDatabaseCount('identity_verifications', 1);
        $this->assertSame('pending', $verification->fresh()->status);
        $owner->delete();
        $this->assertSame([], Storage::disk('identity')->allFiles());
        $this->assertDatabaseCount('identity_verifications', 0);
    }

    public function test_upload_validation_and_self_review_prevent_untrusted_verification(): void
    {
        Storage::fake('identity');
        $owner = $this->owner();
        $this->actingAs($owner)->post('/my-profile/identity', ['government_id' => UploadedFile::fake()->create('document.pdf'), 'selfie' => UploadedFile::fake()->create('bad.svg'), 'consent' => false, 'status' => 'approved'])->assertSessionHasErrors(['government_id', 'selfie', 'consent', 'status']);
        $this->assertSame([], Storage::disk('identity')->allFiles());
        $oversized = $this->files();
        $oversized['government_id'] = UploadedFile::fake()->image('large.jpg')->size(2049);
        $this->post('/my-profile/identity', $oversized)->assertSessionHasErrors('government_id');
        $this->assertSame([], Storage::disk('identity')->allFiles());
        $owner->forceFill(['is_super_admin' => true, 'two_factor_secret' => encrypt('SECRET'), 'two_factor_confirmed_at' => now()])->save();
        $this->post('/my-profile/identity', $this->files())->assertRedirect();
        $verification = IdentityVerification::firstOrFail();
        $this->withSession(['admin.two_factor_proof' => $owner->id.':'.hash('sha256', (string) $owner->two_factor_secret)])->put("/admin/identity/{$verification->id}", ['status' => 'approved', 'reason' => 'My own ID'])->assertForbidden();
    }
}
