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
    @include('pdf.partials.bank-details')
</body>
</html>
