<?php

namespace App\Http\Controllers\Admin;

use App\Helper;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Product;
use App\ProductDailyPrice;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Maatwebsite\Excel\Facades\Excel;

class ProductDailyPriceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth_admin');
    }

    public function index(Request $request)
    {
        $daily_prices = ProductDailyPrice::selectRaw('date, MAX(created_at) as created_at, MAX(updated_at) as updated_at')->where('status', ProductDailyPrice::$status['active']);

        if ($request->get('fdate')) {
            $daily_prices->where('date', '>=', $request->get('fdate'));
        }
        if ($request->get('tdate')) {
            $daily_prices->where('date', '<=', $request->get('tdate'));
        }
        
        foreach ($daily_prices as $key => $value) {
        }

        $daily_prices = $daily_prices->orderBy('date', 'desc')->groupBy('date')->get();
        return view(
            'admin.products.product-daily-price', [
            'daily_prices' => $daily_prices,
            'status_options' => ProductDailyPrice::$status,
            'input' => $request->all(),
            ]
        );
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
