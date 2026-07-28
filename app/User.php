<?php

namespace App;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 
        'email', 
        'password', 
        'category', 
        'customer_type',
        'credit_balance',
        'payment_term_days',
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
        'login_code', 
        'registration_token',
        'registration_token_expires_at',
        'registration_completed_at',
        'remark', 
        'status', 
        'role_slug',
        'price_permission',
        'invoice_visibility',
        'invoice_price_permission',
        'default_driver_id',
        'sql_customer_code',
        'ssm',
        'tin_no',
        'autocount_sync_status',
        'autocount_synced_at',
        'fax_no',
        'locale',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'credit_balance' => 'decimal:2',
        'registration_token_expires_at' => 'datetime',
        'registration_completed_at' => 'datetime',
        'autocount_synced_at' => 'datetime',
    ];

    public static $attribute_rules = [
        'name' => ['required', 'unique:users', 'string', 'max:100'],
        'email' => ['nullable', 'email', 'max:100'],
        'password' => ['required', 'string'],
        'category' => ['nullable', 'string', 'max:30'],
        'attn_name' => ['nullable', 'string', 'max:30'],
        'attn_contact' => ['nullable', 'string', 'max:30'],
        'billing_address' => ['required', 'string', 'max:100'],
        'billing_postcode' => ['required', 'string', 'max:5'],
        'billing_state' => ['required', 'string', 'max:30'],
        'shipping_address' => ['nullable', 'string', 'max:100'],
        'shipping_postcode' => ['nullable', 'string', 'max:5'],
        'shipping_state' => ['nullable', 'string', 'max:30'],
        'payment_method' => ['required', 'array'],
        'remark' => ['nullable', 'string', 'max:500'],
        'fax_no' => ['nullable', 'string', 'max:20'],
        'ssm' => ['nullable', 'string', 'max:50'],
        'tin_no' => ['nullable', 'string', 'max:50'],
    ];

    public static $payment_method = [
        'cod' => 'cod',
        'term' => 'term',
        'in-store' => 'in-store',
        'bank-transfer' => 'bank-transfer',
        'e-wallet' => 'e-wallet',
    ];

    public static $user_status = [
        'active' => 'active',
        'locked' => 'locked',
        'terminated' => 'terminated',
    ];

    public function isCreditCustomer(): bool
    {
        return strtolower((string) ($this->customer_type ?? 'cod')) === 'credit';
    }

    public function isCodCustomer(): bool
    {
        return !$this->isCreditCustomer();
    }

    /** Payment methods available when admin creates an order for a registered customer. */
    public static function adminOrderPaymentMethodKeys(User $customer): array
    {
        return [
            $customer->isCreditCustomer() ? 'term' : 'cod',
            'in-store',
        ];
    }

    public static function walkInOrderPaymentMethodKeys(): array
    {
        return ['cod', 'in-store'];
    }

    public static function adminOrderPaymentMethodLabels(?User $customer): array
    {
        $keys = $customer
            ? self::adminOrderPaymentMethodKeys($customer)
            : self::walkInOrderPaymentMethodKeys();

        $labels = [];
        foreach ($keys as $key) {
            $labels[$key] = __('user.payment_method.' . $key);
        }

        return $labels;
    }

    public static function paymentTermOptions(): array
    {
        $options = __('customers.payment_term_options');

        return is_array($options) ? $options : [];
    }

    public function paymentTermDays(): int
    {
        if (!$this->isCreditCustomer()) {
            return 0;
        }

        $days = (int) ($this->payment_term_days ?? 30);

        return $days > 0 ? $days : 30;
    }

    public function paymentTermLabel(): string
    {
        if (!$this->isCreditCustomer()) {
            return __('customers.payment_term_not_applicable');
        }

        $options = static::paymentTermOptions();
        $days = $this->paymentTermDays();

        return $options[$days] ?? __('customers.payment_term_days_count', ['count' => $days]);
    }

    public function generateRegistrationToken(int $expiryDays = 7): string
    {
        do {
            $token = \App\Helper::generateRandomString(64);
        } while (static::where('registration_token', $token)->exists());

        $this->update([
            'registration_token' => $token,
            'registration_token_expires_at' => now()->addDays($expiryDays),
        ]);

        return $token;
    }

    public function registrationUrl(): ?string
    {
        if (!$this->registration_token) {
            return null;
        }

        return url('/register/' . \Illuminate\Support\Facades\Crypt::encryptString($this->registration_token));
    }

    public function hasCompletedRegistration(): bool
    {
        return (bool) $this->registration_completed_at;
    }

    public function isPendingRegistration(): bool
    {
        return !$this->hasCompletedRegistration() && (bool) $this->registration_token;
    }

    public function registrationTokenValid(): bool
    {
        if (!$this->registration_token) {
            return false;
        }

        if ($this->registration_token_expires_at && $this->registration_token_expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Orders (invoices) placed by this customer.
     */
    public function orders()
    {
        return $this->hasMany(Order::class, 'user_id', 'id');
    }

    /**
     * The delivery driver this customer is permanently assigned to.
     */
    public function defaultDriver()
    {
        return $this->belongsTo(Driver::class, 'default_driver_id', 'id');
    }

    public function drivers()
    {
        return $this->belongsToMany(Driver::class, 'customer_drivers', 'user_id', 'driver_id')
            ->withTimestamps()
            ->orderBy('drivers.name');
    }

    /**
     * @return array<int, int>
     */
    public function assignedDriverIds(): array
    {
        $ids = $this->drivers()->pluck('drivers.id')->map(static fn ($id) => (int) $id)->all();

        if (empty($ids) && $this->default_driver_id) {
            return [(int) $this->default_driver_id];
        }

        if ($this->default_driver_id && in_array((int) $this->default_driver_id, $ids, true)) {
            $defaultId = (int) $this->default_driver_id;

            return array_values(array_unique(array_merge(
                [$defaultId],
                array_values(array_diff($ids, [$defaultId]))
            )));
        }

        return $ids;
    }

    /**
     * @param  array<int|string>|null  $driverIds
     */
    public function syncDrivers(?array $driverIds): void
    {
        $driverIds = collect($driverIds ?? [])
            ->filter(static fn ($id) => $id !== null && $id !== '')
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $this->drivers()->sync($driverIds);
        $this->update(['default_driver_id' => $driverIds[0] ?? null]);
    }

    public const LOGIN_CODE_LENGTH = 10;

    public static function generateLoginCode(): string
    {
        do {
            $code = Helper::generateRandomString(self::LOGIN_CODE_LENGTH);
        } while (self::where('login_code', $code)->exists());

        return $code;
    }

    public function fastLoginUrl(): string
    {
        return url('fast-login/' . $this->login_code);
    }

    public function autocountSyncStatusKey(): string
    {
        return $this->autocount_sync_status ?: 'pending';
    }
}
