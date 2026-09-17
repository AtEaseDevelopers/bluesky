<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ \App\PdfHelper::bilingual('pdf.doc.do_title') }}</title>
    @include('pdf.partials.font-styles')
</head>
<body>
    @php
        $doShowPrices = ($show_prices ?? false) && ($order->pdfCustomer()->invoice_price_permission ?? true);
    @endphp
    @include('pdf.partials.document-items', [
        'doc_title' => \App\PdfHelper::bilingual('pdf.doc.do_title'),
        'number_label' => \App\PdfHelper::bilingual('pdf.meta.do_no'),
        'number_value' => $do_no,
        'show_price_columns' => $doShowPrices,
        'has_price_permission' => true,
        'footer_mode' => $doShowPrices ? 'full' : 'weight',
    ])
    @include('pdf.partials.bank-details')
</body>
</html>
