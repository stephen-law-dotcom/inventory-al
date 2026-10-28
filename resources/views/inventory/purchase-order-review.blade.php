@php
    $purchaseOrderStatusLabel = fn ($status) => [
        'draft' => 'Draft',
        'on_progress' => 'On Progress',
        'submitted' => 'Submitted',
        'issued' => 'Submitted',
        'partial_received' => 'Partial Received',
        'received' => 'Received',
        'rejected' => 'Ditolak',
    ][$status] ?? str_replace('_', ' ', (string) $status);
    $purchaseOrderDisplayStatus = match (true) {
        $purchaseOrder->status !== 'draft' => $purchaseOrderStatusLabel($purchaseOrder->status),
        ! $purchaseOrder->proposed_by && (bool) $purchaseOrder->rejected_at => 'Dikembalikan untuk revisi',
        ! $purchaseOrder->proposed_by => 'Draft',
        ! $purchaseOrder->checked_by => 'Menunggu Kepala Purchasing',
        ! $purchaseOrder->approved_by => 'Menunggu Direktur',
        default => 'Disetujui Direktur',
    };
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Review {{ $purchaseOrder->number }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=2">
    <style>
        :root {
            --bg: #f3f7f8;
            --panel: #ffffff;
            --ink: #102a43;
            --muted: #627789;
            --line: #d9e5ea;
            --navy: #112b3e;
            --teal: #087f78;
            --teal-dark: #066a65;
            --amber: #e7a629;
            --green: #16794b;
            --red: #b42318;
            --shadow: 0 10px 28px rgba(22, 48, 65, .09);
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background: var(--bg);
            color: var(--ink);
            font-family: Inter, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 14px;
            line-height: 1.5;
        }
        a { color: inherit; text-decoration: none; }
        .page { width: min(1180px, calc(100% - 32px)); margin: 0 auto; padding: 24px 0 40px; }
        .topbar, .panel {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: var(--shadow);
        }
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 20px 22px;
            border-top: 4px solid var(--teal);
        }
        .eyebrow { margin: 0 0 3px; color: var(--teal); font-size: 12px; font-weight: 900; text-transform: uppercase; }
        h1 { margin: 0; font-size: 27px; line-height: 1.2; letter-spacing: 0; }
        h2 { margin: 0; font-size: 17px; letter-spacing: 0; }
        p { margin: 5px 0 0; color: var(--muted); }
        .top-actions, .actions { display: flex; align-items: center; gap: 9px; flex-wrap: wrap; }
        .button, button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            border: 1px solid transparent;
            border-radius: 6px;
            padding: 9px 14px;
            background: var(--teal);
            color: #fff;
            font: inherit;
            font-weight: 800;
            cursor: pointer;
        }
        .button:hover, button:hover { background: var(--teal-dark); }
        .button.ghost { border-color: var(--line); background: #f4f7f9; color: var(--navy); }
        .button.ghost:hover { background: #e9f0f3; }
        .status {
            display: inline-flex;
            align-items: center;
            min-height: 30px;
            border-radius: 999px;
            padding: 5px 10px;
            background: #fff5dc;
            color: #8a5a00;
            font-size: 12px;
            font-weight: 900;
            text-transform: uppercase;
        }
        .status.submitted, .status.issued, .status.received { background: #e7f7ef; color: var(--green); }
        .status.on_progress { background: #fff5dc; color: #8a5a00; }
        .notice { margin-top: 14px; padding: 12px 14px; border: 1px solid #b8dfca; border-radius: 7px; background: #eefaf3; color: var(--green); font-weight: 800; }
        .notice.error { border-color: #f0b8b4; background: #fff4f2; color: var(--red); }
        .layout { display: grid; grid-template-columns: minmax(0, 1.7fr) minmax(280px, .8fr); gap: 16px; margin-top: 16px; align-items: start; }
        .main-column, .side-column { display: grid; gap: 16px; }
        .panel { padding: 20px; }
        .section-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 16px; }
        .meta-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .meta {
            min-width: 0;
            padding: 13px 14px;
            border: 1px solid var(--line);
            border-radius: 6px;
            background: #f9fbfc;
        }
        .meta span { display: block; margin-bottom: 3px; color: var(--muted); font-size: 11px; font-weight: 800; text-transform: uppercase; }
        .meta strong { display: block; overflow-wrap: anywhere; }
        .table-wrap { overflow-x: auto; border: 1px solid var(--line); border-radius: 7px; }
        table { width: 100%; min-width: 760px; border-collapse: collapse; }
        th, td { padding: 12px; border-bottom: 1px solid var(--line); text-align: left; vertical-align: top; }
        th { background: #edf4f6; color: #435a6d; font-size: 11px; text-transform: uppercase; white-space: nowrap; }
        tbody tr:last-child td { border-bottom: 0; }
        .item-name { font-weight: 800; }
        .item-note { margin-top: 3px; color: var(--muted); font-size: 12px; }
        .number { text-align: right; white-space: nowrap; }
        .total-row { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-top: 16px; padding-top: 16px; border-top: 2px solid var(--navy); }
        .total-row strong { font-size: 22px; }
        .vendor-name { margin-top: 8px; font-size: 18px; font-weight: 900; }
        .detail-list { display: grid; gap: 11px; margin-top: 14px; }
        .detail-list div { padding-bottom: 10px; border-bottom: 1px solid var(--line); }
        .detail-list div:last-child { padding-bottom: 0; border-bottom: 0; }
        .detail-list span { display: block; color: var(--muted); font-size: 11px; font-weight: 800; text-transform: uppercase; }
        .detail-list strong { display: block; margin-top: 2px; overflow-wrap: anywhere; }
        .review-box { border-left: 4px solid var(--amber); }
        .review-box ul { margin: 13px 0 0; padding-left: 20px; color: #405668; }
        .review-box li + li { margin-top: 6px; }
        .issue-panel { position: sticky; top: 16px; }
        .issue-panel form { margin-top: 16px; }
        .issue-panel button { width: 100%; min-height: 46px; }
        .locked { margin-top: 14px; padding: 12px; border-radius: 6px; background: #edf4f6; color: #4d6273; font-weight: 700; }
        .signature-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-top: 16px; }
        .signature { min-height: 132px; padding: 13px; border: 1px solid var(--line); border-radius: 7px; background: #fbfdfe; display: flex; flex-direction: column; justify-content: space-between; }
        .signature span { color: var(--muted); font-size: 11px; font-weight: 900; text-transform: uppercase; }
        .signature strong { display: block; margin-top: 3px; }
        .signature small { color: var(--muted); }
        .signature-line { border-top: 1px solid #708494; padding-top: 7px; text-align: center; font-weight: 900; }
        .rejection-box { margin-top:14px; padding:13px 14px; border:1px solid #f0b8b4; border-radius:7px; background:#fff4f2; color:var(--red); }
        .rejection-box strong,.rejection-box span { display:block; }
        .rejection-box span { margin-top:4px; }
        .reject-form { display:flex; align-items:center; gap:8px; flex:1 1 360px; }
        .reject-form input { flex:1; min-width:180px; min-height:40px; border:1px solid #e5aaa5; border-radius:6px; padding:9px 11px; font:inherit; }
        .reject-form button { background:#fff4f2; border-color:#e5aaa5; color:var(--red); }
        .reject-form button:hover { background:#ffe4df; }
        .reject-form .decline-button { background:var(--red); border-color:var(--red); color:#fff; }
        .reject-form .decline-button:hover { background:#a92c25; }
        .invoice-list { display:grid; gap:12px; }
        .invoice-row { display:grid; grid-template-columns:minmax(0,1fr) minmax(280px,.9fr); gap:14px; align-items:end; padding:14px; border:1px solid var(--line); border-radius:7px; background:#f9fbfc; }
        .invoice-form { display:flex; gap:8px; align-items:end; }
        .invoice-form label { flex:1; color:var(--muted); font-size:11px; font-weight:800; text-transform:uppercase; }
        .invoice-form input { display:block; width:100%; min-height:40px; margin-top:5px; border:1px solid var(--line); border-radius:6px; padding:9px 11px; color:var(--ink); background:#fff; font:inherit; }

        @media (max-width: 860px) {
            .layout { grid-template-columns: 1fr; }
            .issue-panel { position: static; }
        }
        @media (max-width: 640px) {
            .page { width: min(100% - 20px, 1180px); padding-top: 10px; }
            .topbar { align-items: flex-start; flex-direction: column; padding: 17px; }
            h1 { font-size: 22px; }
            .top-actions, .top-actions .button { width: 100%; }
            .meta-grid { grid-template-columns: 1fr; }
            .signature-grid { grid-template-columns: 1fr; }
            .panel { padding: 16px; }
            .total-row { align-items: flex-start; flex-direction: column; }
            .reject-form { width:100%; align-items:stretch; flex-direction:column; }
            .reject-form input,.reject-form button { width:100%; }
            .invoice-row { grid-template-columns:1fr; }
            .invoice-form { align-items:stretch; flex-direction:column; }
        }

        /* Review polish */
        body { background: linear-gradient(180deg, #fbfcfd 0, var(--bg) 250px); }
        .page { display: grid; gap: 16px; }
        .topbar,
        .panel,
        .meta,
        .signature {
            border-color: #d7e2e8;
        }
        .topbar > div { min-width: 0; }
        .topbar h1,
        .topbar p { overflow-wrap: anywhere; }
        .panel {
            position: relative;
            overflow: visible;
        }
        .panel::before {
            content: "";
            position: absolute;
            inset: 0 0 auto;
            height: 3px;
            background: linear-gradient(90deg, var(--teal), var(--amber));
            border-radius: 8px 8px 0 0;
        }
        .meta,
        .signature {
            background: #fbfcfd;
        }
        .table-wrap {
            overflow-x: auto;
        }
        tbody tr:hover {
            background: #f2fbfb;
        }
        button,
        .button,
        .status {
            white-space: nowrap;
        }
        @media (max-width: 900px) {
            .page { width: min(100% - 24px, 1180px); padding-top: 14px; }
            .panel { padding: 16px; }
        }
    </style>
</head>
<body>
@php
    $returnContext = request()->query('from');
    $returnCompanyId = request()->integer('company_id') ?: null;
    $defaultReturnContext = $purchaseOrder->status === 'received'
        ? 'history'
        : ($purchaseOrder->status === 'draft' && $purchaseOrder->proposed_by && (! $purchaseOrder->checked_by || ! $purchaseOrder->approved_by) ? 'approval' : 'active');
    $returnContext = in_array($returnContext, ['active', 'approval', 'history', 'approvals', 'dashboard', 'reports'], true)
        ? $returnContext
        : $defaultReturnContext;
    $returnUrl = match ($returnContext) {
        'approvals' => route('approvals', array_filter(['company_id' => $returnCompanyId])),
        'dashboard' => route('dashboard', array_filter(['company_id' => $returnCompanyId])),
        'reports' => route('reports'),
        default => route('purchase-orders', array_filter([
            'tab' => $returnContext === 'active' ? null : $returnContext,
            'company_id' => $returnCompanyId,
        ])),
    };
    $contextQuery = array_filter(['from' => $returnContext, 'company_id' => $returnCompanyId]);
@endphp
<main class="page">
    <header class="topbar">
        <div>
            <p class="eyebrow">Pemeriksaan sebelum diterbitkan</p>
            <h1>Review Purchase Order {{ $purchaseOrder->number }}</h1>
            <p>Asal Order Request: <strong>{{ $purchaseOrder->purchaseRequest->number }}</strong> - {{ $purchaseOrder->purchaseRequest->camp->name }}.</p>
        </div>
        <div class="top-actions">
            <span class="status {{ $purchaseOrder->status }}">{{ $purchaseOrderDisplayStatus }}</span>
            @if ($purchaseOrder->status === 'draft' && ! $purchaseOrder->proposed_by && auth()->user()?->hasPermission('purchase_orders.manage'))
                <a class="button ghost" href="{{ route('purchase-orders.edit', array_merge(['purchaseOrder' => $purchaseOrder], $contextQuery)) }}">Edit Draft</a>
            @endif
            <a class="button ghost" href="{{ $returnUrl }}">Kembali</a>
            <a class="button ghost" href="{{ route('purchase-orders.print', $purchaseOrder) }}" target="_blank">Pratinjau Cetak</a>
        </div>
    </header>

    @if (session('success'))
        <div class="notice">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="notice error">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="notice error">{{ $errors->first() }}</div>
    @endif

    <div class="layout">
        <div class="main-column">
            <section class="panel">
                <div class="section-heading">
                    <h2>Informasi PO</h2>
                    <strong>{{ $purchaseOrder->number }}</strong>
                </div>
                <div class="meta-grid">
                    <div class="meta"><span>Nomor Order Request</span><strong>{{ $purchaseOrder->purchaseRequest->number }}</strong></div>
                    <div class="meta"><span>Tanggal PO</span><strong>{{ $purchaseOrder->ordered_at->format('d M Y') }}</strong></div>
                    <div class="meta"><span>Perusahaan</span><strong>{{ $purchaseOrder->purchaseRequest->camp->company_name }}</strong></div>
                    <div class="meta"><span>Site tujuan</span><strong>{{ $purchaseOrder->purchaseRequest->camp->name }}</strong></div>
                    <div class="meta"><span>Tujuan pengiriman</span><strong>{{ $purchaseOrder->delivery_destination === 'direct_site' ? 'Langsung ke site' : 'Gudang kantor' }}</strong></div>
                    <div class="meta"><span>Pemohon</span><strong>{{ $purchaseOrder->purchaseRequest->requested_by }}</strong></div>
                    <div class="meta"><span>Estimasi tiba</span><strong>{{ $purchaseOrder->expected_at?->format('d M Y') ?: '-' }}</strong></div>
                </div>
            </section>

            @if ($purchaseOrder->delivery_destination === 'direct_site' && $purchaseOrder->directSiteDispatch?->siteReceipts->isNotEmpty())
                <section class="panel">
                    <div class="section-heading">
                        <h2>Dokumen Penerimaan Direct Site</h2>
                        <span>Faktur dapat dilengkapi Purchasing</span>
                    </div>
                    <div class="invoice-list">
                        @foreach($purchaseOrder->directSiteDispatch->siteReceipts->sortByDesc('received_at') as $siteReceipt)
                            <div class="invoice-row">
                                <div class="detail-list" style="margin-top:0">
                                    <div><span>No. Penerimaan</span><strong>{{ $siteReceipt->number }}</strong></div>
                                    <div><span>Diterima di Site</span><strong>{{ $siteReceipt->received_at?->format('d M Y') }} oleh {{ $siteReceipt->received_by }}</strong></div>
                                </div>
                                @if(auth()->user()?->hasPermission('site_receipt_invoices.manage'))
                                    <form class="invoice-form" method="post" action="{{ route('site-receipts.invoice.update', $siteReceipt) }}">
                                        @csrf
                                        @method('PUT')
                                        <label>Nomor faktur vendor (opsional)
                                            <input name="invoice_number" value="{{ old('invoice_number', $siteReceipt->invoice_number) }}" placeholder="Dapat diisi setelah faktur diterima kantor" maxlength="100">
                                        </label>
                                        <button type="submit">Simpan Faktur</button>
                                    </form>
                                @else
                                    <div class="meta"><span>Nomor Faktur Vendor</span><strong>{{ $siteReceipt->invoice_number ?: 'Belum diisi' }}</strong></div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="panel">
                <div class="section-heading">
                    <h2>Approval Purchase Order</h2>
                    <span>{{ $purchaseOrder->rejected_at && ! $purchaseOrder->proposed_by ? 'Dikembalikan untuk revisi' : (! $purchaseOrder->proposed_by ? 'Draft - belum diajukan' : ($purchaseOrder->approved_by ? 'Disetujui Direktur' : ($purchaseOrder->checked_by ? 'Menunggu Direktur' : 'Menunggu Pemeriksaan'))) }}</span>
                </div>
                <div class="signature-grid">
                    <div class="signature">
                        <div><span>Diusulkan Oleh</span><strong>{{ $purchaseOrder->proposedBy?->name ?? '-' }}</strong><small>Admin Purchasing</small></div>
                        <div class="signature-line">{{ $purchaseOrder->proposed_at?->format('d M Y H:i') ?: '-' }}</div>
                    </div>
                    <div class="signature">
                        <div><span>Diperiksa Oleh</span><strong>{{ $purchaseOrder->checkedBy?->name ?? '-' }}</strong><small>Kepala Divisi Purchasing</small></div>
                        <div class="signature-line">{{ $purchaseOrder->checked_at?->format('d M Y H:i') ?: 'Menunggu' }}</div>
                    </div>
                    <div class="signature">
                        <div><span>Disetujui Oleh</span><strong>{{ $purchaseOrder->approvedBy?->name ?? '-' }}</strong><small>Direktur</small></div>
                        <div class="signature-line">{{ $purchaseOrder->approved_at?->format('d M Y H:i') ?: 'Menunggu' }}</div>
                    </div>
                </div>
                @if ($purchaseOrder->rejected_at)
                    <div class="rejection-box">
                        <strong>{{ $purchaseOrder->status === 'rejected' ? 'Ditolak' : 'Dikembalikan untuk revisi' }} oleh {{ $purchaseOrder->rejectedBy?->name ?? '-' }} ({{ $purchaseOrder->rejection_stage === 'director' ? 'Direktur' : 'Kepala Divisi Purchasing' }})</strong>
                        <span>{{ $purchaseOrder->rejection_note }}</span>
                        <small>{{ $purchaseOrder->rejected_at->format('d M Y H:i') }}</small>
                    </div>
                @endif
                <div class="actions" style="margin-top:14px">
                    @if ($purchaseOrder->status === 'draft' && ! $purchaseOrder->proposed_by && auth()->user()?->hasPermission('purchase_orders.manage'))
                        <form method="post" action="{{ route('purchase-orders.propose', $purchaseOrder) }}" onsubmit="return confirm('Ajukan {{ $purchaseOrder->number }} ke proses approval? Setelah diajukan, draft tidak dapat diedit.')">@csrf<button>Ajukan Approval PO</button></form>
                    @endif
                    @if ($purchaseOrder->status === 'draft' && $purchaseOrder->proposed_by && auth()->user()?->hasPermission('purchase_orders.check') && ! $purchaseOrder->checked_by)
                        <form method="post" action="{{ route('purchase-orders.check', $purchaseOrder) }}">@csrf<button>Periksa PO</button></form>
                        <form class="reject-form" method="post" action="{{ route('purchase-orders.reject', $purchaseOrder) }}">
                            @csrf
                            <input name="rejection_note" placeholder="Alasan pengembalian atau penolakan" required maxlength="2000">
                            <button type="submit" onclick="return confirm('Kembalikan PO {{ $purchaseOrder->number }} ke Purchasing untuk direvisi?')">Kembalikan untuk Revisi</button>
                            <button class="decline-button" type="submit" formaction="{{ route('purchase-orders.decline', $purchaseOrder) }}" onclick="return confirm('Tolak PO {{ $purchaseOrder->number }} secara permanen? Proses pengadaan akan dihentikan.')">Tolak PO</button>
                        </form>
                    @endif
                    @if ($purchaseOrder->status === 'draft' && auth()->user()?->hasPermission('purchase_orders.approve') && $purchaseOrder->checked_by && ! $purchaseOrder->approved_by)
                        <form method="post" action="{{ route('purchase-orders.approve', $purchaseOrder) }}" onsubmit="return confirm('Berikan persetujuan akhir untuk PO {{ $purchaseOrder->number }}?')">@csrf<button>Setujui PO</button></form>
                        <form class="reject-form" method="post" action="{{ route('purchase-orders.reject', $purchaseOrder) }}">
                            @csrf
                            <input name="rejection_note" placeholder="Alasan pengembalian atau penolakan" required maxlength="2000">
                            <button type="submit" onclick="return confirm('Kembalikan PO {{ $purchaseOrder->number }} ke Purchasing untuk direvisi?')">Kembalikan untuk Revisi</button>
                            <button class="decline-button" type="submit" formaction="{{ route('purchase-orders.decline', $purchaseOrder) }}" onclick="return confirm('Tolak PO {{ $purchaseOrder->number }} secara permanen? Proses pengadaan akan dihentikan.')">Tolak PO</button>
                        </form>
                    @endif
                </div>
            </section>

            <section class="panel">
                <div class="section-heading">
                    <h2>Rincian Barang</h2>
                    <span>{{ $purchaseOrder->items->count() }} item</span>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>No</th>
                            <th>Unit</th>
                            <th>Barang</th>
                            <th>Qty</th>
                            @if ($canSeePrices)
                                <th class="number">Harga Satuan</th>
                                <th class="number">Total</th>
                            @endif
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($purchaseOrder->items as $item)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $item->requestItem?->unit?->code ?: '-' }}</td>
                                <td>
                                    <div class="item-name">{{ $item->inventoryItem?->name ?: $item->description }}</div>
                                    <div class="item-note">SKU {{ $item->inventoryItem?->sku ?: '-' }} - Merek {{ $item->brand_name ?: '-' }}</div>
                                    @if ($item->requestItem?->specification)
                                        <div class="item-note">{{ $item->requestItem->specification }}</div>
                                    @endif
                                </td>
                                <td>{{ number_format($item->quantity_ordered, 0, ',', '.') }} {{ $item->uom }}</td>
                                @if ($canSeePrices)
                                    <td class="number">{{ \App\Support\Currency::format($item->unit_price, $purchaseOrder->currency_code) }}</td>
                                    <td class="number">{{ \App\Support\Currency::format($item->total, $purchaseOrder->currency_code) }}</td>
                                @endif
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($canSeePrices)
                    <div class="total-row"><span>Subtotal</span><strong>{{ \App\Support\Currency::format($purchaseOrder->subtotal, $purchaseOrder->currency_code) }}</strong></div>
                    <div class="total-row"><span>Diskon ({{ number_format($purchaseOrder->discount_percent, 2, ',', '.') }}%)</span><strong>{{ \App\Support\Currency::format($purchaseOrder->discount_amount, $purchaseOrder->currency_code) }}</strong></div>
                    <div class="total-row"><span>PPN ({{ number_format($purchaseOrder->ppn_percent, 2, ',', '.') }}%)</span><strong>{{ \App\Support\Currency::format($purchaseOrder->ppn_amount, $purchaseOrder->currency_code) }}</strong></div>
                    <div class="total-row"><span>Grand Total</span><strong>{{ \App\Support\Currency::format($purchaseOrder->grand_total, $purchaseOrder->currency_code) }}</strong></div>
                    @if ($purchaseOrder->currency_code !== 'IDR')
                        @php
                            $rateSourceLabel = match ($purchaseOrder->exchange_rate_source) {
                                'frankfurter' => 'Frankfurter (mid-market)',
                                'BI' => 'Bank Indonesia',
                                'manual' => 'Manual',
                                default => 'Sistem',
                            };
                        @endphp
                        <div class="total-row"><span>Ekuivalen IDR</span><strong>≈ {{ \App\Support\Currency::format($purchaseOrder->grandTotalIdr()) }}</strong></div>
                        <div class="total-row"><span>Kurs {{ $rateSourceLabel }} {{ optional($purchaseOrder->exchange_rate_date)->format('d M Y') }}</span><strong>Rp{{ number_format($purchaseOrder->exchange_rate, 2, ',', '.') }}/{{ $purchaseOrder->currency_code }}</strong></div>
                        @if ($purchaseOrder->actual_exchange_rate)
                            <div class="total-row"><span>Kurs realisasi{{ $purchaseOrder->actual_exchange_rate_date ? ' '.$purchaseOrder->actual_exchange_rate_date->format('d M Y') : '' }}</span><strong>Rp{{ number_format($purchaseOrder->actual_exchange_rate, 2, ',', '.') }}/{{ $purchaseOrder->currency_code }}</strong></div>
                        @endif
                    @endif
                @endif
            </section>

            @if ($purchaseOrder->purchaseRequest->purpose || $purchaseOrder->notes)
                <section class="panel">
                    <h2>Catatan Pengadaan</h2>
                    @if ($purchaseOrder->purchaseRequest->purpose)
                        <p><strong>Tujuan:</strong> {{ $purchaseOrder->purchaseRequest->purpose }}</p>
                    @endif
                    @if ($purchaseOrder->notes)
                        <p><strong>Catatan PO:</strong> {{ $purchaseOrder->notes }}</p>
                    @endif
                </section>
            @endif
        </div>

        <aside class="side-column">
            <section class="panel">
                <h2>Vendor</h2>
                <div class="vendor-name">{{ $purchaseOrder->vendor->name }}</div>
                <div class="detail-list">
                    <div><span>Kontak</span><strong>{{ $purchaseOrder->vendor->contact_person ?: '-' }}</strong></div>
                    <div><span>Telepon</span><strong>{{ $purchaseOrder->vendor->phone ?: '-' }}</strong></div>
                    <div><span>Alamat</span><strong>{{ $purchaseOrder->vendor->address ?: '-' }}</strong></div>
                    <div><span>Dibuat oleh</span><strong>{{ $purchaseOrder->createdBy?->name ?: '-' }}</strong></div>
                </div>
            </section>

            <section class="panel review-box">
                <h2>Poin Pemeriksaan</h2>
                <ul>
                    <li>Vendor dan tujuan pengiriman sudah benar.</li>
                    <li>Barang, spesifikasi, unit, serta jumlah sudah sesuai PR.</li>
                    @if ($canSeePrices)
                        <li>Harga satuan dan total sudah sesuai penawaran terpilih.</li>
                    @endif
                    <li>Estimasi kedatangan dapat dipenuhi vendor.</li>
                </ul>
            </section>

            <section class="panel review-box">
                <h2>Timeline PO</h2>
                <ul>
                    @forelse (($timelineLogs ?? collect()) as $log)
                        <li>{{ $log->created_at?->format('d M Y H:i') }} - {{ $log->description }} oleh {{ $log->user?->name ?? 'System' }}</li>
                    @empty
                        <li>Belum ada timeline purchase order.</li>
                    @endforelse
                </ul>
            </section>

            <section class="panel issue-panel">
                <h2>Flow Pembelian PO</h2>
                @if ($purchaseOrder->status === 'draft' && $canIssue && $purchaseOrder->approved_by)
                    <form method="post" action="{{ route('purchase-orders.submit', $purchaseOrder) }}" onsubmit="return confirm('Launch {{ $purchaseOrder->number }} ke status on progress? PO belum bisa diterima gudang sampai benar-benar submitted.')">
                        @csrf
                        <button type="submit">Launch PO</button>
                    </form>
                @elseif ($purchaseOrder->status === 'draft' && ! $purchaseOrder->proposed_by)
                    <div class="locked">Draft belum diajukan approval.</div>
                @elseif ($purchaseOrder->status === 'draft' && ! $purchaseOrder->approved_by)
                    <div class="locked">Menunggu approval PO.</div>
                @elseif ($purchaseOrder->status === 'on_progress')
                    <div class="locked">Di-launch oleh {{ $purchaseOrder->submittedBy?->name ?: '-' }} pada {{ $purchaseOrder->submitted_at?->format('d M Y H:i') ?: '-' }}</div>
                    @if ($canIssue)
                        <form method="post" action="{{ route('purchase-orders.issue', $purchaseOrder) }}" onsubmit="return confirm('Submit final {{ $purchaseOrder->number }} ke vendor sekarang?')">
                            @csrf
                            <button type="submit">Submit PO Final</button>
                        </form>
                    @endif
                @elseif ($purchaseOrder->status !== 'draft')
                    <p>PO ini sudah submitted pada {{ $purchaseOrder->issued_at?->format('d M Y H:i') ?: '-' }}.</p>
                    @if ($purchaseOrder->delivery_destination === 'direct_site')
                        <div class="locked">Barang dikirim langsung oleh vendor ke {{ $purchaseOrder->purchaseRequest->camp->name }}.</div>
                    @endif
                    <div class="locked">Diajukan oleh {{ $purchaseOrder->issuedBy?->name ?: '-' }}</div>
                @else
                    <div class="locked">Akses purchase order issue diperlukan.</div>
                @endif
            </section>
        </aside>
    </div>
</main>
</body>
</html>
