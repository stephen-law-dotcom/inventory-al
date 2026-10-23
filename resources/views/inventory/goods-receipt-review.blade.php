<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detail {{ $goodsReceipt->number }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=2">
    <style>
        :root { --bg:#f4f7f9; --panel:#fff; --ink:#172534; --navy:#102a43; --muted:#667687; --line:#d7e2e8; --accent:#087f78; --accent-dark:#06645f; --good:#087f5b; --bad:#b42318; --shadow:0 8px 22px rgba(16,42,67,.08); }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; background:linear-gradient(180deg,#fbfcfd 0,var(--bg) 240px); color:var(--ink); font-family:Inter,"Segoe UI",Roboto,Arial,sans-serif; font-size:14px; line-height:1.45; }
        a { color:inherit; text-decoration:none; }
        .page { width:min(1180px,calc(100% - 32px)); margin:0 auto; padding:20px 0 38px; display:grid; gap:14px; }
        .topbar,.panel { position:relative; overflow:hidden; background:var(--panel); border:1px solid var(--line); border-radius:7px; box-shadow:var(--shadow); }
        .topbar::before,.panel::before { content:""; position:absolute; inset:0 0 auto; height:3px; background:linear-gradient(90deg,var(--accent),#e3a72f); }
        .topbar { padding:18px 20px; display:flex; align-items:flex-start; justify-content:space-between; gap:16px; }
        h1 { margin:0; color:var(--navy); font-size:25px; }
        h2 { margin:0 0 14px; color:var(--navy); font-size:17px; }
        p { margin:5px 0 0; color:var(--muted); }
        .button { display:inline-flex; align-items:center; justify-content:center; min-height:40px; padding:9px 14px; border:1px solid var(--line); border-radius:6px; background:#f3f6f8; color:#24394d; font-weight:800; }
        .panel { padding:19px; }
        .meta-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; }
        .meta { padding:12px; border:1px solid var(--line); border-radius:6px; background:#fbfcfd; }
        .meta span { display:block; color:var(--muted); font-size:11px; font-weight:800; text-transform:uppercase; }
        .meta strong { display:block; margin-top:4px; color:var(--navy); overflow-wrap:anywhere; }
        .table-wrap { overflow-x:auto; border:1px solid var(--line); border-radius:6px; }
        table { width:100%; min-width:880px; border-collapse:collapse; }
        th,td { padding:11px 12px; border-bottom:1px solid var(--line); text-align:left; vertical-align:top; }
        th { background:#eef3f5; color:#465d70; font-size:11px; text-transform:uppercase; white-space:nowrap; }
        tbody tr:last-child td { border-bottom:0; }
        .item-name { color:var(--navy); font-weight:800; }
        .muted { color:var(--muted); font-size:12px; }
        .pill { display:inline-flex; padding:4px 9px; border-radius:999px; background:#e8eff1; color:#294052; font-size:11px; font-weight:800; white-space:nowrap; }
        .pill.good { background:#d9f5eb; color:var(--good); }
        .pill.issue { background:#ffe4df; color:var(--bad); }
        .credit-link { color:var(--accent-dark); font-weight:800; }
        .summary { display:flex; justify-content:flex-end; gap:18px; margin-top:14px; color:var(--muted); }
        .summary strong { color:var(--navy); }
        .notes { margin-top:14px; padding:12px 14px; border-left:3px solid var(--accent); background:#f5faf9; }
        .notice { padding:12px 14px; border:1px solid #9edbc9; border-radius:6px; background:#eaf8f3; color:var(--good); font-weight:800; }
        @media (max-width:820px) { .page { width:min(100% - 24px,1180px); padding-top:12px; } .topbar { flex-direction:column; } .button { width:100%; } .meta-grid { grid-template-columns:1fr 1fr; } }
        @media (max-width:520px) { .meta-grid { grid-template-columns:1fr; } h1 { font-size:21px; } .panel { padding:15px; } }
    </style>
</head>
<body>
@php
    $purchaseOrder = $goodsReceipt->purchaseOrder;
    $conditionLabels = ['good' => 'Baik', 'damaged' => 'Rusak', 'wrong_item' => 'Barang salah', 'quantity_short' => 'Jumlah kurang', 'spec_mismatch' => 'Spesifikasi tidak sesuai', 'other' => 'Lainnya'];
    $returnUrl = route('receipts', array_filter([
        'tab' => $purchaseOrder->status === 'received' ? 'history' : null,
        'company_id' => request()->integer('company_id') ?: null,
    ]));
@endphp
<main class="page">
    <header class="topbar">
        <div><h1>Detail Penerimaan Gudang</h1><p>{{ $goodsReceipt->number }} · PO {{ $purchaseOrder->number }}</p></div>
        <a class="button" href="{{ $returnUrl }}">Kembali</a>
    </header>

    @if (session('success'))
        <div class="notice">{{ session('success') }}</div>
    @endif

    <section class="panel">
        <h2>Informasi Penerimaan</h2>
        <div class="meta-grid">
            <div class="meta"><span>No. penerimaan</span><strong>{{ $goodsReceipt->number }}</strong></div>
            <div class="meta"><span>Faktur vendor</span><strong>{{ $goodsReceipt->invoice_number ?: '-' }}</strong></div>
            <div class="meta"><span>Tanggal diterima</span><strong>{{ $goodsReceipt->received_at?->format('d M Y') ?: '-' }}</strong></div>
            <div class="meta"><span>Diterima oleh</span><strong>{{ $goodsReceipt->received_by ?: '-' }}</strong></div>
            <div class="meta"><span>Vendor</span><strong>{{ $purchaseOrder->vendor->name }}</strong></div>
            <div class="meta"><span>Order Request</span><strong>{{ $purchaseOrder->purchaseRequest->number }}</strong></div>
            <div class="meta"><span>Site tujuan</span><strong>{{ $purchaseOrder->purchaseRequest->camp->name }}</strong></div>
            <div class="meta"><span>Status PO</span><strong>{{ str($purchaseOrder->status)->replace('_',' ')->title() }}</strong></div>
        </div>
        @if ($goodsReceipt->notes)<div class="notes"><strong>Catatan:</strong> {{ $goodsReceipt->notes }}</div>@endif
    </section>

    <section class="panel">
        <h2>Barang Diterima</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>No</th><th>Barang</th><th>Unit</th><th>Qty PO</th><th>Qty diterima</th><th>Kondisi</th><th>Catatan</th><th>Credit Note</th></tr></thead>
                <tbody>
                @forelse ($goodsReceipt->items as $receiptItem)
                    @php
                        $poItem = $receiptItem->purchaseOrderItem;
                        $creditNotes = $receiptItem->creditNoteItems->pluck('creditNote')->filter()->unique('id');
                    @endphp
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td><div class="item-name">{{ $poItem->inventoryItem?->sku }} - {{ $poItem->description }}</div><div class="muted">{{ $poItem->brand_name ?: '-' }} · {{ $poItem->part_number ?: 'Tanpa nomor part' }}</div></td>
                        <td>{{ $poItem->requestItem?->unit?->code ?: '-' }}</td>
                        <td>{{ number_format($poItem->quantity_ordered,0,',','.') }} {{ $poItem->uom }}</td>
                        <td><strong>{{ number_format($receiptItem->quantity_received,0,',','.') }} {{ $poItem->uom }}</strong></td>
                        <td><span class="pill {{ $receiptItem->condition === 'good' ? 'good' : 'issue' }}">{{ $conditionLabels[$receiptItem->condition] ?? str($receiptItem->condition)->replace('_',' ')->title() }}</span></td>
                        <td>{{ $receiptItem->notes ?: '-' }}</td>
                        <td>
                            @forelse ($creditNotes as $creditNote)
                                <a class="credit-link" href="{{ route('credit-notes.review', $creditNote) }}">{{ $creditNote->number }}</a>@if (! $loop->last)<br>@endif
                            @empty
                                <span class="muted">-</span>
                            @endforelse
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8">Belum ada detail barang penerimaan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="summary"><span>{{ $goodsReceipt->items->count() }} jenis barang</span><strong>{{ number_format($goodsReceipt->items->sum('quantity_received'),0,',','.') }} total qty diterima</strong></div>
    </section>
</main>
</body>
</html>
