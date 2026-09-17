@php
    $company = $company ?? config('portal.company');
    $addressLines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $company['address'] ?? '')));

    $metaNumberLabel = $number_label ?? \App\PdfHelper::bilingual('pdf.meta.invoice_no');
    $metaNumber = $number_value ?? '';
    $metaDate = $date ?? '';
    $metaTime = $time ?? '';
    $metaDateTime = trim($metaDate . ($metaTime !== '' && $metaTime !== null ? ' ' . $metaTime : ''));
    $metaTerm = $payment_term ?? '-';
    $metaFulfillment = $fulfillment ?? (isset($order) ? $order->pdfFulfillmentDisplayLabel() : null);
@endphp
<!-- Document title -->
<table style="width: 100%; border-collapse: collapse; font-family: 'Noto Sans SC', 'Noto Sans TC', 'DejaVu Sans', sans-serif;">
    <tr>
        <td style="text-align: center; padding-bottom: 14px;">
            <span style="font-size: 24px; font-weight: 700; letter-spacing: 6px;">{{ $doc_title ?? \App\PdfHelper::bilingual('pdf.doc.invoice_title') }}</span>
        </td>
    </tr>
</table>
<!-- Company (left) + document meta (right) — widths match billing/shipping address boxes below -->
<table style="width: 100%; border-collapse: collapse; font-family: 'Noto Sans SC', 'Noto Sans TC', 'DejaVu Sans', sans-serif;">
    <tr>
        <td style="width: 49%; vertical-align: top; word-wrap: break-word; overflow-wrap: break-word;">
            <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
                <tr><td style="font-size: 15px; font-weight: 700; padding: 1px 0; word-wrap: break-word;">{{ $company['name'] ?? env('APP_NAME') }}</td></tr>
                @if (!empty($company['registration_no']))
                    <tr><td style="font-size: 12px; font-weight: 700; padding: 1px 0;">REG No: {{ $company['registration_no'] }}</td></tr>
                @endif
                @if (!empty($company['tin_no']))
                    <tr><td style="font-size: 12px; font-weight: 700; padding: 1px 0;">TIN No: {{ $company['tin_no'] }}</td></tr>
                @endif
                @foreach ($addressLines as $line)
                    <tr><td style="font-size: 12px; padding: 1px 0; word-wrap: break-word;">{{ $line }}</td></tr>
                @endforeach
                @if (!empty($company['phone']))
                    <tr><td style="font-size: 12px; padding: 1px 0;">Phone : {{ $company['phone'] }}</td></tr>
                @endif
            </table>
        </td>
        <td style="width: 2%;"></td>
        <td style="width: 49%; vertical-align: top;">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="width: 40%; font-size: 12px; padding: 1px 0;">{{ $metaNumberLabel }}</td>
                    <td style="width: 5%; font-size: 12px;">:</td>
                    <td style="font-size: 12px; font-weight: 700;">{{ $metaNumber }}</td>
                </tr>
                @if ($metaDateTime !== '')
                    <tr>
                        <td style="font-size: 12px; padding: 1px 0;">{{ \App\PdfHelper::bilingual('pdf.meta.date') }}</td>
                        <td style="font-size: 12px;">:</td>
                        <td style="font-size: 12px;">{{ $metaDateTime }}</td>
                    </tr>
                @endif
                <tr>
                    <td style="font-size: 12px; padding: 1px 0;">{{ \App\PdfHelper::bilingual('pdf.meta.payment_term') }}</td>
                    <td style="font-size: 12px;">:</td>
                    <td style="font-size: 12px;">{{ $metaTerm }}</td>
                </tr>
                @if (!empty($metaFulfillment))
                    <tr>
                        <td style="font-size: 12px; padding: 1px 0;">{{ \App\PdfHelper::bilingual('pdf.meta.fulfillment') }}</td>
                        <td style="font-size: 12px;">:</td>
                        <td style="font-size: 12px;">{{ $metaFulfillment }}</td>
                    </tr>
                @endif
            </table>
        </td>
    </tr>
</table>
