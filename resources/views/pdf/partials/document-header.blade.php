@php
    $locale = $locale ?? 'zh_CN';
    $company = $company ?? config('portal.company');
    $addressLines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $company['address'] ?? '')));

    $metaNumberLabel = $number_label ?? __('pdf.meta.invoice_no', [], $locale);
    $metaNumber = $number_value ?? '';
    $metaDate = $date ?? '';
    $metaTime = $time ?? '';
    $metaTerm = $payment_term ?? '-';
    $metaCurrency = $currency ?? 'MYR';
    $metaCustomerCode = $customer_code ?? '-';
    $metaFulfillment = $fulfillment ?? (isset($order) ? $order->fulfillmentTypeLabel() : null);
@endphp
<!-- Document title -->
<table style="width: 100%; border-collapse: collapse; font-family: 'Noto Sans SC', 'Noto Sans TC', 'DejaVu Sans', sans-serif;">
    <tr>
        <td style="text-align: center; padding-bottom: 14px;">
            <span style="font-size: 24px; font-weight: 700; letter-spacing: 6px;">{{ $doc_title ?? __('pdf.doc.invoice_title', [], $locale) }}</span>
        </td>
    </tr>
</table>
<!-- Company (left) + document meta (right) -->
<table style="width: 100%; border-collapse: collapse; font-family: 'Noto Sans SC', 'Noto Sans TC', 'DejaVu Sans', sans-serif;">
    <tr>
        <td style="width: 58%; vertical-align: top;">
            <table style="width: 100%; border-collapse: collapse;">
                <tr><td style="font-size: 15px; font-weight: 700; padding: 1px 0;">{{ $company['name'] ?? env('APP_NAME') }}</td></tr>
                @if (!empty($company['registration_no']))
                    <tr><td style="font-size: 12px; font-weight: 700; padding: 1px 0;">REG No: {{ $company['registration_no'] }}</td></tr>
                @endif
                @if (!empty($company['tin_no']))
                    <tr><td style="font-size: 12px; font-weight: 700; padding: 1px 0;">TIN No: {{ $company['tin_no'] }}</td></tr>
                @endif
                @foreach ($addressLines as $line)
                    <tr><td style="font-size: 12px; padding: 1px 0;">{{ $line }}</td></tr>
                @endforeach
                @if (!empty($company['phone']))
                    <tr><td style="font-size: 12px; padding: 1px 0;">Phone : {{ $company['phone'] }}</td></tr>
                @endif
            </table>
        </td>
        <td style="width: 42%; vertical-align: top;">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="width: 40%; font-size: 12px; padding: 1px 0;">{{ $metaNumberLabel }}</td>
                    <td style="width: 5%; font-size: 12px;">:</td>
                    <td style="font-size: 12px; font-weight: 700;">{{ $metaNumber }}</td>
                </tr>
                <tr>
                    <td style="font-size: 12px; padding: 1px 0;">{{ __('pdf.meta.date', [], $locale) }}</td>
                    <td style="font-size: 12px;">:</td>
                    <td style="font-size: 12px;">{{ $metaDate }}</td>
                </tr>
                @if ($metaTime !== '' && $metaTime !== null)
                    <tr>
                        <td style="font-size: 12px; padding: 1px 0;">{{ __('pdf.meta.time', [], $locale) }}</td>
                        <td style="font-size: 12px;">:</td>
                        <td style="font-size: 12px;">{{ $metaTime }}</td>
                    </tr>
                @endif
                <tr>
                    <td style="font-size: 12px; padding: 1px 0;">{{ __('pdf.meta.payment_term', [], $locale) }}</td>
                    <td style="font-size: 12px;">:</td>
                    <td style="font-size: 12px;">{{ $metaTerm }}</td>
                </tr>
                <tr>
                    <td style="font-size: 12px; padding: 1px 0;">{{ __('pdf.meta.currency', [], $locale) }}</td>
                    <td style="font-size: 12px;">:</td>
                    <td style="font-size: 12px;">{{ $metaCurrency }}</td>
                </tr>
                <tr>
                    <td style="font-size: 12px; padding: 1px 0;">{{ __('pdf.meta.customer_code', [], $locale) }}</td>
                    <td style="font-size: 12px;">:</td>
                    <td style="font-size: 12px;">{{ $metaCustomerCode }}</td>
                </tr>
                @if (!empty($metaFulfillment))
                    <tr>
                        <td style="font-size: 12px; padding: 1px 0;">{{ __('pdf.meta.fulfillment', [], $locale) }}</td>
                        <td style="font-size: 12px;">:</td>
                        <td style="font-size: 12px;">{{ $metaFulfillment }}</td>
                    </tr>
                @endif
            </table>
        </td>
    </tr>
</table>
