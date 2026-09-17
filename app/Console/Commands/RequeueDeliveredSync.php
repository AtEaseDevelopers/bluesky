<?php

namespace App\Console\Commands;

use App\Order;
use App\User;
use Illuminate\Console\Command;

class RequeueDeliveredSync extends Command
{
    protected $signature = 'orders:requeue-delivered-sync
                            {--name=* : Customer name(s) to target (case-insensitive). Defaults to the built-in customer list.}
                            {--dry-run : Preview the orders that would change; write nothing}
                            {--force : Also re-queue delivered orders that already have a DO/invoice in AutoCount, clearing their refs (may create duplicate documents)}';

    protected $description = 'Set delivered orders of the given customers to autocount_sync_status = pending_sync so the AutoCount plugin re-picks them up.';

    /**
     * Default customers to re-queue when --name is not supplied. Matched
     * case-insensitively against users.name; unmatched entries are reported.
     */
    private const DEFAULT_CUSTOMERS = [
        '9H会所 21, JLN KEDONDONG',                                   // 21 kedondong
        'MOON VN SEAFOOD',                                           // moonVN
        'MeiHua Catering Culture Developmen Sdn Bhd - UMI',          // UMI
        'GOLDEN CHICKEN HOT POT TRX - 金鸡煲火锅',                     // golden chicken
        'THE JING HOUSE - 尚金楼',                                    // the jing house
        'SIN KEE RESTAURANT - 新记海鲜饭店',                           // sin kee
        'daily market sdn bhd',                                      // DM
        'AQUATIC HARVEST SDN BHD',                                   // aquatic harvest
        'ZHANG YUAN WAI - 张员外 湖南湘菜烧烤',                         // zhangyuanwai
        'HAI KAH LANG (TRX)',                                        // hai kah lang TRX
        'HAI ZHONG BAO SEAFOOD RESTAURANT M SDN BHD - 海中宝上汤活海鲜', // 海中宝
        'PARAMOUNT FOODPRINT SDN BHD - DEWAKAN RESTAURANT',          // dewakan
        'MING SHENG AQUATIC SUPPLIER',                               // mingsheng
        'XIN RONG KEE (PUCHONG) SDN BHD',                            // xin rong kee (Puchong only)
        'BEIJING FINE CUISINE SDN BHD 北京鸭王',                      // Beijing Fine cuisine
        'GOLD COAST INDULGENCE SDN BHD - ZHENG PALACE',             // zheng palace
        'CHAO YAN GE GONG GUAN SDN BHD',                             // chaoyangegongguan
        'RESTAURANT KU YUE TIN SDN BHD',                             // kuyuetin (active/COD; NOT the "- DORMANT" record)
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $names = array_values(array_filter(
            array_map('trim', (array) $this->option('name')),
            fn ($n) => $n !== ''
        ));
        if (empty($names)) {
            $names = self::DEFAULT_CUSTOMERS;
        }

        // Resolve each requested name to a customer (case-insensitive exact
        // match on the trimmed name) and track the ones that don't resolve.
        $customers = User::query()
            ->whereIn(\DB::raw('LOWER(TRIM(name))'), array_map(fn ($n) => mb_strtolower($n), $names))
            ->get();

        $matchedByLower = $customers->keyBy(fn (User $u) => mb_strtolower(trim($u->name)));
        $unmatched = collect($names)->filter(
            fn ($n) => !$matchedByLower->has(mb_strtolower($n))
        )->values();

        if ($unmatched->isNotEmpty()) {
            $this->warn('No customer matched for: ' . $unmatched->implode(', '));
        }

        if ($customers->isEmpty()) {
            $this->error('No customers matched — nothing to do.');

            return 1;
        }

        $query = Order::query()
            ->whereIn('user_id', $customers->pluck('id'))
            ->where('status', Order::$status['delivered']);

        // Without --force, never touch delivered orders that already have a
        // document in AutoCount — re-queuing those would create duplicates.
        if (!$force) {
            $query->whereNull('api_do_id')->whereNull('api_invoice_id');
        }

        $orders = $query->orderBy('user_id')->orderBy('id')->get();

        if ($orders->isEmpty()) {
            $this->info('No delivered orders to re-queue for the matched customers.');

            return 0;
        }

        $customersById = $customers->keyBy('id');
        $this->info(($dryRun ? '[DRY RUN] ' : '')
            . $orders->count() . ' delivered order(s) across ' . $orders->pluck('user_id')->unique()->count()
            . ' customer(s) will be set to pending_sync'
            . ($force ? ' (DO/invoice refs cleared)' : '') . ':');

        $this->table(
            ['Order', 'Customer', 'Invoice', 'From', 'DO', 'INV'],
            $orders->map(fn (Order $o) => [
                '#' . $o->id,
                optional($customersById->get($o->user_id))->name ?? ('user ' . $o->user_id),
                $o->invoice_number ?: '—',
                $o->autocount_sync_status ?: '—',
                $o->api_do_id ?: '—',
                $o->api_invoice_id ?: '—',
            ])->all()
        );

        if ($force) {
            $this->warn('--force clears api_do_id / api_invoice_id: the plugin will create NEW documents. '
                . 'Only proceed if these orders have no (or deleted) documents in AutoCount, or you will get duplicates.');
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('Dry run only — nothing changed. Re-run without --dry-run to apply.');

            return 0;
        }

        $payload = [
            'autocount_sync_status' => 'pending_sync',
            'autocount_synced_at' => null,
        ];
        if ($force) {
            $payload['api_do_id'] = null;
            $payload['api_invoice_id'] = null;
        }

        $updated = $query->update($payload);

        $this->newLine();
        $this->info("Done — {$updated} delivered order(s) set to pending_sync.");

        return 0;
    }
}
