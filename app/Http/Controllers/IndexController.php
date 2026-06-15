<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use App\Product;
use App\User;

class IndexController extends Controller
{
    public function get_products_list(Request $request)
    {
        $id = $request['id'];
        if ($id != 'products_visibility') {
            $customer = User::find($id);
        }

        $products = Product::
            where('status', Product::$status['active'])
            ->get();

        $products_output = [];
        foreach ($products as $key => $value) {
            $image = json_decode($value->images, true);
            $products[$key]->original_price = $value->price;
            if ($id != 'products_visibility') {
                $products[$key]->price = Product::get_today_price($value->id, $customer);
            }
            if (isset($image[0])) {
                $products[$key]->image_url = url('/') . '/' . Product::$path."/".$value->id."/".$image[0];
            } else {
                $products[$key]->image_url = asset('assets/images/product-default.jpg');
            }
            $products[$key]->product_option = Product::getOption($value->id, true);
            $products_output[$value->id] = $products[$key];
        }

        $selected_ids = json_decode($request->input('selected_ids', '[]'), true);

        $view = view('partials.products_list', compact('products', 'selected_ids'))->render();
        return Response::json(['success' => true, 'view' => $view]);
    }
}
