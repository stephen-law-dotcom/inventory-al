<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order Request {{ $purchaseRequest->number }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=2">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #eceff1;
            color: #111;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
            line-height: 1.25;
        }
        .toolbar {
            width: min(760px, calc(100% - 28px));
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
            width: min(760px, calc(100% - 28px));
            min-height: 1040px;
            margin: 10px auto 24px;
            padding: 30px 32px 28px;
            background: #fff;
            border: 1px solid #c9c9c9;
            box-shadow: 0 10px 28px rgba(30, 42, 56, .13);
        }
        .top-line {
            display: grid;
            grid-template-columns: 1fr 1.1fr 1fr;
            gap: 12px;
            align-items: start;
        }
        .company { font-weight: 700; }
        .company div + div { margin-top: 2px; }
        .title { text-align: center; padding-top: 16px; }
        h1 {
            margin: 0;
            font-size: 20px;
            line-height: 1;
            font-weight: 800;
        }
        .page-no { text-align: right; font-size: 10px; }
        .meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 20px;
        }
        .meta-left div, .meta-right div { margin-bottom: 5px; }
        .meta-right { text-align: right; }
        .label { display: inline-block; min-width: 76px; font-weight: 700; }
        .note-line { margin-top: 18px; text-align: right; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2px;
            table-layout: fixed;
        }
        th, td {
            border: 1px solid #111;
            padding: 4px 5px;
            text-align: left;
            vertical-align: top;
            overflow-wrap: anywhere;
        }
        th {
            text-align: center;
            font-size: 10px;
            font-weight: 800;
        }
        .col-no { width: 28px; text-align: center; }
        .col-std { width: 58px; }
        .col-index { width: 48px; }
        .col-part { width: 98px; }
        .col-desc { width: auto; }
        .col-qty { width: 58px; text-align: center; }
        .col-remark { width: 122px; }
        .text-center { text-align: center; }
        .location-date { margin-top: 18px; }
        .signatures {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 22px;
            margin-top: 4px;
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .signature {
            min-height: 126px;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .signature-role { font-weight: 700; text-align: left; }
        .signature-name {
            display: inline-block;
            min-width: 112px;
            border-bottom: 1px solid #111;
            padding: 0 4px 2px;
            font-weight: 700;
        }
        .signature-image {
            display: block;
            width: 118px;
            height: 46px;
            object-fit: contain;
            margin: 4px auto 2px;
            mix-blend-mode: multiply;
        }
        .signature-title { margin-top: 3px; }
        .footer-code {
            margin-top: 8px;
            text-align: right;
            font-size: 9px;
            color: #333;
        }
        @media print {
            @page { size: A4 portrait; margin: 10mm; }
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet {
                width: 100%;
                min-height: 0;
                margin: 0;
                padding: 4mm 3mm;
                border: 0;
                box-shadow: none;
            }
            .signatures { display: grid !important; grid-template-columns: repeat(4, minmax(0, 1fr)) !important; }
        }
        @media (max-width: 760px) {
            .sheet { padding: 20px; }
            .top-line, .meta, .signatures { grid-template-columns: 1fr; }
            .title, .meta-right, .page-no { text-align: left; }
            table { display: block; overflow-x: auto; }
        }
    </style>
</head>
<body>
@php
    $formatDate = fn ($date) => $date ? \Illuminate\Support\Carbon::parse($date)->format('d-m-Y') : '-';
    $equipment = $purchaseRequest->items
        ->pluck('unit')
        ->filter()
        ->map(fn ($unit) => trim($unit->code . ' | ' . $unit->name))
        ->unique()
        ->values()
        ->implode(', ');
    $equipment = $equipment ?: ($purchaseRequest->unit ? trim($purchaseRequest->unit->code . ' | ' . $purchaseRequest->unit->name) : '-');
    $equipmentSignature = $approvalSignatures['equipment'] ?? ['label' => 'Diperiksa Oleh', 'name' => null];
    $ppicSignature = $approvalSignatures['ppic'] ?? ['label' => 'Dipesan Oleh', 'name' => null];
    $directorSignature = $approvalSignatures['director'] ?? ['label' => 'Diketahui Oleh', 'name' => null];
    $signatureImageFor = fn (?string $name) => $name ? \App\Models\User::where('name', $name)->value('signature_image') : null;
    $requestedSignatureImage = $signatureImageFor($purchaseRequest->requested_by);
    $equipmentSignatureImage = $signatureImageFor($equipmentSignature['name'] ?? null);
    $ppicSignatureImage = $signatureImageFor($ppicSignature['name'] ?? null);
    $directorSignatureImage = $signatureImageFor($directorSignature['name'] ?? null);
@endphp
<div class="toolbar"><button onclick="window.print()">Cetak / Simpan PDF</button></div>

<main class="sheet">
    <header class="top-line">
        <div class="company">
            <div>{{ $purchaseRequest->camp->company_name }}</div>
            <div>{{ $purchaseRequest->camp->location ?: $purchaseRequest->camp->name }}</div>
        </div>
        <div class="title"><h1>Order Request</h1></div>
        <div class="page-no">Page : 1 / 1</div>
    </header>

    <section class="meta">
        <div class="meta-left">
            <div><span class="label">OR Number</span>: {{ $purchaseRequest->number }}</div>
            <div><span class="label">Equipment</span>: {{ $equipment }}</div>
        </div>
        <div class="meta-right">
            <div><span class="label">Date</span>: {{ $formatDate($purchaseRequest->requested_at) }}</div>
        </div>
    </section>

    <div class="note-line">Mohon dipenuhi pesanan kami sbb.</div>

    <table>
        <thead>
        <tr>
            <th class="col-no">No.</th>
            <th class="col-std">Std/Page</th>
            <th class="col-index">Index</th>
            <th class="col-part">Part Number</th>
            <th class="col-desc">Description</th>
            <th class="col-qty">Qty</th>
            <th class="col-remark">Remark</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($purchaseRequest->items as $requestItem)
            <tr>
                <td class="col-no">{{ $loop->iteration }}.</td>
                <td></td>
                <td></td>
                <td>{{ $requestItem->inventoryItem->sku }}</td>
                <td>{{ $requestItem->inventoryItem->name }}{{ $requestItem->inventoryItem->description ? ' - '.$requestItem->inventoryItem->description : '' }}</td>
                <td class="text-center">{{ number_format($requestItem->quantity, 0, ',', '.') }} {{ $requestItem->uom ?? $requestItem->inventoryItem->uom }}</td>
                <td>{{ $requestItem->specification ?: $purchaseRequest->purpose ?: '-' }}</td>
            </tr>
        @endforeach
        @for ($row = $purchaseRequest->items->count() + 1; $row <= max(4, $purchaseRequest->items->count()); $row++)
            <tr>
                <td class="col-no">{{ $row }}.</td>
                <td>&nbsp;</td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
        @endfor
        </tbody>
    </table>

    <div class="footer-code">FM-LOG-OR-01</div>
    <div class="location-date">{{ $purchaseRequest->camp->name }}, {{ $formatDate(now()) }}</div>

    <section class="signatures">
        <div class="signature">
            <div class="signature-role">Dibuat Oleh,</div>
            <div>
                @if ($requestedSignatureImage)<img class="signature-image" src="{{ $requestedSignatureImage }}" alt="TTD {{ $purchaseRequest->requested_by }}">@endif
                <div class="signature-name">{{ $purchaseRequest->requested_by }}</div>
                <div class="signature-title">Admin Gudang</div>
            </div>
        </div>
        <div class="signature">
            <div class="signature-role">Diperiksa Oleh,</div>
            <div>
                @if ($equipmentSignatureImage)<img class="signature-image" src="{{ $equipmentSignatureImage }}" alt="TTD {{ $equipmentSignature['name'] }}">@endif
                <div class="signature-name">{{ $equipmentSignature['name'] ?: 'Logistik' }}</div>
                <div class="signature-title">{{ $equipmentSignature['label'] ?? 'Logistik' }}</div>
            </div>
        </div>
        <div class="signature">
            <div class="signature-role">Dipesan Oleh,</div>
            <div>
                @if ($ppicSignatureImage)<img class="signature-image" src="{{ $ppicSignatureImage }}" alt="TTD {{ $ppicSignature['name'] }}">@endif
                <div class="signature-name">{{ $ppicSignature['name'] ?: 'Chief Mechanic' }}</div>
                <div class="signature-title">{{ $ppicSignature['label'] ?? 'Chief Mechanic' }}</div>
            </div>
        </div>
        <div class="signature">
            <div class="signature-role">Diketahui Oleh,</div>
            <div>
                @if ($directorSignatureImage)<img class="signature-image" src="{{ $directorSignatureImage }}" alt="TTD {{ $directorSignature['name'] }}">@endif
                <div class="signature-name">{{ $directorSignature['name'] ?: 'Manager Camp' }}</div>
                <div class="signature-title">{{ $directorSignature['label'] ?? 'Manager Camp' }}</div>
            </div>
        </div>
    </section>
</main>
</body>
</html>
