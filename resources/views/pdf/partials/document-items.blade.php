@php
    $showCols = $show_price_columns ?? false;
    $hasPerm = $has_price_permission ?? true;
    $footerMode = $footer_mode ?? ($showCols ? 'full' : 'weight');
    $remarkNote = $remark_note ?? '1. 货物售出，概不退换。';

    $total_weight = 0;
    $lineSubtotal = 0;
    if ($showCols && $hasPerm) {
        foreach ($order_items as $prod) {
            $lineSubtotal += (float) $prod->price;
        }
    }
    $deliveryFee = (float) ($order->delivery_fee ?? 0);
    $adjustment = (float) ($order->amount_adjustment ?? 0);
    $discount = (float) ($order->discount ?? 0);
    $grandTotal = $lineSubtotal + $deliveryFee + $adjustment - $discount;

    $currency = $currency ?? 'MYR';
    $money = fn ($v) => $currency . ' ' . number_format((float) $v, 2);
@endphp
<table style="width: 100%; font-family: 'Noto Sans SC', 'Noto Sans TC', 'DejaVu Sans', sans-serif; border-collapse: collapse; margin: 16px 0 0 0;">
    <tr>
        <td style="font-size: 12px; background-color: #e6e6e6; font-weight: 700; padding: 6px 6px; width: {{ $showCols ? '6%' : '7%' }};">序号</td>
        <td style="font-size: 12px; background-color: #e6e6e6; font-weight: 700; padding: 6px 6px; width: {{ $showCols ? '13%' : '16%' }};">产品编号</td>
        <td style="font-size: 12px; background-color: #e6e6e6; font-weight: 700; padding: 6px 6px; width: {{ $showCols ? '31%' : '45%' }};">产品描述</td>
        <td style="font-size: 12px; background-color: #e6e6e6; font-weight: 700; padding: 6px 6px; width: {{ $showCols ? '10%' : '14%' }}; text-align: center;">数量</td>
        <td style="font-size: 12px; background-color: #e6e6e6; font-weight: 700; padding: 6px 6px; width: {{ $showCols ? '12%' : '18%' }}; text-align: center;">重量</td>
        @if ($showCols)
            <td style="font-size: 12px; background-color: #e6e6e6; font-weight: 700; padding: 6px 6px; width: 14%; text-align: right;">单价</td>
            <td style="font-size: 12px; background-color: #e6e6e6; font-weight: 700; padding: 6px 6px; width: 14%; text-align: right;">小计</td>
        @endif
    </tr>
    @foreach ($order_items as $key => $prod)
        <tr>
            <td style="font-size: 12px; text-align: left; padding: 6px 6px; border-bottom: 1px solid #d5d5d5; vertical-align: top;">{{ $key + 1 }}</td>
            <td style="font-size: 12px; text-align: left; padding: 6px 6px; border-bottom: 1px solid #d5d5d5; vertical-align: top;">{{ $prod->sku ?? '' }}</td>
            <td style="font-size: 12px; text-align: left; padding: 6px 6px; border-bottom: 1px solid #d5d5d5; vertical-align: top;">
                {{ $prod->name }}
                @if (!empty($prod->remark))
                    <br><span style="font-size: 11px; color: #666666;">{{ $prod->remark }}</span>
                @endif
            </td>
            <td style="font-size: 12px; text-align: center; padding: 6px 6px; border-bottom: 1px solid #d5d5d5; vertical-align: top;">{{ $prod->show_qty == true ? ($prod->quantity ?? 0) : '' }}</td>
            <td style="font-size: 12px; text-align: center; padding: 6px 6px; border-bottom: 1px solid #d5d5d5; vertical-align: top;">{{ $prod->show_weight == true ? (\App\OrderProduct::displayWeight($prod) ?? '') : '' }}</td>
            @if ($showCols)
                <td style="font-size: 12px; text-align: right; padding: 6px 6px; border-bottom: 1px solid #d5d5d5; vertical-align: top;">{{ $hasPerm ? number_format((float) $prod->unit_price, 2) : '-' }}</td>
                <td style="font-size: 12px; text-align: right; padding: 6px 6px; border-bottom: 1px solid #d5d5d5; vertical-align: top;">{{ $hasPerm ? number_format((float) $prod->price, 2) : '-' }}</td>
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
<!-- Totals -->
<table style="width: 100%; font-family: 'Noto Sans SC', 'Noto Sans TC', 'DejaVu Sans', sans-serif; border-collapse: collapse; margin: 20px 0 0 0;">
    <tr>
        <td style="width: 55%; vertical-align: top;">
            <span style="font-size: 12px; font-weight: 700;">备注 :</span><br>
            <span style="font-size: 12px;">{{ $remarkNote }}</span>
        </td>
        <td style="width: 45%; vertical-align: top;">
            <table style="width: 100%; border-collapse: collapse;">
                @if ($footerMode === 'full')
                    <tr>
                        <td style="font-size: 12px; text-align: left; padding: 3px 0;">总重量 :</td>
                        <td style="font-size: 12px; text-align: right; padding: 3px 0;">{{ $total_weight ?? 0 }} KG</td>
                    </tr>
                    <tr>
                        <td style="font-size: 12px; text-align: left; padding: 3px 0;">小计 :</td>
                        <td style="font-size: 12px; text-align: right; padding: 3px 0;">{{ $money($lineSubtotal) }}</td>
                    </tr>
                    @if ($deliveryFee != 0)
                        <tr>
                            <td style="font-size: 12px; text-align: left; padding: 3px 0;">运费 :</td>
                            <td style="font-size: 12px; text-align: right; padding: 3px 0;">{{ $money($deliveryFee) }}</td>
                        </tr>
                    @endif
                    @if ($adjustment != 0)
                        <tr>
                            <td style="font-size: 12px; text-align: left; padding: 3px 0;">调整 :</td>
                            <td style="font-size: 12px; text-align: right; padding: 3px 0;">{{ $money($adjustment) }}</td>
                        </tr>
                    @endif
                    @if ($discount != 0)
                        <tr>
                            <td style="font-size: 12px; text-align: left; padding: 3px 0;">折扣 :</td>
                            <td style="font-size: 12px; text-align: right; padding: 3px 0;">- {{ $money($discount) }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td style="font-size: 14px; font-weight: 700; text-align: left; padding: 8px 0 4px 0; border-top: 1px solid #000;">总金额 :</td>
                        <td style="font-size: 14px; font-weight: 700; text-align: right; padding: 8px 0 4px 0; border-top: 1px solid #000;">{{ $money($grandTotal) }}</td>
                    </tr>
                @else
                    <tr>
                        <td style="font-size: 14px; font-weight: 700; text-align: left; padding: 8px 0 4px 0; border-top: 1px solid #000;">总重量 :</td>
                        <td style="font-size: 14px; font-weight: 700; text-align: right; padding: 8px 0 4px 0; border-top: 1px solid #000;">{{ $total_weight ?? 0 }} KG</td>
                    </tr>
                @endif
            </table>
        </td>
    </tr>
</table>
@if ($footerMode === 'full' && isset($payments) && $payments->count())
    @php
        $paidTotal = (float) $payments->sum('amount');
    @endphp
    <table style="width: 100%; font-family: 'Noto Sans SC', 'Noto Sans TC', 'DejaVu Sans', sans-serif; border-collapse: collapse; margin: 12px 0 0 0;">
        <tr>
            <td style="width: 55%;"></td>
            <td style="width: 45%;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td colspan="2" style="font-size: 12px; font-weight: 700; padding: 6px 0 2px 0;">已收款项</td>
                    </tr>
                    @foreach ($payments as $payment)
                        <tr>
                            <td style="font-size: 12px; text-align: left; padding: 2px 0;">{{ $payment_method_labels[$payment->payment_method] ?? $payment->payment_method }} :</td>
                            <td style="font-size: 12px; text-align: right; padding: 2px 0;">{{ $money($payment->amount) }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td style="font-size: 12px; font-weight: 700; text-align: left; padding: 4px 0;">已付总额 :</td>
                        <td style="font-size: 12px; font-weight: 700; text-align: right; padding: 4px 0;">{{ $money($paidTotal) }}</td>
                    </tr>
                    @if ($paidTotal < $grandTotal)
                        <tr>
                            <td style="font-size: 12px; font-weight: 700; text-align: left; padding: 4px 0;">未付余额 :</td>
                            <td style="font-size: 12px; font-weight: 700; text-align: right; padding: 4px 0;">{{ $money($grandTotal - $paidTotal) }}</td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>
@endif
