@php
    $bankFontSize = $bank_font_size ?? '10px';
    $bankMarginTop = $bank_margin_top ?? '10px';
@endphp
<table style="width: 100%; font-family: 'Noto Sans SC', 'Noto Sans TC', 'DejaVu Sans', sans-serif; border-collapse: collapse; margin: {{ $bankMarginTop }} 0 0 0;">
    <tr>
        <td>
            <span style="font-size: {{ $bankFontSize }}; font-weight: 700;">{{ \App\PdfHelper::bilingual('pdf.bank.title') }}</span><br>
            <span style="font-size: {{ $bankFontSize }}; font-weight: 700;">BLUESKY LIVE SEAFOOD SUPPLY SDN BHD</span><br>
            <span style="font-size: {{ $bankFontSize }}; font-weight: 700;">CIMB BANK - 8011442312</span>
        </td>
    </tr>
</table>
