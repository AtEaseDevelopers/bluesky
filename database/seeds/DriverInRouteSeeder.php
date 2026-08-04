<?php

use App\Services\DriverInRouteOrderSeedService;
use Illuminate\Database\Seeder;

/**
 * Seeds one "in route" delivery order per active driver (for driver portal testing).
 *
 * Prefer: php artisan orders:seed-driver-in-route
 *
 * Or after composer dump-autoload:
 *   php artisan tinker --execute="(new DriverInRouteSeeder)->setCommand(app('Illuminate\Contracts\Console\Kernel'))->run();"
 */
class DriverInRouteSeeder extends Seeder
{
    public function run(): void
    {
        app(DriverInRouteOrderSeedService::class)->run(function (string $message): void {
            $this->log($message);
        });
    }

    private function log(string $message): void
    {
        if ($this->command) {
            $this->command->line($message);
        }
    }
}
