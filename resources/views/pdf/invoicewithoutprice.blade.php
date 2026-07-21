<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice</title>
    @include('pdf.partials.font-styles')
</head>
<body>
    <!-- Header -->
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
                                {{ $order->billing_address }}<br />
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
                        <td colspan="3">
                            <span style="font-size: 18px; font-weight: 700;">Invoice</span><br>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 30%;">
                            <span style="font-size: 14px; font-weight: 700;">NO.</span><br>
                        </td>
                        <td style="width: 5%;">:</td>
                        <td>
                            <span style="font-size: 14px; font-weight: 700;">{{ $invoice_number }}</span><br>
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
    @include('pdf.partials.invoice-customer-contact')
    <!-- Items -->
    @php
        $total_weight = 0;
        $sub_total = 0;
        $total = 0;
    @endphp
    <table style="width: 100%; font-family: 'Noto Sans SC', 'Noto Sans TC', 'DejaVu Sans', sans-serif; border-collapse: collapse; margin: 10px 0 0 0;">
        <tr>
            <td style="font-size: 14px; border-top: solid 1px black; border-bottom: solid 1px black; font-weight: 700; padding: 5px 0 5px 0; width: 10%;">NO.</td>
            <td style="font-size: 14px; border-top: solid 1px black; border-bottom: solid 1px black; font-weight: 700; width: 30%; text-align: left;">DESCRIPTION</td>
            <td style="font-size: 14px; border-top: solid 1px black; border-bottom: solid 1px black; font-weight: 700; width: 10%; text-align: left;">QTY</td>
            <td style="font-size: 14px; border-top: solid 1px black; border-bottom: solid 1px black; font-weight: 700; width: 30%; text-align: left;">REMARK</td>
            <td style="font-size: 14px; border-top: solid 1px black; border-bottom: solid 1px black; font-weight: 700; width: 30%; text-align: center;">WEIGHT</td>
        </tr>
        @foreach ($order_items as $key => $prod)
            <tr>
                <td style="font-size: 14px; text-align: left; padding: 5px 0;">{{ $key + 1 }}</td>
                <td style="font-size: 14px; text-align: left;">{{ $prod->name }}</td>
                <td style="font-size: 14px; text-align: left;">{{ $prod->show_qty == true ? ($prod->quantity ?? 0) : '' }}</td>
                <td style="font-size: 14px; text-align: left;">{{ $prod->remark }}</td>
                <td style="font-size: 14px; text-align: center;">{{ $prod->show_weight == true ? (\App\OrderProduct::displayWeight($prod) ?? '') : '' }}</td>
            </tr>
            @php
                if ($prod->show_weight == true) {
                    $lineWeight = \App\OrderProduct::reportWeightValue($prod);
                    if ($lineWeight !== null) {
                        $total_weight = ($total_weight ?? 0) + $lineWeight;
                    }
                }
            @endphp
        @endforeach
    </table>
    <!-- Footer -->
    <table style="width: 100%; font-family: 'Noto Sans SC', 'Noto Sans TC', 'DejaVu Sans', sans-serif; border-collapse: collapse; margin: 50px 0 0 0;">
        <tr>
            <td style="border-top: solid 1px black; border-bottom: solid 1px black;" colspan="2"></td>
            <td style="font-size: 14px; border-top: solid 1px black; border-bottom: solid 1px black; font-weight: 700; text-align: right; padding: 5px 0 5px 0;">Total Quantity : {{ $total_weight }}</td>
        </tr>
    </table>
</body>
</html>