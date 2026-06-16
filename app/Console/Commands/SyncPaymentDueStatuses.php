<?php

namespace App\Console\Commands;

use App\Services\OrderService;
use Illuminate\Console\Command;

class SyncPaymentDueStatuses extends Command
{
    protected $signature = 'orders:sync-payment-due';

    protected $description = 'Refresh payment due status for overdue credit orders';

    public function handle(OrderService $orderService): int
    {
        $orderService->syncOverduePaymentStatuses();
        $this->info('Payment due statuses synced.');

        return 0;
    }
}
