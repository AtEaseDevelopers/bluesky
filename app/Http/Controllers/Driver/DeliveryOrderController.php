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

    /** Status value drivers may submit (admin dispatches to in_route). */
    public static $driver_settable_status_keys = ['delivered'];

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

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $like = '%' . $search . '%';

                $builder->where('orders.do_no', 'like', $like)
                    ->orWhere('orders.invoice_number', 'like', $like)
                    ->orWhere('orders.attn_name', 'like', $like)
                    ->orWhere('orders.attn_contact', 'like', $like)
                    ->orWhere('orders.walk_in_phone', 'like', $like)
                    ->orWhere('orders.shipping_address', 'like', $like)
                    ->orWhere('orders.billing_address', 'like', $like)
                    ->orWhereHas('customer', function ($customerQuery) use ($like) {
                        $customerQuery->where('name', 'like', $like)
                            ->orWhere('attn_contact', 'like', $like);
                    });

                if (ctype_digit($search)) {
                    $builder->orWhere('orders.id', (int) $search);
                }
            });
        }

        $orders = $query->orderByRaw("CASE status
                WHEN 'in_route' THEN 0 WHEN 'delivering' THEN 0
                WHEN 'packing' THEN 1 WHEN 'pending' THEN 1 WHEN 'processing' THEN 1
                WHEN 'delivered' THEN 2 WHEN 'completed' THEN 2
                ELSE 4 END")
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $orders->setCollection(
            $orders->getCollection()->map(function (Order $order) {
                return $order->ensureDoNumber();
            })
        );

        return view('driver.orders.index', [
            'orders' => $orders,
            'activeStatus' => $request->status,
            'searchQuery' => $search,
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
            'driverStatuses' => self::driverStatusesForOrder($order),
            'deliveryStatusContext' => self::driverDeliveryStatusContext($order),
            'paymentMethods' => self::driverPaymentMethodsFor(
                $order->isCreditCustomer() ? 'credit' : 'cod',
                $order->isCreditCustomer()
            ),
            'proofRequiredMethods' => self::$driverProofRequiredMethods,
        ]);
    }

    /**
     * Mark order as delivered (only allowed after admin has set in route).
     */
    public function updateStatus(Request $request, $id)
    {
        $order = $this->findAssignedOrder($id);

        $data = $request->validate([
            'status' => ['required', 'in:' . implode(',', self::$driver_settable_status_keys)],
            'delivery_proof' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:4096'],
        ], [
            'delivery_proof.required' => __('driver_portal.deliveries.delivery_proof_required'),
            'delivery_proof.image' => __('driver_portal.deliveries.delivery_proof_format'),
            'delivery_proof.mimes' => __('driver_portal.deliveries.delivery_proof_format'),
            'delivery_proof.max' => __('driver_portal.deliveries.delivery_proof_size'),
        ]);

        if (!self::canDriverMarkDelivered($order)) {
            return back()->with('error', __('driver_portal.deliveries.delivered_requires_in_route'));
        }

        $extension = $request->file('delivery_proof')->getClientOriginalExtension();
        $filename = 'delivery_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $path = Order::$path . '/' . $order->id;

        Storage::disk('local')->put(
            $path . '/' . $filename,
            file_get_contents($request->file('delivery_proof'))
        );

        $order->update([
            'delivery_proof' => $filename,
            'delivery_confirmed_at' => now(),
        ]);

        try {
            app(OrderStatusService::class)->transition(
                $order->fresh(),
                Order::$status['delivered'],
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
     * Serve the uploaded delivery proof for an assigned order.
     */
    public function downloadDeliveryProof($id)
    {
        $order = $this->findAssignedOrder($id);

        if (!$order->delivery_proof) {
            abort(404, __('driver_portal.deliveries.delivery_proof_not_found'));
        }

        $path = Order::$path . '/' . $order->id . '/' . $order->delivery_proof;
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
        $order = Order::where('id', $id)
            ->where('driver_id', Auth::guard('web_driver')->id())
            ->where('fulfillment_type', Order::$fulfillment_types['delivery'])
            ->firstOrFail();

        return $order->ensureDoNumber();
    }

    public static function statusesForFilter(string $filter): array
    {
        return match ($filter) {
            'processing' => ['pending', 'packing', 'processing'],
            'in_route', 'delivering' => ['in_route', 'delivering'],
            'delivered', 'completed' => ['delivered', 'completed'],
            default => [],
        };
    }

    public static function canDriverMarkDelivered(Order $order): bool
    {
        $canonical = self::$legacy_status_map[$order->status] ?? $order->status;

        return $canonical === Order::$status['in_route'];
    }

    /** @return array<string, string> */
    public static function driverStatusesForOrder(Order $order): array
    {
        if (!self::canDriverMarkDelivered($order)) {
            return [];
        }

        return [
            'delivered' => self::statusLabel('delivered'),
        ];
    }

    /**
     * UI context for the driver delivery-status card on the order detail page.
     *
     * @return array{mode: string, statuses?: array<string, string>, canonical?: string}
     */
    public static function driverDeliveryStatusContext(Order $order): array
    {
        if (self::canDriverMarkDelivered($order)) {
            return [
                'mode' => 'confirm',
                'statuses' => self::driverStatusesForOrder($order),
            ];
        }

        $canonical = self::$legacy_status_map[$order->status] ?? $order->status;

        if (in_array($canonical, [Order::$status['delivered'], Order::$status['completed']], true)) {
            return [
                'mode' => $order->delivery_proof ? 'done_with_proof' : 'done_no_proof',
                'canonical' => $canonical,
            ];
        }

        return [
            'mode' => 'waiting',
            'canonical' => $canonical,
        ];
    }

    /** @return array<string, string> */
    public static function driverStatusLabels(): array
    {
        $labels = [];
        foreach (self::$driver_settable_status_keys as $status) {
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
