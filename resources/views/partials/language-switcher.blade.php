@php
    $currentLocale = $currentLocale ?? app()->getLocale();
    $supportedLocales = $supportedLocales ?? config('locale.supported', ['en' => 'English']);
@endphp
<li class="nav-item dropdown">
    <a class="nav-link dropdown-toggle" href="javascript:void(0);" role="button"
        data-bs-toggle="dropdown" aria-expanded="false">
        {{ __('ui.language') }}
    </a>
    <ul class="dropdown-menu dropdown-menu-end">
        @foreach ($supportedLocales as $code => $label)
            <li>
                <a class="dropdown-item {{ $currentLocale === $code ? 'active' : '' }}"
                    href="{{ route('locale.switch', $code) }}">
                    {{ $label }}
                </a>
            </li>
        @endforeach
    </ul>
</li>
