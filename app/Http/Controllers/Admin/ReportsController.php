<?php

namespace App\Http\Controllers\Admin;

use App\Area;
use App\Driver;
use App\Http\Controllers\Controller;

use App\Exports\DailySummaryStockReport;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\DailySummaryReport;
use Illuminate\Support\Facades\DB;
use App\Exports\DailySaleReport;
use Illuminate\Http\Request;
use App\System;
use App\Helper;
use App\Order;
use App\Exports\SqlDoExportReport;
use App\Services\DailySalesReportService;

class ReportsController extends Controller
{
    public function daily_sales_report(Request $request, DailySalesReportService $reportService)
    {
        [$startDate, $endDate] = $reportService->dateRange($request);

        $data = $this->get_order_filters();
        $data['singleDateReport'] = true;
        $data['reportDate'] = $startDate;
        $data['startDate'] = $startDate;
        $data['endDate'] = $endDate;
        $data['orders'] = $reportService->salesLines($request);
        $data['paymentSummary'] = $reportService->paymentCollectionSummary($request);
        $data['reportService'] = $reportService;

        return view('admin.reports.daily_sales_report', $data);
    }

    public function export_daily_sales_report(Request $request, DailySalesReportService $reportService)
    {
        [$startDate, $endDate] = $reportService->dateRange($request);
        $filename = 'Daily Sale Report - ' . $startDate;
        if ($startDate !== $endDate) {
            $filename .= ' to ' . $endDate;
        }
        $filename .= '.xlsx';

        return Excel::download(new DailySaleReport($request, $reportService), $filename);
    }

    public function daily_summary_report(Request $request)
    {
        // format current date or from and to date
        $fdate = $request->fdate;
        $tdate = $request->tdate;

        $today = now()->toDateString();
        $startDate = $fdate ?: $today;
        $endDate = $tdate ?: $today;
        $startDate = min($startDate, $endDate);

        $data = $this->get_order_filters();
        $data['orders'] = DB::table('order_products')
            ->join('orders', 'orders.id', '=', 'order_products.order_id')
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->join('products', 'products.id', '=', 'order_products.product_id')
            ->select(
                'orders.id',
                'orders.created_at',
                'users.name',
                'order_products.product_name',
                'products.sku',
                'order_products.quantity',
                'order_products.weight',
                'orders.order_weight',
            )
            ->whereBetween('orders.created_at', [$startDate, $endDate . " 23:59:59"])
            ->when($request->id, function ($q) {
                return $q->where('orders.id', request()->id);
            })
            ->when($request->status, function ($q) {
                return $q->where('orders.status', request()->status);
            })
            ->when($request->driver, function ($q) {
                return $q->where('orders.driver_id', request()->driver);
            })
            ->when($request->customer, function ($q) {
                return $q->where('orders.user_id', request()->customer);
            })
            ->when($areaFilter = Area::orderFilterValue($request->input('area')), function ($q) use ($areaFilter) {
                return $q->where('orders.area', $areaFilter);
            })
            ->get()
            ->toArray();

        return view('admin.reports.daily_summary_report', $data);
    }

    public function export_daily_summary_report(Request $request)
    {
        $fdate = $request->fdate;
        $tdate = $request->tdate;

        $today = now()->toDateString();
        $startDate = $fdate ?: $today;
        $endDate = $tdate ?: $today;
        $date = min($startDate, $endDate);

        return Excel::download(new DailySummaryReport(), 'Daily Summary Report - ' . $date . '.xlsx');
    }

    public function daily_summary_stock_report(Request $request)
    {
        // format current date or from and to date
        $fdate = $request->fdate;
        $tdate = $request->tdate;

        $today = now()->toDateString();
        $startDate = $fdate ?: $today;
        $endDate = $tdate ?: $today;
        $startDate = min($startDate, $endDate);

        $data = $this->get_order_filters();

         $data['orders'] = DB::table('order_products')
            ->join('products', 'products.id', '=', 'order_products.product_id')
            ->join('orders', 'orders.id', '=', 'order_products.order_id') // <-- Add this join
            ->select(
                'order_products.product_name',
                'products.sku',
                DB::raw('SUM(order_products.quantity) AS quantity')
            )
            ->whereBetween('order_products.created_at', [$startDate, $endDate . " 23:59:59"])
            ->when($request->id, function ($q) {
                return $q->where('order_products.order_id', request()->id);
            })
            ->when($request->status, function ($q) {
                return $q->where('orders.status', request()->status);
            })
            ->when($request->driver, function ($q) {
                return $q->where('orders.driver_id', request()->driver);
            })
            ->when($request->customer, function ($q) {
                return $q->where('orders.user_id', request()->customer);
            })
            ->when($areaFilter = Area::orderFilterValue($request->input('area')), function ($q) use ($areaFilter) {
                return $q->where('orders.area', $areaFilter);
            })
            ->groupBy('order_products.product_name', 'products.sku') // group properly
            ->get()
            ->toArray();

        return view('admin.reports.daily_summary_stock_report', $data);
    }

    public function export_daily_summary_stock_report(Request $request)
    {
        $fdate = $request->fdate;
        $tdate = $request->tdate;

        $today = now()->toDateString();
        $startDate = $fdate ?: $today;
        $endDate = $tdate ?: $today;
        $date = min($startDate, $endDate);

        return Excel::download(new DailySummaryStockReport(), 'Daily Summary Stock Report - ' . $date . '.xlsx');
    }

    private function get_order_filters()
    {
        $options = app(DailySalesReportService::class)->filterOptions();

        return array_merge($options, [
            'query_params' => Helper::query_params(request()->input()),
        ]);
    }

    public function do_report(Request $request)
    {
        // format current date or from and to date
        $fdate = $request->fdate;
        $tdate = $request->tdate;

        $today = now()->toDateString();
        $startDate = $fdate ?: $today;
        $endDate = $tdate ?: $today;
        $startDate = min($startDate, $endDate);

        $data = $this->get_order_filters();

        $data['orders'] = DB::table('orders')
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->select(
                'users.name',
                'orders.id',
                'orders.user_id',
                'orders.order_weight',
                'orders.total_price',
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
                'orders.driver_id',
                'orders.status',
                'orders.created_at',
                'orders.updated_at',
                DB::raw("
                    (
                        SELECT GROUP_CONCAT(CONCAT(p.name, ': ', IFNULL(CONCAT(op.weight, 'KG'), '')) SEPARATOR '<br />')
                        FROM products p
                        JOIN order_products op ON p.id = op.product_id
                        WHERE op.order_id = orders.id
                    ) as product_info
                "),
            )
            ->whereBetween('orders.created_at', [$startDate, $endDate . " 23:59:59"])
            ->when($request->id, function ($q) {
                return $q->where('orders.id', request()->id);
            })
            ->when($request->status, function ($q) {
                return $q->where('orders.status', request()->status);
            })
            ->when($request->driver, function ($q) {
                return $q->where('orders.driver_id', request()->driver);
            })
            ->when($request->customer, function ($q) {
                return $q->where('orders.user_id', request()->customer);
            })
            ->when($areaFilter = Area::orderFilterValue($request->input('area')), function ($q) use ($areaFilter) {
                return $q->where('orders.area', $areaFilter);
            })
            ->get()
            ->toArray();

        $data['orderModels'] = Order::whereIn(
            'id',
            collect($data['orders'])->pluck('id')
        )->get()->keyBy('id');

        $drivers_arr = Driver::optionsForSelect();
        $data['order_drivers'] = $drivers_arr;

        return view('admin.reports.do_report', $data);
    }
    
    public function sql_do_export_report(Request $request)
    {
        // format current date or from and to date
        // $fdate = $request->fdate;
        // $tdate = $request->tdate;

        // $today = now()->toDateString();
        // $startDate = $fdate ?: $today;
        // $endDate = $tdate ?: $today;
        // $startDate = min($startDate, $endDate);

        $data = $this->get_order_filters();

        // $data['orders'] = DB::table('orders')
        //     ->join('users', 'users.id', '=', 'orders.user_id')
        //     ->select(
        //         'users.name',
        //         'orders.id',
        //         'orders.user_id',
        //         'orders.order_weight',
        //         'orders.total_price',
        //         'orders.payment_method',
        //         'orders.area',
        //         'orders.billing_address',
        //         'orders.billing_city',
        //         'orders.billing_postcode',
        //         'orders.billing_state',
        //         'orders.shipping_address',
        //         'orders.shipping_city',
        //         'orders.shipping_postcode',
        //         'orders.shipping_state',
        //         'orders.driver_id',
        //         'orders.status',
        //         'orders.created_at',
        //         'orders.updated_at',
        //         DB::raw("
        //             (
        //                 SELECT GROUP_CONCAT(CONCAT(p.name, ': ', op.weight, 'KG') SEPARATOR '<br />')
        //                 FROM products p
        //                 JOIN order_products op ON p.id = op.product_id
        //                 WHERE op.order_id = orders.id
        //             ) as product_info
        //         "),
        //     )
        //     ->whereBetween('orders.created_at', [$startDate, $endDate . " 23:59:59"])
        //     ->when($request->id, function ($q) {
        //         return $q->where('orders.id', request()->id);
        //     })
        //     ->when($request->status, function ($q) {
        //         return $q->where('orders.status', request()->status);
        //     })
        //     ->when($request->driver, function ($q) {
        //         return $q->where('orders.driver_id', request()->driver);
        //     })
        //     ->when($request->customer, function ($q) {
        //         return $q->where('orders.user_id', request()->customer);
        //     })
        //     ->when($request->area, function ($q) {
        //         return $q->where('orders.area', request()->area);
        //     })
        //     ->get()
        //     ->toArray();

        $drivers_arr = Driver::optionsForSelect();
        $data['order_drivers'] = $drivers_arr;

        return view('admin.reports.sql_do_export_report', $data);
    }

    public function sql_do_export_report_excel(Request $req) {
        $fdate = $req->fdate;
        $tdate = $req->tdate;

        $today = now()->toDateString();
        $startDate = $fdate ?: $today;
        $endDate = $tdate ?: $today;
        $date = min($startDate, $endDate);

        return Excel::download(new SqlDoExportReport($req), 'SQL DO Export Report - ' . $date . '.xlsx');
    }
}
