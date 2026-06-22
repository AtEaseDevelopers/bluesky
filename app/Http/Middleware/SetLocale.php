<?php

namespace App\Http\Middleware;

use App\Services\LocaleService;
use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    public function __construct(protected LocaleService $localeService)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $locale = $this->localeService->apply($request);
        view()->share('currentLocale', $locale);
        view()->share('supportedLocales', $this->localeService->supported());
        view()->share('htmlLang', $this->localeService->htmlLang($locale));

        return $next($request);
    }
}
