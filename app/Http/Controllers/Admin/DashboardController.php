<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth_admin');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        $total_orders = Order::where('status', '!=', Order::$status['cancelled'])->count();
        $total_sales = Order::where('status', '!=', Order::$status['cancelled'])->sum('total_price');

        $currentDay = Carbon::now()->day;
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;
        $total_orders_month = Order::where('status', '!=', Order::$status['cancelled'])
            ->whereMonth('created_at', $currentMonth)
            ->whereYear('created_at', $currentYear)
            ->count();
        $total_sales_month = Order::where('status', '!=', Order::$status['cancelled'])
            ->whereMonth('created_at', $currentMonth)
            ->whereYear('created_at', $currentYear)
            ->sum('total_price');
        $total_orders_today = Order::where('status', '!=', Order::$status['cancelled'])
            ->where('created_at', '>=', Carbon::now()->format('Y-m-d 00:00:00'))
            ->where('created_at', '<=', Carbon::now()->format('Y-m-d 23:59:59'))
            ->count();
        $total_sales_today = Order::where('status', '!=', Order::$status['cancelled'])
            ->where('created_at', '>=', Carbon::now()->format('Y-m-d 00:00:00'))
            ->where('created_at', '<=', Carbon::now()->format('Y-m-d 23:59:59'))
            ->sum('total_price');

        
        $today_orders = Order::where('status', '!=', Order::$status['cancelled'])
            ->whereDay('created_at', $currentDay)
            ->whereMonth('created_at', $currentMonth)
            ->whereYear('created_at', $currentYear)
            ->with('customer')
            ->get();

        // Fetch daily sales data from the Order model
        $first_day = Carbon::now()->firstOfMonth()->format('Y-m-d');
        $last_day = Carbon::now()->lastOfMonth()->format('Y-m-d');

        $sales_date = $first_day;
        while($sales_date <= $last_day){
            $daily_sales[$sales_date] = 0;
            $sales_date = Carbon::parse($sales_date)->addDay()->format('Y-m-d');
        }

        $daily_sales_data = Order::whereYear('created_at', $currentYear)
            ->whereMonth('created_at', $currentMonth)
            ->where('status', '!=', Order::$status['cancelled'])
            ->selectRaw('DATE(created_at) as date, SUM(total_price) as sales')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('sales', 'date');

        foreach ($daily_sales_data as $key => $value) {
            $daily_sales[$key] = (double) $value;
        }

        return view(
            'admin.dashboard', [
            'summary' => [
                'total_orders' => $total_orders ?? 0,
                'total_orders_month' => $total_orders_month ?? 0,
                'total_orders_today' => $total_orders_today ?? 0,
                'total_sales' => $total_sales ?? 0,
                'total_sales_month' => $total_sales_month ?? 0,
                'total_sales_today' => $total_sales_today ?? 0,
            ],
            'today_orders' => $today_orders,
            'charts' => [
                'daily_sales' => [
                    'dates' => array_keys($daily_sales),
                    'sales' => array_values($daily_sales),
                ],
            ],
            ]
        );
    }

    public function profile()
    {
        $data['user'] = Auth::guard('web_admin')->user();

        return view('admin.profile', $data);
    }

    public function profile_update(Request $request)
    {
        $user = Auth::guard('web_admin')->user();
        if ($request['type'] == 'profile') {
            $this->validate($request, [
                'name' => 'required',
                'username' => 'required|unique:admins,username,' . $user->id,
                'email' => 'required|unique:admins,email,' . $user->id,
            ]);

            $user->update([
                'name' => $request->name,
                'username' => $request->username,
                'email' => $request->email,
            ]);

            return back()->with('success', 'Profile has been updated');
        } elseif ($request['type'] == 'password') {
            $this->validate($request, [
                'old_password' => ['required'],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
            ]);

            if (!\Hash::check($request->old_password, $user->password)) {
                return back()->with('warning', 'Old password is incorrect');
            }

            $user->update([
                'password' => bcrypt($request->password)
            ]);

            return back()->with('success', 'Password has been changed');
        }
    }
}
