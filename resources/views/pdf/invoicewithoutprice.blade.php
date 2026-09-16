<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('pdf.doc.invoice_title', [], $locale ?? 'zh_CN') }}</title>
    @include('pdf.partials.font-styles')
</head>
<body>
    @foreach (($locales ?? ['zh_CN', 'en']) as $loopIndex => $locale)
        @if ($loopIndex > 0)
            <div style="page-break-before: always;"></div>
        @endif
        @include('pdf.partials.document-header', [
            'doc_title' => __('pdf.doc.invoice_title', [], $locale),
            'number_label' => __('pdf.meta.invoice_no', [], $locale),
            'number_value' => $invoice_number,
        ])
        @include('pdf.partials.address-boxes')
        @include('pdf.partials.document-items', [
            'show_price_columns' => false,
            'footer_mode' => 'weight',
        ])
        @include('pdf.partials.bank-details')
    @endforeach
</body>
</html>
