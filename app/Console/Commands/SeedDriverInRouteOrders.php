<?php

namespace App\Console\Commands;

use App\Services\DriverInRouteOrderSeedService;
use Illuminate\Console\Command;

class SeedDriverInRouteOrders extends Command
{
    protected $signature = 'orders:seed-driver-in-route';

    protected $description = 'Seed one in-route delivery order per active driver';

    public function handle(DriverInRouteOrderSeedService $service): int
    {
        $result = $service->run(function (string $message): void {
            $this->line($message);
        });

        if ($result['created'] === 0 && $result['skipped'] === 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
