<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use App\Exports\AdminCustomerExport;
use Illuminate\Support\Facades\Crypt;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Helper;
use App\Imports\CustomersImport;
use App\ProductVisibility;
use App\System;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class CustomerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth_admin');
    }

    public function index(Request $request)
    {
        // Filtered data
        $name = $request['name'];
        $email = $request['email'];
        $category = $request['category'];
        $area = $request['area'];
        $shipping_state = $request['shipping_state'];
        $status = $request['status'];
        $customer_type = $request['customer_type'];

        $users = User::query()
            ->leftJoin('areas', 'areas.id', '=', 'users.area')
            ->select(
                'users.*',
                DB::raw('areas.area_name as area'),
            )
            ->when(($name != null), function ($q) use ($name) {
                return $q->where('users.name', 'LIKE', "%$name%");
            })
            ->when(($email != null), function ($q) use ($email) {
                return $q->where('users.email', 'LIKE', "%$email%");
            })
            ->when(($category != null), function ($q) use ($category) {
                return $q->where('users.category', $category);
            })
            ->when(($area != null), function ($q) use ($area) {
                return $q->where('users.area', $area);
            })
            ->when(($shipping_state != null), function ($q) use ($shipping_state) {
                return $q->where('users.shipping_state', $shipping_state);
            })
            ->when(($status != null), function ($q) use ($status) {
                return $q->where('users.status', $status);
            })
            ->when($customer_type === 'cod', function ($q) {
                return $q->where(function ($q) {
                    $q->where('users.customer_type', 'cod')->orWhereNull('users.customer_type');
                });
            })
            ->when($customer_type === 'credit', function ($q) {
                return $q->where('users.customer_type', 'credit');
            })
            ->paginate(15);

        $areas = DB::table('areas')->select('id', 'area_name')->get()->toArray();
        // $category_list = User::select('category')
        //     ->groupBy('category')
        //     ->pluck('category')
        //     ->toArray();
        $category_list = DB::table('customer_categories')->select('id', 'category')->get()->toArray();

        return view('admin.customers.index', [
                'category_list' => $category_list,
                'users' => $users,
                'areas' => $areas,
                'input' => $request->all(),
                'query_params' => Helper::query_params($request->input()),
                'shipping_state_options' => System::$country_state['MY'],
            ]
        );
    }

    public function export(Request $request)
    {
        $users = User::select('name', 'email', 'category', 'shipping_address', 'shipping_postcode', 'shipping_state', 'remark', 'status', 'created_at as join_date');

        if ($filter_name = $request->input('name')) {
            $users->where('name', 'LIKE', "%$filter_name%");
        }

        if ($filter_email = $request->input('email')) {
            $users->where('email', 'LIKE', "%$filter_email%");
        }

        if ($filter_category = $request->input('category')) {
            $users->where('category', $filter_category);
        }

        if ($filter_shipping_state = $request->input('shipping_state')) {
            $users->where('shipping_state', $filter_shipping_state);
        }

        if ($filter_status = $request->input('status')) {
            $users->where('status', $filter_status);
        }

        if ($filter_customer_type = $request->input('customer_type')) {
            if ($filter_customer_type === 'cod') {
                $users->where(function ($q) {
                    $q->where('customer_type', 'cod')->orWhereNull('customer_type');
                });
            } elseif ($filter_customer_type === 'credit') {
                $users->where('customer_type', 'credit');
            }
        }

        $header = ['No', 'Name', 'Email', 'Category', 'Shipping Address', 'Shipping Postcode', 'Shipping State', 'remark', 'Status', 'Created At']; // Adjust the header based on your data model
        return Excel::download(new AdminCustomerExport($users->get(), $header), Carbon::now()->format('YmdHis').'-Customer-List.xlsx');
    }

    public function syncAutoCount(Request $request)
    {
        $request->validate([
            'customer_ids' => 'required|array|min:1',
            'customer_ids.*' => 'integer|exists:users,id',
        ]);

        $result = app(\App\Services\AutoCountSyncService::class)->syncCustomers(
            $request->input('customer_ids', []),
            Auth::guard('web_admin')->id()
        );

        if ($result['synced'] === 0) {
            $message = $result['errors'][0] ?? __('customers.js.sync_autocount_none');

            return back()->with('error', $message);
        }

        $message = __('customers.js.sync_autocount_success', ['count' => $result['synced']]);

        if ($result['skipped'] > 0) {
            $message .= ' ' . __('customers.js.sync_autocount_skipped', ['count' => $result['skipped']]);
        }

        return back()->with('success', $message);
    }

    public function deleteCustomerProduct(Request $request)
    {
        ProductVisibility::where('id', $request['id'])->delete();
        return response()->json([]);
    }

    public function import_customers()
    {
        return view('admin.customers.import_customers');
    }

    public function import_customers_submit(Request $request)
    {
        $request->validate(
            [
                'file' => 'required|file|mimes:xlsx,csv',
            ]
        );

        Excel::import(new CustomersImport, $request->file('file'));
        try {
            // Import the file with transaction handling inside the import
            return back();
        } catch (\Exception $e) {
            // Catch the exception thrown during the import process and display it
            return back();
        }
    }
}
