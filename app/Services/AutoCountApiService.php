<?php

namespace App\Services;

use App\Order;
use App\OrderProduct;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AutoCountApiService
{
    public function validateBranch(Request $request): bool
    {
        $branchId = (string) $request->query('branch_id', '');
        $expected = (string) config('autocount.branch_email', '');

        if ($expected === '') {
            return true;
        }

        return strcasecmp($branchId, $expected) === 0;
    }

    public function nextPendingOrder(): ?array
    {
        $order = $this->baseOrderQuery()
            ->where('autocount_sync_status', 'pending_sync')
            ->whereNull('api_do_id')
            ->orderBy('id')
            ->first();

        return $order ? $this->toSyncPayload($order, 'pending') : null;
    }

    public function nextProcessOrder(): ?array
    {
        $order = $this->baseOrderQuery()
            ->whereIn('autocount_sync_status', ['pending_sync', 'do_created'])
            ->whereNotNull('api_do_id')
            ->whereNull('api_invoice_id')
            ->orderBy('id')
            ->first();

        return $order ? $this->toSyncPayload($order, 'process') : null;
    }

    public function nextPaidOrder(): ?array
    {
        $order = $this->baseOrderQuery()
            ->where('autocount_sync_status', 'synced')
            ->whereNotNull('api_invoice_id')
            ->whereHas('customer', function ($q) {
                $q->where('customer_type', 'credit');
            })
            ->orderBy('id')
            ->first();

        return $order ? $this->toSyncPayload($order, 'paid') : null;
    }

    public function applyDocumentUpdate(array $payload): void
    {
        $orderId = (int) ($payload['id'] ?? 0);
        $type = strtoupper((string) ($payload['type'] ?? ''));
        $number = (string) ($payload['number'] ?? '');

        $order = Order::find($orderId);
        if (!$order) {
            throw new \InvalidArgumentException('Order not found.');
        }

        if ($type === 'DO') {
            $order->api_do_id = $number;
            $order->autocount_sync_status = 'do_created';
        } elseif (in_array($type, ['INV', 'CS'], true)) {
            $order->api_invoice_id = $number;
            $order->autocount_sync_status = 'synced';
            $order->autocount_synced_at = now();
        }

        $order->save();

        app(AutoCountSyncService::class)->log(
            $order,
            $order->autocount_sync_status,
            'AutoCount document created: ' . $type . ' ' . $number
        );
    }

    public function applyPaidUpdate(array $payload): void
    {
        $orderId = (int) ($payload['id'] ?? 0);
        $order = Order::find($orderId);

        if (!$order) {
            throw new \InvalidArgumentException('Order not found.');
        }

        $order->autocount_sync_status = 'paid_synced';
        $order->save();

        app(AutoCountSyncService::class)->log(
            $order,
            'paid_synced',
            'AutoCount payment confirmed. Ref: ' . ($payload['number'] ?? '')
        );
    }

    public function logError(array $payload): void
    {
        $message = (string) ($payload['message'] ?? 'Unknown AutoCount error');
        $orderId = (int) data_get($payload, 'model.order.id', 0);
        $order = $orderId ? Order::find($orderId) : null;

        Log::error('AutoCount plugin error', ['message' => $message, 'order_id' => $orderId]);

        if ($order) {
            $order->autocount_sync_status = 'sync_error';
            $order->save();

            app(AutoCountSyncService::class)->log($order, 'sync_error', null, $message);
        }
    }

    public function pendingCustomers(): array
    {
        return User::query()
            ->where('autocount_sync_status', 'pending_sync')
            ->whereNotNull('registration_completed_at')
            ->where('status', User::$user_status['active'])
            ->orderBy('id')
            ->get()
            ->map(fn (User $user) => $this->toCustomerPayload($user))
            ->values()
            ->all();
    }

    public function applyCustomerUpdate(array $payload): void
    {
        $customerId = (int) ($payload['id'] ?? 0);
        $accNo = trim((string) ($payload['AccNo'] ?? $payload['acc_no'] ?? ''));

        if (!$customerId || $accNo === '') {
            throw new \InvalidArgumentException('Customer id and AccNo are required.');
        }

        $user = User::find($customerId);
        if (!$user) {
            throw new \InvalidArgumentException('Customer not found.');
        }

        $user->update([
            'sql_customer_code' => $accNo,
            'autocount_sync_status' => 'synced',
            'autocount_synced_at' => now(),
        ]);
    }

    protected function toCustomerPayload(User $user): array
    {
        $billing = $this->splitAddressLines($user->billing_address);
        $shipping = $this->splitAddressLines($user->shipping_address ?: $user->billing_address);
        $customerCode = $this->normalizeCustomerCode($user->sql_customer_code);

        return [
            'id' => $user->id,
            'api_account_no' => $customerCode,
            'name' => $user->name,
            'phone_no' => $user->attn_contact,
            'category' => $user->category,
            'billing_address' => $user->billing_address,
            'billing_address1' => $billing[0],
            'billing_address2' => $billing[1],
            'billing_address3' => $billing[2],
            'billing_address4' => $billing[3],
            'billing_postcode' => $user->billing_postcode,
            'billing_state' => $user->billing_state,
            'attn_name' => $user->attn_name,
            'attn_contact' => $user->attn_contact,
            'shipping_address' => $user->shipping_address,
            'shipping_postcode' => $user->shipping_postcode,
            'shipping_state' => $user->shipping_state,
            'payment_method' => $user->customer_type,
            'email' => $user->email,
            'status' => $user->status,
            'created_at' => $user->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $user->updated_at->format('Y-m-d H:i:s'),
        ];
    }

    protected function normalizeCustomerCode(?string $code): ?string
    {
        $code = trim((string) $code);

        if ($code === '' || strcasecmp($code, '300-0000') === 0) {
            return null;
        }

        return $code;
    }

    protected function splitAddressLines(?string $address): array
    {
        $lines = ['', '', '', ''];
        if (!$address) {
            return $lines;
        }

        $parts = array_map('trim', explode(',', $address));
        $index = 0;

        foreach ($parts as $part) {
            if ($index >= 4) {
                break;
            }

            $lines[$index++] = mb_substr($part, 0, 40);
        }

        return $lines;
    }

    protected function baseOrderQuery()
    {
        return Order::query()
            ->with(['customer', 'orderProducts'])
            ->where('payment_status', Order::$payment_status['paid'])
            ->where('status', Order::$status['delivered']);
    }

    protected function toSyncPayload(Order $order, string $type): array
    {
        $customer = $order->customer;
        $lines = $order->orderProducts()
            ->where('status', OrderProduct::$status['active'])
            ->get();

        $productIds = $lines->pluck('product_id')->filter()->unique();
        $products = \App\Product::with('uom')->whereIn('id', $productIds)->get()->keyBy('id');

        $details = [];
        foreach ($lines as $line) {
            $product = $products->get($line->product_id);
            $qty = (float) ($line->weight > 0 ? $line->weight : $line->quantity);
            $unitPrice = (float) $line->unit_price;
            $details[] = [
                'Item' => $product ? $product->sku : $line->product_name,
                'UOM' => $product && $product->uom ? $product->uom->uom_name : 'KG',
                'Qty' => $qty,
                'UnitPrice' => number_format($unitPrice, 2, '.', ''),
                'Description' => $line->product_name,
                'Location' => '',
                'AccNo' => $customer ? $customer->sql_customer_code : '',
                'DeliveryDate' => optional($order->delivery_date)->format('Y-m-d') ?: $order->created_at->format('Y-m-d'),
                'Discount' => '',
                'Tax' => '',
                'SubTotal' => round($qty * $unitPrice, 2),
            ];
        }

        return [
            'order' => [
                'id' => $order->id,
                'api_invoice_id' => $order->api_invoice_id,
                'api_do_id' => $order->api_do_id,
                'user_id' => $order->user_id,
                'total_price' => number_format((float) $order->total_price, 2, '.', ''),
                'billing_address' => $order->billing_address,
                'billing_postcode' => $order->billing_postcode,
                'billing_state' => $order->billing_state,
                'attn_name' => $order->attn_name,
                'attn_contact' => $order->attn_contact,
                'shipping_address' => $order->shipping_address,
                'shipping_postcode' => $order->shipping_postcode,
                'shipping_state' => $order->shipping_state,
                'payment_method' => $this->mapPaymentMethod($order),
                'delivery_datetime' => optional($order->delivery_date)->format('Y-m-d H:i:s') ?: $order->created_at->format('Y-m-d H:i:s'),
                'status' => $order->status,
                'created_at' => $order->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $order->updated_at->format('Y-m-d H:i:s'),
                'agentname' => '',
                'customer' => $customer ? [
                    'id' => $customer->id,
                    'api_account_no' => $customer->sql_customer_code,
                    'name' => $customer->name,
                    'phone_no' => $customer->attn_contact,
                    'billing_address' => $customer->billing_address,
                    'billing_postcode' => $customer->billing_postcode,
                    'billing_state' => $customer->billing_state,
                    'shipping_address' => $customer->shipping_address,
                    'shipping_postcode' => $customer->shipping_postcode,
                    'shipping_state' => $customer->shipping_state,
                    'payment_method' => $this->mapPaymentMethod($order),
                    'email' => $customer->email,
                    'status' => $customer->status,
                    'created_at' => $customer->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $customer->updated_at->format('Y-m-d H:i:s'),
                ] : null,
            ],
            'detail' => $details,
            'type' => $type,
            'doc_id' => 0,
        ];
    }

    protected function mapPaymentMethod(Order $order): string
    {
        return $order->isCreditCustomer() ? 'credit' : 'cod';
    }
}
