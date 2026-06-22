<?php

use App\Driver;
use App\Order;
use App\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Focused dummy data for manually verifying the Driver "Assigned Customers"
 * module (assigned customer list + invoice payment status & due dates).
 *
 * Idempotent — safe to re-run. Creates one login-ready driver with customers
 * whose invoices span every payment/due state the views render.
 *
 *   Driver portal — username: driverdemo   password: password
 */
class DriverCustomerVerifySeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $driver = Driver::updateOrCreate(
                ['username' => 'driverdemo'],
                [
                    'name' => 'Demo Driver',
                    'phone' => '0123456789',
                    'lorry_number' => 'LRY-DEMO',
                    'password' => Hash::make('password'),
                    'is_active' => true,
                ]
            );

            // 1) Credit customer with invoices in every state.
            $credit = $this->customer($driver, [
                'name' => 'Verify Credit Seafood',
                'login_code' => 'VERIFY-CREDIT-1',
                'email' => 'verify-credit1@demo.test',
                'customer_type' => 'credit',
                'payment_term_days' => 30,
                'payment_method' => json_encode(['term']),
            ]);

            $this->invoice($credit, $driver, 'INV-V1001', 300.00, 300.00, 'paid', Order::$status['delivered'], now()->subDays(2));
            $this->invoice($credit, $driver, 'INV-V1002', 500.00, 200.00, 'partial', Order::$status['in_route'], now());            // due today
            $this->invoice($credit, $driver, 'INV-V1003', 250.00, 0.00, 'unpaid', Order::$status['delivered'], now()->subDays(5)); // overdue
            $this->invoice($credit, $driver, 'INV-V1004', 180.00, 0.00, 'unpaid', Order::$status['in_route'], now()->addDays(20)); // not yet due

            // 2) COD customer — exercises the "Record Payment" action.
            //    Drivers may record COD payment only when the order is in route or
            //    delivered and still has a balance; the full balance is required.
            $cod = $this->customer($driver, [
                'name' => 'Verify COD Mart',
                'login_code' => 'VERIFY-COD-1',
                'email' => 'verify-cod1@demo.test',
                'customer_type' => 'cod',
                'payment_method' => json_encode(['cod']),
            ]);

            // Already paid — no Record Payment button.
            $this->invoice($cod, $driver, 'INV-V2001', 120.00, 120.00, 'paid', Order::$status['delivered'], null);
            // In route, unpaid — Record Payment button SHOWS (amount locked to RM 90.00).
            $this->invoice($cod, $driver, 'INV-V2002', 90.00, 0.00, 'unpaid', Order::$status['in_route'], null);
            // Delivered, unpaid — Record Payment button SHOWS (amount locked to RM 150.00).
            $this->invoice($cod, $driver, 'INV-V2004', 150.00, 0.00, 'unpaid', Order::$status['delivered'], null);
            // Not yet out for delivery — Record Payment button HIDDEN (cannot collect COD yet).
            $this->invoice($cod, $driver, 'INV-V2005', 75.00, 0.00, 'unpaid', Order::$status['pending'], null);
            // Cancelled — excluded from the invoice list entirely.
            $this->invoice($cod, $driver, 'INV-V2003', 99.00, 0.00, 'unpaid', Order::$status['cancelled'], null);

            // 3) Assigned customer with no invoices (empty-state check).
            $this->customer($driver, [
                'name' => 'Verify Empty Hotel',
                'login_code' => 'VERIFY-CREDIT-2',
                'email' => 'verify-credit2@demo.test',
                'customer_type' => 'credit',
                'payment_term_days' => 60,
                'payment_method' => json_encode(['term']),
            ]);
        });

        $this->command->info('Driver customer verify data seeded.');
        $this->command->line('Driver portal — username: driverdemo   password: password');
        $this->command->line('Visit: /driver/login  then  /driver/customers');
        $this->command->line('Record Payment — open "Verify COD Mart": INV-V2002 / INV-V2004 show the button');
        $this->command->line('  (amount locked to the full balance); INV-V2005 (pending) hides it.');
        $this->command->line('Credit case — open "Verify Credit Seafood": INV-V1002 / INV-V1003 allow an editable amount.');
    }

    private function customer(Driver $driver, array $attrs): User
    {
        $defaults = [
            'password' => Hash::make('password'),
            'status' => User::$user_status['active'],
            'registration_completed_at' => now(),
            'category' => 'restaurant',
            'credit_balance' => 0,
            'attn_name' => 'Contact Person',
            'attn_contact' => '0131111000',
            'billing_address' => '12 Jalan Pasar',
            'billing_postcode' => '50000',
            'billing_state' => 'Kuala Lumpur',
            'shipping_address' => '12 Jalan Pasar',
            'shipping_postcode' => '50000',
            'shipping_state' => 'Kuala Lumpur',
            'price_permission' => true,
            'invoice_visibility' => true,
            'invoice_price_permission' => true,
            'default_driver_id' => $driver->id,
        ];

        return User::updateOrCreate(
            ['login_code' => $attrs['login_code']],
            array_merge($defaults, $attrs)
        );
    }

    private function invoice(
        User $customer,
        Driver $driver,
        string $invoiceNumber,
        float $total,
        float $paid,
        string $paymentStatus,
        string $status,
        $dueDate
    ): void {
        Order::updateOrCreate(
            ['invoice_number' => $invoiceNumber],
            [
                'user_id' => $customer->id,
                'order_type' => Order::$order_types['registered'],
                'total_price' => $total,
                'subtotal' => $total,
                'delivery_fee' => 0,
                'attn_name' => $customer->attn_name,
                'attn_contact' => $customer->attn_contact,
                'billing_address' => $customer->billing_address,
                'billing_postcode' => $customer->billing_postcode,
                'billing_state' => $customer->billing_state,
                'shipping_address' => $customer->shipping_address,
                'shipping_postcode' => $customer->shipping_postcode,
                'shipping_state' => $customer->shipping_state,
                'payment_method' => $customer->isCreditCustomer() ? 'term' : 'cod',
                'payment_status' => $paymentStatus,
                'paid_amount' => $paid,
                'payment_due_date' => $dueDate ? $dueDate->toDateString() : null,
                'status' => $status,
                'driver_id' => $driver->id,
                'do_no' => 'DO-' . substr($invoiceNumber, 4),
                'do_date' => now()->toDateString(),
            ]
        );
    }
}
