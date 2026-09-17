@php
    $showCols = $show_price_columns ?? false;
    $hasPerm = $has_price_permission ?? true;
    $footerMode = $footer_mode ?? ($showCols ? 'full' : 'weight');
    $remarkNote = $remark_note ?? \App\PdfHelper::bilingual('pdf.totals.remark_note');
    $footerNoteSize = '10px';

    $headerData = [
        'doc_title' => $doc_title ?? \App\PdfHelper::bilingual('pdf.doc.invoice_title'),
        'number_label' => $number_label ?? \App\PdfHelper::bilingual('pdf.meta.invoice_no'),
        'number_value' => $number_value ?? '',
    ];

    // Fixed line count per PDF page (not visual row height). With priced footer + header,
    // ~10 single-line descriptions usually fit A4; long names/remarks wrap inside the
    // same numbered line and use more vertical space (may spill to the next sheet).
    $perPage = $showCols ? 10 : 12;
    $chunks = collect($order_items)->chunk($perPage)->values();
    $lastChunkIndex = $chunks->count() - 1;

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
@foreach ($chunks as $chunkIndex => $chunk)
    @include('pdf.partials.document-header', $headerData)
    @include('pdf.partials.address-boxes')
    <table style="width: 100%; font-family: 'Noto Sans SC', 'Noto Sans TC', 'DejaVu Sans', sans-serif; border-collapse: collapse; margin: 16px 0 0 0;">
        <tr>
            <td style="font-size: 12px; background-color: #e6e6e6; font-weight: 700; padding: 6px 6px; width: {{ $showCols ? '6%' : '7%' }};">{{ \App\PdfHelper::bilingual('pdf.items.no') }}</td>
            <td style="font-size: 12px; background-color: #e6e6e6; font-weight: 700; padding: 6px 6px; width: {{ $showCols ? '13%' : '16%' }};">{{ \App\PdfHelper::bilingual('pdf.items.sku') }}</td>
            <td style="font-size: 12px; background-color: #e6e6e6; font-weight: 700; padding: 6px 6px; width: {{ $showCols ? '31%' : '45%' }};">{{ \App\PdfHelper::bilingual('pdf.items.description') }}</td>
            <td style="font-size: 12px; background-color: #e6e6e6; font-weight: 700; padding: 6px 6px; width: {{ $showCols ? '10%' : '14%' }}; text-align: center;">{{ \App\PdfHelper::bilingual('pdf.items.qty') }}</td>
            <td style="font-size: 12px; background-color: #e6e6e6; font-weight: 700; padding: 6px 6px; width: {{ $showCols ? '12%' : '18%' }}; text-align: center;">{{ \App\PdfHelper::bilingual('pdf.items.weight') }}</td>
            @if ($showCols)
                <td style="font-size: 12px; background-color: #e6e6e6; font-weight: 700; padding: 6px 6px; width: 14%; text-align: right;">{{ \App\PdfHelper::bilingual('pdf.items.unit_price') }}</td>
                <td style="font-size: 12px; background-color: #e6e6e6; font-weight: 700; padding: 6px 6px; width: 14%; text-align: right;">{{ \App\PdfHelper::bilingual('pdf.items.subtotal') }}</td>
            @endif
        </tr>
        @foreach ($chunk as $key => $prod)
            <tr>
                <td style="font-size: 12px; text-align: left; padding: 6px 6px; border-bottom: 1px solid #d5d5d5; vertical-align: top;">{{ $chunkIndex * $perPage + $loop->index + 1 }}</td>
                <td style="font-size: 12px; text-align: left; padding: 6px 6px; border-bottom: 1px solid #d5d5d5; vertical-align: top;">{{ $prod->sku ?? '' }}</td>
                <td style="font-size: 12px; text-align: left; padding: 6px 6px; border-bottom: 1px solid #d5d5d5; vertical-align: top; word-wrap: break-word; overflow-wrap: break-word;">
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

    @if ($chunkIndex === $lastChunkIndex)
        <!-- Remark, bank details, and totals — only on the final page -->
        <table style="width: 100%; font-family: 'Noto Sans SC', 'Noto Sans TC', 'DejaVu Sans', sans-serif; border-collapse: collapse; margin: 12px 0 0 0; page-break-inside: avoid;">
            <tr>
                <td style="width: 55%; vertical-align: top; padding-right: 8px;">
                    <span style="font-size: {{ $footerNoteSize }}; font-weight: 700;">{{ \App\PdfHelper::bilingual('pdf.totals.remark') }} :</span><br>
                    <span style="font-size: {{ $footerNoteSize }}; line-height: 1.35;">{{ $remarkNote }}</span>
                    @include('pdf.partials.bank-details', ['bank_font_size' => $footerNoteSize, 'bank_margin_top' => '8px'])
                </td>
                <td style="width: 45%; vertical-align: top;">
                    <table style="width: 100%; border-collapse: collapse;">
                        @if ($footerMode === 'full')
                            <tr>
                                <td style="font-size: 12px; text-align: left; padding: 3px 0;">{{ \App\PdfHelper::bilingual('pdf.totals.total_weight') }} :</td>
                                <td style="font-size: 12px; text-align: right; padding: 3px 0;">{{ $total_weight ?? 0 }} KG</td>
                            </tr>
                            <tr>
                                <td style="font-size: 12px; text-align: left; padding: 3px 0;">{{ \App\PdfHelper::bilingual('pdf.items.subtotal') }} :</td>
                                <td style="font-size: 12px; text-align: right; padding: 3px 0;">{{ $money($lineSubtotal) }}</td>
                            </tr>
                            @if ($deliveryFee != 0)
                                <tr>
                                    <td style="font-size: 12px; text-align: left; padding: 3px 0;">{{ \App\PdfHelper::bilingual('pdf.totals.delivery_fee') }} :</td>
                                    <td style="font-size: 12px; text-align: right; padding: 3px 0;">{{ $money($deliveryFee) }}</td>
                                </tr>
                            @endif
                            @if ($adjustment != 0)
                                <tr>
                                    <td style="font-size: 12px; text-align: left; padding: 3px 0;">{{ \App\PdfHelper::bilingual('pdf.totals.adjustment') }} :</td>
                                    <td style="font-size: 12px; text-align: right; padding: 3px 0;">{{ $money($adjustment) }}</td>
                                </tr>
                            @endif
                            @if ($discount != 0)
                                <tr>
                                    <td style="font-size: 12px; text-align: left; padding: 3px 0;">{{ \App\PdfHelper::bilingual('pdf.totals.discount') }} :</td>
                                    <td style="font-size: 12px; text-align: right; padding: 3px 0;">- {{ $money($discount) }}</td>
                                </tr>
                            @endif
                            <tr>
                                <td style="font-size: 14px; font-weight: 700; text-align: left; padding: 8px 0 4px 0; border-top: 1px solid #000;">{{ \App\PdfHelper::bilingual('pdf.totals.total_amount') }} :</td>
                                <td style="font-size: 14px; font-weight: 700; text-align: right; padding: 8px 0 4px 0; border-top: 1px solid #000;">{{ $money($grandTotal) }}</td>
                            </tr>
                            @if (isset($payments) && $payments->count())
                                @php
                                    $paidTotal = (float) $payments->sum('amount');
                                @endphp
                                <tr>
                                    <td colspan="2" style="font-size: 12px; font-weight: 700; padding: 10px 0 2px 0;">{{ \App\PdfHelper::bilingual('pdf.totals.payments_received') }}</td>
                                </tr>
                                @foreach ($payments as $payment)
                                    <tr>
                                        <td style="font-size: 12px; text-align: left; padding: 2px 0;">{{ $payment_method_labels[$payment->payment_method] ?? $payment->payment_method }} :</td>
                                        <td style="font-size: 12px; text-align: right; padding: 2px 0;">{{ $money($payment->amount) }}</td>
                                    </tr>
                                @endforeach
                                <tr>
                                    <td style="font-size: 12px; font-weight: 700; text-align: left; padding: 4px 0;">{{ \App\PdfHelper::bilingual('pdf.totals.total_paid') }} :</td>
                                    <td style="font-size: 12px; font-weight: 700; text-align: right; padding: 4px 0;">{{ $money($paidTotal) }}</td>
                                </tr>
                                @if ($paidTotal < $grandTotal)
                                    <tr>
                                        <td style="font-size: 12px; font-weight: 700; text-align: left; padding: 4px 0;">{{ \App\PdfHelper::bilingual('pdf.totals.balance_due') }} :</td>
                                        <td style="font-size: 12px; font-weight: 700; text-align: right; padding: 4px 0;">{{ $money($grandTotal - $paidTotal) }}</td>
                                    </tr>
                                @endif
                            @endif
                        @else
                            <tr>
                                <td style="font-size: 14px; font-weight: 700; text-align: left; padding: 8px 0 4px 0; border-top: 1px solid #000;">{{ \App\PdfHelper::bilingual('pdf.totals.total_weight') }} :</td>
                                <td style="font-size: 14px; font-weight: 700; text-align: right; padding: 8px 0 4px 0; border-top: 1px solid #000;">{{ $total_weight ?? 0 }} KG</td>
                            </tr>
                        @endif
                    </table>
                </td>
            </tr>
        </table>
    @endif

    @if ($chunkIndex !== $lastChunkIndex)
        <div style="page-break-after: always;"></div>
    @endif
@endforeach
