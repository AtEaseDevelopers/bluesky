@php
    $company = $company ?? config('portal.company');
    $addressLines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $company['address'] ?? '')));
@endphp
<table style="width: 100%; border-collapse: collapse;">
    <tr>
        <td style="text-align: center; width: 100%;">
            <span style="font-size: 22px; font-weight: 700;">{{ $company['name'] ?? env('APP_NAME') }}</span><br>
            @if (!empty($company['registration_no']))
                <span style="font-size: 12px;">({{ $company['registration_no'] }})</span><br>
            @endif
            @foreach ($addressLines as $line)
                <span style="font-size: 16px;">{{ $line }}</span><br>
            @endforeach
        </td>
    </tr>
</table>
