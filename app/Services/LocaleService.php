<?php

namespace App\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LocaleService
{
    public function supported(): array
    {
        return config('locale.supported', ['en' => 'English']);
    }

    public function isSupported(string $locale): bool
    {
        return array_key_exists($locale, $this->supported());
    }

    public function resolve(Request $request): string
    {
        $sessionLocale = $request->session()->get('locale');
        if ($sessionLocale && $this->isSupported($sessionLocale)) {
            return $sessionLocale;
        }

        $accountLocale = $this->resolveFromAuthenticatedUser();
        if ($accountLocale) {
            return $accountLocale;
        }

        $default = config('locale.default', config('app.locale', 'en'));

        return $this->isSupported($default) ? $default : 'en';
    }

    public function apply(Request $request): string
    {
        $locale = $this->resolve($request);
        app()->setLocale($locale);

        return $locale;
    }

    public function switch(Request $request, string $locale): string
    {
        if (!$this->isSupported($locale)) {
            abort(404);
        }

        $request->session()->put('locale', $locale);
        $this->persistForAuthenticatedUser($locale);
        app()->setLocale($locale);

        return $locale;
    }

    public function syncSessionFromUser(?Authenticatable $user): void
    {
        $locale = $this->localeFromUser($user);
        if ($locale) {
            session(['locale' => $locale]);
            app()->setLocale($locale);
        }
    }

    public function persistForAuthenticatedUser(string $locale): void
    {
        foreach (['web_admin', 'web', 'web_driver'] as $guard) {
            $user = Auth::guard($guard)->user();
            if (!$user) {
                continue;
            }

            if (!in_array('locale', $user->getFillable(), true)) {
                continue;
            }

            if (($user->locale ?? null) === $locale) {
                return;
            }

            $user->forceFill(['locale' => $locale])->save();

            return;
        }
    }

    public function htmlLang(string $locale): string
    {
        return $locale === 'zh_CN' ? 'zh-CN' : $locale;
    }

    protected function resolveFromAuthenticatedUser(): ?string
    {
        foreach (['web_admin', 'web', 'web_driver'] as $guard) {
            $user = Auth::guard($guard)->user();
            $locale = $this->localeFromUser($user);
            if ($locale) {
                return $locale;
            }
        }

        return null;
    }

    protected function localeFromUser(?Authenticatable $user): ?string
    {
        if (!$user || empty($user->locale)) {
            return null;
        }

        return $this->isSupported($user->locale) ? $user->locale : null;
    }
}
