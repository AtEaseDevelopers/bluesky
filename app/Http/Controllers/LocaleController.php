<?php

namespace App\Http\Controllers;

use App\Services\LocaleService;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function switch(Request $request, string $locale, LocaleService $localeService)
    {
        $localeService->switch($request, $locale);

        return redirect()->back();
    }
}
