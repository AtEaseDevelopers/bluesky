<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Driver\Concerns\RecordsDriverPayments;
use App\Order;
use App\OrderPayment;
use App\OrderProduct;
use App\Product;
use App\Services\OrderService;
use App\Services\OrderStatusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DeliveryOrderController extends Controller
{
    use RecordsDriverPayments;

    /** Statuses a driver may set (canonical order workflow values). */
    public static $driver_status_keys = ['in_route', 'delivered'];

    /** Legacy DB values kept for display/filter compatibility. */
    public static $legacy_status_map = [
        'processing' => 'processing',
        'delivering' => 'in_route',
        'completed' => 'delivered',
    ];

    /**
     * Assigned delivery order list for the logged-in driver.
     */
    public function index(Request $request)
    {
        $driver = Auth::guard('web_driver')->user();

        $query = Order::with('customer')
            ->where('driver_id', $driver->id)
            ->where('fulfillment_type', Order::$fulfillment_types['delivery'])
            ->where('status', '!=', Order::$status['cancelled']);

        if ($request->filled('status')) {
            $filterStatuses = self::statusesForFilter($request->status);
            if ($filterStatuses !== []) {
                $query->whereIn('status', $filterStatuses);
            }
        }

        $orders = $query->orderByRaw("CASE status
                WHEN 'in_route' THEN 0 WHEN 'delivering' THEN 0
                WHEN 'packing' THEN 1 WHEN 'pending' THEN 1 WHEN 'customer_reviewing' THEN 1 WHEN 'processing' THEN 1
                WHEN 'delivered' THEN 2 WHEN 'completed' THEN 2
                ELSE 4 END")
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('driver.orders.index', [
            'orders' => $orders,
            'activeStatus' => $request->status,
        ]);
    }

    /**
     * Delivery order detail.
     */
    public function show($id)
    {
        $order = $this->findAssignedOrder($id);

        $order->load(['customer', 'payments']);

        $orderProducts = OrderProduct::query()
            ->select('order_products.*', 'products.sell_in')
            ->leftJoin('products', 'products.id', '=', 'order_products.product_id')
            ->where('order_products.order_id', $order->id)
            ->where('order_products.status', OrderProduct::$status['active'])
            ->get();

        $productsById = Product::whereIn('id', $orderProducts->pluck('product_id')->unique()->filter())
            ->get()
            ->keyBy('id');

        return view('driver.orders.show', [
            'order' => $order,
            'orderProducts' => $orderProducts,
            'productsById' => $productsById,
            'canAdjustOrder' => Order::canDriverAdjustQuantities($order->status),
            'driverStatuses' => self::driverStatusLabels(),
            'paymentMethods' => self::driverPaymentMethodsFor(
                optional($order->customer)->isCreditCustomer() ? 'credit' : 'cod'
            ),
            'proofRequiredMethods' => self::$driverProofRequiredMethods,
        ]);
    }

    /**
     * Update delivery status (In Route / Delivered).
     */
    public function updateStatus(Request $request, $id)
    {
        $order = $this->findAssignedOrder($id);

        $data = $request->validate([
            'status' => ['required', 'in:' . implode(',', self::$driver_status_keys)],
        ]);

        try {
            app(OrderStatusService::class)->transition(
                $order,
                Order::$status[$data['status']],
                null
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('driver_portal.deliveries.status_updated', [
            'status' => self::statusLabel($data['status']),
        ]));
    }

    /**
     * Adjust actual qty/weight on delivery (recalculates line totals and order total).
     */
    public function adjustOrder(Request $request, $id)
    {
        $order = $this->findAssignedOrder($id);

        if (!Order::canDriverAdjustQuantities($order->status)) {
            return back()->with('error', __('driver_portal.deliveries.adjust_not_allowed'));
        }

        $rules = [
            'line_items' => 'required|array',
        ];

        $orderProducts = OrderProduct::query()
            ->select('order_products.id', 'order_products.product_id')
            ->where('order_products.order_id', $order->id)
            ->whereIn('order_products.id', array_keys($request->input('line_items', [])))
            ->get()
            ->keyBy('id');

        foreach ($request->input('line_items', []) as $lineId => $item) {
            $orderProduct = $orderProducts->get((int) $lineId);
            if (!$orderProduct) {
                continue;
            }

            $product = Product::find($orderProduct->product_id);
            if (!$product) {
                continue;
            }

            $sellIn = Product::resolveSellInForOrderLine($orderProduct, $product);

            if (Product::lineNeedsQuantityInput($sellIn)) {
                $rules['line_items.' . $lineId . '.quantity'] = 'required|numeric|min:0.001';
            } else {
                $rules['line_items.' . $lineId . '.quantity'] = 'nullable';
            }

            if (Product::lineNeedsWeightInput($sellIn)) {
                $rules['line_items.' . $lineId . '.weight'] = 'required|numeric|min:0.001';
            } else {
                $rules['line_items.' . $lineId . '.weight'] = 'nullable|numeric|min:0';
            }
        }

        $request->validate($rules);

        app(OrderService::class)->applyDriverAdjustments(
            $order,
            $request->input('line_items', [])
        );

        return back()->with('success', __('driver_portal.deliveries.adjust_saved'));
    }

    /**
     * Record a payment collected from the customer.
     */
    public function recordPayment(Request $request, $id)
    {
        return $this->recordDriverPayment($request, $this->findAssignedOrder($id));
    }

    /**
     * Serve the uploaded payment proof for an assigned order.
     */
    public function downloadProof($id)
    {
        $order = $this->findAssignedOrder($id);

        $payment = $order->payments()
            ->where('status', OrderPayment::STATUS_CONFIRMED)
            ->whereNotNull('payment_proof')
            ->orderByDesc('id')
            ->first();

        if (!$payment) {
            abort(404, __('driver_portal.errors.no_payment_proof'));
        }

        $path = Order::$path . '/' . $order->id . '/payments/' . $payment->payment_proof;
        if (!Storage::disk('local')->exists($path)) {
            abort(404, __('driver_portal.errors.file_not_found'));
        }

        $mime = Storage::disk('local')->mimeType($path);
        $file = Storage::disk('local')->get($path);

        return response($file)->header('Content-Type', $mime);
    }

    /**
     * Fetch an order ensuring it belongs to the logged-in driver.
     */
    protected function findAssignedOrder($id)
    {
        return Order::where('id', $id)
            ->where('driver_id', Auth::guard('web_driver')->id())
            ->where('fulfillment_type', Order::$fulfillment_types['delivery'])
            ->firstOrFail();
    }

    public static function statusesForFilter(string $filter): array
    {
        return match ($filter) {
            'processing' => ['pending', 'packing', 'customer_reviewing', 'processing'],
            'in_route', 'delivering' => ['in_route', 'delivering'],
            'delivered', 'completed' => ['delivered', 'completed'],
            default => [],
        };
    }

    /** @return array<string, string> */
    public static function driverStatusLabels(): array
    {
        $labels = [];
        foreach (self::$driver_status_keys as $status) {
            $labels[$status] = self::statusLabel($status);
        }

        return $labels;
    }

    public static function statusLabel(string $status): string
    {
        $canonical = self::$legacy_status_map[$status] ?? $status;
        $key = 'order.status.' . $canonical;
        $label = __($key);

        return $label !== $key ? $label : ucfirst(str_replace('_', ' ', $status));
    }
}
