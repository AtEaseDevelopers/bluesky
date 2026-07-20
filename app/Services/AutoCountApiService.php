<?php

namespace App\Services;

use App\CustomerCategoryProduct;
use App\Order;
use App\OrderProduct;
use App\Product;
use App\ProductCategory;
use App\ProductStock;
use App\Uom;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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

    public function importCustomers(array $payload): array
    {
        $rows = $payload['customers'] ?? $payload;
        if (!is_array($rows)) {
            throw new \InvalidArgumentException('Customers payload must be an array.');
        }

        $result = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                $result['skipped']++;
                continue;
            }

            $accNo = $this->normalizeCustomerCode($row['AccNo'] ?? $row['acc_no'] ?? null);
            if (!$accNo) {
                $result['skipped']++;
                continue;
            }

            try {
                $user = User::query()->where('sql_customer_code', $accNo)->first();
                $email = trim((string) ($row['email'] ?? $row['EmailAddress'] ?? ''));

                if (!$user && $email !== '') {
                    $user = User::query()->where('email', $email)->first();
                }

                $attributes = $this->mapImportedCustomer($row, $accNo);

                if ($user) {
                    $user->update($attributes);
                    $result['updated']++;
                    continue;
                }

                $name = (string) ($attributes['name'] ?? '');
                if ($name !== '' && User::query()->where('name', $name)->exists()) {
                    $attributes['name'] = mb_substr($name . ' (' . $accNo . ')', 0, 100);
                }

                User::create(array_merge($attributes, [
                    'password' => Hash::make('ecommerce123'),
                    'login_code' => User::generateLoginCode(),
                    'registration_completed_at' => now(),
                    'role_slug' => 'customer',
                ]));
                $result['created']++;
            } catch (\Throwable $e) {
                $result['errors'][] = $accNo . ': ' . $e->getMessage();
                Log::error('AutoCount customer import failed', [
                    'acc_no' => $accNo,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    public function importProducts(array $payload): array
    {
        $rows = $payload['products'] ?? $payload;
        if (!is_array($rows)) {
            throw new \InvalidArgumentException('Products payload must be an array.');
        }

        $result = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                $result['skipped']++;
                continue;
            }

            $sku = trim((string) ($row['sku'] ?? $row['ItemCode'] ?? $row['item_code'] ?? ''));
            if ($sku === '') {
                $result['skipped']++;
                continue;
            }

            try {
                $attributes = $this->mapImportedProduct($row, $sku);
                $product = Product::query()->where('sku', $sku)->first();

                if ($product) {
                    $product->update($attributes);
                    $result['updated']++;
                    continue;
                }

                $product = Product::create($attributes);
                ProductStock::firstOrCreate(
                    ['product_id' => $product->id],
                    ['quantity' => 0, 'weight' => 0]
                );

                foreach (\Illuminate\Support\Facades\DB::table('customer_categories')->pluck('id') as $categoryId) {
                    CustomerCategoryProduct::firstOrCreate([
                        'customer_category_id' => $categoryId,
                        'product_id' => $product->id,
                    ]);
                }

                $result['created']++;
            } catch (\Throwable $e) {
                $result['errors'][] = $sku . ': ' . $e->getMessage();
                Log::error('AutoCount product import failed', [
                    'sku' => $sku,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    public function importInvoices(array $payload): array
    {
        $rows = $payload['invoices'] ?? $payload;
        if (!is_array($rows)) {
            throw new \InvalidArgumentException('Invoices payload must be an array.');
        }

        $result = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                $result['skipped']++;
                continue;
            }

            $docNo = trim((string) ($row['doc_no'] ?? $row['DocNo'] ?? $row['number'] ?? ''));
            if ($docNo === '') {
                $result['skipped']++;
                continue;
            }

            try {
                $order = $this->findOrderForImportedInvoice($row, $docNo);
                if (!$order) {
                    $result['skipped']++;
                    continue;
                }

                $order->loadMissing('customer');
                $docType = strtoupper(trim((string) ($row['doc_type'] ?? $row['type'] ?? 'INV')));
                $doDocNo = trim((string) ($row['do_doc_no'] ?? $row['DoDocNo'] ?? ''));
                $outstanding = (float) ($row['outstanding'] ?? $row['Outstanding'] ?? 0);
                $isPaidInAutoCount = $docType === 'CS' || $outstanding <= 0.00001;

                $updates = [
                    'api_invoice_id' => $docNo,
                    'autocount_synced_at' => now(),
                ];

                if ($doDocNo !== '' && empty($order->api_do_id)) {
                    $updates['api_do_id'] = $doDocNo;
                }

                if ($isPaidInAutoCount && $order->customer && $order->customer->isCreditCustomer()) {
                    $updates['autocount_sync_status'] = 'paid_synced';
                } else {
                    $updates['autocount_sync_status'] = 'synced';
                }

                $order->update($updates);

                if (!$order->invoice_number) {
                    app(OrderService::class)->generateInvoiceNumber($order->fresh());
                    $order = $order->fresh();
                }

                $message = 'AutoCount invoice imported: ' . $docType . ' ' . $docNo;
                if ($doDocNo !== '') {
                    $message .= ' (DO ' . $doDocNo . ')';
                }

                \App\AutoCountSyncLog::create([
                    'order_id' => $order->id,
                    'invoice_number' => $order->invoice_number,
                    'sync_status' => $updates['autocount_sync_status'],
                    'response_message' => $message,
                    'error_message' => null,
                    'admin_id' => null,
                ]);

                $result['updated']++;
            } catch (\Throwable $e) {
                $result['errors'][] = $docNo . ': ' . $e->getMessage();
                Log::error('AutoCount invoice import failed', [
                    'doc_no' => $docNo,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    protected function findOrderForImportedInvoice(array $row, string $docNo): ?Order
    {
        $orderId = (int) ($row['order_id'] ?? $row['oms_order_id'] ?? 0);
        if ($orderId > 0) {
            $order = Order::find($orderId);
            if ($order) {
                return $order;
            }
        }

        $order = Order::query()->where('api_invoice_id', $docNo)->first();
        if ($order) {
            return $order;
        }

        $doDocNo = trim((string) ($row['do_doc_no'] ?? $row['DoDocNo'] ?? ''));
        if ($doDocNo !== '') {
            $order = Order::query()->where('api_do_id', $doDocNo)->first();
            if ($order) {
                return $order;
            }
        }

        $debtorCode = trim((string) ($row['debtor_code'] ?? $row['DebtorCode'] ?? $row['AccNo'] ?? ''));
        if ($debtorCode === '') {
            return null;
        }

        $query = Order::query()
            ->whereNull('api_invoice_id')
            ->whereHas('customer', function ($query) use ($debtorCode) {
                $query->where('sql_customer_code', $debtorCode);
            });

        $docDate = $this->parseImportDate($row['doc_date'] ?? $row['DocDate'] ?? null);
        if ($docDate) {
            $query->whereDate('created_at', $docDate);
        }

        return $query->orderBy('id')->first();
    }

    protected function parseImportDate($value): ?\Carbon\Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($value)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function mapImportedCustomer(array $row, string $accNo): array
    {
        $billing = $this->joinAddressLines(
            $row['billing_address1'] ?? $row['Address1'] ?? null,
            $row['billing_address2'] ?? $row['Address2'] ?? null,
            $row['billing_address3'] ?? $row['Address3'] ?? null,
            $row['billing_address4'] ?? $row['Address4'] ?? null
        );
        $shipping = $this->joinAddressLines(
            $row['shipping_address1'] ?? $row['DeliverAddr1'] ?? null,
            $row['shipping_address2'] ?? $row['DeliverAddr2'] ?? null,
            $row['shipping_address3'] ?? $row['DeliverAddr3'] ?? null,
            $row['shipping_address4'] ?? $row['DeliverAddr4'] ?? null
        );

        if ($billing === '') {
            $billing = (string) ($row['billing_address'] ?? '-');
        }
        if ($shipping === '') {
            $shipping = $billing;
        }

        $customerType = $this->mapImportedCustomerType($row);
        $isActive = $this->toBool($row['IsActive'] ?? $row['is_active'] ?? $row['status'] ?? true);

        return [
            'name' => mb_substr(trim((string) ($row['name'] ?? $row['CompanyName'] ?? $row['company_name'] ?? $accNo)), 0, 100),
            'email' => $this->nullableString($row['email'] ?? $row['EmailAddress'] ?? null),
            'category' => $this->nullableString($row['category'] ?? $row['PriceCategory'] ?? null),
            'customer_type' => $customerType,
            'payment_term_days' => $customerType === 'credit'
                ? $this->parsePaymentTermDays($row['DisplayTerm'] ?? $row['credit_term'] ?? null)
                : null,
            'attn_name' => $this->nullableString($row['attn_name'] ?? $row['Attention'] ?? null),
            'attn_contact' => $this->nullableString($row['phone_no'] ?? $row['Phone1'] ?? $row['attn_contact'] ?? null),
            'fax_no' => $this->nullableString($row['fax_no'] ?? $row['Fax1'] ?? null),
            'billing_address' => mb_substr($billing, 0, 100),
            'billing_postcode' => mb_substr((string) ($row['billing_postcode'] ?? ''), 0, 5),
            'billing_state' => mb_substr((string) ($row['billing_state'] ?? ''), 0, 30),
            'shipping_address' => mb_substr($shipping, 0, 100),
            'shipping_postcode' => mb_substr((string) ($row['shipping_postcode'] ?? $row['billing_postcode'] ?? ''), 0, 5),
            'shipping_state' => mb_substr((string) ($row['shipping_state'] ?? $row['billing_state'] ?? ''), 0, 30),
            'payment_method' => json_encode($customerType === 'credit' ? [User::$payment_method['term']] : [User::$payment_method['cod']]),
            'sql_customer_code' => $accNo,
            'status' => $isActive ? User::$user_status['active'] : User::$user_status['locked'],
            'autocount_sync_status' => 'synced',
            'autocount_synced_at' => now(),
        ];
    }

    protected function mapImportedProduct(array $row, string $sku): array
    {
        $uomName = trim((string) ($row['uom'] ?? $row['UOM'] ?? $row['description'] ?? 'KG'));
        if ($uomName === '') {
            $uomName = 'KG';
        }

        $categoryName = trim((string) ($row['category'] ?? $row['ItemGroup'] ?? $row['item_group'] ?? 'General'));
        if ($categoryName === '') {
            $categoryName = 'General';
        }

        $uom = Uom::firstOrCreate(['uom_name' => mb_substr($uomName, 0, 30)]);
        $category = ProductCategory::firstOrCreate(['category_name' => mb_substr($categoryName, 0, 50)]);

        $price = (float) ($row['price'] ?? $row['StandardSellingPrice'] ?? $row['standard_selling_price'] ?? 0);
        $isActive = $this->toBool($row['IsActive'] ?? $row['is_active'] ?? $row['status'] ?? true);
        $sellIn = $this->resolveSellIn($uomName, $row['sell_in'] ?? null);

        return [
            'uom_id' => $uom->id,
            'product_category_id' => $category->id,
            'name' => mb_substr(trim((string) ($row['name'] ?? $row['Description'] ?? $sku)), 0, 50),
            'description' => mb_substr(trim((string) ($row['description'] ?? $uomName)), 0, 200),
            'sku' => mb_substr($sku, 0, 50),
            'price' => max(0, $price),
            'status' => $isActive ? Product::$status['active'] : Product::$status['inactive'],
            'sell_in' => $sellIn,
        ];
    }

    protected function joinAddressLines(...$lines): string
    {
        $parts = [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line !== '') {
                $parts[] = $line;
            }
        }

        return implode(', ', $parts);
    }

    protected function mapImportedCustomerType(array $row): string
    {
        $explicit = strtolower(trim((string) ($row['customer_type'] ?? $row['payment_method'] ?? '')));
        if (in_array($explicit, ['credit', 'term'], true)) {
            return 'credit';
        }
        if (in_array($explicit, ['cod', 'cash'], true)) {
            return 'cod';
        }

        $term = trim((string) ($row['DisplayTerm'] ?? $row['credit_term'] ?? ''));
        if ($term !== '' && !preg_match('/^(cod|cash)$/i', $term)) {
            return 'credit';
        }

        return 'cod';
    }

    protected function parsePaymentTermDays(?string $term): int
    {
        $term = trim((string) $term);
        if ($term === '') {
            return 30;
        }

        if (preg_match('/(\d+)/', $term, $matches)) {
            $days = (int) $matches[1];

            return $days > 0 ? $days : 30;
        }

        return 30;
    }

    protected function resolveSellIn(string $uomName, ?string $explicit): string
    {
        $explicit = strtolower(trim((string) $explicit));
        if (in_array($explicit, [Product::SELL_IN_QTY, Product::SELL_IN_WEIGHT, Product::SELL_IN_QTY_BILL_WEIGHT], true)) {
            return $explicit;
        }

        $uom = strtoupper($uomName);
        if (in_array($uom, ['KG', 'KGS', 'KILO', 'KILOGRAM'], true)) {
            return Product::SELL_IN_WEIGHT;
        }

        return Product::SELL_IN_QTY;
    }

    protected function nullableString($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function toBool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value !== 0;
        }

        $value = strtolower(trim((string) $value));

        return !in_array($value, ['0', 'false', 'inactive', 'no', 'n'], true);
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
