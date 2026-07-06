    <!-- Items -->
    @php
        $total_weight = 0;
        $lineSubtotal = 0;
        $showPrices = $show_prices ?? false;
        $customer = $order->pdfCustomer();
        $canShowLinePrices = $showPrices && ($customer->invoice_price_permission ?? true);

        if ($canShowLinePrices) {
            foreach ($order_items as $prod) {
                $lineSubtotal += (float) $prod->price;
            }
        }

        $deliveryFee = (float) ($order->delivery_fee ?? 0);
        $adjustment = (float) ($order->amount_adjustment ?? 0);
        $grandTotal = $lineSubtotal + $deliveryFee + $adjustment;
    @endphp
    <table style="width: 100%; font-family: sans-serif; border-collapse: collapse; margin: 10px 0 0 0;">
        <tr>
            <td style="font-size: 14px; border-top: solid 1px black; border-bottom: solid 1px black; font-weight: 700; padding: 5px 0 5px 0; width: 8%;">NO.</td>
            <td style="font-size: 14px; border-top: solid 1px black; border-bottom: solid 1px black; font-weight: 700; width: {{ $canShowLinePrices ? '24%' : '30%' }}; text-align: left;">DESCRIPTION</td>
            <td style="font-size: 14px; border-top: solid 1px black; border-bottom: solid 1px black; font-weight: 700; width: 8%; text-align: left;">QTY</td>
            <td style="font-size: 14px; border-top: solid 1px black; border-bottom: solid 1px black; font-weight: 700; width: {{ $canShowLinePrices ? '18%' : '24%' }}; text-align: left;">REMARK</td>
            <td style="font-size: 14px; border-top: solid 1px black; border-bottom: solid 1px black; font-weight: 700; width: 12%; text-align: center;">WEIGHT</td>
            @if ($canShowLinePrices)
                <td style="font-size: 14px; border-top: solid 1px black; border-bottom: solid 1px black; font-weight: 700; width: 12%; text-align: center;">U. PRICE</td>
                <td style="font-size: 14px; border-top: solid 1px black; border-bottom: solid 1px black; font-weight: 700; width: 12%; text-align: center;">SUB TOTAL</td>
            @endif
        </tr>
        @foreach ($order_items as $key => $prod)
            <tr>
                <td style="font-size: 14px; text-align: left; padding: 5px 0;">{{ $key + 1 }}</td>
                <td style="font-size: 14px; text-align: left;">{{ $prod->name }}</td>
                <td style="font-size: 14px; text-align: left;">{{ $prod->show_qty == true ? ($prod->quantity ?? '') : '' }}</td>
                <td style="font-size: 14px; text-align: left;">{{ $prod->remark }}</td>
                <td style="font-size: 14px; text-align: center;">{{ $prod->show_weight == true ? (\App\OrderProduct::displayWeight($prod) ?? '') : '' }}</td>
                @if ($canShowLinePrices)
                    <td style="font-size: 14px; text-align: center;">{{ number_format((float) $prod->unit_price, 2) }}</td>
                    <td style="font-size: 14px; text-align: center;">{{ number_format((float) $prod->price, 2) }}</td>
                @endif
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
        @if ($canShowLinePrices)
            <tr>
                <td style="border-top: solid 1px black;" colspan="2"></td>
                <td style="font-size: 14px; border-top: solid 1px black; font-weight: 700; text-align: right; padding: 5px 0 5px 0;">SUB TOTAL : {{ number_format($lineSubtotal, 2) }}</td>
            </tr>
            @if ($deliveryFee != 0)
                <tr>
                    <td colspan="2"></td>
                    <td style="font-size: 14px; font-weight: 700; text-align: right; padding: 5px 0;">DELIVERY FEE : {{ number_format($deliveryFee, 2) }}</td>
                </tr>
            @endif
            @if ($adjustment != 0)
                <tr>
                    <td colspan="2"></td>
                    <td style="font-size: 14px; font-weight: 700; text-align: right; padding: 5px 0;">ADJUSTMENT : {{ number_format($adjustment, 2) }}</td>
                </tr>
            @endif
            <tr>
                <td style="border-top: solid 1px black; border-bottom: solid 1px black;"></td>
                <td style="font-size: 14px; border-top: solid 1px black; border-bottom: solid 1px black; font-weight: 700; text-align: right; padding: 5px 0 5px 0;">TOTAL WEIGHT : {{ $total_weight }} KG</td>
                <td style="font-size: 14px; border-top: solid 1px black; border-bottom: solid 1px black; font-weight: 700; text-align: right; padding: 5px 0 5px 0;">TOTAL AMOUNT : {{ number_format($grandTotal, 2) }}</td>
            </tr>
        @else
            <tr>
                <td style="border-top: solid 1px black; border-bottom: solid 1px black;" colspan="2"></td>
                <td style="font-size: 14px; border-top: solid 1px black; border-bottom: solid 1px black; font-weight: 700; text-align: right; padding: 5px 0 5px 0;">Total Weight : {{ $total_weight }} KG</td>
            </tr>
        @endif
    </table>
