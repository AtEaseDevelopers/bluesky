<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>送货单 Delivery Order</title>
    @include('pdf.partials.font-styles')
</head>
<body>
    @php
        $doShowPrices = ($show_prices ?? false) && ($order->pdfCustomer()->invoice_price_permission ?? true);
    @endphp
    @include('pdf.partials.document-header', [
        'doc_title' => '送货单',
        'number_label' => '送货单号',
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
                <span style="font-size: 12px;">本人／本公司确认已收到上述货物，货品状况良好。</span><br>
                <span style="font-size: 11px; color: #666666;">I/We hereby confirm the above goods have been received in good order &amp; condition.</span>
            </td>
        </tr>
        <tr>
            <td style="font-size: 12px; text-align: center; border-top: solid 1px black; padding: 5px 0 0 0;">授权签名<br><span style="font-size: 11px; color: #666666;">Authorised Signature</span></td>
            <td style="width: 10%;"></td>
            <td style="font-size: 12px; text-align: center; border-top: solid 1px black; padding: 5px 0 0 0;">客户公司盖章及签名<br><span style="font-size: 11px; color: #666666;">Customer Company Stamp &amp; Signature</span></td>
        </tr>
    </table>
    @include('pdf.partials.bank-details')
</body>
</html>
