<?php

namespace App\Console\Commands;

use App\Order;
use App\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ConvertOrdersToWalkIn extends Command
{
    protected $signature = 'orders:convert-to-walk-in
                            {--date= : Order date to match against created_at (Y-m-d); required unless --id is given}
                            {--customer=* : Customer name(s) to match, case-insensitive & partial (repeatable); required unless --id is given}
                            {--id=* : Convert these exact order id(s), bypassing --date/--customer (use for midnight-boundary orders)}
                            {--dry-run : Preview the orders that would change; write nothing}';

    protected $description = 'Convert a customer\'s registered orders on a given date into true walk-in orders '
        . '(order_type=walk_in, copy name/phone into walk_in fields, detach user_id).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $ids = array_values(array_filter(
            (array) $this->option('id'),
            fn ($id) => $id !== null && $id !== '' && ctype_digit((string) $id)
        ));

        // Explicit-id mode: convert exactly these orders, regardless of date or
        // customer. Handy for orders placed just after midnight that still
        // belong to the previous day's batch.
        if (!empty($ids)) {
            $orders = Order::query()
                ->with('customer:id,name,attn_contact')
                ->whereIn('id', $ids)
                ->where('order_type', '!=', Order::$order_types['walk_in'])
                ->orderBy('id')
                ->get();

            return $this->preview($orders, 'ids ' . implode(', ', $ids), $dryRun);
        }

        $rawDate = (string) $this->option('date');
        if ($rawDate === '') {
            $this->error('--date is required (e.g. --date=2026-09-17).');

            return 1;
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $rawDate)->startOfDay();
        } catch (\Throwable $e) {
            $this->error("Invalid --date '{$rawDate}'. Use Y-m-d, e.g. 2026-09-17.");

            return 1;
        }

        $names = array_values(array_filter(
            array_map('trim', (array) $this->option('customer')),
            fn ($n) => $n !== ''
        ));
        if (empty($names)) {
            $this->error('At least one --customer name is required.');

            return 1;
        }

        // Resolve each name to customer account(s). Report ambiguous / missing
        // matches so nobody's orders are touched by accident.
        $userIds = [];
        foreach ($names as $name) {
            $matches = User::query()
                ->whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($name) . '%'])
                ->orderBy('name')
                ->get(['id', 'name', 'attn_contact']);

            if ($matches->isEmpty()) {
                $this->warn("No customer matches \"{$name}\" — skipped.");

                continue;
            }

            if ($matches->count() > 1) {
                $this->warn("\"{$name}\" matched " . $matches->count() . ' customers: '
                    . $matches->pluck('name')->implode(', ') . ' — all will be included.');
            }

            foreach ($matches as $m) {
                $userIds[$m->id] = $m->id;
            }
        }

        if (empty($userIds)) {
            $this->error('No customers resolved from the given names. Nothing to do.');

            return 1;
        }

        $orders = Order::query()
            ->with('customer:id,name,attn_contact')
            ->whereIn('user_id', array_values($userIds))
            ->whereDate('created_at', $date->toDateString())
            ->where('order_type', '!=', Order::$order_types['walk_in'])
            ->orderBy('id')
            ->get();

        return $this->preview($orders, $date->toDateString(), $dryRun);
    }

    /**
     * Preview the resolved orders and, unless --dry-run, convert them.
     */
    protected function preview(\Illuminate\Support\Collection $orders, string $scope, bool $dryRun): int
    {
        if ($orders->isEmpty()) {
            $this->info("No matching registered orders for {$scope}.");

            return 0;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '')
            . $orders->count() . " order(s) for {$scope} will be converted to walk-in:");

        $this->table(
            ['Order', 'Invoice', 'Customer', 'From type', 'walk_in_name', 'walk_in_phone', 'Total'],
            $orders->map(fn (Order $o) => [
                '#' . $o->id,
                $o->invoice_number ?: '—',
                optional($o->customer)->name ?: ('user#' . $o->user_id),
                $o->order_type,
                optional($o->customer)->name ?: '—',
                optional($o->customer)->attn_contact ?: '—',
                number_format((float) $o->total_price, 2),
            ])->all()
        );

        $this->newLine();
        $this->warn('Conversion detaches the customer: user_id is set to NULL and is_general=true. '
            . 'This is not automatically reversible.');

        if ($dryRun) {
            $this->newLine();
            $this->warn('Dry run only — nothing changed. Re-run without --dry-run to apply.');

            return 0;
        }

        $updated = 0;
        DB::transaction(function () use ($orders, &$updated) {
            foreach ($orders as $order) {
                $order->forceFill([
                    'order_type' => Order::$order_types['walk_in'],
                    'walk_in_name' => optional($order->customer)->name,
                    'walk_in_phone' => optional($order->customer)->attn_contact,
                    'user_id' => null,
                    'is_general' => true,
                ])->save();
                $updated++;
            }
        });

        $this->newLine();
        $this->info("Done — {$updated} order(s) converted to walk-in.");

        return 0;
    }
}
