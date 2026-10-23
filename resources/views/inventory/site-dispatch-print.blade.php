<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Packing List {{ $siteDispatch->number }}</title>
    <style>
        @page { size:A4 portrait; margin:9mm 10mm; }
        * { box-sizing:border-box; }
        body { margin:0; color:#111; background:#eef1f3; font-family:Arial,Helvetica,sans-serif; font-size:10px; }
        .toolbar { position:sticky; top:0; z-index:10; display:flex; justify-content:flex-end; gap:8px; max-width:210mm; margin:0 auto 10px; padding:10px; background:#eef1f3; }
        .toolbar button,.toolbar a { min-height:36px; padding:8px 13px; border:1px solid #aeb8bf; border-radius:5px; background:#fff; color:#172534; font:700 12px Arial,sans-serif; text-decoration:none; cursor:pointer; }
        .print-page { width:210mm; min-height:297mm; margin:0 auto 12px; padding:9mm 10mm; background:#fff; box-shadow:0 5px 20px rgba(16,42,67,.13); page-break-after:always; }
        .print-page:last-child { page-break-after:auto; }
        .header { display:grid; grid-template-columns:1fr 1.25fr 1fr; align-items:start; min-height:67px; margin-bottom:8px; }
        .company { font-size:10px; line-height:1.35; }
        .company strong { display:block; font-size:12px; }
        .title { padding-top:14px; text-align:center; }
        .title h1 { margin:0 0 3px; font-size:18px; }
        .title strong { font-size:13px; }
        .draft-mark { display:inline-block; margin-top:5px; padding:2px 8px; border:1px solid #555; font-size:9px; letter-spacing:0; }
        .document-meta { text-align:right; font-size:9px; line-height:1.45; }
        table { width:100%; border-collapse:collapse; table-layout:fixed; }
        thead { display:table-header-group; }
        tr { page-break-inside:avoid; }
        th,td { height:19px; padding:3px 4px; border:1px solid #222; vertical-align:middle; overflow:hidden; overflow-wrap:anywhere; }
        th { height:24px; text-align:center; font-size:10px; }
        .col-no { width:5%; text-align:center; }
        .col-part { width:17%; }
        .col-description { width:20%; }
        .col-qty { width:10%; text-align:center; }
        .col-unit { width:11%; }
        .col-vendor { width:18%; }
        .col-remark { width:19%; }
        .location-date { margin:15px 0 11px; font-size:10px; }
        .signatures { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:18px; text-align:center; page-break-inside:avoid; }
        .signature { min-height:105px; display:flex; flex-direction:column; justify-content:space-between; }
        .signature-title { font-weight:700; }
        .signature-body { min-height:79px; display:flex; flex-direction:column; justify-content:flex-end; align-items:center; }
        .signature-image { width:105px; height:47px; object-fit:contain; mix-blend-mode:multiply; margin-bottom:-4px; }
        .signature-line { width:100%; min-height:21px; padding:5px 3px 0; border-bottom:1px solid #111; font-weight:700; }
        .signature-role { margin-top:3px; font-size:8px; color:#333; }
        @media print { body { background:#fff; } .toolbar { display:none; } .print-page { width:auto; min-height:auto; margin:0; padding:0; box-shadow:none; } }
        @media screen and (max-width:820px) { .toolbar { justify-content:stretch; } .toolbar button,.toolbar a { flex:1; text-align:center; } }
    </style>
</head>
<body>
@php
    $items = $siteDispatch->items->values();
    $pages = $items->chunk(39)->values();
    $totalPages = max(1, $pages->count());
    $company = $siteDispatch->camp->company;
    $companyAddress = trim((string) ($company?->address ?: $siteDispatch->camp->location));
    $originCity = collect(explode(',', $companyAddress))->map(fn ($part) => trim($part))->filter()->last()
        ?: $siteDispatch->camp->location
        ?: '-';
    $receiver = $siteDispatch->siteReceipts->sortByDesc('received_at')->first()?->receivedByUser;
    $returnUrl = route('dispatches.review', array_filter([
        'siteDispatch' => $siteDispatch,
        'from' => request()->query('from'),
        'company_id' => request()->integer('company_id') ?: null,
    ]));
@endphp

<div class="toolbar"><button type="button" onclick="window.print()">Cetak / PDF</button><a href="{{ $returnUrl }}">Kembali</a></div>

@forelse ($pages as $pageIndex => $pageItems)
    <main class="print-page">
        <header class="header">
            <div class="company"><strong>{{ $company?->name ?? $siteDispatch->camp->company_name }}</strong>{{ $companyAddress ?: '-' }}<br>Telp. {{ $company?->phone ?: '-' }}</div>
            <div class="title"><h1>PACKING LIST</h1><strong>No : {{ $siteDispatch->number }}</strong>@if($siteDispatch->status === 'draft')<br><span class="draft-mark">DRAFT - BELUM DIKIRIM</span>@endif</div>
            <div class="document-meta">FM-LOG-01/04<br><em>Page {{ $pageIndex + 1 }} of {{ $totalPages }}</em></div>
        </header>

        <table>
            <thead><tr><th class="col-no">No.</th><th class="col-part">Part Number</th><th class="col-description">Description</th><th class="col-qty">Qty</th><th class="col-unit">Unit</th><th class="col-vendor">Vendor</th><th class="col-remark">Remark</th></tr></thead>
            <tbody>
            @foreach ($pageItems as $item)
                @php
                    $poItem = $item->purchaseOrderItem;
                    $purchaseOrder = $poItem?->purchaseOrder;
                    $purchaseRequest = $item->requestItem?->purchaseRequest;
                    $references = collect([$purchaseOrder?->number, $purchaseRequest?->number])->filter()->unique()->implode(' / ');
                    $remark = collect([$references, $item->notes])->filter()->implode(' - ');
                @endphp
                <tr>
                    <td class="col-no">{{ ($pageIndex * 39) + $loop->iteration }}.</td>
                    <td class="col-part">{{ $poItem?->part_number ?: $item->inventoryItem?->sku ?: '-' }}</td>
                    <td class="col-description">{{ $item->inventoryItem?->name ?: $poItem?->description ?: '-' }}</td>
                    <td class="col-qty">{{ number_format($item->quantity_sent,0,',','.') }} {{ $poItem?->uom ?? $item->inventoryItem?->uom }}</td>
                    <td class="col-unit">{{ $item->unit?->code ?? $siteDispatch->camp->name }}</td>
                    <td class="col-vendor">{{ $purchaseOrder?->vendor?->name ?? '-' }}</td>
                    <td class="col-remark">{{ $remark ?: '-' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        @if ($loop->last)
            <div class="location-date">{{ $originCity }}, {{ ($siteDispatch->sent_at ?? $siteDispatch->created_at)?->format('d-m-Y') }}</div>
            <section class="signatures">
                @foreach ([
                    ['title' => 'Diterima Oleh,', 'user' => $receiver, 'fallback' => 'Belum diterima', 'role' => 'Penerima Camp'],
                    ['title' => 'Diperiksa Oleh,', 'user' => $equipmentHead, 'fallback' => 'Kepala Peralatan', 'role' => 'Kepala Peralatan'],
                    ['title' => 'Disiapkan Oleh,', 'user' => $purchasingStaff, 'fallback' => 'Staff Purchasing', 'role' => 'Staff Purchasing'],
                    ['title' => 'Dikirim Oleh,', 'user' => $siteDispatch->dispatchedBy, 'fallback' => 'Pengirim', 'role' => 'Pengirim'],
                ] as $signature)
                    <div class="signature">
                        <div class="signature-title">{{ $signature['title'] }}</div>
                        <div class="signature-body">
                            @if ($signature['user']?->signature_image)<img class="signature-image" src="{{ $signature['user']->signature_image }}" alt="TTD {{ $signature['user']->name }}">@endif
                            <div class="signature-line">{{ $signature['user']?->name ?? $signature['fallback'] }}</div>
                            <div class="signature-role">{{ $signature['role'] }}</div>
                        </div>
                    </div>
                @endforeach
            </section>
        @endif
    </main>
@empty
    <main class="print-page"><p>Packing List belum memiliki barang.</p></main>
@endforelse
</body>
</html>
