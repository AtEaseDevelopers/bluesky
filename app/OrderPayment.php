<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OrderPayment extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'order_id',
        'payment_method',
        'amount',
        'status',
        'payment_proof',
        'recorded_by',
        'recorded_by_driver',
        'submitted_by_user_id',
        'bulk_payment_id',
        'notes',
    ];

    public static $payment_methods = [
        'cash' => 'Cash',
        'qr' => 'QR',
        'bank-transfer' => 'Bank Transfer',
        'e-wallet' => 'E-Wallet',
        'payment-gateway' => 'Payment Gateway',
        'credit-term' => 'Credit Term',
        'customer-credit' => 'Customer Credit',
        'cod' => 'COD',
        'in-store' => 'In-Store Payment',
    ];

    /** Admin-recorded payments at delivery / collection (COD customers). */
    public static $cod_admin_methods = [
        'cash' => 'Cash',
        'qr' => 'QR',
        'bank-transfer' => 'Bank Transfer',
        'cod' => 'COD',
        'in-store' => 'In-Store Payment',
    ];

    /** Admin-recorded payments (credit customers — may pay after delivery). */
    public static $credit_admin_methods = [
        'bank-transfer' => 'Bank Transfer',
        'e-wallet' => 'E-Wallet',
        'qr' => 'QR',
        'payment-gateway' => 'Payment Gateway',
        'credit-term' => 'Credit Term',
        'cash' => 'Cash',
        'in-store' => 'In-Store Payment',
    ];

    /** Customer-uploaded proof at delivery (COD). */
    public static $cod_customer_methods = [
        'cash' => 'Cash',
        'qr' => 'QR',
    ];

    /** Customer-uploaded proof (credit — pay by due date). */
    public static $credit_customer_methods = [
        'bank-transfer' => 'Bank Transfer',
        'e-wallet' => 'E-Wallet',
        'payment-gateway' => 'Payment Gateway',
        'credit-term' => 'Credit Term',
    ];

    /** @deprecated Use adminMethodsFor / customerSubmitMethodsFor */
    public static $customer_payment_methods = [];

    public static function adminMethodsFor(?string $customerType): array
    {
        return ($customerType === 'credit')
            ? self::$credit_admin_methods
            : self::$cod_admin_methods;
    }

    public static function customerSubmitMethodsFor(?string $customerType): array
    {
        return ($customerType === 'credit')
            ? self::$credit_customer_methods
            : self::$cod_customer_methods;
    }

    public static function customerTypeFromOrder(?Order $order): string
    {
        if (!$order || !$order->customer) {
            return 'cod';
        }

        return ($order->customer->customer_type ?? 'cod') === 'credit' ? 'credit' : 'cod';
    }

    public static $status_labels = [
        self::STATUS_PENDING => 'Pending Review',
        self::STATUS_CONFIRMED => 'Confirmed',
        self::STATUS_REJECTED => 'Rejected',
    ];

    /** Max upload size in kilobytes (4 MB). */
    public const PROOF_MAX_KB = 4096;

    public static $proof_mimes = ['jpg', 'jpeg', 'png', 'pdf'];

    public static function proofRules(bool $required = true): array
    {
        $rules = ['file', 'mimes:' . implode(',', self::$proof_mimes), 'max:' . self::PROOF_MAX_KB];

        array_unshift($rules, $required ? 'required' : 'nullable');

        return $rules;
    }

    public static function proofAcceptAttribute(): string
    {
        return 'image/jpeg,image/png,application/pdf,.jpg,.jpeg,.png,.pdf';
    }

    public static function proofHelpText(): string
    {
        return 'JPG, PNG or PDF only. Maximum ' . (self::PROOF_MAX_KB / 1024) . ' MB.';
    }

    public static function proofValidationMessages(string $attribute = 'payment_proof'): array
    {
        $maxMb = self::PROOF_MAX_KB / 1024;

        return [
            "{$attribute}.required" => 'Payment proof is required.',
            "{$attribute}.file" => 'Payment proof must be a valid file upload.',
            "{$attribute}.mimes" => 'Payment proof must be a JPG, PNG image or PDF file.',
            "{$attribute}.max" => "Payment proof must not exceed {$maxMb} MB.",
        ];
    }

    public static function assertValidProof(?\Illuminate\Http\UploadedFile $file, bool $required = true): void
    {
        if (!$file) {
            if ($required) {
                throw new \InvalidArgumentException('Payment proof is required.');
            }

            return;
        }

        $validator = \Illuminate\Support\Facades\Validator::make(
            ['payment_proof' => $file],
            ['payment_proof' => ['file', 'mimes:' . implode(',', self::$proof_mimes), 'max:' . self::PROOF_MAX_KB]],
            self::proofValidationMessages()
        );

        if ($validator->fails()) {
            throw new \InvalidArgumentException($validator->errors()->first('payment_proof'));
        }
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function recorder()
    {
        return $this->belongsTo(Admin::class, 'recorded_by');
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function bulkPayment()
    {
        return $this->belongsTo(BulkPayment::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
