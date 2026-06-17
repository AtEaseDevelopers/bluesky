<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\PublicOrderLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PublicOrderLinkController extends Controller
{
    public function index()
    {
        $links = PublicOrderLink::with(['order', 'creator'])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return view('admin.public-order-links.index', [
            'links' => $links,
        ]);
    }

    public function generate(Request $request)
    {
        $link = PublicOrderLink::create([
            'token' => PublicOrderLink::generateToken(),
            'created_by' => Auth::guard('web_admin')->id(),
        ]);

        return redirect(route('admin.public-order-links.index'))
            ->with('success', 'Public order link generated. Share it with the customer — it expires after one order is submitted.')
            ->with('generated_link', $link->url);
    }
}
