@php
    $logoPath = config('portal.company.logo', 'assets/images/logo.png');
    $logoAsset = asset($logoPath);
    $logoHeight = $height ?? '50px';
    $logoClass = trim('app-logo ' . ($class ?? ''));
@endphp
<img src="{{ $logoAsset }}" alt="{{ config('portal.company.name') }}" class="{{ $logoClass }}" style="height: {{ $logoHeight }}; width: auto;">
