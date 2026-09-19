<?php

namespace App;

use App\Services\OrderService;
use Illuminate\Database\Eloquent\Builder;
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
        'discount',
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
        'courier_proof',
        'courier_confirmed_at',
        'courier_confirmed_by',
        'delivery_proof',
        'delivery_confirmed_at',
        'status',
        'driver_id',
        'driver_assigned_at',
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
        'payment_held_at',
        'payment_held_by',
    ];

    protected $casts = [
        'payment_due_date' => 'date',
        'delivery_date' => 'date',
        'completed_at' => 'datetime',
        'pickup_confirmed_at' => 'datetime',
        'courier_confirmed_at' => 'datetime',
        'driver_assigned_at' => 'datetime',
        'payment_held_at' => 'datetime',
        'is_estimated' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $order) {
            if (!$order->isDirty('driver_id')) {
                return;
            }

            if ($order->driver_id) {
                $order->driver_assigned_at = now();
            } else {
                $order->driver_assigned_at = null;
            }
        });

        static::saving(function (self $order) {
            $order->flattenTotalDecimal();
        });
    }

    /**
     * First order id that participates in discount flattening. Orders with a
     * lower id keep their original cents and never accrue a discount, even when
     * edited later; orders from this id onward have their grand total floored to
     * whole ringgit.
     */
    public const DISCOUNT_EFFECTIVE_ORDER_ID = 310;

    /**
     * Whether the discount flattening applies to this order, decided by the
     * order's id. A brand-new, unsaved order has no id yet, so we look at the
     * next id the table will hand out.
     */
    public function discountFlatteningActive(): bool
    {
        $id = $this->id ?: ((int) static::max('id') + 1);

        return $id >= self::DISCOUNT_EFFECTIVE_ORDER_ID;
    }

    /**
     * Flatten the grand total's decimal: whenever the total changes, round it
     * down to whole ringgit and record the shaved cents as a discount so the
     * balance due lands on a clean amount. Only active for orders from
     * {@see self::DISCOUNT_EFFECTIVE_ORDER_ID} onward.
     */
    public function flattenTotalDecimal(): void
    {
        if (!$this->isDirty('total_price')) {
            return;
        }

        if (!$this->discountFlatteningActive()) {
            return;
        }

        $raw = (float) $this->total_price;

        if ($raw <= 0) {
            $this->discount = 0;

            return;
        }

        // Work in integer cents to avoid floating-point drift on the split.
        $rawCents = (int) round($raw * 100);
        $flooredCents = intdiv($rawCents, 100) * 100;

        $this->total_price = $flooredCents / 100;
        $this->discount = ($rawCents - $flooredCents) / 100;
    }

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
        'in_route' => 'in_route',
        'delivered' => 'delivered',
        'completed' => 'completed',
        'cancelled' => 'cancelled',
    ];

    public const LIST_STATUS_PENDING_PACKING = 'pending_packing';

    public const LIST_STATUS_ALL = 'all';

    public static function defaultListStatuses(): array
    {
        return [
            self::$status['pending'],
            self::$status['packing'],
        ];
    }

    public static function listStatusFilterKey(\Illuminate\Http\Request $request): string
    {
        if (!$request->has('status')) {
            return self::LIST_STATUS_PENDING_PACKING;
        }

        return (string) $request->input('status', self::LIST_STATUS_ALL);
    }

    public static function applyListStatusFilter($query, string $statusFilter): void
    {
        if ($statusFilter === self::LIST_STATUS_ALL) {
            return;
        }

        if ($statusFilter === self::LIST_STATUS_PENDING_PACKING) {
            $query->whereIn('status', self::defaultListStatuses());

            return;
        }

        $query->where('status', $statusFilter);
    }

    public function scopeFilterByAddressSearch($query, ?string $term)
    {
        $pattern = Helper::likePattern($term);
        if ($pattern === null) {
            return $query;
        }

        $addressColumns = [
            'area',
            'billing_address',
            'billing_city',
            'billing_postcode',
            'billing_state',
            'shipping_address',
            'shipping_city',
            'shipping_postcode',
            'shipping_state',
        ];

        return $query->where(function ($query) use ($pattern, $addressColumns) {
            foreach ($addressColumns as $column) {
                $query->orWhere('orders.' . $column, 'like', $pattern);
            }
        });
    }

    public static $payment_status = [
        'unpaid' => 'unpaid',
        'pending' => 'pending',
        'partial' => 'partial',
        'paid' => 'paid',
        'payment_due' => 'payment_due',
        // Goods delivered but payment deferred/held (COD equivalent of the
        // credit-customer "credit" holding state). Backed by orders.payment_held_at.
        'on_hold' => 'on_hold',
    ];

    public static $order_types = [
        'registered' => 'registered',
        'walk_in' => 'walk_in',
        'public' => 'public',
        'pos' => 'pos',
    ];

    // AutoCount invoice sync states an order can hold, in workflow order. Used to
    // populate the admin order listing's "Invoice Sync Status" filter. An order
    // now syncs straight to an Invoice (credit → 'synced') or Cash Sale
    // (COD → 'paid_synced'); the retired SO+DO pipeline's 'do_created' step is no
    // longer a workflow state, so it is not offered as a filter option (its label
    // is kept for any legacy order still parked in that state).
    public static $autocount_sync_statuses = [
        'pending' => 'pending',
        'pending_sync' => 'pending_sync',
        'synced' => 'synced',
        'paid_synced' => 'paid_synced',
        'sync_error' => 'sync_error',
        'skipped' => 'skipped',
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
        return $this->isPosOrder() || $this->isWalkInOrder();
    }

    public function isPickupFulfillmentOrder(): bool
    {
        return $this->isPickup() && !$this->isInStoreOrder();
    }

    public function isFulfilled(): bool
    {
        return in_array($this->status, [
            self::$status['delivered'],
            self::$status['completed'],
        ], true);
    }

    /** A confirmed "buy now, pay later" charge has been recorded on this order. */
    public function hasConfirmedCreditTermPayment(): bool
    {
        return $this->payments()
            ->where('status', OrderPayment::STATUS_CONFIRMED)
            ->where('payment_method', 'credit-term')
            ->exists();
    }

    /**
     * The credit customer still owes money on their account (negative balance),
     * so credit orders may not be completed until it is settled.
     */
    public function hasOutstandingCredit(): bool
    {
        if (!$this->isCreditCustomer()) {
            return false;
        }

        $this->loadMissing('customer');

        return (float) ($this->customer->credit_balance ?? 0) < -0.009;
    }

    /** Total confirmed "buy now, pay later" charges recorded on this order. */
    public function creditTermChargedAmount(): float
    {
        return (float) $this->payments()
            ->where('status', OrderPayment::STATUS_CONFIRMED)
            ->where('payment_method', 'credit-term')
            ->sum('amount');
    }

    /** Portion of this order's credit-term charge already settled on the ledger. */
    public function creditSettledAmount(): float
    {
        return (float) CustomerCreditLog::where('order_id', $this->id)
            ->where('type', 'credit_settlement')
            ->sum('amount');
    }

    /** Credit-term amount still owed on this specific order. */
    public function creditOutstandingAmount(): float
    {
        return max(0, round($this->creditTermChargedAmount() - $this->creditSettledAmount(), 2));
    }

    /**
     * Only an order carried on the credit account (settled by a credit-term
     * charge) must wait for its own credit-term amount to be settled before it
     * can complete. Each order settles independently, so other unsettled orders
     * on the same customer do not block it.
     */
    public function requiresCreditSettlementBeforeComplete(): bool
    {
        if (!$this->hasConfirmedCreditTermPayment()) {
            return false;
        }

        return $this->creditOutstandingAmount() > 0.009;
    }

    /**
     * Delivered orders carrying a confirmed credit-term ("buy now, pay later")
     * charge — the orders on a credit customer's account awaiting settlement.
     * A full settlement completes them; unsettled ones stay delivered.
     */
    public function scopeCarriedOnCredit($query)
    {
        return $query->where('status', self::$status['delivered'])
            ->whereHas('payments', function ($q) {
                $q->where('status', OrderPayment::STATUS_CONFIRMED)
                    ->where('payment_method', 'credit-term');
            });
    }

    public function isCompleted(): bool
    {
        return $this->status === self::$status['completed'];
    }

    public function canSyncToAutoCount(): bool
    {
        // Credit customers: the invoice is pushed to AutoCount once the goods are
        // delivered. The balance is carried on the credit account and settled
        // later, so payment need not be complete at sync time.
        if ($this->isCreditCustomer()) {
            return $this->isFulfilled();
        }

        // Cash / COD customers: only once the order is completed and fully paid.
        return $this->isCompleted()
            && $this->payment_status === self::$payment_status['paid'];
    }

    public function canEditFulfillment(): bool
    {
        return $this->canShowFulfillmentPanel()
            && !in_array($this->status, [
                self::$status['completed'],
                self::$status['cancelled'],
            ], true);
    }

    public function canShowFulfillmentPanel(): bool
    {
        return $this->status !== self::$status['pending'];
    }

    public static $contact_methods = [
        'whatsapp' => 'whatsapp',
        'wechat' => 'wechat',
    ];

    public static $fulfillment_types = [
        'delivery' => 'delivery',
        'pickup' => 'pickup',
        'courier' => 'courier',
    ];

    public function isDelivery(): bool
    {
        return ($this->fulfillment_type ?? self::$fulfillment_types['delivery']) === self::$fulfillment_types['delivery'];
    }

    public function isPickup(): bool
    {
        return ($this->fulfillment_type ?? self::$fulfillment_types['delivery']) === self::$fulfillment_types['pickup'];
    }

    public function isCourier(): bool
    {
        return ($this->fulfillment_type ?? self::$fulfillment_types['delivery']) === self::$fulfillment_types['courier'];
    }

    public function requiresHandoverProof(): bool
    {
        return $this->allowsHandoverProofUpload();
    }

    /** Pickup and courier orders may attach a handover photo (registered and walk-in). */
    public function allowsHandoverProofUpload(): bool
    {
        if (!$this->isPickup() && !$this->isCourier()) {
            return false;
        }

        // POS counter sales are paid and handed over in one step.
        if ($this->isPosOrder()) {
            return false;
        }

        return true;
    }

    public function fulfillmentTypeLabel(): string
    {
        $key = 'order.fulfillment_types.' . ($this->fulfillment_type ?? 'delivery');
        $label = __($key);

        return $label !== $key ? $label : ucfirst($this->fulfillment_type ?? 'delivery');
    }

    /** Driver name for delivery; pickup / Lalamove labels for other fulfillment types (PDF meta). */
    public function pdfFulfillmentDisplayLabel(): string
    {
        if ($this->isPickup() || $this->isCourier()) {
            return $this->fulfillmentTypeLabel();
        }

        if ($this->isDelivery()) {
            $driver = $this->relationLoaded('driver') ? $this->driver : $this->driver()->first();
            if ($driver && trim((string) $driver->name) !== '') {
                return trim($driver->name);
            }
        }

        return $this->fulfillmentTypeLabel();
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
        // Use the eager-loaded relation when available (e.g. the order listing)
        // so a page of orders doesn't trigger a query per row.
        if ($this->relationLoaded('payments')) {
            return $this->payments
                ->where('status', OrderPayment::STATUS_CONFIRMED)
                ->where('settles_credit', false)
                ->groupBy('payment_method')
                ->map(fn ($group) => (float) $group->sum('amount'))
                ->all();
        }

        return $this->payments()
            ->where('status', OrderPayment::STATUS_CONFIRMED)
            ->where('settles_credit', false)
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

    /** Recorded (confirmed) payment methods only, no amounts — for the order listing. */
    public function recordedPaymentMethodsLabel(): string
    {
        $methods = array_keys($this->paymentBreakdown());

        if (empty($methods)) {
            return '-';
        }

        $labels = array_map(
            fn ($method) => OrderPayment::paymentMethodLabel($method) ?? $method,
            $methods
        );

        return implode(' + ', $labels);
    }

    public function preferredPaymentMethodLabel(): ?string
    {
        return $this->deliveryPaymentPreferenceLabel()
            ?? OrderPayment::paymentMethodLabel($this->payment_method);
    }

    public function hasDeliveryPaymentPreference(): bool
    {
        return in_array($this->payment_method ?? '', OrderPayment::codDeliveryPreferenceKeys(), true);
    }

    public function deliveryPaymentPreferenceLabel(): ?string
    {
        if (!$this->hasDeliveryPaymentPreference()) {
            return null;
        }

        $labelKey = 'orders.member.checkout.cod_methods.' . $this->payment_method;
        $label = __($labelKey);

        return $label !== $labelKey
            ? $label
            : OrderPayment::paymentMethodLabel($this->payment_method);
    }

    public function hasCodDeliveryPreference(): bool
    {
        return $this->isCodCustomer() && $this->hasDeliveryPaymentPreference();
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

    public function scopeForCreditCustomers(Builder $query): Builder
    {
        return $query->whereNotNull('orders.user_id')
            ->whereHas('customer', function (Builder $customerQuery) {
                $customerQuery->where('customer_type', 'credit');
            });
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
        return $this->paysInStore();
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

    public function canConfirmHandover(): bool
    {
        if (!$this->canEditFulfillment() || !$this->allowsHandoverProofUpload()) {
            return false;
        }

        if ($this->handoverProofFilename()) {
            return false;
        }

        if ($this->isPickup() || $this->isCourier()) {
            return in_array($this->status, [
                self::$status['packing'],
                self::$status['in_route'],
            ], true);
        }

        return false;
    }

    /** @deprecated Use canConfirmHandover() */
    public function canConfirmPickup(): bool
    {
        return $this->canConfirmHandover();
    }

    public function handoverProofFilename(): ?string
    {
        if (!$this->allowsHandoverProofUpload()) {
            return null;
        }

        return $this->courier_proof ?: $this->pickup_proof;
    }

    public function handoverProofUrl(): ?string
    {
        $filename = $this->handoverProofFilename();

        if (!$filename) {
            return null;
        }

        if ($this->isCourier() && $this->courier_proof === $filename) {
            return route('admin.orders.courier-proof', [$this->id, $filename]);
        }

        return route('admin.orders.pickup-proof', [$this->id, $filename]);
    }

    public function handoverConfirmedAt(): ?\Illuminate\Support\Carbon
    {
        if ($this->courier_confirmed_at) {
            return $this->courier_confirmed_at;
        }

        return $this->pickup_confirmed_at;
    }

    public function pickupProofUrl(): ?string
    {
        return $this->handoverProofUrl();
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

        if ($this->isCodCustomer()) {
            return in_array($this->status, [
                self::$status['packing'],
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
     * Whether a driver may record a manual payment in the driver portal.
     * Checkout payment preference (e.g. e-wallet) is reference only — when
     * online collection is available, the driver can also record what was
     * actually collected on delivery.
     */
    public function canRecordDriverPayment(): bool
    {
        if ($this->canRecordAdminPayment()) {
            return true;
        }

        return $this->isDelivery() && $this->canSettleGatewayPayment();
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

    /**
     * Total amount the customer still owes on this order for self-service
     * payment. Combines the uncovered order balance with any credit-term charge
     * still unsettled on the ledger — the latter reads as balanceDue()==0 even
     * though the money has not been received, so it must be counted here.
     */
    public function outstandingForCustomer(): float
    {
        return round($this->balanceDue() + $this->creditOutstandingAmount(), 2);
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

    /** Goods delivered but payment deferred — flagged on hold, not yet settled. */
    public function isPaymentOnHold(): bool
    {
        return $this->payment_status === self::$payment_status['on_hold'];
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
        return in_array($this->status, [
            self::$status['packing'],
            self::$status['in_route'],
            self::$status['delivered'],
        ], true);
    }

    /**
     * Admins may always view the delivery order regardless of status. The
     * customer-facing gate (canShowDeliveryOrder) stays restricted.
     */
    public function canAdminShowDeliveryOrder(): bool
    {
        return true;
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

    public function canAdminAdjustPricing(): bool
    {
        if ($this->status === self::$status['cancelled']) {
            return false;
        }

        if ($this->status === self::$status['completed']) {
            return false;
        }

        if ($this->isFullyPaid() || $this->payment_status === self::$payment_status['paid']) {
            return false;
        }

        return in_array($this->status, [
            self::$status['pending'],
            self::$status['packing'],
            self::$status['in_route'],
            self::$status['delivered'],
        ], true);
    }

    /**
     * Whether the admin order-edit form (reassign customer, edit
     * addresses/products) may be opened for this order. A cancelled order, or a
     * delivered order that is already fully paid, is closed to edits.
     */
    public function canAdminEditOrder(): bool
    {
        if ($this->status === self::$status['cancelled']) {
            return false;
        }

        if (in_array($this->status, [self::$status['delivered']], true) && $this->isFullyPaid()) {
            return false;
        }

        return true;
    }

    public static function canDriverAdjustQuantities(string $status): bool
    {
        return in_array($status, [
            self::$status['in_route'],
            'delivering',
        ], true);
    }

    public static function canEditDeliveryFee(string $status): bool
    {
        return in_array($status, [
            self::$status['pending'],
            self::$status['packing'],
            self::$status['in_route'],
            self::$status['delivered'],
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
