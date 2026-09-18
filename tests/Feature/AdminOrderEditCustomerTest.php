<?php

namespace Tests\Feature;

use App\Admin;
use App\CustomerCreditLog;
use App\Order;
use App\OrderPayment;
use App\Product;
use App\Services\CreditService;
use App\Services\OrderService;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The admin Order Edit page lets an admin change who an order belongs to:
 * pick the type (registered vs walk-in) first, then the customer. A walk-in
 * customer can be reused from a past walk-in entry or typed in fresh. Changing
 * the customer refreshes the order's identity and address/contact details.
 */
class AdminOrderEditCustomerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        return Admin::forceCreate([
            'name' => 'Boss',
            'username' => 'boss' . rand(1000, 9999),
            'email' => 'boss' . rand(1000, 9999) . '@example.com',
            'role' => 'superadmin',
            'password' => Hash::make('password'),
        ]);
    }

    private function makeCustomer(string $name, string $type = 'cod'): User
    {
        return User::forceCreate([
            'name' => $name,
            'email' => 'cust' . rand(1000, 9999) . '@example.com',
            'password' => Hash::make('password'),
            'category' => $type,
            'customer_type' => $type,
            'status' => 'active',
            'payment_method' => 'cod',
            'login_code' => 'code' . rand(1000, 9999),
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
            'shipping_address' => '1 Market St',
            'shipping_postcode' => '50000',
            'shipping_state' => 'WP',
        ]);
    }

    private function makeCreditCustomer(float $balance = 0): User
    {
        return User::forceCreate([
            'name' => 'Credit Co ' . rand(1000, 9999),
            'email' => 'credit' . rand(1000, 9999) . '@example.com',
            'password' => Hash::make('password'),
            'category' => 'credit',
            'customer_type' => 'credit',
            'credit_balance' => $balance,
            'status' => 'active',
            'payment_method' => json_encode(['term']),
            'login_code' => 'code' . rand(1000, 9999),
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
            'shipping_address' => '1 Market St',
            'shipping_postcode' => '50000',
            'shipping_state' => 'WP',
        ]);
    }

    private function makeRegisteredOrder(User $customer): Order
    {
        return Order::forceCreate([
            'user_id' => $customer->id,
            'order_type' => Order::$order_types['registered'],
            'total_price' => 30.00,
            'subtotal' => 30.00,
            'delivery_fee' => 0,
            'amount_adjustment' => 0,
            'order_weight' => 0,
            'paid_amount' => 0,
            'status' => 'pending',
            'fulfillment_type' => 'delivery',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
        ]);
    }

    private function makeWalkInOrder(string $name, ?string $phone = null): Order
    {
        return Order::forceCreate([
            'user_id' => null,
            'order_type' => Order::$order_types['walk_in'],
            'walk_in_name' => $name,
            'walk_in_phone' => $phone,
            'total_price' => 30.00,
            'subtotal' => 30.00,
            'delivery_fee' => 0,
            'amount_adjustment' => 0,
            'order_weight' => 0,
            'paid_amount' => 0,
            'status' => 'pending',
            'fulfillment_type' => 'pickup',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'billing_address' => 'Counter',
        ]);
    }

    private function editUrl(Order $order): string
    {
        return route('admin.orders.edit', encrypt($order->id));
    }

    private function updateUrl(Order $order): string
    {
        return route('admin.orders.update', encrypt($order->id));
    }

    /** @test */
    public function registered_order_edit_lists_customers_and_offers_the_walk_in_option(): void
    {
        $customer = $this->makeCustomer('Acme Seafood');
        $other = $this->makeCustomer('Beta Foods');
        $order = $this->makeRegisteredOrder($customer);

        $this->actingAs($this->admin(), 'web_admin')
            ->get($this->editUrl($order))
            ->assertOk()
            ->assertSee($customer->name)
            ->assertSee($other->name)
            // Crossing is allowed, so the walk-in switch is offered.
            ->assertSee('id="is_walk_in"', false)
            ->assertSee(__('orders.walk_in_customer'));
    }

    /** @test */
    public function edit_page_for_walk_in_order_preselects_walk_in_mode(): void
    {
        $order = $this->makeWalkInOrder('John Walkin', '0123456789');

        $this->actingAs($this->admin(), 'web_admin')
            ->get($this->editUrl($order))
            ->assertOk()
            ->assertSee('John Walkin')
            ->assertSee('0123456789');
    }

    /** @test */
    public function saving_a_product_line_without_a_remark_does_not_error(): void
    {
        // The remark input is optional and may be omitted per line; the update
        // must not blow up on a missing remark key.
        $customer = $this->makeCustomer('Acme Seafood');
        $order = $this->makeRegisteredOrder($customer);
        $product = Product::forceCreate([
            'name' => 'Prawn ' . rand(1000, 9999),
            'sku' => 'SKU' . rand(1000, 9999),
            'price' => 12.00,
            'status' => Product::$status['active'],
            'images' => json_encode(['prawn.jpg']),
            'sell_in' => Product::SELL_IN_QTY,
        ]);

        $this->actingAs($this->admin(), 'web_admin')
            ->post($this->updateUrl($order), [
                'customer_id' => $customer->id,
                'attn_name' => 'PIC',
                'attn_contact' => '0100000000',
                'payment_method' => 'cod',
                'billing_address' => '9 New Rd',
                'shipping_address' => '9 New Rd',
                'product_id' => [$product->id],
                'quantity' => [2],
                // no 'remark' key at all
            ])
            ->assertRedirect(route('admin.orders.summary', $order->id));

        $this->assertDatabaseHas('order_products', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'remark' => '',
            'status' => 'active',
        ]);
    }

    /** @test */
    public function admin_can_reassign_order_to_a_different_registered_customer(): void
    {
        $customer = $this->makeCustomer('Acme Seafood');
        $target = $this->makeCustomer('Beta Foods');
        $order = $this->makeRegisteredOrder($customer);

        $this->actingAs($this->admin(), 'web_admin')
            ->post($this->updateUrl($order), [
                'is_walk_in' => 0,
                'customer_id' => $target->id,
                'customer' => $target->id,
                'attn_name' => 'PIC',
                'attn_contact' => '0100000000',
                'payment_method' => 'cod',
                'billing_address' => '9 New Rd',
                'shipping_address' => '9 New Rd',
            ])
            ->assertRedirect(route('admin.orders.summary', $order->id));

        $order->refresh();
        $this->assertSame($target->id, $order->user_id);
        $this->assertSame(Order::$order_types['registered'], $order->order_type);
        $this->assertNull($order->walk_in_name);
    }

    /** @test */
    public function reassigning_a_credit_order_transfers_the_charge_to_the_new_credit_customer(): void
    {
        $from = $this->makeCreditCustomer();
        $to = $this->makeCreditCustomer();
        $order = $this->makeRegisteredOrder($from);

        // Buy-now-pay-later charge: the old customer owes 30 on this order.
        app(OrderService::class)->recordPayment($order->fresh(), 'credit-term', 30.00, null, null, null);
        $this->assertEqualsWithDelta(-30.00, (float) $from->fresh()->credit_balance, 0.001);

        $this->actingAs($this->admin(), 'web_admin')
            ->post($this->updateUrl($order), [
                'customer_id' => $to->id,
                'attn_name' => 'PIC',
                'attn_contact' => '0100000000',
                'payment_method' => 'term',
                'billing_address' => '1 Market St',
                'shipping_address' => '1 Market St',
            ])
            ->assertRedirect(route('admin.orders.summary', $order->id));

        $order->refresh();
        $this->assertSame($to->id, $order->user_id);
        // The charge moves: old customer restored, new customer now owes it.
        $this->assertEqualsWithDelta(0.00, (float) $from->fresh()->credit_balance, 0.001);
        $this->assertEqualsWithDelta(-30.00, (float) $to->fresh()->credit_balance, 0.001);
        // The credit-term payment is NOT voided — it stays with the order.
        $this->assertSame(1, OrderPayment::where('order_id', $order->id)
            ->where('payment_method', 'credit-term')
            ->where('status', OrderPayment::STATUS_CONFIRMED)
            ->count());
        $this->assertSame(1, CustomerCreditLog::where('order_id', $order->id)
            ->where('user_id', $to->id)
            ->where('type', 'credit_term')
            ->count());
    }

    /** @test */
    public function reassigning_a_credit_order_to_a_cod_customer_voids_the_charge(): void
    {
        $from = $this->makeCreditCustomer();
        $to = $this->makeCustomer('Rui Han'); // COD — cannot carry a credit-term charge
        $order = $this->makeRegisteredOrder($from);

        app(OrderService::class)->recordPayment($order->fresh(), 'credit-term', 30.00, null, null, null);
        $this->assertEqualsWithDelta(-30.00, (float) $from->fresh()->credit_balance, 0.001);

        $this->actingAs($this->admin(), 'web_admin')
            ->post($this->updateUrl($order), [
                'customer_id' => $to->id,
                'attn_name' => 'PIC',
                'attn_contact' => '0100000000',
                'payment_method' => 'cod',
                'billing_address' => '9 New Rd',
                'shipping_address' => '9 New Rd',
            ])
            ->assertRedirect(route('admin.orders.summary', $order->id));

        $order->refresh();
        $this->assertSame($to->id, $order->user_id);
        $this->assertEqualsWithDelta(0.00, (float) $from->fresh()->credit_balance, 0.001);
        // No credit account to carry it, so the charge is voided.
        $this->assertSame(0, OrderPayment::where('order_id', $order->id)
            ->where('payment_method', 'credit-term')
            ->where('status', OrderPayment::STATUS_CONFIRMED)
            ->count());
    }

    /** @test */
    public function reassigning_between_cod_customers_does_not_touch_the_credit_ledger(): void
    {
        $from = $this->makeCustomer('Acme Seafood');
        $to = $this->makeCustomer('Beta Foods');
        $order = $this->makeRegisteredOrder($from);

        $this->actingAs($this->admin(), 'web_admin')
            ->post($this->updateUrl($order), [
                'customer_id' => $to->id,
                'attn_name' => 'PIC',
                'attn_contact' => '0100000000',
                'payment_method' => 'cod',
                'billing_address' => '9 New Rd',
                'shipping_address' => '9 New Rd',
            ])
            ->assertRedirect(route('admin.orders.summary', $order->id));

        $this->assertSame(0, CustomerCreditLog::where('order_id', $order->id)->count());
    }

    /** @test */
    public function editing_without_changing_the_customer_posts_no_reversal(): void
    {
        $customer = $this->makeCreditCustomer();
        $order = $this->makeRegisteredOrder($customer);
        app(OrderService::class)->recordPayment($order->fresh(), 'credit-term', 30.00, null, null, null);

        $this->actingAs($this->admin(), 'web_admin')
            ->post($this->updateUrl($order), [
                'customer_id' => $customer->id, // unchanged
                'attn_name' => 'PIC',
                'attn_contact' => '0100000000',
                'payment_method' => 'term',
                'billing_address' => '1 Market St',
                'shipping_address' => '1 Market St',
            ])
            ->assertRedirect(route('admin.orders.summary', $order->id));

        // Charge stays put; nothing moved.
        $this->assertEqualsWithDelta(-30.00, (float) $customer->fresh()->credit_balance, 0.001);
        $this->assertSame(0, CustomerCreditLog::where('order_id', $order->id)
            ->where('type', 'credit_reversal')
            ->count());
    }

    /** @test */
    public function the_charge_follows_the_order_across_a_chain_of_reassignments(): void
    {
        $a = $this->makeCreditCustomer();
        $b = $this->makeCreditCustomer();
        $c = $this->makeCreditCustomer();
        $order = $this->makeRegisteredOrder($a);
        app(OrderService::class)->recordPayment($order->fresh(), 'credit-term', 30.00, null, null, null);

        $service = app(CreditService::class);
        $service->reassignOrderCredit($order->fresh(), $a->fresh(), $b->fresh(), null); // a -> b
        $service->reassignOrderCredit($order->fresh(), $b->fresh(), $c->fresh(), null); // b -> c

        // Only the final owner carries the charge; the rest are back to zero.
        $this->assertEqualsWithDelta(0.00, (float) $a->fresh()->credit_balance, 0.001);
        $this->assertEqualsWithDelta(0.00, (float) $b->fresh()->credit_balance, 0.001);
        $this->assertEqualsWithDelta(-30.00, (float) $c->fresh()->credit_balance, 0.001);
    }

    /** @test */
    public function reassigns_using_the_hidden_customer_id_when_the_select_is_absent(): void
    {
        // At submit the select2 dropdown is disabled (product step), so only the
        // JS-synced hidden customer_id is posted. Reassignment must still work.
        $customer = $this->makeCustomer('Acme Seafood');
        $target = $this->makeCustomer('Rui Han');
        $order = $this->makeRegisteredOrder($customer);

        $this->actingAs($this->admin(), 'web_admin')
            ->post($this->updateUrl($order), [
                'customer_id' => $target->id, // no 'customer' key — select disabled
                'attn_name' => 'PIC',
                'attn_contact' => '0100000000',
                'payment_method' => 'cod',
                'billing_address' => '9 New Rd',
                'shipping_address' => '9 New Rd',
            ])
            ->assertRedirect(route('admin.orders.summary', $order->id));

        $order->refresh();
        $this->assertSame($target->id, $order->user_id);
    }

    /** @test */
    public function admin_can_convert_a_registered_order_to_a_walk_in(): void
    {
        $customer = $this->makeCustomer('Acme Seafood');
        $order = $this->makeRegisteredOrder($customer);

        $this->actingAs($this->admin(), 'web_admin')
            ->post($this->updateUrl($order), [
                'is_walk_in' => 1,
                'walk_in_name' => 'Cash Buyer',
                'walk_in_phone' => '0198887777',
                'attn_name' => 'Cash Buyer',
                'attn_contact' => '0198887777',
                'payment_method' => 'cod',
                'billing_address' => '',
                'shipping_address' => '',
            ])
            ->assertRedirect(route('admin.orders.summary', $order->id));

        $order->refresh();
        $this->assertNull($order->user_id);
        $this->assertSame(Order::$order_types['walk_in'], $order->order_type);
        $this->assertSame('Cash Buyer', $order->walk_in_name);
        $this->assertSame('0198887777', $order->walk_in_phone);
    }

    /** @test */
    public function admin_can_assign_a_walk_in_order_to_a_registered_customer(): void
    {
        $target = $this->makeCustomer('Acme Seafood');
        $order = $this->makeWalkInOrder('Cash Buyer', '0198887777');

        $this->actingAs($this->admin(), 'web_admin')
            ->post($this->updateUrl($order), [
                'is_walk_in' => 0,
                'customer_id' => $target->id,
                'customer' => $target->id,
                'attn_name' => 'PIC',
                'attn_contact' => '0100000000',
                'payment_method' => 'cod',
                'billing_address' => '9 New Rd',
                'shipping_address' => '9 New Rd',
            ])
            ->assertRedirect(route('admin.orders.summary', $order->id));

        $order->refresh();
        $this->assertSame($target->id, $order->user_id);
        $this->assertSame(Order::$order_types['registered'], $order->order_type);
        $this->assertNull($order->walk_in_name);
        $this->assertNull($order->walk_in_phone);
    }

    /** @test */
    public function admin_can_rename_a_walk_in_order(): void
    {
        $order = $this->makeWalkInOrder('Old Name', '0111111111');

        $this->actingAs($this->admin(), 'web_admin')
            ->post($this->updateUrl($order), [
                'is_walk_in' => 1,
                'walk_in_name' => 'New Name',
                'walk_in_phone' => '0122223333',
                'attn_name' => 'New Name',
                'attn_contact' => '0122223333',
                'payment_method' => 'cod',
                'billing_address' => '',
                'shipping_address' => '',
            ])
            ->assertRedirect(route('admin.orders.summary', $order->id));

        $order->refresh();
        $this->assertNull($order->user_id);
        $this->assertSame(Order::$order_types['walk_in'], $order->order_type);
        $this->assertSame('New Name', $order->walk_in_name);
        $this->assertSame('0122223333', $order->walk_in_phone);
    }

    /** @test */
    public function converting_to_a_walk_in_requires_a_name(): void
    {
        $order = $this->makeWalkInOrder('Old Name', '0111111111');

        $this->actingAs($this->admin(), 'web_admin')
            ->post($this->updateUrl($order), [
                'is_walk_in' => 1,
                'walk_in_name' => '',
                'payment_method' => 'cod',
            ])
            ->assertSessionHasErrors('walk_in_name');

        $order->refresh();
        $this->assertSame('Old Name', $order->walk_in_name);
    }

    /** @test */
    public function converting_a_credit_order_to_walk_in_voids_the_charge_and_restores_the_customer(): void
    {
        $customer = $this->makeCreditCustomer();
        $order = $this->makeRegisteredOrder($customer);
        app(OrderService::class)->recordPayment($order->fresh(), 'credit-term', 30.00, null, null, null);
        $this->assertEqualsWithDelta(-30.00, (float) $customer->fresh()->credit_balance, 0.001);

        $this->actingAs($this->admin(), 'web_admin')
            ->post($this->updateUrl($order), [
                'is_walk_in' => 1,
                'walk_in_name' => 'Cash Buyer',
                'walk_in_phone' => '0198887777',
                'attn_name' => 'Cash Buyer',
                'attn_contact' => '0198887777',
                'payment_method' => 'cod',
                'billing_address' => '',
                'shipping_address' => '',
            ])
            ->assertRedirect(route('admin.orders.summary', $order->id));

        $order->refresh();
        $this->assertNull($order->user_id);
        // No credit account carries the order, so the charge is reversed + voided.
        $this->assertEqualsWithDelta(0.00, (float) $customer->fresh()->credit_balance, 0.001);
        $this->assertSame(0, OrderPayment::where('order_id', $order->id)
            ->where('payment_method', 'credit-term')
            ->where('status', OrderPayment::STATUS_CONFIRMED)
            ->count());
    }

    /** @test */
    public function walk_in_search_returns_distinct_past_entries_matching_the_term(): void
    {
        // Two orders from the same walk-in customer must collapse to one entry.
        $this->makeWalkInOrder('John Tan', '0111111111');
        $this->makeWalkInOrder('John Tan', '0111111111');
        $this->makeWalkInOrder('Mary Lee', '0122222222');

        $response = $this->actingAs($this->admin(), 'web_admin')
            ->getJson(route('admin.orders.walk-in-search', ['q' => 'john']))
            ->assertOk()
            ->assertJson(['success' => true]);

        $results = $response->json('results');
        $this->assertCount(1, $results);
        $this->assertSame('John Tan', $results[0]['name']);
        $this->assertSame('0111111111', $results[0]['phone']);
    }

    /** @test */
    public function summary_shows_edit_button_for_an_editable_order(): void
    {
        // encrypt() is non-deterministic, so assert on the stable path prefix.
        $order = $this->makeRegisteredOrder($this->makeCustomer('Acme Seafood'));

        $this->actingAs($this->admin(), 'web_admin')
            ->get(route('admin.orders.summary', $order->id))
            ->assertOk()
            ->assertSee('/admin/order/edit/', false);
    }

    /** @test */
    public function summary_hides_edit_button_for_a_cancelled_order(): void
    {
        $order = $this->makeRegisteredOrder($this->makeCustomer('Acme Seafood'));
        $order->update(['status' => 'cancelled']);

        $this->actingAs($this->admin(), 'web_admin')
            ->get(route('admin.orders.summary', $order->id))
            ->assertOk()
            ->assertDontSee('/admin/order/edit/', false);
    }

    /** @test */
    public function delivered_fully_paid_order_is_not_editable(): void
    {
        $editable = new Order(['status' => 'delivered', 'total_price' => 30, 'paid_amount' => 0]);
        $settled = new Order(['status' => 'delivered', 'total_price' => 30, 'paid_amount' => 30]);
        $cancelled = new Order(['status' => 'cancelled', 'total_price' => 30, 'paid_amount' => 0]);

        $this->assertTrue($editable->canAdminEditOrder());
        $this->assertFalse($settled->canAdminEditOrder());
        $this->assertFalse($cancelled->canAdminEditOrder());
    }

    /** @test */
    public function opening_edit_for_a_closed_order_redirects_to_summary(): void
    {
        $customer = $this->makeCustomer('Acme Seafood');
        $order = $this->makeRegisteredOrder($customer);
        $order->update(['status' => 'cancelled']);

        $this->actingAs($this->admin(), 'web_admin')
            ->get($this->editUrl($order))
            ->assertRedirect(route('admin.orders.summary', $order->id));
    }

    /** @test */
    public function walk_in_search_without_term_lists_recent_entries(): void
    {
        $this->makeWalkInOrder('John Tan', '0111111111');
        $this->makeWalkInOrder('Mary Lee', '0122222222');

        $results = $this->actingAs($this->admin(), 'web_admin')
            ->getJson(route('admin.orders.walk-in-search'))
            ->assertOk()
            ->json('results');

        $names = array_column($results, 'name');
        $this->assertContains('John Tan', $names);
        $this->assertContains('Mary Lee', $names);
    }
}
