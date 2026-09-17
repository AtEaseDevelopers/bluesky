<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class QueueCustomerAutocountSyncCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(string $name, array $overrides = []): User
    {
        return User::forceCreate(array_merge([
            'name' => $name,
            'email' => 'cust' . rand(1000, 9999) . '@example.com',
            'password' => Hash::make('password'),
            'category' => 'credit',
            'customer_type' => 'credit',
            'status' => 'active',
            'payment_method' => 'term',
            'login_code' => 'code' . rand(1000, 9999),
            'sql_customer_code' => '300-' . rand(1000, 9999),
            'registration_completed_at' => now(),
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
        ], $overrides));
    }

    /** @test */
    public function it_queues_named_customers_for_sync(): void
    {
        $customer = $this->makeCustomer('XIAO LIJIA HUNAN RESTAURANT (Kuantan)', [
            'autocount_sync_status' => 'synced',
            'autocount_synced_at' => now(),
        ]);

        $this->artisan('customers:queue-autocount-sync --name="XIAO LIJIA HUNAN RESTAURANT (Kuantan)"')
            ->assertExitCode(0);

        $customer->refresh();
        $this->assertSame('pending_sync', $customer->autocount_sync_status);
        $this->assertNull($customer->autocount_synced_at);
    }

    /** @test */
    public function customer_name_match_is_case_insensitive(): void
    {
        $customer = $this->makeCustomer('XIAO LIJIA HUNAN RESTAURANT (YP)', [
            'autocount_sync_status' => 'synced',
        ]);

        $this->artisan('customers:queue-autocount-sync --name="xiao lijia hunan restaurant (yp)"')
            ->assertExitCode(0);

        $this->assertSame('pending_sync', $customer->fresh()->autocount_sync_status);
    }

    /** @test */
    public function dry_run_changes_nothing(): void
    {
        $customer = $this->makeCustomer('京诚胡同烧烤店', [
            'autocount_sync_status' => 'synced',
        ]);

        $this->artisan('customers:queue-autocount-sync --name="京诚胡同烧烤店" --dry-run')
            ->assertExitCode(0);

        $this->assertSame('synced', $customer->fresh()->autocount_sync_status);
    }

    /** @test */
    public function it_only_touches_the_named_customers(): void
    {
        $target = $this->makeCustomer('XIAO LIJIA HUNAN RESTAURANT (YP)', ['autocount_sync_status' => 'synced']);
        $other = $this->makeCustomer('XIAO LIJIA HUNAN RESTAURANT BATU PAHAT', ['autocount_sync_status' => 'synced']);

        $this->artisan('customers:queue-autocount-sync --name="XIAO LIJIA HUNAN RESTAURANT (YP)"')
            ->assertExitCode(0);

        $this->assertSame('pending_sync', $target->fresh()->autocount_sync_status);
        $this->assertSame('synced', $other->fresh()->autocount_sync_status);
    }

    /** @test */
    public function it_queues_by_id_bypassing_name_matching(): void
    {
        // A name with fullwidth parens that would be awkward to type exactly.
        $customer = $this->makeCustomer('XIAO LIJIA HUNAN RESTAURANT（YP）', ['autocount_sync_status' => 'synced']);

        $this->artisan('customers:queue-autocount-sync --id=' . $customer->id)
            ->assertExitCode(0);

        $this->assertSame('pending_sync', $customer->fresh()->autocount_sync_status);
    }

    /** @test */
    public function missing_ids_are_reported_and_do_not_fail(): void
    {
        $customer = $this->makeCustomer('XIAO LIJIA HUNAN RESTAURANT（YP）', ['autocount_sync_status' => 'synced']);

        $this->artisan('customers:queue-autocount-sync --id=' . $customer->id . ' --id=999999')
            ->expectsOutput('No customer found for id(s): 999999')
            ->assertExitCode(0);

        $this->assertSame('pending_sync', $customer->fresh()->autocount_sync_status);
    }

    /** @test */
    public function unmatched_names_are_reported_and_do_not_fail(): void
    {
        $this->makeCustomer('XIAO LIJIA HUNAN RESTAURANT (YP)');

        $this->artisan('customers:queue-autocount-sync --name="XIAO LIJIA HUNAN RESTAURANT (YP)" --name="No Such Customer"')
            ->expectsOutput('No customer matched for: No Such Customer')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_warns_when_a_matched_customer_is_not_registered_or_active(): void
    {
        $customer = $this->makeCustomer('XIAO LIJIA HUNAN RESTAURANT (Kuantan)', [
            'registration_completed_at' => null,
            'autocount_sync_status' => 'pending',
        ]);

        $this->artisan('customers:queue-autocount-sync --name="XIAO LIJIA HUNAN RESTAURANT (Kuantan)"')
            ->assertExitCode(0);

        // Still queued, but the operator is warned it won't be fetched.
        $this->assertSame('pending_sync', $customer->fresh()->autocount_sync_status);
    }
}
