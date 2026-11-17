<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pengiriman {{ $siteDispatch->number }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=2">
    <style>
        :root {
            --bg: #f2f7f8;
            --panel: #ffffff;
            --ink: #102a43;
            --muted: #627789;
            --line: #d7e3e9;
            --teal: #078a82;
            --teal-dark: #066d67;
            --green: #16794b;
            --amber: #a76600;
            --red: #b42318;
            --blue: #315bdc;
            --shadow: 0 12px 30px rgba(22, 48, 65, .08);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background: linear-gradient(180deg, #fbfcfd 0, var(--bg) 250px);
            color: var(--ink);
            font-family: Inter, "Segoe UI", Roboto, Arial, sans-serif;
            font-size: 14px;
            line-height: 1.5;
        }
        h1, h2, h3, p { margin: 0; }
        h1 { font-size: 28px; line-height: 1.2; font-weight: 900; overflow-wrap: anywhere; }
        h2 { font-size: 18px; font-weight: 900; }
        h3 { font-size: 14px; font-weight: 900; }
        p, .muted { color: var(--muted); }
        small { color: var(--muted); }
        .page { width: min(1180px, calc(100% - 32px)); margin: 0 auto; padding: 24px 0 42px; }
        .topbar, .panel, .notice {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: var(--shadow);
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            align-items: flex-start;
            padding: 20px 22px;
            border-top: 4px solid var(--teal);
        }
        .eyebrow {
            margin-bottom: 4px;
            color: var(--teal);
            font-size: 12px;
            font-weight: 900;
            text-transform: uppercase;
        }
        .subtitle { margin-top: 5px; }
        .actions { display: flex; justify-content: flex-end; gap: 8px; flex-wrap: wrap; align-items: center; }
        .actions form { margin: 0; }
        button, .button {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            min-height: 42px;
            border: 1px solid var(--teal);
            border-radius: 7px;
            padding: 10px 14px;
            background: var(--teal);
            color: #fff;
            font: inherit;
            font-weight: 900;
            line-height: 1.2;
            text-decoration: none;
            cursor: pointer;
            white-space: nowrap;
        }
        button:hover, .button:hover { border-color: var(--teal-dark); background: var(--teal-dark); }
        .ghost { border-color: var(--line); background: #f4f7f9; color: var(--ink); }
        .ghost:hover { border-color: #cad8df; background: #eaf1f4; }
        .notice { margin-top: 14px; padding: 13px 15px; border-color: #b8dfca; background: #eefaf3; color: var(--green); font-weight: 800; }
        .notice.error { border-color: #efb4af; background: #fff4f2; color: var(--red); }
        .panel { margin-top: 16px; padding: 20px; }
        .section-head { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; margin-bottom: 14px; }
        .section-head p { margin-top: 3px; }
        .pill {
            display: inline-flex;
            align-items: center;
            min-height: 27px;
            border-radius: 999px;
            padding: 5px 10px;
            background: #e8f3f5;
            color: #315265;
            font-size: 12px;
            font-weight: 900;
            white-space: nowrap;
        }
        .pill.ok { background: #dff8ed; color: #087343; }
        .pill.wait { background: #fff3d8; color: #875a00; }
        .pill.info { background: #e9efff; color: var(--blue); }
        .pill.bad { background: #ffebe8; color: var(--red); }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            border: 1px solid var(--line);
            border-radius: 7px;
            overflow: hidden;
        }
        .summary-item { min-width: 0; padding: 14px; border-right: 1px solid var(--line); background: #fbfcfd; }
        .summary-item:last-child { border-right: 0; }
        .summary-item > span {
            display: block;
            margin-bottom: 5px;
            color: var(--muted);
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
        }
        .summary-item strong { display: block; overflow-wrap: anywhere; }
        .summary-item small { display: block; margin-top: 3px; overflow-wrap: anywhere; }
        .table-wrap {
            width: 100%;
            max-width: 100%;
            overflow-x: auto;
            border: 1px solid var(--line);
            border-radius: 7px;
            -webkit-overflow-scrolling: touch;
        }
        table { display: table; width: 100%; min-width: 900px; border-collapse: collapse; background: #fff; }
        th, td { padding: 11px 12px; border-bottom: 1px solid var(--line); text-align: left; vertical-align: top; }
        tbody tr:last-child td { border-bottom: 0; }
        tbody tr:hover { background: #f5fbfb; }
        th { background: #edf6f7; color: #40576b; font-size: 11px; font-weight: 900; text-transform: uppercase; white-space: nowrap; }
        .item-name { font-weight: 900; }
        .number { text-align: right; white-space: nowrap; }
        .receipt-progress { display: grid; gap: 4px; }
        details.history-panel { padding: 0; overflow: hidden; }
        .history-summary {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            padding: 18px 20px;
            cursor: pointer;
            list-style: none;
        }
        .history-summary::-webkit-details-marker { display: none; }
        .history-summary::after { content: "+"; flex: 0 0 auto; color: var(--teal); font-size: 22px; font-weight: 900; line-height: 1; }
        details[open] .history-summary::after { content: "-"; }
        .history-summary:focus-visible { outline: 3px solid rgba(7, 138, 130, .22); outline-offset: -3px; }
        .history-summary p { margin-top: 3px; }
        .history-content { display: grid; gap: 10px; padding: 0 20px 20px; }
        .receipt-record { padding: 14px; border: 1px solid var(--line); border-radius: 7px; background: #f8fbfc; }
        .receipt-record-head { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; margin-bottom: 10px; }
        .receipt-record-meta { margin-top: 3px; color: var(--muted); font-size: 12px; }
        .receipt-record-items { display: grid; gap: 0; border-top: 1px solid var(--line); }
        .receipt-record-item { display: flex; justify-content: space-between; gap: 12px; padding: 8px 0; border-bottom: 1px solid var(--line); }
        .receipt-record-item:last-child { padding-bottom: 0; border-bottom: 0; }
        .receipt-form { display: grid; gap: 14px; }
        .field { display: grid; gap: 6px; min-width: 0; }
        .field > span { color: #40576b; font-size: 12px; font-weight: 900; }
        input, select {
            width: 100%;
            min-height: 42px;
            border: 1px solid var(--line);
            border-radius: 7px;
            padding: 10px 11px;
            background: #fff;
            color: var(--ink);
            font: inherit;
            font-weight: 700;
        }
        input:focus, select:focus { border-color: var(--teal); outline: 3px solid rgba(7, 138, 130, .14); }
        input[readonly] { background: #f3f7f8; color: #536c7e; }
        .received-by { max-width: 420px; }
        .receipt-items { display: grid; gap: 10px; }
        .receipt-item {
            display: grid;
            grid-template-columns: minmax(180px, 1.25fr) minmax(120px, .5fr) minmax(180px, .8fr) minmax(220px, 1.2fr);
            gap: 12px;
            align-items: end;
            padding: 14px;
            border: 1px solid var(--line);
            border-radius: 7px;
            background: #f8fbfc;
        }
        .receipt-item-info { align-self: center; min-width: 0; }
        .receipt-item-info strong { display: block; overflow-wrap: anywhere; }
        .receipt-item-info span { display: block; margin-top: 4px; color: var(--muted); font-size: 12px; }
        .form-actions { display: flex; justify-content: flex-end; }
        @media (max-width: 900px) {
            .topbar { flex-direction: column; }
            .actions { justify-content: flex-start; }
            .summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .summary-item:nth-child(2) { border-right: 0; }
            .summary-item:nth-child(-n+2) { border-bottom: 1px solid var(--line); }
            .receipt-item { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .receipt-item-info { grid-column: 1 / -1; }
        }
        @media (max-width: 640px) {
            .page { width: min(100% - 20px, 1180px); padding-top: 10px; }
            .topbar, .panel { padding: 16px; }
            h1 { font-size: 23px; }
            .actions, .actions form, .actions button, .actions .button { width: 100%; }
            .summary-grid, .receipt-item { grid-template-columns: 1fr; }
            .summary-item { border-right: 0; border-bottom: 1px solid var(--line); }
            .summary-item:last-child { border-bottom: 0; }
            .section-head, .receipt-record-head, .receipt-record-item { align-items: flex-start; flex-direction: column; }
            .history-summary { align-items: flex-start; padding: 16px; }
            .history-content { padding: 0 16px 16px; }
            .form-actions, .form-actions button { width: 100%; }
        }
    </style>
</head>
<body>
@php
    $returnContext = request()->query('from');
    $returnCompanyId = request()->integer('company_id') ?: null;
    $defaultReturnContext = $siteDispatch->status === 'draft'
        ? 'draft'
        : (in_array($siteDispatch->status, ['received_good', 'received_with_issue', 'closed', 'cancelled'], true)
            ? 'history'
            : (auth()->user()?->hasPermission('site_receipts.manage') ? 'receiving' : 'transit'));
    $returnContext = $returnContext === 'outbound' ? 'draft' : $returnContext;
    $returnContext = in_array($returnContext, ['draft', 'transit', 'receiving', 'history'], true)
        ? $returnContext
        : $defaultReturnContext;
    $contextQuery = array_filter(['from' => $returnContext, 'company_id' => $returnCompanyId]);
    $returnUrl = route('dispatches', array_filter(['tab' => $returnContext, 'company_id' => $returnCompanyId]));
    $canReceiveAtSite = auth()->user()?->hasPermission('site_receipts.manage')
        && (auth()->user()?->isAdmin()
            || in_array((int) $siteDispatch->camp_id, auth()->user()?->scopedCampIds() ?? [], true));
    $statusLabel = [
        'draft' => 'Draft',
        'sent' => 'Dalam Pengiriman',
        'partial_received' => 'Diterima Sebagian',
        'received_good' => 'Diterima Baik',
        'received_with_issue' => 'Diterima Bermasalah',
        'closed' => 'Selesai',
        'cancelled' => 'Dibatalkan',
    ][$siteDispatch->status] ?? ucwords(str_replace('_', ' ', $siteDispatch->status));
    $statusPillClass = match ($siteDispatch->status) {
        'received_good', 'closed' => 'ok',
        'sent', 'partial_received' => 'wait',
        'received_with_issue', 'cancelled' => 'bad',
        default => 'info',
    };
@endphp

<main class="page">
    <header class="topbar">
        <div>
            <p class="eyebrow">{{ $siteDispatch->source_type === 'direct_vendor' ? 'Pengiriman Langsung Vendor' : 'Detail Packing List' }}</p>
            <h1>{{ $siteDispatch->number }}</h1>
            <p class="subtitle">{{ $siteDispatch->camp->company_name }} - {{ $siteDispatch->camp->name }}</p>
        </div>
        <div class="actions">
            @if ($siteDispatch->status === 'draft' && auth()->user()->hasPermission('dispatches.manage'))
                <a class="button ghost" href="{{ route('dispatches.edit', $siteDispatch) }}">Edit Draft</a>
                <form method="post" action="{{ route('dispatches.cancel', $siteDispatch) }}" data-confirm="Batalkan draft {{ $siteDispatch->number }}? Stok tidak akan berubah dan dokumen dipindahkan ke Riwayat.">
                    @csrf
                    <button class="ghost" type="submit">Batalkan Draft</button>
                </form>
                <form method="post" action="{{ route('dispatches.send', $siteDispatch) }}" data-confirm="Kirim {{ $siteDispatch->number }} ke site? Stok gudang akan langsung berkurang dan draft tidak dapat diedit lagi.">
                    @csrf
                    <button type="submit">Kirim ke Site</button>
                </form>
            @endif
            @if (in_array($siteDispatch->status, ['sent', 'partial_received'], true) && auth()->user()->canApproveManagerCamp() && ! $siteDispatch->acknowledged_at)
                <form method="post" action="{{ route('dispatches.acknowledge', $siteDispatch) }}" data-confirm="Konfirmasi bahwa Manager Camp mengetahui pengiriman {{ $siteDispatch->number }}?">
                    @csrf
                    <button type="submit">Mengetahui Pengiriman</button>
                </form>
            @endif
            <a class="button" href="{{ route('dispatches.print', array_merge(['siteDispatch' => $siteDispatch], $contextQuery)) }}">Cetak / PDF</a>
            <a class="button ghost" href="{{ $returnUrl }}">Kembali</a>
        </div>
    </header>

    @if (session('success'))
        <div class="notice">{{ session('success') }}</div>
    @endif
    @if (session('error') || $errors->any())
        <div class="notice error">{{ session('error') ?: $errors->first() }}</div>
    @endif

    <section class="panel" aria-label="Ringkasan pengiriman">
        <div class="summary-grid">
            <div class="summary-item">
                <span>{{ $siteDispatch->status === 'draft' ? 'Tanggal dibuat' : 'Tanggal kirim' }}</span>
                <strong>{{ ($siteDispatch->sent_at ?? $siteDispatch->created_at)?->format('d M Y') ?? '-' }}</strong>
            </div>
            <div class="summary-item">
                <span>Status</span>
                <strong><span class="pill {{ $statusPillClass }}">{{ $statusLabel }}</span></strong>
            </div>
            <div class="summary-item">
                <span>Pengirim / ekspedisi</span>
                <strong>{{ $siteDispatch->driver_name ?: 'Belum dilengkapi' }}</strong>
                <small>{{ $siteDispatch->vehicle_number ?: 'Nomor kendaraan/resi belum dilengkapi' }}</small>
            </div>
            <div class="summary-item">
                <span>Diketahui Manager Camp</span>
                <strong>{{ $siteDispatch->status === 'draft' ? 'Belum dikirim' : ($siteDispatch->acknowledgedBy?->name ?? 'Menunggu') }}</strong>
            </div>
        </div>
    </section>

    <section class="panel">
        <div class="section-head">
            <div>
                <h2>Daftar Barang</h2>
                <p>{{ $siteDispatch->items->count() }} jenis barang dalam dokumen pengiriman.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Part Number</th>
                        <th>Deskripsi</th>
                        <th class="number">Qty Kirim</th>
                        <th>Unit</th>
                        <th>Vendor</th>
                        <th>PO / OR</th>
                        <th>Diterima</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($siteDispatch->items as $item)
                        @php
                            $receivedQuantity = $item->receiptItems->sum('quantity_received');
                            $remainingQuantity = max(0, $item->quantity_sent - $receivedQuantity);
                            $uom = $item->purchaseOrderItem?->uom ?? $item->inventoryItem->uom;
                        @endphp
                        <tr>
                            <td><strong>{{ $item->inventoryItem->sku }}</strong></td>
                            <td>
                                <span class="item-name">{{ $item->inventoryItem->name }}</span>
                                @if ($item->notes)
                                    <br><small>{{ $item->notes }}</small>
                                @endif
                            </td>
                            <td class="number">{{ $item->quantity_sent }} {{ $uom }}</td>
                            <td>{{ $item->unit?->code ?? 'Stok camp' }}</td>
                            <td>{{ $item->purchaseOrderItem?->purchaseOrder?->vendor?->name ?? '-' }}</td>
                            <td>
                                <strong>{{ $item->purchaseOrderItem?->purchaseOrder?->number ?? '-' }}</strong>
                                <br><small>{{ $item->requestItem?->purchaseRequest?->number ?? '-' }}</small>
                            </td>
                            <td>
                                <div class="receipt-progress">
                                    <strong>{{ $receivedQuantity }} / {{ $item->quantity_sent }} {{ $uom }}</strong>
                                    <span class="pill {{ $remainingQuantity <= 0 ? 'ok' : ($receivedQuantity > 0 ? 'wait' : 'info') }}">
                                        {{ $remainingQuantity <= 0 ? 'Lengkap' : 'Sisa '.$remainingQuantity.' '.$uom }}
                                    </span>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @if ($siteDispatch->siteReceipts->isNotEmpty())
        <details class="panel history-panel" @if (session('success')) open @endif>
            <summary class="history-summary">
                <div>
                    <h2>Riwayat Penerimaan Site</h2>
                    <p>Dokumen penerimaan yang sudah dicatat untuk pengiriman ini.</p>
                </div>
                <span class="pill info">{{ $siteDispatch->siteReceipts->count() }} penerimaan</span>
            </summary>
            <div class="history-content">
                @foreach ($siteDispatch->siteReceipts->sortByDesc('received_at') as $siteReceipt)
                    <article class="receipt-record">
                        <div class="receipt-record-head">
                            <div>
                                <h3>{{ $siteReceipt->number }}</h3>
                                <div class="receipt-record-meta">{{ $siteReceipt->received_at?->format('d M Y') }} | {{ $siteReceipt->received_by }}</div>
                            </div>
                            <span class="pill {{ $siteReceipt->all_good_receipt ? 'ok' : 'wait' }}">
                                {{ $siteReceipt->all_good_receipt ? 'Diterima baik' : 'Sebagian / bermasalah' }}
                            </span>
                        </div>
                        <div class="receipt-record-items">
                            @foreach ($siteReceipt->items as $receiptItem)
                                <div class="receipt-record-item">
                                    <span>{{ $receiptItem->dispatchItem?->inventoryItem?->name ?? 'Barang' }}</span>
                                    <strong>{{ $receiptItem->quantity_received }} | {{ ucwords(str_replace('_', ' ', $receiptItem->condition)) }}</strong>
                                </div>
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </div>
        </details>
    @endif

    @if ($canReceiveAtSite && in_array($siteDispatch->status, ['sent', 'partial_received'], true))
        <section class="panel">
            <div class="section-head">
                <div>
                    <h2>Catat Penerimaan Camp</h2>
                    <p>Masukkan jumlah dan kondisi barang yang benar-benar diterima.</p>
                </div>
            </div>
            <form class="receipt-form" method="post" action="{{ route('site-receipts.store', $siteDispatch) }}" data-confirm="Konfirmasi penerimaan {{ $siteDispatch->number }}? Barang baik akan masuk ke stok camp dan barang bermasalah dapat diteruskan ke Credit Note.">
                @csrf
                <label class="field received-by">
                    <span>Diterima oleh</span>
                    <input value="{{ auth()->user()->name }}" readonly>
                </label>

                <div class="receipt-items">
                    @foreach ($siteDispatch->items as $item)
                        @php
                            $remaining = max(0, $item->quantity_sent - $item->receiptItems->sum('quantity_received'));
                            $uom = $item->purchaseOrderItem?->uom ?? $item->inventoryItem->uom;
                        @endphp
                        <div class="receipt-item">
                            <input type="hidden" name="items[{{ $loop->index }}][site_dispatch_item_id]" value="{{ $item->id }}">
                            <div class="receipt-item-info">
                                <strong>{{ $item->inventoryItem->name }}</strong>
                                <span>{{ $item->inventoryItem->sku }} | Sisa kiriman {{ $remaining }} {{ $uom }}</span>
                            </div>
                            <label class="field">
                                <span>Qty diterima</span>
                                <input name="items[{{ $loop->index }}][quantity_received]" type="number" min="0" max="{{ $remaining }}" value="{{ $remaining }}">
                            </label>
                            <label class="field">
                                <span>Kondisi</span>
                                <select name="items[{{ $loop->index }}][condition]">
                                    <option value="good">Baik</option>
                                    <option value="damaged">Rusak</option>
                                    <option value="wrong_item">Barang salah</option>
                                    <option value="spec_mismatch">Spesifikasi tidak sesuai</option>
                                    <option value="quantity_short">Kurang</option>
                                    <option value="other">Lainnya</option>
                                </select>
                            </label>
                            <label class="field">
                                <span>Catatan</span>
                                <input name="items[{{ $loop->index }}][notes]" placeholder="Wajib untuk barang bermasalah">
                            </label>
                        </div>
                    @endforeach
                </div>

                <div class="form-actions">
                    <button type="submit">Konfirmasi Penerimaan</button>
                </div>
            </form>
        </section>
    @endif
</main>

@include('inventory.components.confirm-submit')
</body>
</html>
