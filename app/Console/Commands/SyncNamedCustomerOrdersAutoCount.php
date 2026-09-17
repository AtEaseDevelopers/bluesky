<?php

namespace App\Console\Commands;

use App\Order;
use App\Services\AutoCountSyncService;
use App\Services\BlueskyDebtorsImportService;
use App\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class SyncNamedCustomerOrdersAutoCount extends Command
{
    protected $signature = 'orders:sync-autocount-for-customers
                            {--dry-run : List matches and orders; do not queue}
                            {--force : Clear api_do_id / api_invoice_id and re-queue (may duplicate in AutoCount)}
                            {--customer=* : Additional customer name search terms}
                            {--from= : Only orders created on/after (Y-m-d)}
                            {--to= : Only orders created on/before (Y-m-d)}
                            {--queue-customers : Also set matched customers to pending_sync for debtor sync}';

    protected $description = 'Queue eligible orders for named customers to AutoCount (pending_sync for the plugin).';

    /** @var list<string> */
    private const DEFAULT_CUSTOMER_TERMS = [
        '21 kedondong',
        'moonVN',
        'UMI',
        'kuyuetin',
        'golden chicken',
        'the jing house',
        'sin kee',
        'DM',
        'aquatic harvest',
        'zhangyuanwai（下午5点）',
        'hai kah lang TRX',
        '海中宝',
        'dewakan',
        'mingsheng',
        'xin rong kee',
        'Beijing Fine cuisine',
        'zheng palace',
        'chaoyangegongguan',
    ];

    /** Extra LIKE tokens when the label alone does not match OMS names. */
    private const SEARCH_ALIASES = [
        '21 kedondong' => ['kedondong', '21 jln kedondong'],
        'moonvn' => ['moon vn'],
        'kuyuetin' => ['kuyue tin', 'kuyue'],
        'dm' => ['dm restaurant', ' d.m.'],
        'zhangyuanwai（下午5点）' => ['zhangyuan', 'zhang yuan'],
        'hai kah lang trx' => ['hai kah lang (trx)', 'hai kah lang trx'],
        '海中宝' => ['hai zhong bao', '海中宝'],
        'mingsheng' => ['ming sheng', 'mingsheng'],
        'chaoyangegongguan' => ['chao yan ge gong guan'],
    ];

    public function handle(AutoCountSyncService $syncService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $queueCustomers = (bool) $this->option('queue-customers');

        $terms = array_merge(self::DEFAULT_CUSTOMER_TERMS, (array) $this->option('customer'));
        $terms = array_values(array_unique(array_filter(array_map('trim', $terms))));

        $users = User::query()
            ->whereNotNull('registration_completed_at')
            ->orderBy('name')
            ->get(['id', 'name', 'sql_customer_code', 'autocount_sync_status']);

        $resolved = $this->resolveCustomers($terms, $users);

        $this->info('Customer match:');
        $this->table(
            ['Search term', 'Customer', 'AccNo', 'Sync status'],
            collect($resolved)->map(function ($row) {
                $user = $row['user'];

                return [
                    $row['term'],
                    $user ? $user->name : '— NOT FOUND —',
                    $user ? ($user->sql_customer_code ?: '—') : '—',
                    $user ? ($user->autocount_sync_status ?: '—') : '—',
                ];
            })->all()
        );

        $missing = collect($resolved)->whereNull('user')->pluck('term')->all();
        if ($missing !== []) {
            $this->warn('No OMS customer for: ' . implode(', ', $missing));
        }

        $customerIds = collect($resolved)->pluck('user')->filter()->pluck('id')->unique()->values()->all();
        if ($customerIds === []) {
            $this->error('No customers matched — nothing to sync.');

            return 1;
        }

        if ($queueCustomers && !$dryRun) {
            $result = $syncService->syncCustomers($customerIds);
            $this->info("Customers queued for AutoCount: {$result['synced']} (skipped {$result['skipped']}).");
        } elseif ($queueCustomers) {
            $this->line('[DRY RUN] Would queue ' . count($customerIds) . ' customer(s) for debtor sync.');
        }

        $ordersQuery = Order::query()
            ->with('customer')
            ->whereIn('user_id', $customerIds)
            ->where('status', '!=', Order::$status['cancelled'])
            ->orderBy('id');

        if ($this->option('from')) {
            $ordersQuery->where('created_at', '>=', $this->option('from') . ' 00:00:00');
        }
        if ($this->option('to')) {
            $ordersQuery->where('created_at', '<=', $this->option('to') . ' 23:59:59');
        }

        if (!$force) {
            $ordersQuery->where(function ($q) {
                $q->whereNull('api_do_id')->whereNull('api_invoice_id');
            });
        }

        $orders = $ordersQuery->get();

        if ($orders->isEmpty()) {
            $this->info('No orders found for matched customers (check --from/--to or --force for already-synced docs).');

            return 0;
        }

        $rows = [];
        $queued = 0;
        $skipped = 0;

        foreach ($orders as $order) {
            $eligible = $order->canSyncToAutoCount();
            $rows[] = [
                '#' . $order->id,
                $order->customer->name ?? '—',
                $order->invoice_number ?: '—',
                $order->status,
                $order->payment_status,
                $order->autocount_sync_status ?: '—',
                $eligible ? 'yes' : 'no',
            ];

            if ($dryRun) {
                continue;
            }

            if ($force && ($order->api_do_id || $order->api_invoice_id)) {
                $order->update([
                    'api_do_id' => null,
                    'api_invoice_id' => null,
                ]);
                $order = $order->fresh();
            }

            if (!$eligible) {
                $skipped++;

                continue;
            }

            $log = $syncService->syncIfEligible($order);
            if (in_array($log->sync_status, ['pending_sync', 'synced', 'synced_successfully'], true)) {
                $queued++;
            } else {
                $skipped++;
            }
        }

        $this->newLine();
        $this->info(($dryRun ? '[DRY RUN] ' : '') . $orders->count() . ' order(s):');
        $this->table(
            ['Order', 'Customer', 'Invoice', 'Status', 'Payment', 'AC sync', 'Eligible'],
            $rows
        );

        if ($dryRun) {
            $this->warn('Dry run — re-run without --dry-run to set pending_sync on eligible orders.');
            $this->line('Tip: add --queue-customers so debtors sync before invoices.');

            return 0;
        }

        if ($force) {
            $this->warn('--force was used: cleared DO/invoice refs on selected orders; plugin may create new AutoCount documents.');
        }

        $this->info("Queued for AutoCount: {$queued}. Skipped (not eligible or blocked): {$skipped}.");

        return 0;
    }

    /**
     * @param  list<string>  $terms
     * @param  Collection<int, User>  $users
     * @return list<array{term: string, user: ?User}>
     */
    private function resolveCustomers(array $terms, Collection $users): array
    {
        $byKey = $users->keyBy(fn (User $u) => BlueskyDebtorsImportService::normalizeMatchName($u->name));

        $out = [];
        foreach ($terms as $term) {
            $user = $this->matchOneCustomer($term, $users, $byKey);
            $out[] = ['term' => $term, 'user' => $user];
        }

        return $out;
    }

    /**
     * @param  Collection<int, User>  $users
     * @param  Collection<string, User>  $byKey
     */
    private function matchOneCustomer(string $term, Collection $users, Collection $byKey): ?User
    {
        $key = $this->normalizeSearchTerm($term);
        if ($byKey->has($key)) {
            return $byKey->get($key);
        }

        $aliases = self::SEARCH_ALIASES[$key] ?? [];
        foreach ($aliases as $alias) {
            $aliasKey = $this->normalizeSearchTerm($alias);
            if ($byKey->has($aliasKey)) {
                return $byKey->get($aliasKey);
            }
        }

        $tokens = array_values(array_filter(preg_split('/\s+/u', $key) ?: [], fn ($t) => mb_strlen($t) >= 2));
        if ($tokens !== []) {
            $candidates = $users->filter(function (User $user) use ($tokens) {
                $nameKey = BlueskyDebtorsImportService::normalizeMatchName($user->name);
                foreach ($tokens as $token) {
                    if (!str_contains($nameKey, $token)) {
                        return false;
                    }
                }

                return true;
            });

            if ($candidates->count() === 1) {
                return $candidates->first();
            }

            if ($candidates->count() > 1) {
                return $candidates->sortBy(fn (User $u) => mb_strlen($u->name))->first();
            }
        }

        foreach ($users as $user) {
            $nameKey = BlueskyDebtorsImportService::normalizeMatchName($user->name);
            if ($key !== '' && (str_contains($nameKey, $key) || str_contains($key, $nameKey))) {
                return $user;
            }
        }

        foreach ($aliases as $alias) {
            $aliasKey = $this->normalizeSearchTerm($alias);
            foreach ($users as $user) {
                $nameKey = BlueskyDebtorsImportService::normalizeMatchName($user->name);
                if ($aliasKey !== '' && str_contains($nameKey, $aliasKey)) {
                    return $user;
                }
            }
        }

        return null;
    }

    private function normalizeSearchTerm(string $term): string
    {
        $term = preg_replace('/[（(][^)）]*[)）]/u', '', $term) ?? $term;
        $term = BlueskyDebtorsImportService::normalizeMatchName($term);

        return $term;
    }
}
