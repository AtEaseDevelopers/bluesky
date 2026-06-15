<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {
        if (Auth::guard('web')->user()) {
            return redirect(route('member.products'));
        } elseif (Auth::check()) {
            return redirect(route('admin.dashboard'));
        } else {
            return view('home');
        }
    }
}
