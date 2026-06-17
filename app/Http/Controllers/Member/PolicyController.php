<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;

class PolicyController extends Controller
{
    public const PAGES = [
        'about-us' => 'About Us',
        'contact-us' => 'Contact Us',
        'return-refund' => 'Return / Refund Policy',
        'shipping-fulfilment' => 'Shipping / Fulfilment Policy',
        'privacy' => 'Privacy Policy',
    ];

    public function show(string $page)
    {
        if (!array_key_exists($page, self::PAGES)) {
            abort(404);
        }

        return view('member.policies.show', [
            'page' => $page,
            'pageTitle' => self::PAGES[$page],
            'pages' => self::PAGES,
            'company' => config('portal.company'),
            'content' => config("portal.pages.{$page}"),
            'kycNote' => config('portal.kyc_note'),
        ]);
    }
}
