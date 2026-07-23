<?php

namespace App;

use App\Services\OrderService;
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
        'contact_method',
        'wechat_id',
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
        'api_do_id',
        'api_invoice_id',
        'is_estimated',
        'completed_at',
        'transfer_slip',
        'pickup_proof',
        'pickup_confirmed_at',
        'pickup_confirmed_by',
        'delivery_proof',
        'delivery_confirmed_at',
        'status',
        'driver_id',
        'fulfillment_type',
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
        'pickup_confirmed_at' => 'datetime',
        'is_estimated' => 'boolean',
    ];

    public static $path = 'orders';

    public static $attribute_rules = [
        'attn_name' => ['nullable', 'string', 'max:30'],
        'attn_contact' => ['nullable', 'string', 'max:30'],
        'contact_method' => ['required', 'in:whatsapp,wechat'],
        'wechat_id' => ['nullable', 'required_if:contact_method,wechat', 'string', 'max:100'],
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
        'packing' => 'packing',
        'handed_to_customer' => 'handed_to_customer',
        'in_route' => 'in_route',
        'delivered' => 'delivered',
        'completed' => 'completed',
        'cancelled' => 'cancelled',
    ];

    public static $payment_status = [
        'unpaid' => 'unpaid',
        'pending' => 'pending',
        'partial' => 'partial',
        'paid' => 'paid',
        'payment_due' => 'payment_due',
    ];

    public static $order_types = [
        'registered' => 'registered',
        'walk_in' => 'walk_in',
        'public' => 'public',
        'pos' => 'pos',
    ];

    public function isPosOrder(): bool
    {
        return $this->order_type === self::$order_types['pos'];
    }

    public function isWalkInOrder(): bool
    {
        return $this->order_type === self::$order_types['walk_in'];
    }

    public function isPublicOrder(): bool
    {
        return $this->order_type === self::$order_types['public'];
    }

    /**
     * Orders with no registered customer account that post to a generic AutoCount debtor.
     */
    public function usesGenericWalkInDebtor(): bool
    {
        if ($this->isWalkInOrder() || $this->isPublicOrder()) {
            return true;
        }

        return $this->isPosOrder() && !$this->user_id;
    }

    public function genericWalkInDebtorCode(): ?string
    {
        if (!$this->usesGenericWalkInDebtor()) {
            return null;
        }

        $typeCode = config('autocount.walk_in_debtor_codes.' . $this->order_type);
        $code = trim((string) ($typeCode ?: config('autocount.walk_in_debtor_code', '')));

        if ($code === '' || strcasecmp($code, '300-0000') === 0) {
            return null;
        }

        return $code;
    }

    public function isInStoreOrder(): bool
    {
        if ($this->isPosOrder() || $this->isWalkInOrder()) {
            return true;
        }

        return $this->order_type === self::$order_types['registered']
            && $this->isPickup()
            && !$this->delivery_slot_id;
    }

    public function isPickupFulfillmentOrder(): bool
    {
        return $this->isPickup() && !$this->isInStoreOrder();
    }

    public function isFulfilled(): bool
    {
        return in_array($this->status, [
            self::$status['delivered'],
            self::$status['handed_to_customer'],
            self::$status['completed'],
        ], true);
    }

    public static $contact_methods = [
        'whatsapp' => 'whatsapp',
        'wechat' => 'wechat',
    ];

    public static $fulfillment_types = [
        'delivery' => 'delivery',
        'pickup' => 'pickup',
    ];

    public function isDelivery(): bool
    {
        return ($this->fulfillment_type ?? self::$fulfillment_types['delivery']) === self::$fulfillment_types['delivery'];
    }

    public function isPickup(): bool
    {
        return ($this->fulfillment_type ?? self::$fulfillment_types['delivery']) === self::$fulfillment_types['pickup'];
    }

    public function fulfillmentTypeLabel(): string
    {
        $key = 'order.fulfillment_types.' . ($this->fulfillment_type ?? 'delivery');
        $label = __($key);

        return $label !== $key ? $label : ucfirst($this->fulfillment_type ?? 'delivery');
    }

    public function contactMethodLabel(): string
    {
        $method = $this->contact_method ?? self::$contact_methods['whatsapp'];
        $key = 'orders.contact_method.' . $method;
        $label = __($key);

        return $label !== $key ? $label : ucfirst($method);
    }

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

    public function autocountSyncLogs()
    {
        return $this->hasMany(AutoCountSyncLog::class);
    }

    public function latestAutoCountSyncError(): ?string
    {
        $log = $this->autocountSyncLogs()
            ->where('sync_status', 'sync_error')
            ->latest('id')
            ->first();

        return $log?->error_message ?: $log?->response_message;
    }

    public function paymentBreakdown(): array
    {
        return $this->payments()
            ->where('status', OrderPayment::STATUS_CONFIRMED)
            ->selectRaw('payment_method, SUM(amount) as total_amount')
            ->groupBy('payment_method')
            ->pluck('total_amount', 'payment_method')
            ->map(fn ($amount) => (float) $amount)
            ->all();
    }

    public function paymentMethodsLabel(): string
    {
        $breakdown = $this->paymentBreakdown();

        if (empty($breakdown)) {
            return '-';
        }

        $parts = [];
        foreach ($breakdown as $method => $amount) {
            $label = OrderPayment::paymentMethodLabel($method) ?? $method;
            $parts[] = $label . ' RM ' . number_format($amount, 2);
        }

        return implode(' + ', $parts);
    }

    public function preferredPaymentMethodLabel(): ?string
    {
        return OrderPayment::paymentMethodLabel($this->payment_method);
    }

    public function hasCodDeliveryPreference(): bool
    {
        return $this->isCodCustomer()
            && in_array($this->payment_method, OrderPayment::codDeliveryPreferenceKeys(), true);
    }

    public function orderProducts()
    {
        return $this->hasMany(OrderProduct::class);
    }

    public function isCreditCustomer(): bool
    {
        if ($this->user_id) {
            $this->loadMissing('customer');
        }

        return $this->customer !== null
            && strtolower((string) ($this->customer->customer_type ?? 'cod')) === 'credit';
    }

    public function paysInStore(): bool
    {
        return ($this->payment_method ?? null) === User::$payment_method['in-store'];
    }

    public function shouldAutoApplyCredit(): bool
    {
        if (!$this->user_id || !$this->isCreditCustomer()) {
            return false;
        }

        return !$this->paysInStore();
    }

    public function isCodCustomer(): bool
    {
        return !$this->isCreditCustomer();
    }

    public function driverCustomerTypeLabel(): string
    {
        return $this->isCreditCustomer()
            ? __('driver_portal.customers.credit')
            : __('driver_portal.customers.cod');
    }

    public function allowsOverpayment(): bool
    {
        return $this->isCreditCustomer();
    }

    public function requiresExactPayment(): bool
    {
        return $this->isCodCustomer() || $this->paysInStore();
    }

    public function customerType(): string
    {
        return $this->isCreditCustomer() ? 'credit' : 'cod';
    }

    public function allowedAdminPaymentMethods(): array
    {
        return OrderPayment::adminMethodsFor($this->customerType());
    }

    public function allowedCustomerPaymentMethods(): array
    {
        return OrderPayment::customerSubmitMethodsFor($this->customerType());
    }

    public function canConfirmPickup(): bool
    {
        return $this->isPickupFulfillmentOrder()
            && $this->status === self::$status['packing'];
    }

    public function pickupProofUrl(): ?string
    {
        if (!$this->pickup_proof) {
            return null;
        }

        return route('admin.orders.pickup-proof', [$this->id, $this->pickup_proof]);
    }

    public function deliveryProofUrl(): ?string
    {
        if (!$this->delivery_proof) {
            return null;
        }

        return route('admin.orders.delivery-proof', [$this->id, $this->delivery_proof]);
    }

    public function canRecordAdminPayment(): bool
    {
        if ($this->status === self::$status['cancelled'] || $this->balanceDue() <= 0) {
            return false;
        }

        if ($this->isInStoreOrder()) {
            return $this->status === self::$status['handed_to_customer'];
        }

        if ($this->isPickupFulfillmentOrder()) {
            if ($this->isCodCustomer()) {
                return $this->status === self::$status['delivered'];
            }

            return in_array($this->status, [
                self::$status['packing'],
                self::$status['delivered'],
            ], true);
        }

        if ($this->isCodCustomer()) {
            return in_array($this->status, [
                self::$status['in_route'],
                self::$status['delivered'],
            ], true);
        }

        return in_array($this->status, [
            self::$status['packing'],
            self::$status['in_route'],
            self::$status['delivered'],
        ], true);
    }

    /**
     * A confirmed online gateway payment (Revenue Monster) is authoritative:
     * the customer has already paid real money, so it must be recorded no
     * matter where the order sits in fulfilment (customers may "pay now" up
     * front). Unlike manual admin collection — which is gated by delivery
     * status via canRecordAdminPayment() — the only order we must not settle
     * against is a cancelled one, which should be refunded instead.
     */
    public function canSettleGatewayPayment(): bool
    {
        return $this->status !== self::$status['cancelled'] && $this->balanceDue() > 0;
    }

    public function balanceDue(): float
    {
        return max(0, (float) $this->total_price - (float) $this->paid_amount);
    }

    /** Ensure delivery orders have a DO number before drivers or PDFs reference them. */
    public function ensureDoNumber(): self
    {
        if ($this->fulfillment_type !== self::$fulfillment_types['delivery']) {
            return $this;
        }

        if (preg_match('/^DO-\d{6}-\d+$/', (string) $this->do_no)) {
            return $this;
        }

        return app(OrderService::class)->assignDoNumber($this);
    }

    public function paymentCollected(): bool
    {
        return (float) $this->paid_amount > 0;
    }

    public function isFullyPaid(): bool
    {
        if ($this->status === self::$status['cancelled']) {
            return false;
        }

        return $this->balanceDue() <= 0.009;
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

        return $this->isFullyPaid();
    }

    public function canShowDeliveryOrder(): bool
    {
        if ($this->isPickupFulfillmentOrder()) {
            return in_array($this->status, [
                self::$status['packing'],
                self::$status['delivered'],
            ], true);
        }

        return in_array($this->status, [
            self::$status['in_route'],
            self::$status['delivered'],
        ], true);
    }

    public function canSubmitPaymentProof(): bool
    {
        if ($this->status === self::$status['cancelled'] || $this->balanceDue() <= 0) {
            return false;
        }

        if ($this->isCodCustomer()) {
            return in_array($this->status, [
                self::$status['in_route'],
                self::$status['delivered'],
            ], true);
        }

        return in_array($this->status, [
            self::$status['packing'],
            self::$status['in_route'],
            self::$status['delivered'],
        ], true);
    }

    public static function canAdjustQuantities(string $status): bool
    {
        return in_array($status, [
            self::$status['pending'],
            self::$status['packing'],
        ], true);
    }

    public static function canDriverAdjustQuantities(string $status): bool
    {
        return in_array($status, [
            self::$status['in_route'],
            self::$status['delivered'],
            'delivering',
            'completed',
        ], true);
    }

    public static function canEditDeliveryFee(string $status): bool
    {
        return in_array($status, [
            self::$status['pending'],
            self::$status['packing'],
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
     * Customer details for PDF documents (registered or walk-in orders).
     */
    public function pdfCustomer(): object
    {
        if ($this->customer) {
            return $this->customer;
        }

        return (object) [
            'name' => $this->walk_in_name ?: ($this->attn_name ?: 'Walk-in Customer'),
            'attn_contact' => $this->walk_in_phone ?: $this->attn_contact,
            'sql_customer_code' => null,
            'fax_no' => null,
            'invoice_price_permission' => true,
        ];
    }

    public function autocountSyncStatusKey(): string
    {
        return $this->autocount_sync_status ?: 'pending';
    }

    public function paymentDueStatusKey(): string
    {
        if ($this->status === self::$status['cancelled'] || $this->isCodCustomer()) {
            return 'not_applicable';
        }

        if ($this->isFullyPaid()) {
            return 'paid';
        }

        if (!$this->payment_due_date) {
            return 'not_set';
        }

        $dueDate = $this->payment_due_date->toDateString();
        $today = now()->toDateString();

        if ($dueDate > $today) {
            return 'not_due';
        }

        if ($dueDate === $today) {
            return 'due_today';
        }

        return 'overdue';
    }
}
