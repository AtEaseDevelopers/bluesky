<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('pdf.doc.do_title', [], $locale ?? 'zh_CN') }}</title>
    @include('pdf.partials.font-styles')
</head>
<body>
    @php
        $locale = $locale ?? 'zh_CN';
        $doShowPrices = ($show_prices ?? false) && ($order->pdfCustomer()->invoice_price_permission ?? true);
    @endphp
    @include('pdf.partials.document-header', [
        'doc_title' => __('pdf.doc.do_title', [], $locale),
        'number_label' => __('pdf.meta.do_no', [], $locale),
        'number_value' => $do_no,
    ])
    @include('pdf.partials.address-boxes')
    @include('pdf.partials.document-items', [
        'show_price_columns' => $doShowPrices,
        'has_price_permission' => true,
        'footer_mode' => $doShowPrices ? 'full' : 'weight',
    ])
    <!-- Acknowledgement & signatures -->
    <table style="width: 100%; font-family: 'Noto Sans SC', 'Noto Sans TC', 'DejaVu Sans', sans-serif; border-collapse: collapse; margin: 40px 0 0 0;">
        <tr>
            <td colspan="3" style="padding: 0 0 80px 0;">
                <span style="font-size: 12px;">{{ __('pdf.do2.ack', [], $locale) }}</span>
            </td>
        </tr>
        <tr>
            <td style="font-size: 12px; text-align: center; border-top: solid 1px black; padding: 5px 0 0 0;">{{ __('pdf.do2.sign_authorised', [], $locale) }}</td>
            <td style="width: 10%;"></td>
            <td style="font-size: 12px; text-align: center; border-top: solid 1px black; padding: 5px 0 0 0;">{{ __('pdf.do2.sign_customer', [], $locale) }}</td>
        </tr>
    </table>
    @include('pdf.partials.bank-details')
</body>
</html>
