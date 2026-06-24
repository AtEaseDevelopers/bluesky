<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Order</title>
</head>
<body>
    <!-- Header -->
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="width: 20%; text-align: center; vertical-align: middle;">
            </td>
            <td style="text-align: center; width: 60%;">
                <span style="font-size: 22px; font-weight: 700;">{{ env('APP_NAME') }}</span><br>
                <span style="font-size: 12px;">(1130071.K)</span><br>
                <span style="font-size: 16px;">Level 11, Menara KEN TTDI, 37,</span><br>
                <span style="font-size: 16px;">Jalan Burhanuddin Helmi, Taman Tun Dr Ismail,</span><br>
                <span style="font-size: 16px;">60000 Kuala Lumpur, Wilayah Persekutuan Kuala Lumpur</span><br>
                {{-- <span style="font-size: 16px;">Phone : </span><br> --}}
                {{-- <span style="font-size: 16px;">A/C Dept : </span> --}}
            </td>
            <td style="width: 10%; text-align: center; vertical-align: middle;">
                <img src="{{ public_path('assets/images/mesti-logo2.jpg') }}" alt="" style="height: 62.5px; width: 62.5px;">
            </td>
            <td style="width: 10%; text-align: center; vertical-align: middle;">
                <img src="{{ public_path('assets/images/mesti-logo.jpg') }}" alt="" style="height: 62.5px; width: 62.5px;">
            </td>
        </tr>
    </table>
    <!-- Sub Header -->
    <table style="width: 100%; border-collapse: collapse; font-family: sans-serif; margin: 20px 0 0 0;">
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
    <table style="width: 100%; border-collapse: collapse; font-family: sans-serif; margin: 10px 0 0 0;">
        <tr>
            <td style="width: 30%;">
                <span style="font-size: 14px; font-weight: 700;">A/C NO : <span style="font-weight: 100;">{{ $order->pdfCustomer()->sql_customer_code ?? '-' }}</span></span><br>
            </td>
            <td style="width: 70%;">
                <span style="font-size: 14px; font-weight: 700;">TEL : <span style="font-weight: 100;">{{ $order->pdfCustomer()->attn_contact ?? '-' }}</span></span><br>
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
    @php
        $total_weight = 0;
    @endphp
    <table style="width: 100%; font-family: sans-serif; border-collapse: collapse; margin: 10px 0 0 0;">
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
                <td style="font-size: 14px; text-align: left;">{{ $prod->quantity ?? '' }}</td>
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
    <table style="width: 100%; font-family: sans-serif; border-collapse: collapse; margin: 50px 0 0 0;">
        <tr>
            <td style="border-top: solid 1px black; border-bottom: solid 1px black;" colspan="2"></td>
            <td style="font-size: 14px; border-top: solid 1px black; border-bottom: solid 1px black; font-weight: 700; text-align: right; padding: 5px 0 5px 0;">Total Weight : {{ $total_weight }} KG</td>
        </tr>
    </table>
    <!-- Footer -->
    <table style="width: 100%; font-family: sans-serif; border-collapse: collapse; margin: 30px 0 0 0;">
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