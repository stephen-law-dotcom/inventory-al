@php
    $statusLabel = fn ($status) => [
        'draft' => 'Draft / Request',
        'sent_to_vendor' => 'Dikirim ke Vendor',
        'credited' => 'Credited / Dana Kembali',
        'closed' => 'Selesai',
    ][$status] ?? ucwords(str_replace('_', ' ', (string) $status));
    $sourceLabel = fn ($source) => [
        'goods_receipt' => 'Dari Penerimaan Gudang',
        'site_request' => 'Request dari Camp',
        'manual_return' => 'Manual Return / Barang Tidak Dipakai',
    ][$source] ?? ucwords(str_replace('_', ' ', (string) $source));
    $statusClass = fn ($status) => in_array($status, ['credited', 'closed'], true) ? 'ok' : ($status === 'draft' ? 'wait' : 'info');
    $camp = $creditNote->purchaseOrder?->purchaseRequest?->camp ?? $creditNote->siteReceipt?->siteDispatch?->camp ?? $creditNote->camp;
    $totalValue = $creditNote->items->sum('total');
    $allowedStatusOptions = \App\Support\WorkflowState::creditNoteOptions($creditNote->status);
    $returnContext = request()->query('from');
    $returnCompanyId = request()->integer('company_id') ?: null;
    $returnContext = in_array($returnContext, ['active', 'history', 'reports'], true)
        ? $returnContext
        : (in_array($creditNote->status, ['credited', 'closed'], true) ? 'history' : 'active');
    $returnUrl = $returnContext === 'reports'
        ? route('reports')
        : route('credit-notes', array_filter([
            'tab' => $returnContext === 'active' ? null : $returnContext,
            'company_id' => $returnCompanyId,
        ]));
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detail {{ $creditNote->number }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=2">
    <style>
        :root { --bg:#eef6f8; --panel:#fff; --text:#102a43; --muted:#557086; --line:#d6e4ea; --accent:#0b8f8a; --amber:#e3a72f; --red:#c9443b; --blue:#3157d4; --shadow:0 18px 45px rgba(16,42,67,.12); }
        * { box-sizing:border-box; }
        body { margin:0; font-family:Inter, "Segoe UI", Arial, sans-serif; background:linear-gradient(135deg,#f8fbfc,#eaf4f7); color:var(--text); }
        .page { width:min(1180px, calc(100% - 32px)); margin:24px auto; display:grid; gap:16px; }
        .topbar, .panel, .stat { background:var(--panel); border:1px solid var(--line); border-radius:8px; box-shadow:var(--shadow); padding:18px; }
        .topbar { display:flex; justify-content:space-between; gap:14px; align-items:flex-start; border-top:4px solid var(--accent); }
        .eyebrow { margin:0 0 6px; color:var(--accent); font-size:12px; font-weight:900; text-transform:uppercase; }
        h1, h2, h3, p { margin:0; }
        h1 { font-size:30px; line-height:1.1; }
        h2 { font-size:18px; margin-bottom:12px; }
        p, .muted { color:var(--muted); line-height:1.45; }
        .actions { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
        .button, button { border:0; border-radius:8px; padding:10px 14px; background:var(--accent); color:#fff; font-weight:900; text-decoration:none; cursor:pointer; }
        .ghost { background:#f4f8fa; color:var(--text); border:1px solid var(--line); }
        .pill { display:inline-flex; align-items:center; min-height:26px; padding:5px 10px; border-radius:999px; background:#edf4f7; color:var(--text); font-size:12px; font-weight:900; }
        .pill.ok { background:#dff8ed; color:#087343; }
        .pill.wait { background:#fff3d8; color:#875a00; }
        .pill.info { background:#e9efff; color:var(--blue); }
        .stats { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:12px; }
        .stat span { color:var(--muted); font-size:13px; }
        .stat strong { display:block; margin-top:8px; font-size:24px; }
        .layout { display:grid; grid-template-columns:1fr 330px; gap:16px; align-items:start; }
        .stack { display:grid; gap:16px; }
        .meta-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:10px; }
        .meta { border:1px solid var(--line); border-radius:8px; padding:12px; background:#f8fbfc; }
        .meta span { display:block; color:var(--muted); font-size:12px; font-weight:800; text-transform:uppercase; }
        .meta strong { display:block; margin-top:5px; }
        table { width:100%; border-collapse:collapse; border:1px solid var(--line); border-radius:8px; overflow:hidden; }
        th, td { padding:11px; border-bottom:1px solid var(--line); text-align:left; vertical-align:top; }
        th { background:#edf6f8; color:#40576b; font-size:12px; text-transform:uppercase; }
        .number { text-align:right; white-space:nowrap; }
        form { display:grid; gap:10px; }
        input, select { width:100%; border:1px solid var(--line); border-radius:8px; padding:10px 12px; font:inherit; font-weight:700; }
        label { display:grid; gap:6px; color:#40576b; font-size:12px; font-weight:900; }
        .two { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
        .timeline { display:grid; gap:10px; }
        .step { border-left:4px solid var(--accent); background:#f8fbfc; border-radius:8px; padding:11px; }
        .step h3 { font-size:14px; margin-bottom:4px; }
        @media (max-width:900px) { .topbar, .layout { grid-template-columns:1fr; display:grid; } .stats { grid-template-columns:1fr 1fr; } .meta-grid, .two { grid-template-columns:1fr; } }
        @media (max-width:560px) { .page { width:min(100% - 20px, 1180px); margin:10px auto; } .stats { grid-template-columns:1fr; } h1 { font-size:24px; } }
    </style>
</head>
<body>
<main class="page">
    <header class="topbar">
        <div>
            <p class="eyebrow">Detail Credit Note</p>
            <h1>{{ $creditNote->number }}</h1>
            <p>{{ $sourceLabel($creditNote->source_type) }} - {{ $camp?->company_name ?? '-' }} / {{ $camp?->name ?? '-' }}</p>
        </div>
        <div class="actions">
            <span class="pill {{ $statusClass($creditNote->status) }}">{{ $statusLabel($creditNote->status) }}</span>
            <a class="button ghost" href="{{ $returnUrl }}">Kembali</a>
        </div>
    </header>

    @if (session('success'))
        <section class="panel"><span class="pill ok">{{ session('success') }}</span></section>
    @endif
    @if (session('error') || $errors->any())
        <section class="panel"><span class="pill wait">{{ session('error') ?: $errors->first() }}</span></section>
    @endif

    <div class="stats">
        <div class="stat"><span>Status</span><strong>{{ $statusLabel($creditNote->status) }}</strong></div>
        <div class="stat"><span>Total Qty Problem</span><strong>{{ number_format($creditNote->items->sum('quantity_problem'), 0, ',', '.') }}</strong></div>
        <div class="stat"><span>Pengajuan Ulang</span><strong>{{ $creditNote->items->whereNotNull('retry_request_item_id')->count() }}/{{ $creditNote->items->count() }}</strong></div>
        <div class="stat">
            <span>Nilai Credit</span>
            <strong>{{ $canSeePrices ? \App\Support\Currency::format($totalValue, $creditNote->currency_code) : 'Disembunyikan' }}</strong>
            @if ($canSeePrices && $creditNote->currency_code !== 'IDR')<div class="muted">≈ {{ \App\Support\Currency::format($totalValue * $creditNote->exchange_rate) }}</div>@endif
        </div>
    </div>

    <div class="layout">
        <div class="stack">
            <section class="panel">
                <h2>Informasi Sumber</h2>
                <div class="meta-grid">
                    <div class="meta"><span>Sumber</span><strong>{{ $sourceLabel($creditNote->source_type) }}</strong></div>
                    <div class="meta"><span>Tanggal</span><strong>{{ $creditNote->credit_note_date?->format('d M Y') }}</strong></div>
                    <div class="meta"><span>Vendor</span><strong>{{ $creditNote->vendor?->name ?: '-' }}</strong></div>
                    <div class="meta"><span>Faktur</span><strong>{{ $creditNote->invoice_number ?: '-' }}</strong></div>
                    <div class="meta"><span>PO</span><strong>{{ $creditNote->purchaseOrder?->number ?: '-' }}</strong></div>
                    <div class="meta"><span>Mata Uang / Kurs</span><strong>{{ $creditNote->currency_code }}{{ $creditNote->currency_code !== 'IDR' ? ' · Rp'.number_format($creditNote->exchange_rate, 2, ',', '.') : '' }}</strong></div>
                    <div class="meta"><span>Diajukan Oleh</span><strong>{{ $creditNote->requested_by_name ?: $creditNote->created_by_name }}</strong></div>
                </div>
            </section>

            <section class="panel">
                <h2>Barang Bermasalah</h2>
                <table>
                    <thead><tr><th>Barang</th><th>Masalah</th><th>Qty</th>@if ($canSeePrices)<th class="number">Harga Credit</th><th class="number">Total</th>@endif<th>Pengajuan Ulang</th><th>Catatan</th></tr></thead>
                    <tbody>
                    @foreach ($creditNote->items as $item)
                        <tr>
                            <td><strong>{{ $item->inventoryItem?->name }}</strong><div class="muted">{{ $item->inventoryItem?->sku }}</div></td>
                            <td>{{ ucwords(str_replace('_', ' ', $item->issue_type)) }}<div class="muted">{{ ucwords(str_replace('_', ' ', $item->resolution)) }}</div></td>
                            <td>{{ number_format($item->quantity_problem, 0, ',', '.') }} {{ $item->purchaseOrderItem?->uom ?? $item->inventoryItem?->uom }}</td>
                            @if ($canSeePrices)
                                <td class="number">{{ \App\Support\Currency::format($item->unit_price, $item->currency_code) }}</td>
                                <td class="number">{{ \App\Support\Currency::format($item->total, $item->currency_code) }}</td>
                            @endif
                            <td>
                                @if ($item->retryRequestItem)
                                    <span class="pill ok">Sudah dibuat</span>
                                    <div class="muted">{{ $item->retryRequestItem->purchaseRequest?->number ?: 'Item ulang #'.$item->retryRequestItem->id }}</div>
                                @else
                                    <span class="pill wait">Belum dibuat</span>
                                @endif
                            </td>
                            <td>{{ $item->notes ?: '-' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </section>

            <section class="panel">
                <h2>Timeline Credit Note</h2>
                <div class="timeline">
                    @forelse ($timelineLogs as $log)
                        <div class="step">
                            <h3>{{ $log->description }}</h3>
                            <p>{{ $log->user?->name ?? 'System' }} - {{ $log->created_at?->format('d M Y H:i') }} - {{ $log->event }}</p>
                        </div>
                    @empty
                        <div class="step"><p>Belum ada timeline untuk credit note ini.</p></div>
                    @endforelse
                </div>
            </section>
        </div>

        <aside class="stack">
            @if ($canManageCreditNotes)
                <section class="panel">
                    <h2>Update Status</h2>
                    <form method="post" action="{{ route('credit-notes.update', $creditNote) }}" data-confirm="Perbarui status {{ $creditNote->number }}? Status credited atau selesai pada pengembalian manual akan mengurangi stok.">
                        @csrf @method('put')
                        <label>Status
                            <select name="status">
                                @foreach ($allowedStatusOptions as $statusOption)
                                    <option value="{{ $statusOption }}" @selected($creditNote->status === $statusOption)>{{ $statusLabel($statusOption) }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Catatan <input name="notes" value="{{ $creditNote->notes }}"></label>
                        <button>Update Credit Note</button>
                    </form>
                </section>
                @php
                    $retryCreated = $creditNote->items->whereNotNull('retry_request_item_id')->count();
                    $canCreateRetry = $creditNote->source_type !== 'manual_return' && in_array($creditNote->status, ['credited', 'closed'], true) && $retryCreated < $creditNote->items->count();
                @endphp
                <section class="panel">
                    <h2>Pengajuan Vendor Ulang</h2>
                    @if ($canCreateRetry)
                        <form method="post" action="{{ route('credit-notes.retry.store', $creditNote) }}" onsubmit="return confirm('Buat pengajuan vendor ulang dari {{ $creditNote->number }}?')">
                            @csrf
                            <button>Buat Pengajuan Ulang</button>
                        </form>
                    @else
                        <div class="pill wait">Belum bisa atau sudah lengkap</div>
                    @endif
                </section>
            @endif
            <section class="panel">
                <h2>Kontrol Stok</h2>
                <div style="margin-top:10px">
                    <span class="pill {{ $creditNote->stock_deducted_at ? 'ok' : 'wait' }}">{{ $creditNote->stock_deducted_at ? 'Stok sudah dikurangi' : 'Stok belum dikurangi' }}</span>
                </div>
            </section>
        </aside>
    </div>
</main>
@include('inventory.components.confirm-submit')
</body>
</html>
