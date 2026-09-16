@php
    $pdfCustomer = $order->pdfCustomer();
    $addressLines = static function ($value) {
        return array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $value)), static function ($line) {
            return $line !== '';
        });
    };

    $billingLines = $addressLines($order->billing_address ?? '');
    $shippingLines = $addressLines($order->shipping_address ?? '');
    if (empty($shippingLines)) {
        $shippingLines = $billingLines;
    }
@endphp
<table style="width: 100%; border-collapse: separate; border-spacing: 0; font-family: 'Noto Sans SC', 'Noto Sans TC', 'DejaVu Sans', sans-serif; margin: 16px 0 0 0;">
    <tr>
        <td style="width: 49%; vertical-align: top; border: 1px solid #9a9a9a;">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="background-color: #e6e6e6; padding: 5px 8px; font-size: 12px; font-weight: 700; border-bottom: 1px solid #9a9a9a;">{{ \App\PdfHelper::bilingual('pdf.addr.billing') }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px; font-size: 12px;">
                        <span style="font-weight: 700;">{{ $pdfCustomer->name }}</span><br>
                        @foreach ($billingLines as $line)
                            {{ $line }}<br>
                        @endforeach
                    </td>
                </tr>
            </table>
        </td>
        <td style="width: 2%;"></td>
        <td style="width: 49%; vertical-align: top; border: 1px solid #9a9a9a;">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="background-color: #e6e6e6; padding: 5px 8px; font-size: 12px; font-weight: 700; border-bottom: 1px solid #9a9a9a;">{{ \App\PdfHelper::bilingual('pdf.addr.shipping') }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px; font-size: 12px;">
                        <span style="font-weight: 700;">{{ $pdfCustomer->name }}</span><br>
                        @foreach ($shippingLines as $line)
                            {{ $line }}<br>
                        @endforeach
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
<table style="width: 100%; border-collapse: collapse; font-family: 'Noto Sans SC', 'Noto Sans TC', 'DejaVu Sans', sans-serif; margin: 8px 0 0 0;">
    <tr>
        <td style="font-size: 12px;">
            <span style="font-weight: 700;">{{ \App\PdfHelper::bilingual('pdf.addr.tel') }} :</span> {{ $customer_phone ?: '-' }}
        </td>
    </tr>
</table>
