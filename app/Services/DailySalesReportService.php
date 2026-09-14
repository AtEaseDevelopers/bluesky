<?php

namespace App\Services;

use App\Area;
use App\Order;
use App\OrderPayment;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DailySalesReportService
{
    public const SUMMARY_CATEGORY_KEYS = [
        'cash',
        'qr',
        'transfer',
        'credit-term',
        'payment-gateway',
    ];

    public function summaryCategoryLabels(): array
    {
        $labels = [];
        foreach (self::SUMMARY_CATEGORY_KEYS as $key) {
            $labels[$key] = __('ui.reports.payment_summary.' . $key);
        }

        return $labels;
    }

    public function dateRange(Request $request): array
    {
        $today = now()->toDateString();

        if ($request->filled('date')) {
            $date = $request->input('date');

            return [$date, $date];
        }

        $startDate = $request->input('fdate') ?: $today;
        $endDate = $request->input('tdate') ?: ($request->input('fdate') ?: $today);

        if ($startDate > $endDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        return [$startDate, $endDate];
    }

    public function filterOptions(): array
    {
        $statuses = [];
        foreach (Order::$status as $status) {
            $statuses[$status] = __('order.status.' . $status);
        }

        return [
            'statuses' => $statuses,
            'payment_methods' => $this->summaryCategoryLabels(),
            'drivers' => DB::table('drivers')->select('id', 'name', 'username')->orderBy('name')->get(),
            'customers' => DB::table('users')->select('id', 'name', 'email')->orderBy('name')->get(),
            'areas' => Area::optionsForSelect(),
        ];
    }

    public function salesLines(Request $request): Collection
    {
        [$startDate, $endDate] = $this->dateRange($request);

        $query = DB::table('order_products')
            ->join('orders', 'orders.id', '=', 'order_products.order_id')
            ->leftJoin('users', 'users.id', '=', 'orders.user_id')
            ->join('products', 'products.id', '=', 'order_products.product_id')
            ->select(
                'orders.id',
                'orders.created_at',
                'orders.status',
                DB::raw("COALESCE(users.name, orders.walk_in_name, orders.attn_name, 'Walk-in / Public') AS name"),
                'order_products.product_name',
                'order_products.product_id',
                'products.description as product_description',
                'products.sku',
                'order_products.quantity',
                'order_products.weight',
                'order_products.unit_price',
                'order_products.price',
                'orders.payment_method',
                'orders.area',
                'orders.billing_address',
                'orders.billing_city',
                'orders.billing_postcode',
                'orders.billing_state',
                'orders.shipping_address',
                'orders.shipping_city',
                'orders.shipping_postcode',
                'orders.shipping_state',
                'orders.updated_at',
                DB::raw(
                    '(SELECT GROUP_CONCAT(DISTINCT op.payment_method)'
                    . ' FROM order_payments op'
                    . ' WHERE op.order_id = orders.id'
                    . " AND op.status = '" . OrderPayment::STATUS_CONFIRMED . "'"
                    . ') AS recorded_payment_methods'
                ),
            )
            ->whereBetween('orders.created_at', [$startDate, $endDate . ' 23:59:59'])
            ->where('order_products.status', 'active');

        $this->applyOrderFilters($query, $request);

        return $query
            ->orderBy('orders.created_at')
            ->orderBy('orders.id')
            ->orderBy('order_products.id')
            ->get();
    }

    public function paymentCollectionSummary(Request $request): array
    {
        [$startDate, $endDate] = $this->dateRange($request);

        $query = DB::table('order_payments')
            ->join('orders', 'orders.id', '=', 'order_payments.order_id')
            ->select(
                'order_payments.payment_method',
                DB::raw('SUM(order_payments.amount) AS total_amount'),
                DB::raw('COUNT(*) AS payment_count')
            )
            ->where('order_payments.status', OrderPayment::STATUS_CONFIRMED)
            ->whereBetween('order_payments.created_at', [$startDate, $endDate . ' 23:59:59']);

        $this->applyOrderFilters($query, $request, 'orders', 'order_payments');

        $rows = $query
            ->groupBy('order_payments.payment_method')
            ->get();

        $summary = [];
        foreach ($this->summaryCategoryLabels() as $key => $label) {
            $summary[$key] = [
                'label' => $label,
                'total' => 0.0,
                'count' => 0,
            ];
        }

        foreach ($rows as $row) {
            $category = $this->mapPaymentMethodToCategory($row->payment_method);
            if (!isset($summary[$category])) {
                continue;
            }

            $summary[$category]['total'] += (float) $row->total_amount;
            $summary[$category]['count'] += (int) $row->payment_count;
        }

        $summary['grand_total'] = [
            'label' => __('ui.reports.grand_total'),
            'total' => collect($summary)->sum('total'),
            'count' => collect($summary)->sum('count'),
        ];

        return $summary;
    }

    public function applyOrderFilters(
        Builder $query,
        Request $request,
        string $ordersAlias = 'orders',
        ?string $paymentsAlias = null
    ): void {
        if ($request->filled('id')) {
            $query->where("{$ordersAlias}.id", $request->input('id'));
        }

        if ($request->filled('status')) {
            $query->where("{$ordersAlias}.status", $request->input('status'));
        }

        if ($request->filled('driver')) {
            $query->where("{$ordersAlias}.driver_id", $request->input('driver'));
        }

        if ($request->filled('customer')) {
            $query->where("{$ordersAlias}.user_id", $request->input('customer'));
        }

        if ($request->filled('area')) {
            $area = Area::orderFilterValue($request->input('area'));
            if ($area) {
                $query->where("{$ordersAlias}.area", $area);
            }
        }

        if ($request->filled('payment_method')) {
            $methods = $this->methodsForCategory($request->input('payment_method'));

            if ($paymentsAlias) {
                // Summary is already scoped to order_payments rows.
                $query->whereIn("{$paymentsAlias}.payment_method", $methods);
            } else {
                // Sales detail is order-based but reports on recorded payments,
                // so keep only orders with a confirmed payment in this category.
                $query->whereExists(function ($sub) use ($ordersAlias, $methods) {
                    $sub->select(DB::raw(1))
                        ->from('order_payments')
                        ->whereColumn('order_payments.order_id', "{$ordersAlias}.id")
                        ->where('order_payments.status', OrderPayment::STATUS_CONFIRMED)
                        ->whereIn('order_payments.payment_method', $methods);
                });
            }
        }
    }

    public function mapPaymentMethodToCategory(?string $method): string
    {
        return match ($method) {
            'cash', 'cod', 'in-store' => 'cash',
            'qr' => 'qr',
            'bank-transfer', 'e-wallet' => 'transfer',
            'credit-term', 'customer-credit', 'term' => 'credit-term',
            'payment-gateway' => 'payment-gateway',
            default => 'other',
        };
    }

    public function methodsForCategory(string $category): array
    {
        return match ($category) {
            'cash' => ['cash', 'cod', 'in-store'],
            'qr' => ['qr'],
            'transfer' => ['bank-transfer', 'e-wallet'],
            'credit-term' => ['credit-term', 'customer-credit', 'term'],
            'payment-gateway' => ['payment-gateway'],
            default => [$category],
        };
    }

    public function paymentMethodLabel(?string $method): string
    {
        if (!$method) {
            return '';
        }

        $key = 'order.payment_methods.' . $method;

        return __($key) !== $key ? __($key) : ucfirst(str_replace('-', ' ', $method));
    }

    /**
     * Label for the payment-method *category* a raw method belongs to. The
     * report's filter dropdown and collection summary both bucket methods into
     * these categories, so the sales-detail column uses the same vocabulary
     * (e.g. "cash" displays as "Cash", "bank-transfer" as "Transfer").
     */
    public function paymentCategoryLabel(?string $method): string
    {
        if (!$method) {
            return '';
        }

        $category = $this->mapPaymentMethodToCategory($method);

        return $this->summaryCategoryLabels()[$category] ?? $this->paymentMethodLabel($method);
    }

    /**
     * The recorded payment method(s) for a sales-detail row, as category
     * labels. `$recordedMethods` is the GROUP_CONCAT of confirmed
     * order_payments methods from salesLines(); returns a de-duplicated,
     * comma-separated list (blank when the order has no recorded payment).
     */
    public function recordedPaymentLabel(?string $recordedMethods): string
    {
        if (!$recordedMethods) {
            return '';
        }

        $labels = [];
        foreach (explode(',', $recordedMethods) as $method) {
            $method = trim($method);
            if ($method === '') {
                continue;
            }
            $labels[] = $this->paymentCategoryLabel($method);
        }

        return implode(', ', array_unique($labels));
    }
}
