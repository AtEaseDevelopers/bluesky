<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Driver\Concerns\RecordsDriverPayments;
use App\Order;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerController extends Controller
{
    use RecordsDriverPayments;

    /**
     * Customers permanently assigned to the logged-in driver, with a summary
     * of their outstanding (non-cancelled) invoices.
     */
    public function index()
    {
        $driver = Auth::guard('web_driver')->user();

        $customers = User::where('default_driver_id', $driver->id)
            ->with(['orders' => function ($query) {
                $query->where('status', '!=', Order::$status['cancelled'])
                    ->orderByDesc('id');
            }])
            ->orderBy('name')
            ->paginate(20);

        // Grand total across every assigned customer (not just the current page),
        // computed in PHP so the balance-per-invoice clamp stays DB-agnostic.
        $grandOutstanding = Order::whereIn('user_id', function ($query) use ($driver) {
                $query->select('id')->from('users')->where('default_driver_id', $driver->id);
            })
            ->where('status', '!=', Order::$status['cancelled'])
            ->get(['total_price', 'paid_amount'])
            ->sum(function (Order $order) {
                return $order->balanceDue();
            });

        return view('driver.customers.index', [
            'customers' => $customers,
            'grandOutstanding' => $grandOutstanding,
        ]);
    }

    /**
     * A single assigned customer with their invoice payment status and due dates.
     */
    public function show($id)
    {
        $driver = Auth::guard('web_driver')->user();

        $customer = User::where('id', $id)
            ->where('default_driver_id', $driver->id)
            ->firstOrFail();

        $invoices = $customer->orders()
            ->where('status', '!=', Order::$status['cancelled'])
            ->orderByRaw('CASE WHEN payment_due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('payment_due_date')
            ->orderByDesc('id')
            ->get();

        $customerType = $customer->isCreditCustomer() ? 'credit' : 'cod';

        return view('driver.customers.show', [
            'customer' => $customer,
            'invoices' => $invoices,
            'outstanding' => self::outstandingTotal($invoices),
            'overdueCount' => self::overdueCount($invoices),
            'paymentMethods' => self::driverPaymentMethodsFor($customerType, $customer->isCreditCustomer()),
            'proofRequiredMethods' => self::$driverProofRequiredMethods,
        ]);
    }

    /**
     * Record a payment collected from an assigned customer against one of their
     * outstanding invoices.
     */
    public function recordPayment(Request $request, $customerId, $orderId)
    {
        $driver = Auth::guard('web_driver')->user();

        $customer = User::where('id', $customerId)
            ->where('default_driver_id', $driver->id)
            ->firstOrFail();

        $order = $customer->orders()
            ->where('id', $orderId)
            ->where('status', '!=', Order::$status['cancelled'])
            ->firstOrFail();

        return $this->recordDriverPayment($request, $order);
    }

    /**
     * Total outstanding balance across a collection of invoices.
     *
     * @param  iterable<Order>  $invoices
     */
    public static function outstandingTotal($invoices): float
    {
        $total = 0.0;
        foreach ($invoices as $invoice) {
            $total += $invoice->balanceDue();
        }

        return $total;
    }

    /**
     * Number of overdue invoices in a collection.
     *
     * @param  iterable<Order>  $invoices
     */
    public static function overdueCount($invoices): int
    {
        $count = 0;
        foreach ($invoices as $invoice) {
            if ($invoice->paymentDueStatusKey() === 'overdue') {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Payment pill (label + css class) for an invoice's paid/unpaid state.
     */
    public static function paymentPill(Order $invoice): array
    {
        if ($invoice->isFullyPaid()) {
            return ['label' => __('driver_portal.payment.paid'), 'class' => 'pill-paid'];
        }

        if ((float) $invoice->paid_amount > 0) {
            return ['label' => __('driver_portal.payment.partial'), 'class' => 'pill-partial'];
        }

        return ['label' => __('driver_portal.payment.unpaid'), 'class' => 'pill-unpaid'];
    }

    /**
     * Due-date badge (label + css class) derived from the invoice due status.
     * Returns null when no due badge should be shown (fully paid).
     */
    public static function dueBadge(Order $invoice): ?array
    {
        switch ($invoice->paymentDueStatusKey()) {
            case 'overdue':
                return ['label' => __('driver_portal.due.overdue'), 'class' => 'pill-unpaid'];
            case 'due_today':
                return ['label' => __('driver_portal.due.today'), 'class' => 'pill-partial'];
            case 'not_due':
                return ['label' => __('driver_portal.due.on_date', [
                    'date' => optional($invoice->payment_due_date)->format('d M Y'),
                ]), 'class' => 'pill-due'];
            case 'not_set':
                return ['label' => __('driver_portal.due.not_set'), 'class' => 'pill-due'];
            case 'not_applicable':
                return $invoice->isCodCustomer()
                    ? ['label' => __('driver_portal.customers.cod'), 'class' => 'pill-due']
                    : null;
            default:
                return null;
        }
    }
}
