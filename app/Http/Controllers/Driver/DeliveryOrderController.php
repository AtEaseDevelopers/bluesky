<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Order;
use App\OrderPayment;
use App\Services\OrderService;
use App\Services\OrderStatusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DeliveryOrderController extends Controller
{
    /** Statuses a driver may set (canonical order workflow values). */
    public static $driver_statuses = [
        'in_route' => 'In Route',
        'delivered' => 'Delivered',
    ];

    /** Legacy DB values kept for display/filter compatibility. */
    public static $legacy_status_map = [
        'processing' => 'processing',
        'delivering' => 'in_route',
        'completed' => 'delivered',
    ];

    /** Map driver-portal form values to canonical payment method keys. */
    public static $payment_method_map = [
        'cash' => 'cash',
        'qr' => 'qr',
        'transfer' => 'bank-transfer',
        'credit' => 'credit-term',
    ];

    /**
     * Payment methods a driver may record.
     */
    public static $payment_methods = [
        'cash' => 'Cash',
        'qr' => 'QR',
        'transfer' => 'Bank Transfer',
        'credit' => 'Credit Term',
    ];

    /**
     * Methods that require a payment proof upload.
     */
    public static $proof_required_methods = ['qr', 'transfer'];

    /**
     * Assigned delivery order list for the logged-in driver.
     */
    public function index(Request $request)
    {
        $driver = Auth::guard('web_driver')->user();

        $query = Order::with('customer')
            ->where('driver_id', $driver->id)
            ->where('status', '!=', Order::$status['cancelled']);

        if ($request->filled('status')) {
            $filterStatuses = self::statusesForFilter($request->status);
            if ($filterStatuses !== []) {
                $query->whereIn('status', $filterStatuses);
            }
        }

        $orders = $query->orderByRaw("CASE status
                WHEN 'in_route' THEN 0 WHEN 'delivering' THEN 0
                WHEN 'pending' THEN 1 WHEN 'customer_reviewing' THEN 1 WHEN 'processing' THEN 1
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

        $order->load(['customer', 'products', 'payments']);

        return view('driver.orders.show', [
            'order' => $order,
            'driverStatuses' => self::$driver_statuses,
            'paymentMethods' => self::$payment_methods,
            'proofRequiredMethods' => self::$proof_required_methods,
        ]);
    }

    /**
     * Update delivery status (In Route / Delivered).
     */
    public function updateStatus(Request $request, $id)
    {
        $order = $this->findAssignedOrder($id);

        $data = $request->validate([
            'status' => ['required', 'in:' . implode(',', array_keys(self::$driver_statuses))],
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

        return back()->with('success', 'Delivery status updated to "' . self::$driver_statuses[$data['status']] . '".');
    }

    /**
     * Record a payment collected from the customer.
     */
    public function recordPayment(Request $request, $id)
    {
        $order = $this->findAssignedOrder($id);

        if (!$order->canRecordAdminPayment()) {
            return back()->with('error', $order->isCodCustomer()
                ? 'COD payment can only be recorded when the order is in route or delivered.'
                : 'Payment cannot be recorded for this order in its current status.');
        }

        $data = $request->validate([
            'payment_method' => ['required', 'in:' . implode(',', array_keys(self::$payment_methods))],
            'paid_amount' => ['required', 'numeric', 'min:0.01'],
            'payment_proof' => [
                'nullable',
                'required_if:payment_method,' . implode(',', self::$proof_required_methods),
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:4096',
            ],
        ], [
            'payment_proof.required_if' => 'Payment proof is required for QR and bank transfer payments.',
        ]);

        $method = self::$payment_method_map[$data['payment_method']] ?? $data['payment_method'];

        try {
            app(OrderService::class)->recordPayment(
                $order,
                $method,
                (float) $data['paid_amount'],
                $request->file('payment_proof'),
                null,
                null,
                Auth::guard('web_driver')->id()
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Payment recorded successfully.');
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
            abort(404, 'No payment proof uploaded.');
        }

        $path = Order::$path . '/' . $order->id . '/payments/' . $payment->payment_proof;
        if (!Storage::disk('local')->exists($path)) {
            abort(404, 'File not found.');
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
            ->firstOrFail();
    }

    public static function statusesForFilter(string $filter): array
    {
        return match ($filter) {
            'processing' => ['pending', 'customer_reviewing', 'processing'],
            'in_route', 'delivering' => ['in_route', 'delivering'],
            'delivered', 'completed' => ['delivered', 'completed'],
            default => [],
        };
    }

    public static function statusLabel(string $status): string
    {
        $canonical = self::$legacy_status_map[$status] ?? $status;
        $key = 'order.status.' . $canonical;
        $label = __($key);

        return $label !== $key ? $label : ucfirst(str_replace('_', ' ', $status));
    }
}
