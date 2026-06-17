<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'is_general',
        'order_type',
        'walk_in_name',
        'walk_in_phone',
        'cart_id',
        'total_price',
        'subtotal',
        'delivery_fee',
        'amount_adjustment',
        'adjustment_remark',
        'attn_name',
        'attn_contact',
        'area',
        'billing_address',
        'billing_city',
        'billing_postcode',
        'billing_state',
        'shipping_address',
        'shipping_city',
        'shipping_postcode',
        'shipping_state',
        'payment_method',
        'payment_due_date',
        'payment_status',
        'paid_amount',
        'invoice_number',
        'autocount_sync_status',
        'autocount_synced_at',
        'is_estimated',
        'completed_at',
        'transfer_slip',
        'status',
        'driver_id',
        'delivery_slot_id',
        'delivery_date',
        'delivery_time_slot',
        'order_weight',
        'do_no',
        'do_date',
        'payment_proof',
        'payment_collected_at',
        'payment_collected_by',
    ];

    protected $casts = [
        'payment_due_date' => 'date',
        'delivery_date' => 'date',
        'completed_at' => 'datetime',
        'is_estimated' => 'boolean',
    ];

    public static $path = 'orders';

    public static $attribute_rules = [
        'attn_name' => ['nullable', 'string', 'max:30'],
        'attn_contact' => ['nullable', 'string', 'max:30'],
        'billing_address' => ['required', 'string', 'max:100'],
        'billing_postcode' => ['required', 'string', 'max:5'],
        'billing_state' => ['required', 'string', 'max:30'],
        'shipping_address' => ['nullable', 'string', 'max:100'],
        'shipping_postcode' => ['nullable', 'string', 'max:5'],
        'shipping_state' => ['nullable', 'string', 'max:30'],
        'payment_method' => ['required'],
        'transfer_slip' => ['nullable', 'required_if:payment_method,bank-transfer', 'mimes:jpg,jpeg,png', 'max:4096'],
    ];

    public static $status = [
        'pending' => 'pending',
        'customer_reviewing' => 'customer_reviewing',
        'in_route' => 'in_route',
        'delivered' => 'delivered',
        'paid_completed' => 'paid_completed',
        'cancelled' => 'cancelled',
    ];

    public static $payment_status = [
        'unpaid' => 'unpaid',
        'partial' => 'partial',
        'paid' => 'paid',
        'payment_due' => 'payment_due',
    ];

    public static $order_types = [
        'registered' => 'registered',
        'walk_in' => 'walk_in',
        'public' => 'public',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class, 'driver_id', 'id');
    }

    public function products()
    {
        return $this->hasMany(OrderProduct::class, 'order_id', 'id');
    }

    public function deliverySlot()
    {
        return $this->belongsTo(DeliverySlot::class);
    }

    public function payments()
    {
        return $this->hasMany(OrderPayment::class);
    }

    public function orderProducts()
    {
        return $this->hasMany(OrderProduct::class);
    }

    public function isCreditCustomer(): bool
    {
        return $this->customer !== null
            && ($this->customer->customer_type ?? 'cod') === 'credit';
    }

    public function balanceDue(): float
    {
        return max(0, (float) $this->total_price - (float) $this->paid_amount);
    }

    public function paymentCollected(): bool
    {
        return (float) $this->paid_amount > 0;
    }

    public function canShowInvoice(): bool
    {
        return $this->paymentCollected();
    }

    public function canShowInvoiceToCustomer(?User $user): bool
    {
        if (!$user || !$user->invoice_visibility) {
            return false;
        }

        return $this->canShowInvoice();
    }

    public function canShowDeliveryOrder(): bool
    {
        return in_array($this->status, [
            self::$status['in_route'],
            self::$status['delivered'],
            self::$status['paid_completed'],
        ], true);
    }

    public static function canAdjustQuantities(string $status): bool
    {
        return in_array($status, [
            self::$status['pending'],
            self::$status['customer_reviewing'],
        ], true);
    }

    public static function canEditDeliveryFee(string $status): bool
    {
        return in_array($status, [
            self::$status['pending'],
            self::$status['customer_reviewing'],
            self::$status['in_route'],
        ], true);
    }

    public function paymentProofUrl(?string $filename): ?string
    {
        if (!$filename) {
            return null;
        }

        return url('/admin/orders/' . $this->id . '/payment-proof/' . $filename);
    }

    /**
     * Customer details for PDF documents (registered, walk-in, or public orders).
     */
    public function pdfCustomer(): object
    {
        if ($this->customer) {
            return $this->customer;
        }

        return (object) [
            'name' => $this->walk_in_name ?: 'Walk-in Customer',
            'attn_contact' => $this->walk_in_phone ?: $this->attn_contact,
            'sql_customer_code' => null,
            'fax_no' => null,
            'invoice_price_permission' => true,
        ];
    }
}
