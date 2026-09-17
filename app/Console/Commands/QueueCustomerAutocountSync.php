<?php

namespace App\Console\Commands;

use App\User;
use Illuminate\Console\Command;

class QueueCustomerAutocountSync extends Command
{
    protected $signature = 'customers:queue-autocount-sync
                            {--name=* : Customer name(s) to queue (case-insensitive exact match). Defaults to the built-in customer list.}
                            {--id=* : Customer id(s) to queue directly, bypassing name matching (useful for names with fullwidth/unicode characters).}
                            {--dry-run : Preview the customers that would change; write nothing}';

    protected $description = 'Set the given customers to autocount_sync_status = pending_sync so the AutoCount plugin re-picks them up as debtors.';

    /**
     * Default customers to queue when --name is not supplied. Matched
     * case-insensitively against users.name; unmatched entries are reported.
     */
    private const DEFAULT_CUSTOMERS = [
        // Names as stored in the DB: fullwidth parens （） and a "bellora sdn bhd" prefix.
        'XIAO LIJIA HUNAN RESTAURANT（Kuantan）',
        'XIAO LIJIA HUNAN RESTAURANT（YP）',
        'bellora sdn bhd 京诚胡同烧烤店',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $ids = array_values(array_filter(
            array_map('intval', (array) $this->option('id')),
            fn ($id) => $id > 0
        ));

        $names = array_values(array_filter(
            array_map('trim', (array) $this->option('name')),
            fn ($n) => $n !== ''
        ));

        // Only fall back to the built-in list when neither --id nor --name given.
        if (empty($ids) && empty($names)) {
            $names = self::DEFAULT_CUSTOMERS;
        }

        // Resolve requested names to customers (case-insensitive exact match on
        // the trimmed name) plus any explicit ids; track names that don't resolve.
        $customers = User::query()
            ->where(function ($q) use ($names, $ids) {
                if (!empty($names)) {
                    $q->whereIn(\DB::raw('LOWER(TRIM(name))'), array_map(fn ($n) => mb_strtolower($n), $names));
                }
                if (!empty($ids)) {
                    $q->orWhereIn('id', $ids);
                }
            })
            ->get();

        $matchedByLower = $customers->keyBy(fn (User $u) => mb_strtolower(trim($u->name)));
        $unmatched = collect($names)->filter(
            fn ($n) => !$matchedByLower->has(mb_strtolower($n))
        )->values();

        if ($unmatched->isNotEmpty()) {
            $this->warn('No customer matched for: ' . $unmatched->implode(', '));
        }

        $matchedIds = $customers->pluck('id')->all();
        $missingIds = array_values(array_diff($ids, $matchedIds));
        if (!empty($missingIds)) {
            $this->warn('No customer found for id(s): ' . implode(', ', $missingIds));
        }

        if ($customers->isEmpty()) {
            $this->error('No customers matched — nothing to do.');

            return 1;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '')
            . $customers->count() . ' customer(s) will be set to pending_sync:');

        // The plugin only pulls customers that are registered AND active
        // (see AutoCountApiService::pendingCustomers). Flag any that aren't so
        // we don't silently queue a customer the plugin will never fetch.
        $ineligible = collect();
        $this->table(
            ['ID', 'Name', 'Type', 'AutoCount Code', 'From', 'Registered', 'Status'],
            $customers->map(function (User $u) use (&$ineligible) {
                $registered = (bool) $u->registration_completed_at;
                $active = $u->status === User::$user_status['active'];
                if (!$registered || !$active) {
                    $ineligible->push($u->name);
                }

                return [
                    $u->id,
                    $u->name,
                    $u->customer_type ?: '—',
                    $u->sql_customer_code ?: '— (new debtor)',
                    $u->autocount_sync_status ?: '—',
                    $registered ? 'yes' : 'NO',
                    $u->status,
                ];
            })->all()
        );

        if ($ineligible->isNotEmpty()) {
            $this->warn('These customers are not registered/active, so the AutoCount plugin will NOT fetch them even once queued: '
                . $ineligible->unique()->implode(', '));
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('Dry run only — nothing changed. Re-run without --dry-run to apply.');

            return 0;
        }

        $updated = User::query()
            ->whereIn('id', $customers->pluck('id'))
            ->update([
                'autocount_sync_status' => 'pending_sync',
                'autocount_synced_at' => null,
            ]);

        $this->newLine();
        $this->info("Done — {$updated} customer(s) set to pending_sync.");

        return 0;
    }
}
