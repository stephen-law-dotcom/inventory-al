<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kartu Stok {{ $item->sku }} - Sistem Inventory AL</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=2">
    <style>
        :root { --bg:#f3f7f8; --panel:#fff; --ink:#102a43; --muted:#627789; --line:#d7e2e8; --teal:#087f78; --amber:#946000; --good:#16794b; --red:#b42318; --blue:#3159c7; --shadow:0 12px 30px rgba(22,48,65,.09); }
        * { box-sizing:border-box; } body { margin:0; background:linear-gradient(180deg,#fbfcfd 0,var(--bg) 250px); color:var(--ink); font:14px/1.5 Inter,"Segoe UI",Arial,sans-serif; }
        .page { width:min(1280px,calc(100% - 32px)); margin:0 auto; padding:24px 0 42px; } .topbar,.panel,.stat { border:1px solid var(--line); border-radius:8px; background:var(--panel); box-shadow:var(--shadow); }
        .topbar { display:flex; justify-content:space-between; align-items:center; gap:18px; padding:20px 22px; border-top:4px solid var(--teal); } h1,h2 { margin:0; letter-spacing:0; } h1 { font-size:27px; line-height:1.2; } h2 { font-size:17px; } p { margin:5px 0 0; color:var(--muted); }
        .eyebrow { margin:0 0 4px; color:var(--teal); font-size:12px; font-weight:900; text-transform:uppercase; } .button { display:inline-flex; min-height:40px; align-items:center; justify-content:center; border:1px solid var(--line); border-radius:6px; padding:9px 14px; background:#f4f7f9; color:var(--ink); text-decoration:none; font-weight:800; white-space:nowrap; } .button:hover { background:#e9f0f3; }
        .stats { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; margin:16px 0; } .stat { padding:15px; border-top:4px solid var(--teal); } .stat:nth-child(2){border-top-color:var(--amber)} .stat:nth-child(3){border-top-color:var(--blue)} .stat:nth-child(4){border-top-color:var(--red)} .stat span { display:block; color:var(--muted); font-size:12px; font-weight:800; } .stat strong { display:block; margin-top:7px; font-size:22px; font-variant-numeric:tabular-nums; }
        .grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; } .panel { position:relative; padding:18px; overflow:hidden; } .panel::before { content:""; position:absolute; inset:0 0 auto; height:3px; background:linear-gradient(90deg,var(--teal),#e7a629); } .full { grid-column:1/-1; } .panel-head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:13px; } .panel-head span { color:var(--muted); font-size:12px; font-weight:800; }
        table { width:100%; border-collapse:separate; border-spacing:0; border:1px solid var(--line); border-radius:8px; overflow:hidden; background:#fff; font-size:13px; } th,td { padding:10px; border-bottom:1px solid var(--line); text-align:left; vertical-align:top; } tr:last-child td { border-bottom:0; } th { background:#eef7f8; color:#4d6273; font-size:11px; font-weight:900; text-transform:uppercase; } tbody tr:hover { background:#f2fbfb; } .muted { color:var(--muted); font-size:12px; } .number { font-variant-numeric:tabular-nums; white-space:nowrap; }
        .pill { display:inline-flex; align-items:center; min-height:23px; padding:3px 8px; border-radius:999px; background:#e8efef; color:#244047; font-size:11px; font-weight:800; white-space:nowrap; } .ok { background:#dcf7ee; color:var(--good); } .warn { background:#fff1d6; color:#8a5a00; } .bad { background:#fee7e2; color:var(--red); } .info { background:#e7efff; color:var(--blue); }
        .balance { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px; } .balance-box { border:1px solid var(--line); border-radius:7px; padding:12px; background:#fbfdfe; } .balance-box span { display:block; color:var(--muted); font-size:12px; font-weight:800; } .balance-box strong { display:block; margin-top:4px; font-size:18px; font-variant-numeric:tabular-nums; } .empty { padding:18px; border:1px dashed var(--line); border-radius:8px; color:var(--muted); background:#fbfdfe; text-align:center; font-weight:800; }
        @media (max-width:900px) { .page { width:min(100% - 24px,1280px); padding-top:14px; } .topbar { flex-direction:column; align-items:flex-start; } .stats,.grid,.balance { grid-template-columns:1fr; } .panel { padding:16px; } table { display:block; overflow-x:auto; } th,td { min-width:120px; } }
    </style>
</head>
<body>
<main class="page">
    <header class="topbar">
        <div>
            <p class="eyebrow">Kartu Stok Barang</p>
            <h1>{{ $item->sku }} - {{ $item->name }}</h1>
            <p>{{ $item->category }} · {{ $item->description ?: 'Tanpa deskripsi' }}</p>
        </div>
        <a class="button" href="{{ route('stock') }}">Kembali ke Stok</a>
    </header>

    <section class="stats">
        <article class="stat"><span>Tersedia di gudang</span><strong>{{ number_format($item->stock_on_hand, 0, ',', '.') }} {{ $item->uom }}</strong></article>
        <article class="stat"><span>Dalam pengiriman</span><strong>{{ number_format($item->stock_reserved, 0, ',', '.') }} {{ $item->uom }}</strong></article>
        <article class="stat"><span>Total stok di camp</span><strong>{{ number_format($campStocks->sum('quantity'), 0, ',', '.') }} {{ $item->uom }}</strong></article>
        <article class="stat"><span>Total pemakaian tercatat</span><strong>{{ number_format($usageLogs->sum('quantity'), 0, ',', '.') }} {{ $item->uom }}</strong></article>
    </section>

    <section class="grid">
        <article class="panel full">
            <div class="panel-head"><div><h2>Saldo per Camp</h2><p>Stok baik yang sudah diterima pada masing-masing camp.</p></div><span>{{ $campStocks->count() }} lokasi</span></div>
            @if ($campStocks->isNotEmpty())
                <div class="balance">
                    @foreach ($campStocks as $campStock)
                        <div class="balance-box"><span>{{ $campStock->camp?->company?->name }}</span><strong>{{ number_format($campStock->quantity, 0, ',', '.') }} {{ $item->uom }}</strong><div class="muted">{{ $campStock->camp?->name }}</div></div>
                    @endforeach
                </div>
            @else <div class="empty">Belum ada stok barang ini yang diterima di camp.</div> @endif
        </article>

        <article class="panel full">
            <div class="panel-head"><div><h2>Riwayat Order dan Purchase Order</h2><p>Asal kebutuhan barang, vendor, dan status pembeliannya.</p></div><span>{{ $purchaseOrderItems->count() }} baris PO</span></div>
            @if ($purchaseOrderItems->isNotEmpty())
                <table><thead><tr><th>Tanggal</th><th>Order Request</th><th>Purchase Order</th><th>Vendor</th><th>Tujuan</th><th>Qty</th>@if($canSeePrices)<th>Harga</th>@endif<th>Status</th></tr></thead><tbody>
                @foreach ($purchaseOrderItems as $poItem)
                    @php $po = $poItem->purchaseOrder; $request = $po?->purchaseRequest; @endphp
                    <tr><td>{{ $po?->ordered_at?->format('d M Y') ?: '-' }}</td><td>{{ $request?->number ?: '-' }}<br><span class="muted">{{ $request?->camp?->name ?: '-' }}</span></td><td>{{ $po?->number ?: '-' }}</td><td>{{ $po?->vendor?->name ?: '-' }}</td><td>{{ $po?->delivery_destination === 'direct_site' ? 'Langsung ke site' : 'Gudang kantor' }}</td><td class="number">{{ number_format($poItem->quantity_ordered, 0, ',', '.') }} {{ $poItem->uom }}</td>@if($canSeePrices)<td class="number">Rp{{ number_format($poItem->total, 0, ',', '.') }}</td>@endif<td><span class="pill {{ in_array($po?->status, ['submitted','issued']) ? 'ok' : ($po?->status === 'on_progress' ? 'warn' : 'info') }}">{{ str_replace('_', ' ', $po?->status ?: '-') }}</span></td></tr>
                @endforeach
                </tbody></table>
            @else <div class="empty">Belum ada order atau purchase order untuk barang ini.</div> @endif
        </article>

        <article class="panel full">
            <div class="panel-head"><div><h2>Riwayat Penerimaan Gudang</h2><p>Barang yang diterima dari vendor melalui gudang kantor.</p></div><span>{{ $goodsReceiptItems->count() }} baris penerimaan</span></div>
            @if ($goodsReceiptItems->isNotEmpty())
                <table><thead><tr><th>Tanggal</th><th>No. Penerimaan</th><th>Faktur</th><th>PO</th><th>Qty</th><th>Kondisi</th><th>Catatan</th></tr></thead><tbody>
                @foreach ($goodsReceiptItems as $receiptItem)
                    <tr><td>{{ $receiptItem->goodsReceipt?->received_at?->format('d M Y') ?: '-' }}</td><td>{{ $receiptItem->goodsReceipt?->number ?: '-' }}</td><td>{{ $receiptItem->goodsReceipt?->invoice_number ?: '-' }}</td><td>{{ $receiptItem->goodsReceipt?->purchaseOrder?->number ?: '-' }}</td><td class="number">{{ number_format($receiptItem->quantity_received, 0, ',', '.') }} {{ $item->uom }}</td><td><span class="pill {{ $receiptItem->condition === 'good' ? 'ok' : 'bad' }}">{{ str_replace('_', ' ', $receiptItem->condition) }}</span></td><td>{{ $receiptItem->notes ?: '-' }}</td></tr>
                @endforeach
                </tbody></table>
            @else <div class="empty">Belum ada penerimaan gudang untuk barang ini.</div> @endif
        </article>

        <article class="panel full">
            <div class="panel-head"><div><h2>Riwayat Pengiriman dan Penerimaan Site</h2><p>Perjalanan barang dari gudang atau vendor hingga diterima camp.</p></div><span>{{ $dispatchItems->count() }} baris pengiriman</span></div>
            @if ($dispatchItems->isNotEmpty())
                <table><thead><tr><th>Tanggal Kirim</th><th>No. Dokumen</th><th>Sumber</th><th>Tujuan</th><th>Qty Kirim</th><th>Qty Diterima</th><th>Status</th></tr></thead><tbody>
                @foreach ($dispatchItems as $dispatchItem)
                    @php $dispatch = $dispatchItem->siteDispatch; $receivedQuantity = $dispatchItem->receiptItems->sum('quantity_received'); @endphp
                    <tr><td>{{ $dispatch?->sent_at?->format('d M Y') ?: '-' }}</td><td>{{ $dispatch?->number ?: '-' }}</td><td>{{ $dispatch?->source_type === 'direct_vendor' ? 'Vendor langsung' : 'Gudang kantor' }}</td><td>{{ $dispatch?->camp?->name ?: '-' }}</td><td class="number">{{ number_format($dispatchItem->quantity_sent, 0, ',', '.') }} {{ $item->uom }}</td><td class="number">{{ number_format($receivedQuantity, 0, ',', '.') }} {{ $item->uom }}</td><td><span class="pill {{ $dispatch?->status === 'received' ? 'ok' : 'warn' }}">{{ str_replace('_', ' ', $dispatch?->status ?: '-') }}</span></td></tr>
                @endforeach
                </tbody></table>
            @else <div class="empty">Belum ada pengiriman site untuk barang ini.</div> @endif
        </article>

        <article class="panel full">
            <div class="panel-head"><div><h2>Riwayat Pemakaian Unit</h2><p>Pemakaian barang oleh unit pada camp.</p></div><span>{{ $usageLogs->count() }} transaksi</span></div>
            @if ($usageLogs->isNotEmpty())
                <table><thead><tr><th>Tanggal</th><th>Unit</th><th>Camp</th><th>Tipe</th><th>Qty</th><th>Operator</th><th>Referensi</th><th>Catatan</th></tr></thead><tbody>
                @foreach ($usageLogs as $usageLog)
                    <tr><td>{{ $usageLog->used_at?->format('d M Y') }}</td><td>{{ $usageLog->unit?->code ?: '-' }}<br><span class="muted">{{ $usageLog->unit?->name }}</span></td><td>{{ $usageLog->unit?->camp?->name ?: '-' }}</td><td>{{ strtoupper($usageLog->usage_type) }}</td><td class="number">{{ number_format($usageLog->quantity, 0, ',', '.') }} {{ $usageLog->uom }}</td><td>{{ $usageLog->mechanic_or_operator ?: '-' }}</td><td>{{ $usageLog->reference_number ?: '-' }}</td><td>{{ $usageLog->notes ?: '-' }}</td></tr>
                @endforeach
                </tbody></table>
            @else <div class="empty">Belum ada pemakaian unit untuk barang ini.</div> @endif
        </article>

        <article class="panel">
            <div class="panel-head"><div><h2>Stock Opname</h2><p>Riwayat perhitungan fisik barang.</p></div><span>{{ $stockOpnameItems->count() }} opname</span></div>
            @if ($stockOpnameItems->isNotEmpty())
                <table><thead><tr><th>Tanggal</th><th>No. Opname</th><th>Selisih</th><th>Status</th></tr></thead><tbody>@foreach($stockOpnameItems as $opnameItem)<tr><td>{{ $opnameItem->stockOpname?->opname_date?->format('d M Y') }}</td><td>{{ $opnameItem->stockOpname?->number }}</td><td class="number">{{ $opnameItem->difference > 0 ? '+' : '' }}{{ number_format($opnameItem->difference, 0, ',', '.') }} {{ $item->uom }}</td><td><span class="pill {{ $opnameItem->variance_status === 'matched' ? 'ok' : 'warn' }}">{{ str_replace('_', ' ', $opnameItem->variance_status) }}</span></td></tr>@endforeach</tbody></table>
            @else <div class="empty">Belum ada stock opname untuk barang ini.</div> @endif
        </article>

        <article class="panel">
            <div class="panel-head"><div><h2>Credit Note</h2><p>Riwayat barang bermasalah atau pengembalian.</p></div><span>{{ $creditNoteItems->count() }} baris</span></div>
            @if ($creditNoteItems->isNotEmpty())
                <table><thead><tr><th>No. CN</th><th>Tanggal</th><th>Qty</th><th>Status</th></tr></thead><tbody>@foreach($creditNoteItems as $creditNoteItem)<tr><td>{{ $creditNoteItem->creditNote?->number ?: '-' }}</td><td>{{ $creditNoteItem->creditNote?->credit_note_date?->format('d M Y') ?: '-' }}</td><td class="number">{{ number_format($creditNoteItem->quantity_problem, 0, ',', '.') }} {{ $item->uom }}</td><td><span class="pill {{ $creditNoteItem->creditNote?->status === 'credited' ? 'ok' : 'warn' }}">{{ str_replace('_', ' ', $creditNoteItem->creditNote?->status ?: '-') }}</span></td></tr>@endforeach</tbody></table>
            @else <div class="empty">Belum ada credit note untuk barang ini.</div> @endif
        </article>

        <article class="panel full">
            <div class="panel-head"><div><h2>Kartu Stok Gudang</h2><p>Perubahan saldo stok gudang kantor yang tercatat oleh sistem.</p></div><span>{{ $stockMovements->count() }} transaksi terakhir</span></div>
            @if ($stockMovements->isNotEmpty())
                <table><thead><tr><th>Waktu</th><th>Tipe</th><th>Qty</th><th>Sebelum</th><th>Sesudah</th><th>Oleh</th><th>Catatan</th></tr></thead><tbody>@foreach($stockMovements as $movement)<tr><td>{{ $movement->created_at?->format('d M Y H:i') }}</td><td>{{ str_replace('_', ' ', $movement->type) }}</td><td class="number">{{ $movement->quantity > 0 ? '+' : '' }}{{ number_format($movement->quantity, 0, ',', '.') }}</td><td class="number">{{ number_format($movement->stock_before, 0, ',', '.') }}</td><td class="number">{{ number_format($movement->stock_after, 0, ',', '.') }}</td><td>{{ $movement->user?->name ?: '-' }}</td><td>{{ $movement->notes ?: '-' }}</td></tr>@endforeach</tbody></table>
            @else <div class="empty">Belum ada kartu stok gudang untuk barang ini.</div> @endif
        </article>
    </section>
</main>
</body>
</html>
