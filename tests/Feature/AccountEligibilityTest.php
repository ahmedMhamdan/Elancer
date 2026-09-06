<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccountEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_accounts_have_safe_database_defaults(): void
    {
        $user = User::factory()->unverified()->create()->refresh();

        $this->assertSame(AccountStatus::Active, $user->status);
        $this->assertSame('en', $user->locale);
        $this->assertFalse($user->is_admin);
        $this->assertFalse($user->canParticipateInMarketplace());
    }

    public function test_new_verified_accounts_are_eligible_before_reloading(): void
    {
        $user = User::factory()->create();

        $this->assertSame(AccountStatus::Active, $user->status);
        $this->assertSame('en', $user->locale);
        $this->assertFalse($user->is_admin);
        $this->assertTrue($user->canParticipateInMarketplace());
    }

    #[DataProvider('eligibilityCases')]
    public function test_marketplace_eligibility(
        AccountStatus $status,
        bool $verified,
        bool $admin,
        bool $expected,
    ): void {
        $user = User::factory()->create([
            'status' => $status,
            'email_verified_at' => $verified ? now() : null,
            'is_admin' => $admin,
        ])->refresh();

        $this->assertSame($expected, $user->canParticipateInMarketplace());
    }

    public static function eligibilityCases(): iterable
    {
        foreach (AccountStatus::cases() as $status) {
            foreach ([false, true] as $verified) {
                foreach ([false, true] as $admin) {
                    yield $status->value.'-verified-'.(int) $verified.'-admin-'.(int) $admin => [
                        $status, $verified, $admin,
                        $status === AccountStatus::Active && $verified,
                    ];
                }
            }
        }
    }

    public function test_account_authority_cannot_be_mass_assigned(): void
    {
        $user = User::factory()->create()->refresh();

        $user->fill(['status' => 'suspended', 'is_admin' => true]);
        $user->save();
        $user->refresh();

        $this->assertSame(AccountStatus::Active, $user->status);
        $this->assertFalse($user->is_admin);
    }
}
