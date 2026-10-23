<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Permintaan Pengadaan Barang {{ $requestItem->purchaseRequest->number }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=2">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #eceff1;
            color: #111;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9.5px;
            line-height: 1.2;
        }
        .toolbar {
            width: min(1040px, calc(100% - 28px));
            margin: 14px auto 0;
            display: flex;
            justify-content: flex-end;
        }
        button {
            min-height: 36px;
            border: 0;
            border-radius: 4px;
            padding: 8px 13px;
            background: #087f78;
            color: #fff;
            font-weight: 700;
            cursor: pointer;
        }
        .sheet {
            width: min(1040px, calc(100% - 28px));
            min-height: 700px;
            margin: 10px auto 24px;
            padding: 26px 30px;
            background: #fff;
            border: 1px solid #c9c9c9;
            box-shadow: 0 10px 28px rgba(30, 42, 56, .13);
        }
        .document-grid {
            display: grid;
            grid-template-columns: 148px 1fr 118px;
            gap: 10px;
            align-items: start;
        }
        .company { font-weight: 700; }
        .title { text-align: center; padding-top: 54px; }
        h1 { margin: 0; font-size: 14px; letter-spacing: .3px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #111; padding: 3px 4px; vertical-align: top; overflow-wrap: anywhere; }
        th { text-align: center; font-weight: 800; }
        .doc-meta td:first-child { width: 58px; font-weight: 700; }
        .small { font-size: 8px; }
        .right-meta { margin-top: 52px; }
        .right-meta td:first-child { width: 36px; font-weight: 700; }
        .item-table { margin-top: 8px; }
        .item-table .no { width: 26px; text-align: center; }
        .item-table .part { width: 112px; }
        .item-table .desc { width: 140px; }
        .item-table .qty { width: 58px; text-align: center; }
        .vendor-group { width: 112px; }
        .remark { width: 88px; }
        .vendor-name { font-size: 7.5px; text-align: center; font-weight: 700; }
        .price { text-align: right; white-space: nowrap; }
        .selected-cell {
            background: #f1f6f4;
            font-weight: 800;
        }
        .selected-cell.price {
            font-weight: 800;
        }
        .selected-qty { display: block; margin-top: 2px; font-size: 7px; font-weight: 700; }
        .total-row td { font-weight: 800; }
        .note-and-sign { display: grid; grid-template-columns: 210px 1fr; gap: 36px; margin-top: 20px; align-items: start; }
        .note-box th { text-align: left; }
        .note-line { height: 18px; }
        .signatures { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; margin-top: 42px; text-align: center; }
        .signature { min-height: 118px; display: flex; flex-direction: column; justify-content: space-between; }
        .signature-role { text-align: left; font-weight: 700; }
        .signature-name { display: inline-block; min-width: 92px; border-bottom: 1px solid #111; padding-bottom: 2px; font-weight: 700; }
        .signature-image { display: block; width: 112px; height: 44px; object-fit: contain; margin: 3px auto 2px; mix-blend-mode: multiply; }
        .signature-title { margin-top: 3px; }
        .footer-code { margin-top: 6px; text-align: right; font-size: 8px; }
        @media print {
            @page { size: A4 landscape; margin: 10mm; }
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { width: 100%; min-height: 0; margin: 0; padding: 3mm 4mm; border: 0; box-shadow: none; }
            .signatures, .note-and-sign { break-inside: avoid; page-break-inside: avoid; }
        }
        @media (max-width: 760px) {
            .sheet { padding: 18px; }
            .document-grid, .note-and-sign, .signatures { grid-template-columns: 1fr; }
            .title { padding-top: 8px; text-align: left; }
            .right-meta { margin-top: 8px; }
            table { display: block; overflow-x: auto; }
        }
    </style>
</head>
<body>
@php
    $purchaseRequest = $requestItem->purchaseRequest;
    $formatDate = fn ($date) => $date ? \Illuminate\Support\Carbon::parse($date)->format('d-m-Y') : '-';
    $rankedQuotations = $requestItem->quotations->sortBy([
        ['unit_price', 'asc'],
        ['available_quantity', 'desc'],
        ['lead_time_days', 'asc'],
    ])->values();
    $visibleQuotations = $rankedQuotations->take(3)->values();
    $equipment = trim(($requestItem->unit?->code ?: $purchaseRequest->unit?->code ?: '-') . ' | ' . ($requestItem->unit?->name ?: $purchaseRequest->unit?->name ?: '-'));
    $logisticName = $requestItem->procurement_created_by_name ?: 'Admin Logistic';
    $equipmentName = $requestItem->procurement_equipment_checked_by_name ?: 'Peralatan';
    $directorName = $requestItem->procurement_director_approved_by_name ?: 'Direktur';
    $signatureImageFor = fn (?string $name) => $name ? \App\Models\User::where('name', $name)->value('signature_image') : null;
    $logisticSignatureImage = $signatureImageFor($requestItem->procurement_created_by_name);
    $equipmentSignatureImage = $signatureImageFor($requestItem->procurement_equipment_checked_by_name);
    $directorSignatureImage = $signatureImageFor($requestItem->procurement_director_approved_by_name);
    $selectedQuantity = fn ($quotation) => $quotation->is_selected ? (int) ($quotation->selected_quantity ?: $requestItem->approvedQuantity()) : 0;
@endphp
<div class="toolbar"><button onclick="window.print()">Cetak / Simpan PDF</button></div>

<main class="sheet">
    <section class="document-grid">
        <div class="company">
            <div>{{ $purchaseRequest->camp->company_name }}</div>
            <div>{{ $purchaseRequest->camp->location ?: $purchaseRequest->camp->name }}</div>
        </div>
        <div class="title"><h1>PERMINTAAN PENGADAAN BARANG</h1></div>
        <div>
            <div class="footer-code">FM-LOG-OP/02<br>Page : 1 of 1</div>
            <table class="right-meta">
                <tr><td>DATE</td><td>{{ $formatDate(now()) }}</td></tr>
                <tr><td>CAMP</td><td>{{ $purchaseRequest->camp->name }}</td></tr>
            </table>
        </div>
    </section>

    <table class="doc-meta" style="margin-top:10px">
        <tr><td>PO NUMBER</td><td>{{ $purchaseRequest->number }}</td></tr>
        <tr><td>EQUIPMENT</td><td>{{ $equipment }}</td></tr>
    </table>

    <table class="item-table">
        <thead>
        <tr>
            <th rowspan="2" class="no">No.</th>
            <th rowspan="2" class="part">Part Number</th>
            <th rowspan="2" class="desc">Description</th>
            <th rowspan="2" class="qty">Qty</th>
            @foreach ($visibleQuotations as $quotation)
                <th colspan="2" class="vendor-group">
                    <div class="vendor-name">{{ $quotation->vendor->name }}</div>
                </th>
            @endforeach
            @for ($i = $visibleQuotations->count(); $i < 3; $i++)
                <th colspan="2" class="vendor-group">&nbsp;</th>
            @endfor
            <th rowspan="2" class="remark">Remark</th>
        </tr>
        <tr>
            @for ($i = 0; $i < 3; $i++)
                <th class="small">Harga</th>
                <th class="small">Merk/Part</th>
            @endfor
        </tr>
        </thead>
        <tbody>
        <tr>
            <td class="no">1</td>
            <td>{{ $requestItem->inventoryItem->sku }}</td>
            <td>{{ $requestItem->inventoryItem->name }}</td>
            <td class="qty">
                {{ number_format($requestItem->quantity, 0, ',', '.') }} {{ $requestItem->uom ?? $requestItem->inventoryItem->uom }}
                @if ($requestItem->procurement_approval_status === 'approved')
                    <span class="selected-qty">Final {{ number_format($requestItem->approvedQuantity(), 0, ',', '.') }} {{ $requestItem->uom ?? $requestItem->inventoryItem->uom }}</span>
                @endif
            </td>
            @foreach ($visibleQuotations as $quotation)
                @php
                    $usedQuantity = $selectedQuantity($quotation);
                    $isUsed = $usedQuantity > 0;
                @endphp
                <td class="price {{ $isUsed ? 'selected-cell' : '' }}">
                    {{ $canSeePrices ? number_format($quotation->unit_price, 0, ',', '.') : '-' }}
                    @if ($isUsed)<span class="selected-qty">{{ $usedQuantity }} {{ $requestItem->uom ?? $requestItem->inventoryItem->uom }}</span>@endif
                </td>
                <td class="{{ $isUsed ? 'selected-cell' : '' }}">
                    {{ $quotation->brand_name ?: '-' }} / {{ $quotation->part_number ?: $requestItem->inventoryItem->sku }}
                    @if ($isUsed)<span class="selected-qty">{{ $usedQuantity }} {{ $requestItem->uom ?? $requestItem->inventoryItem->uom }}</span>@endif
                </td>
            @endforeach
            @for ($i = $visibleQuotations->count(); $i < 3; $i++)
                <td>&nbsp;</td><td></td>
            @endfor
            <td>{{ $requestItem->specification ?: '-' }}</td>
        </tr>
        <tr class="total-row">
            <td colspan="4" style="text-align:right">TOTAL</td>
            @foreach ($visibleQuotations as $quotation)
                @php
                    $usedQuantity = $selectedQuantity($quotation);
                    $totalQuantity = $usedQuantity ?: ($requestItem->procurement_approval_status === 'approved' ? $requestItem->approvedQuantity() : $requestItem->quantity);
                @endphp
                <td class="price {{ $usedQuantity > 0 ? 'selected-cell' : '' }}">{{ $canSeePrices ? number_format($quotation->unit_price * $totalQuantity, 0, ',', '.') : '-' }}</td>
                <td class="{{ $usedQuantity > 0 ? 'selected-cell' : '' }}">{{ $usedQuantity > 0 ? $usedQuantity.' '.($requestItem->uom ?? $requestItem->inventoryItem->uom) : '' }}</td>
            @endforeach
            @for ($i = $visibleQuotations->count(); $i < 3; $i++)
                <td>&nbsp;</td><td></td>
            @endfor
            <td></td>
        </tr>
        </tbody>
    </table>

    <section class="note-and-sign">
        <table class="note-box">
            <tr><th>Note :</th></tr>
            <tr><td class="note-line">{{ $requestItem->quantity_adjustment_reason ? 'Penyesuaian qty: '.$requestItem->quantity_adjustment_reason : '' }}</td></tr>
            <tr><td class="note-line"></td></tr>
            <tr><td class="note-line"></td></tr>
            <tr><td class="note-line"></td></tr>
        </table>
        <div>
            <section class="signatures">
                <div class="signature">
                <div class="signature-role">Dibuat Oleh,</div>
                <div>
                    @if ($logisticSignatureImage)<img class="signature-image" src="{{ $logisticSignatureImage }}" alt="TTD {{ $logisticName }}">@endif
                    <div class="signature-name">{{ $logisticName }}</div>
                    <div class="signature-title">Admin Logistic</div>
                </div>
            </div>
            <div class="signature">
                <div class="signature-role">Diperiksa Oleh,</div>
                <div>
                    @if ($equipmentSignatureImage)<img class="signature-image" src="{{ $equipmentSignatureImage }}" alt="TTD {{ $equipmentName }}">@endif
                    <div class="signature-name">{{ $equipmentName }}</div>
                    <div class="signature-title">Peralatan</div>
                </div>
            </div>
            <div class="signature">
                <div class="signature-role">Disetujui Oleh,</div>
                <div>
                    @if ($directorSignatureImage)<img class="signature-image" src="{{ $directorSignatureImage }}" alt="TTD {{ $directorName }}">@endif
                    <div class="signature-name">{{ $directorName }}</div>
                    <div class="signature-title">Direktur</div>
                </div>
            </div>
            </section>
        </div>
    </section>
</main>
</body>
</html>
