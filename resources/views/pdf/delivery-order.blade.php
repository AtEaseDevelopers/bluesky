<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Order</title>
    @include('pdf.partials.font-styles')
</head>
<body>
    @include('pdf.partials.invoice-letterhead')
    <!-- Sub Header -->
    <table style="width: 100%; border-collapse: collapse; font-family: 'Noto Sans SC', 'Noto Sans TC', 'DejaVu Sans', sans-serif; margin: 20px 0 0 0;">
        <tr>
            <td style="width: 70%; vertical-align: text-top;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 50%; vertical-align: text-top;">
                            <span style="font-size: 14px; font-weight: 700;">BILLING ADDRESS :</span><br><br>
                            <span style="font-size: 14px; font-weight: 700;">{{ $order->pdfCustomer()->name }}</span><br>
                            <span style="font-size: 14px;">
                                {{ $order->billing_address }}
                            </span>
                        </td>
                        <td style="width: 50%; vertical-align: text-top;">
                            <span style="font-size: 14px; font-weight: 700;">DELIVERY ADDRESS :</span><br><br>
                            <span style="font-size: 14px; vertical-align: text-bottom;">
                                {{ $order->shipping_address }}<br />
                            </span>
                        </td>
                    </tr>
                </table>
            </td>
            <td style="width: 30%; vertical-align: text-top;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 30%;">
                            <span style="font-size: 14px; font-weight: 700;">DO NO.</span><br>
                        </td>
                        <td style="width: 5%;">:</td>
                        <td>
                            <span style="font-size: 14px; font-weight: 700;">{{ $do_no }}</span><br>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span style="font-size: 14px;">DATE</span><br>
                        </td>
                        <td>:</td>
                        <td>
                            <span style="font-size: 14px;">{{ $date }}</span><br>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    <!-- Sub Header 2 -->
    <table style="width: 100%; border-collapse: collapse; font-family: 'Noto Sans SC', 'Noto Sans TC', 'DejaVu Sans', sans-serif; margin: 10px 0 0 0;">
        <tr>
            <td style="width: 30%;">
                <span style="font-size: 14px; font-weight: 700;">A/C NO : <span style="font-weight: 100;">{{ $order->pdfCustomer()->sql_customer_code ?? '-' }}</span></span><br>
            </td>
            <td style="width: 70%;">
                <span style="font-size: 14px; font-weight: 700;">TEL : <span style="font-weight: 100;">{{ $customer_phone ?: '-' }}</span></span><br>
            </td>
        </tr>
        <tr>
            <td style="width: 30%;">
            </td>
            <td style="width: 70%;">
                <span style="font-size: 14px; font-weight: 700;">FAX : <span style="font-weight: 100;">{{ $order->pdfCustomer()->fax_no ?? '-' }}</span></span><br>
            </td>
        </tr>
    </table>
    <!-- Items -->
    @include('pdf.partials.delivery-order-lines')
    <!-- Footer -->
    <table style="width: 100%; font-family: 'Noto Sans SC', 'Noto Sans TC', 'DejaVu Sans', sans-serif; border-collapse: collapse; margin: 30px 0 0 0;">
        <tr style="margin: 0 0 50px 0;">
            <td></td>
            <td style="width: 10%;"></td>
            <td style="padding: 0 0 100px 0;">
                <span style="font-size: 14px;">I/We hereby confirmed and received to the above mentioned goods in a good order & condition.</span>
            </td>
        </tr>
        <tr>
            <td style="font-size: 14px; text-align: center; border-top: solid 1px black; padding: 5px 0 0 0;">Authorised Signature</td>
            <td style="width: 10%;"></td>
            <td style="font-size: 14px; text-align: center; border-top: solid 1px black; padding: 5px 0 0 0;">Customer Company Stamp & Signature</td>
        </tr>
    </table>
</body>
</html>