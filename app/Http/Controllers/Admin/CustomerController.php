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
use App\User;
use App\Services\CustomerLifecycleService;
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
        $status = $request['status'];
        $customer_type = $request['customer_type'];

        $users = User::query()
            ->withCount('orders')
            ->leftJoin('areas', 'areas.id', '=', 'users.area')
            ->select(
                'users.*',
                DB::raw('areas.area_name as area'),
            )
            ->when(($name != null), function ($q) use ($name) {
                Helper::applyOrLikeSearch($q, [
                    'users.name',
                    'users.attn_name',
                    'users.attn_contact',
                    'users.billing_address',
                    'users.shipping_address',
                    'users.remark',
                    'users.sql_customer_code',
                    'users.category',
                ], $name);
            })
            ->when(($email != null), function ($q) use ($email) {
                $pattern = Helper::likePattern($email);
                if ($pattern === null) {
                    return $q;
                }

                return $q->where('users.email', 'LIKE', $pattern);
            })
            ->when(($category != null), function ($q) use ($category) {
                return $q->where('users.category', $category);
            })
            ->when($status === User::$user_status['active'], function ($q) {
                return $q->where('users.status', User::$user_status['active']);
            })
            ->when($status === 'inactive', function ($q) {
                return $q->whereIn('users.status', User::inactiveStatusValues());
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

        // $category_list = User::select('category')
        //     ->groupBy('category')
        //     ->pluck('category')
        //     ->toArray();
        $category_list = DB::table('customer_categories')->select('id', 'category')->get()->toArray();

        return view('admin.customers.index', [
                'category_list' => $category_list,
                'users' => $users,
                'input' => $request->all(),
                'query_params' => Helper::query_params($request->input()),
            ]
        );
    }

    public function export(Request $request)
    {
        $users = User::select('name', 'email', 'category', 'shipping_address', 'shipping_postcode', 'shipping_state', 'remark', 'status', 'created_at as join_date');

        if ($filter_name = $request->input('name')) {
            Helper::applyOrLikeSearch($users, [
                'name',
                'attn_name',
                'attn_contact',
                'billing_address',
                'shipping_address',
                'remark',
                'sql_customer_code',
                'category',
            ], $filter_name);
        }

        if ($filter_email = $request->input('email')) {
            $pattern = Helper::likePattern($filter_email);
            if ($pattern !== null) {
                $users->where('email', 'LIKE', $pattern);
            }
        }

        if ($filter_category = $request->input('category')) {
            $users->where('category', $filter_category);
        }

        $filter_status = $request->input('status');
        if ($filter_status === User::$user_status['active']) {
            $users->where('status', User::$user_status['active']);
        } elseif ($filter_status === 'inactive') {
            $users->whereIn('status', User::inactiveStatusValues());
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

    public function destroy(string $customer, CustomerLifecycleService $lifecycleService)
    {
        $user = User::findOrFail(decrypt($customer));

        try {
            $name = $user->name;
            $lifecycleService->delete($user);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect(route('admin.customers'))->with('success', __('customers.delete_success', ['name' => $name]));
    }

    public function deactivate(string $customer, CustomerLifecycleService $lifecycleService)
    {
        $user = User::findOrFail(decrypt($customer));

        try {
            $lifecycleService->deactivate($user);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('customers.deactivate_success', ['name' => $user->name]));
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
