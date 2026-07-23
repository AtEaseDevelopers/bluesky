<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call(DemoDataSeeder::class);
        $this->call(DriverCustomerVerifySeeder::class);
        $this->call(OrderStatusDemoSeeder::class);
        $this->call(Driver1RouteDemoSeeder::class);
    }
}
