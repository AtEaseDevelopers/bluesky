@php
    $currentLocale = $currentLocale ?? app()->getLocale();
    $supportedLocales = $supportedLocales ?? config('locale.supported', ['en' => 'English']);
@endphp
<div class="text-end mb-3">
    @foreach ($supportedLocales as $code => $label)
        <a href="{{ route('locale.switch', $code) }}"
            class="btn btn-sm {{ $currentLocale === $code ? 'btn-primary' : 'btn-outline-secondary' }} me-1">
            {{ $label }}
        </a>
    @endforeach
</div>
