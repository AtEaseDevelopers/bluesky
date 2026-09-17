<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ \App\PdfHelper::bilingual('pdf.doc.invoice_title') }}</title>
    @include('pdf.partials.font-styles')
</head>
<body>
    @include('pdf.partials.document-items', [
        'doc_title' => \App\PdfHelper::bilingual('pdf.doc.invoice_title'),
        'number_label' => \App\PdfHelper::bilingual('pdf.meta.invoice_no'),
        'number_value' => $invoice_number,
        'show_price_columns' => true,
        'has_price_permission' => $user->invoice_price_permission,
        'footer_mode' => 'full',
    ])
    @include('pdf.partials.bank-details')
</body>
</html>
