<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>发票 Invoice</title>
    @include('pdf.partials.font-styles')
</head>
<body>
    @include('pdf.partials.document-header', [
        'doc_title' => '发票',
        'number_label' => '发票编号',
        'number_value' => $invoice_number,
    ])
    @include('pdf.partials.address-boxes')
    @include('pdf.partials.document-items', [
        'show_price_columns' => true,
        'has_price_permission' => $user->invoice_price_permission,
        'footer_mode' => 'full',
    ])
    @include('pdf.partials.bank-details')
</body>
</html>
