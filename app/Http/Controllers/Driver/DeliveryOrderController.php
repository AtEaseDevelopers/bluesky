<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DeliveryOrderController extends Controller
{
    /**
     * Delivery-status values the driver is allowed to set, mapped onto the
     * system's existing order statuses:
     *   "In Route"  => delivering
     *   "Delivered" => completed
     */
    public static $driver_statuses = [
        'delivering' => 'In Route',
        'completed' => 'Delivered',
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

        // Optional status filter from the tab/segmented control.
        if ($request->filled('status') && array_key_exists($request->status, Order::$status)) {
            $query->where('status', Order::$status[$request->status]);
        }

        // Portable ordering (works on MySQL and SQLite): active deliveries first.
        $orders = $query->orderByRaw("CASE status WHEN 'delivering' THEN 0 WHEN 'processing' THEN 1 WHEN 'completed' THEN 2 ELSE 3 END")
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

        $order->load(['customer', 'products']);

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

        $order->update(['status' => $data['status']]);

        return back()->with('success', 'Delivery status updated to "' . self::$driver_statuses[$data['status']] . '".');
    }

    /**
     * Record a payment collected from the customer.
     */
    public function recordPayment(Request $request, $id)
    {
        $order = $this->findAssignedOrder($id);

        $data = $request->validate([
            'payment_method' => ['required', 'in:' . implode(',', array_keys(self::$payment_methods))],
            'paid_amount' => ['required', 'numeric', 'min:0'],
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

        $proofFilename = $order->payment_proof;
        if ($request->hasFile('payment_proof')) {
            $path = Order::$path . '/' . $order->id;
            do {
                $extension = $request->file('payment_proof')->getClientOriginalExtension();
                $proofFilename = time() . rand() . '.' . $extension;
            } while (Storage::disk('local')->exists($path . '/' . $proofFilename));

            Storage::disk('local')->put($path . '/' . $proofFilename, file_get_contents($request->file('payment_proof')));
        }

        $order->update([
            'payment_method' => $data['payment_method'],
            'paid_amount' => $data['paid_amount'],
            'payment_proof' => $proofFilename,
            'payment_collected_at' => Carbon::now(),
            'payment_collected_by' => Auth::guard('web_driver')->id(),
        ]);

        return back()->with('success', 'Payment recorded successfully.');
    }

    /**
     * Serve the uploaded payment proof for an assigned order.
     */
    public function downloadProof($id)
    {
        $order = $this->findAssignedOrder($id);

        if (!$order->payment_proof) {
            abort(404, 'No payment proof uploaded.');
        }

        $path = Order::$path . '/' . $order->id . '/' . $order->payment_proof;
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
}
