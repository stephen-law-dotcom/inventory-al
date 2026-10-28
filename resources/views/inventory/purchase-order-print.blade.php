<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $purchaseOrder->number }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=2">
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #edf1f3; color: #111; font: 11px Arial, Helvetica, sans-serif; }
        .toolbar { width: min(820px, calc(100% - 24px)); margin: 16px auto 0; display: flex; justify-content: flex-end; gap: 8px; }
        button { min-height: 36px; border: 0; border-radius: 5px; padding: 8px 13px; background: #087f78; color: #fff; font-weight: 700; cursor: pointer; }
        .sheet { width: min(820px, calc(100% - 24px)); min-height: 1120px; margin: 10px auto 28px; padding: 32px 34px; background: #fff; box-shadow: 0 5px 24px rgba(28,46,61,.12); }
        .company-header { display: grid; grid-template-columns: 1fr auto; gap: 20px; align-items: start; }
        .company-header strong { display: block; font-size: 12px; }
        .company-header p, .document-code p { margin: 2px 0; }
        .document-code { text-align: right; font-size: 9px; }
        h1 { margin: 22px 0 18px; text-align: center; font-size: 19px; text-transform: uppercase; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 34px; margin-bottom: 10px; }
        .meta-line { display: grid; grid-template-columns: 78px 8px 1fr; min-height: 19px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #222; padding: 5px 6px; text-align: left; vertical-align: top; }
        th { text-align: center; font-weight: 700; }
        .col-no { width: 5%; }.col-part { width: 18%; }.col-description { width: 31%; }.col-qty { width: 10%; }.col-price { width: 17%; }.col-total { width: 19%; }
        .center { text-align: center; }.number { text-align: right; white-space: nowrap; }
        .summary-label { border-left: 0; text-align: right; font-weight: 700; }.summary-currency { width: 25px; border-right: 0; text-align: center; }.summary-value { border-left: 0; text-align: right; }
        .notes { margin-top: 14px; min-height: 36px; white-space: pre-line; }
        .signatures { display: grid; grid-template-columns: repeat(3, 1fr); gap: 48px; margin-top: 40px; text-align: center; break-inside: avoid; }
        .signature { min-height: 145px; display: flex; flex-direction: column; justify-content: space-between; }
        .signature-title { text-align: left; }
        .signature-image { display: block; width: 120px; height: 52px; object-fit: contain; margin: 0 auto 2px; mix-blend-mode: multiply; }
        .signature-line { margin-top: 60px; border-bottom: 1px solid #111; padding-bottom: 3px; font-weight: 700; }
        .signature-line.has-image { margin-top: 0; }
        .signature-role { margin-top: 4px; }
        @media print { body { background: #fff; }.toolbar { display: none; }.sheet { width: 100%; min-height: 0; margin: 0; padding: 0; box-shadow: none; } }
        @media (max-width: 640px) { .sheet { padding: 22px 16px; }.meta { grid-template-columns: 1fr; gap: 8px; }.signatures { grid-template-columns: 1fr; gap: 20px; }table { font-size: 9px; }th,td { padding: 4px; } }
    </style>
</head>
<body>
@php
    $proposedSignatureImage = $purchaseOrder->proposedBy?->signature_image;
    $checkedSignatureImage = $purchaseOrder->checkedBy?->signature_image;
    $approvedSignatureImage = $purchaseOrder->approvedBy?->signature_image;
@endphp
<div class="toolbar"><button onclick="window.print()">Cetak / Simpan PDF</button></div>
<main class="sheet">
    <header class="company-header">
        <div>
            <strong>{{ $purchaseOrder->purchaseRequest->camp->company?->name ?? $purchaseOrder->purchaseRequest->camp->company_name }}</strong>
            <p>{{ $purchaseOrder->purchaseRequest->camp->company?->address ?: '-' }}</p>
            <p>Telp. {{ $purchaseOrder->purchaseRequest->camp->company?->phone ?: '-' }}</p>
        </div>
        <div class="document-code"><strong>FM-LOG-03/02</strong><p>Page 1 of 1</p></div>
    </header>

    <h1>Purchase Order</h1>

    <div class="meta">
        <div>
            <div class="meta-line"><span>To</span><span>:</span><strong>{{ $purchaseOrder->vendor->name }}</strong></div>
            <div class="meta-line"><span>Attn.</span><span>:</span><span>{{ $purchaseOrder->vendor->contact_person ?: '-' }}</span></div>
            <div class="meta-line"><span>Address</span><span>:</span><span>{{ $purchaseOrder->vendor->address ?: '-' }}</span></div>
            <div class="meta-line"><span>Telp.</span><span>:</span><span>{{ $purchaseOrder->vendor->phone ?: '-' }}</span></div>
        </div>
        <div>
            <div class="meta-line"><span>PO Number</span><span>:</span><strong>{{ $purchaseOrder->number }}</strong></div>
            <div class="meta-line"><span>OR Number</span><span>:</span><span>{{ $purchaseOrder->purchaseRequest->number }}</span></div>
            <div class="meta-line"><span>Equipment</span><span>:</span><span>{{ $purchaseOrder->items->pluck('requestItem.unit.code')->filter()->unique()->implode(', ') ?: $purchaseOrder->purchaseRequest->camp->name }}</span></div>
            <div class="meta-line"><span>Delivery</span><span>:</span><span>{{ $purchaseOrder->delivery_destination === 'direct_site' ? 'Direct to '.$purchaseOrder->purchaseRequest->camp->name : 'Head Office Warehouse' }}</span></div>
            <div class="meta-line"><span>Date</span><span>:</span><span>{{ $purchaseOrder->ordered_at->format('d-m-Y') }}</span></div>
        </div>
    </div>

    <table>
        <thead>
            <tr><th class="col-no">No.</th><th class="col-part">Part Number</th><th class="col-description">Description</th><th class="col-qty">Qty</th>@if ($canSeePrices)<th class="col-price">Unit Price</th><th class="col-total">Total</th>@endif</tr>
        </thead>
        <tbody>
        @foreach ($purchaseOrder->items as $item)
            <tr>
                <td class="center">{{ $loop->iteration }}</td>
                <td>{{ $item->part_number ?: $item->inventoryItem?->sku ?: '-' }}</td>
                <td>{{ $item->description }}@if($item->brand_name)<br><small>Merek: {{ $item->brand_name }}</small>@endif</td>
                <td class="center">{{ $item->quantity_ordered }} {{ $item->uom }}</td>
                @if ($canSeePrices)
                    <td class="number">{{ \App\Support\Currency::format($item->unit_price, $purchaseOrder->currency_code) }}</td>
                    <td class="number">{{ \App\Support\Currency::format($item->total, $purchaseOrder->currency_code) }}</td>
                @endif
            </tr>
        @endforeach
        @if ($canSeePrices)
            <tr><td colspan="4" class="summary-label">Summary</td><td class="summary-currency">{{ $purchaseOrder->currency_code }}</td><td class="summary-value">{{ number_format($purchaseOrder->subtotal, 2, '.', ',') }}</td></tr>
            <tr><td colspan="4" class="summary-label">Discount {{ number_format($purchaseOrder->discount_percent, 2, '.', '') }}%</td><td class="summary-currency">{{ $purchaseOrder->currency_code }}</td><td class="summary-value">{{ number_format($purchaseOrder->discount_amount, 2, '.', ',') }}</td></tr>
            <tr><td colspan="4" class="summary-label">PPN {{ number_format($purchaseOrder->ppn_percent, 2, '.', '') }}%</td><td class="summary-currency">{{ $purchaseOrder->currency_code }}</td><td class="summary-value">{{ number_format($purchaseOrder->ppn_amount, 2, '.', ',') }}</td></tr>
            <tr><td colspan="4" class="summary-label">Grand Total</td><td class="summary-currency">{{ $purchaseOrder->currency_code }}</td><td class="summary-value"><strong>{{ number_format($purchaseOrder->grand_total, 2, '.', ',') }}</strong></td></tr>
            @if ($purchaseOrder->currency_code !== 'IDR')
                @php
                    $rateSourceLabel = match ($purchaseOrder->exchange_rate_source) {
                        'frankfurter' => 'Frankfurter mid-market',
                        'BI' => 'Bank Indonesia',
                        'manual' => 'Manual',
                        default => 'Sistem',
                    };
                @endphp
                <tr><td colspan="4" class="summary-label">Ekuivalen IDR (kurs {{ $rateSourceLabel }} {{ optional($purchaseOrder->exchange_rate_date)->format('d-m-Y') }}: {{ number_format($purchaseOrder->exchange_rate, 2, '.', ',') }})</td><td class="summary-currency">Rp.</td><td class="summary-value"><strong>{{ number_format($purchaseOrder->grandTotalIdr(), 2, '.', ',') }}</strong></td></tr>
            @endif
        @endif
        </tbody>
    </table>

    <div class="notes"><strong>Remark:</strong> {{ $purchaseOrder->notes ?: '-' }}<br>Estimasi tiba: {{ $purchaseOrder->expected_at?->format('d-m-Y') ?: '-' }}</div>

    <div class="signatures">
        <div class="signature">
            <div class="signature-title">Diusulkan oleh,</div><div>
            @if ($proposedSignatureImage)<img class="signature-image" src="{{ $proposedSignatureImage }}" alt="TTD {{ $purchaseOrder->proposedBy?->name }}">@endif
            <div class="signature-line {{ $proposedSignatureImage ? 'has-image' : '' }}">{{ $purchaseOrder->proposedBy?->name ?? '-' }}</div>
            <div class="signature-role">Admin Purchasing</div></div>
        </div>
        <div class="signature">
            <div class="signature-title">Diperiksa oleh,</div><div>
            @if ($checkedSignatureImage)<img class="signature-image" src="{{ $checkedSignatureImage }}" alt="TTD {{ $purchaseOrder->checkedBy?->name }}">@endif
            <div class="signature-line {{ $checkedSignatureImage ? 'has-image' : '' }}">{{ $purchaseOrder->checkedBy?->name ?? '-' }}</div>
            <div class="signature-role">Kepala Divisi Purchasing</div></div>
        </div>
        <div class="signature">
            <div class="signature-title">Disetujui oleh,</div><div>
            @if ($approvedSignatureImage)<img class="signature-image" src="{{ $approvedSignatureImage }}" alt="TTD {{ $purchaseOrder->approvedBy?->name }}">@endif
            <div class="signature-line {{ $approvedSignatureImage ? 'has-image' : '' }}">{{ $purchaseOrder->approvedBy?->name ?? '-' }}</div>
            <div class="signature-role">Direktur</div></div>
        </div>
    </div>
</main>
</body>
</html>
