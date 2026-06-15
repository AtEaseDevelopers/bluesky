<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Product;
use App\ProductDailyPrice;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RemoveProductDailyPriceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth_admin');
    }

    public function removeProductDailyPrice(Request $request, ProductDailyPrice $product_daily_price)
    {
        if ($product_daily_price->status != ProductDailyPrice::$status['active']) {
            return redirect()->to('/product-daily-prices')->with('error', "The setting is not found in the system.");
        }
        
        $product_daily_price->update(
            [
            'status' => ProductDailyPrice::$status['removed']
            ]
        );

        return redirect(url('/admin/product-daily-prices'))->with('success', "Daily price setup has been removed.");
    }
}
