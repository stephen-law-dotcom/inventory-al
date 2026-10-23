<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detail {{ $purchaseRequest->number }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=2">
    <style>
        :root {
            --bg: #f4f8fa;
            --panel: #ffffff;
            --ink: #102a43;
            --muted: #627789;
            --line: #d9e5ea;
            --teal: #087f78;
            --teal-dark: #066a65;
            --amber: #e7a629;
            --green: #16794b;
            --red: #b42318;
            --blue: #315bdc;
            --shadow: 0 14px 32px rgba(22, 48, 65, .09);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background: linear-gradient(135deg, #fbfdfd 0%, var(--bg) 100%);
            color: var(--ink);
            font-family: Inter, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 14px;
            line-height: 1.5;
        }
        a { color: inherit; text-decoration: none; }
        .page { width: min(1240px, calc(100% - 32px)); margin: 0 auto; padding: 24px 0 42px; }
        .topbar, .panel, .stat {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: var(--shadow);
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            align-items: flex-start;
            padding: 20px 22px;
            border-top: 4px solid var(--teal);
        }
        h1, h2, h3, p { margin: 0; }
        h1 { font-size: 28px; line-height: 1.2; font-weight: 900; }
        h2 { font-size: 17px; font-weight: 900; }
        h3 { font-size: 15px; font-weight: 900; }
        p { margin-top: 5px; color: var(--muted); }
        .eyebrow { margin-bottom: 4px; color: var(--teal); font-size: 12px; font-weight: 900; text-transform: uppercase; }
        .actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            border: 1px solid transparent;
            border-radius: 6px;
            padding: 9px 12px;
            background: var(--teal);
            color: #fff;
            font-weight: 900;
        }
        .button:hover { background: var(--teal-dark); }
        .ghost { border-color: var(--line); background: #f4f7f9; color: var(--ink); }
        .ghost:hover { background: #e9f0f3; }
        .pill {
            display: inline-flex;
            align-items: center;
            min-height: 26px;
            padding: 4px 10px;
            border-radius: 999px;
            background: #e8efef;
            color: #244047;
            font-size: 12px;
            font-weight: 900;
            white-space: nowrap;
            text-transform: capitalize;
        }
        .ok { background: #dcf7ee; color: var(--green); }
        .wait { background: #fff1d6; color: #8a5a00; }
        .bad { background: #fee7e2; color: var(--red); }
        .info { background: #e8efff; color: var(--blue); }
        .stat-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-top: 16px; }
        .stat { min-height: 104px; padding: 16px; border-left: 4px solid var(--teal); }
        .stat:nth-child(2) { border-left-color: var(--amber); }
        .stat:nth-child(3) { border-left-color: var(--green); }
        .stat:nth-child(4) { border-left-color: var(--blue); }
        .stat span { display: block; color: var(--muted); font-size: 12px; font-weight: 800; }
        .stat strong { display: block; margin-top: 8px; font-size: 23px; font-weight: 900; }
        .layout { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 16px; margin-top: 16px; align-items: start; }
        .main-column, .side-column { display: grid; gap: 16px; min-width: 0; }
        .panel { min-width: 0; padding: 18px; overflow: hidden; }
        .panel-head { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; margin-bottom: 12px; }
        .meta-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
        .meta { min-width: 0; padding: 12px; border: 1px solid var(--line); border-radius: 7px; background: #f9fbfc; }
        .meta span { display: block; margin-bottom: 3px; color: var(--muted); font-size: 11px; font-weight: 900; text-transform: uppercase; }
        .meta strong { display: block; overflow-wrap: anywhere; }
        .table-wrap { width: 100%; max-width: 100%; overflow-x: auto; border: 1px solid var(--line); border-radius: 8px; }
        table { width: 100%; min-width: 760px; border-collapse: collapse; background: #fff; font-size: 13px; }
        th, td { border-bottom: 1px solid var(--line); padding: 11px; text-align: left; vertical-align: top; }
        tbody tr:last-child td { border-bottom: 0; }
        th { background: #eef7f8; color: #4d6273; font-size: 12px; font-weight: 900; text-transform: uppercase; white-space: nowrap; }
        tbody tr:hover { background: #f7fbfc; }
        .number { text-align: right; white-space: nowrap; }
        .item-title { font-weight: 900; }
        .muted { color: var(--muted); }
        .timeline { display: grid; gap: 12px; }
        .step { padding: 13px; border: 1px solid var(--line); border-radius: 8px; background: #fbfcfd; }
        .step-head { display: flex; justify-content: space-between; gap: 10px; align-items: center; }
        .signature-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
        .signature { min-height: 158px; padding: 14px; border: 1px solid var(--line); border-radius: 8px; background: #fbfcfd; display: flex; flex-direction: column; justify-content: space-between; }
        .signature-line { border-top: 1px solid #708494; padding-top: 7px; font-weight: 900; text-align: center; }
        @media (max-width: 1100px) {
            .layout { grid-template-columns: 1fr; }
            .side-column { grid-column: auto; }
            .signature-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 680px) {
            .page { width: min(100% - 20px, 1240px); padding-top: 10px; }
            .topbar { flex-direction: column; padding: 17px; }
            .actions, .actions .button { width: 100%; }
            .stat-grid, .layout, .meta-grid, .signature-grid { grid-template-columns: 1fr; }
            h1 { font-size: 23px; }
        }

        /* Review polish */
        body { background: linear-gradient(180deg, #fbfcfd 0, var(--bg) 250px); }
        .page { display: grid; gap: 16px; }
        .topbar,
        .panel,
        .stat,
        .meta,
        .step,
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
        .stat,
        .meta,
        .step,
        .signature {
            background: #fbfcfd;
        }
        .step:hover,
        .meta:hover {
            border-color: #c7d2da;
            background: #fff;
        }
        tbody tr:hover {
            background: #f2fbfb;
        }
        button,
        .button,
        .pill {
            white-space: nowrap;
        }
        @media (max-width: 680px) {
            .page { width: min(100% - 24px, 1240px); padding-top: 14px; }
            .panel { padding: 16px; }
        }
    </style>
</head>
<body>
@php
    $statusClass = fn ($status) => in_array($status, ['approved', 'ready_for_po', 'vendor_selected', 'po_created', 'po_issued', 'goods_received'], true)
        ? 'ok'
        : (in_array($status, ['rejected'], true) ? 'bad' : (in_array($status, ['pending', 'waiting', 'submitted', 'draft', 'waiting_director', 'waiting_manager_camp', 'logistic_checked'], true) ? 'wait' : 'info'));
    $formatDate = fn ($date) => $date ? \Illuminate\Support\Carbon::parse($date)->format('d M Y H:i') : '-';
    $approvedCount = $purchaseRequest->items->filter(fn ($item) => in_array($item->status, ['ready_for_quotation', 'vendor_selected', 'po_created', 'po_issued', 'goods_received'], true))->count();
    $returnContext = request()->query('from');
    $returnCompanyId = request()->integer('company_id') ?: null;
    $returnContext = in_array($returnContext, ['active', 'history', 'dashboard', 'reports'], true)
        ? $returnContext
        : (in_array($purchaseRequest->status, ['goods_received', 'rejected'], true) ? 'history' : 'active');
    $returnUrl = match ($returnContext) {
        'dashboard' => route('dashboard', array_filter(['company_id' => $returnCompanyId])),
        'reports' => route('reports'),
        default => route('requests', array_filter([
            'tab' => $returnContext === 'active' ? null : $returnContext,
            'company_id' => $returnCompanyId,
        ])),
    };
@endphp
<main class="page">
    <header class="topbar">
        <div>
            <p class="eyebrow">Detail dokumen order request</p>
            <h1>{{ $purchaseRequest->number }}</h1>
            <p>{{ $purchaseRequest->purpose ?: 'Order request kebutuhan barang operasional.' }}</p>
        </div>
        <div class="actions">
            <span class="pill {{ $statusClass($purchaseRequest->status) }}">{{ str_replace('_', ' ', $purchaseRequest->status) }}</span>
            @if ($purchaseRequest->status === 'draft' && auth()->user()?->hasPermission('requests.manage'))
                <a class="button ghost" href="{{ route('purchase-requests.edit', $purchaseRequest) }}">Edit Draft</a>
            @endif
            <a class="button ghost" href="{{ route('purchase-requests.print', $purchaseRequest) }}" target="_blank">Cetak / PDF</a>
            <a class="button ghost" href="{{ $returnUrl }}">Kembali</a>
        </div>
    </header>

    <div class="stat-grid">
        <div class="stat"><span>Total Item</span><strong>{{ $purchaseRequest->items->count() }}</strong><p>{{ $approvedCount }} item sudah diketahui Manager Camp</p></div>
        <div class="stat"><span>Site Pengaju</span><strong>{{ $purchaseRequest->camp->name }}</strong><p>{{ $purchaseRequest->camp->company_name }}</p></div>
        <div class="stat"><span>Status Dokumen</span><strong>{{ str_replace('_', ' ', $purchaseRequest->status) }}</strong></div>
        <div class="stat"><span>Approval</span><strong>{{ $purchaseRequest->manager_camp_approval_status === 'approved' ? 'Lengkap' : 'Berjalan' }}</strong></div>
    </div>

    <div class="layout">
        <div class="main-column">
            <section class="panel">
                <div class="panel-head">
                    <div>
                        <h2>Informasi Order Request</h2>
                    </div>
                </div>
                <div class="meta-grid">
                    <div class="meta"><span>Tanggal Order Request</span><strong>{{ $purchaseRequest->requested_at->format('d M Y') }}</strong></div>
                    <div class="meta"><span>Pemohon</span><strong>{{ $purchaseRequest->requested_by }}</strong></div>
                    <div class="meta"><span>Perusahaan</span><strong>{{ $purchaseRequest->camp->company_name }}</strong></div>
                    <div class="meta"><span>Site</span><strong>{{ $purchaseRequest->camp->name }}</strong></div>
                    <div class="meta"><span>Unit Header</span><strong>{{ $purchaseRequest->unit?->code ?: '-' }} {{ $purchaseRequest->unit?->name ? '- '.$purchaseRequest->unit->name : '' }}</strong></div>
                    <div class="meta"><span>Catatan</span><strong>{{ $purchaseRequest->notes ?: '-' }}</strong></div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-head">
                    <div>
                        <h2>Detail Barang</h2>
                    </div>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>No</th>
                            <th>Unit</th>
                            <th>Barang</th>
                            <th>Qty</th>
                            <th>Status</th>
                            <th>Approval</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($purchaseRequest->items as $requestItem)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $requestItem->unit?->code ?: '-' }}</td>
                                <td>
                                    <div class="item-title">{{ $requestItem->inventoryItem->name }}</div>
                                    <div class="muted">SKU {{ $requestItem->inventoryItem->sku }} - {{ $requestItem->specification ?: 'Tanpa spesifikasi tambahan' }}</div>
                                    @if ($requestItem->rejection_note)
                                        <div class="muted">Reject: {{ $requestItem->rejection_note }}</div>
                                    @endif
                                </td>
                                <td>
                                    <strong>{{ number_format($requestItem->quantity, 0, ',', '.') }} {{ $requestItem->uom ?? $requestItem->inventoryItem->uom }}</strong>
                                    <div class="muted">Diminta camp</div>
                                    @if ($requestItem->procurement_approval_status === 'approved')
                                        <div style="margin-top:5px">
                                            <span class="pill {{ $requestItem->isPartiallyApproved() ? 'wait' : 'ok' }}">
                                                Disetujui {{ number_format($requestItem->approvedQuantity(), 0, ',', '.') }} {{ $requestItem->uom ?? $requestItem->inventoryItem->uom }}
                                            </span>
                                        </div>
                                        @if ($requestItem->quantity_adjustment_reason)
                                            <div class="muted" style="margin-top:5px">Alasan: {{ $requestItem->quantity_adjustment_reason }}</div>
                                        @endif
                                    @endif
                                </td>
                                <td><span class="pill {{ $statusClass($requestItem->status) }}">{{ str_replace('_', ' ', $requestItem->status) }}</span></td>
                                <td>
                                    <div>Logistik: <span class="pill {{ $statusClass($requestItem->logistic_approval_status) }}">{{ $requestItem->logistic_approval_status }}</span></div>
                                    <div style="margin-top:5px">Chief Mechanic: <span class="pill {{ $statusClass($requestItem->chief_mechanic_approval_status) }}">{{ $requestItem->chief_mechanic_approval_status }}</span></div>
                                    <div style="margin-top:5px">Manager Camp: <span class="pill {{ $statusClass($requestItem->manager_camp_approval_status) }}">{{ str_replace('_', ' ', $requestItem->manager_camp_approval_status) }}</span></div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel">
                <div class="panel-head">
                    <div>
                        <h2>Area Tanda Tangan Dokumen</h2>
                    </div>
                </div>
                <div class="signature-grid">
                    <div class="signature">
                        <div>
                            <strong>Dibuat Oleh</strong>
                            <p>Admin Gudang</p>
                        </div>
                        <div class="signature-line">{{ $purchaseRequest->requested_by }}</div>
                    </div>
                    <div class="signature">
                        <div>
                            <strong>Diperiksa Oleh</strong>
                            <p>Logistik - {{ ($approvalSignatures['equipment']['status'] ?? 'waiting') === 'approved' ? $formatDate($approvalSignatures['equipment']['approved_at'] ?? null) : 'Belum approve' }}</p>
                        </div>
                        <div class="signature-line">{{ $approvalSignatures['equipment']['name'] ?? 'Logistik' }}</div>
                    </div>
                    <div class="signature">
                        <div>
                            <strong>Dipesan Oleh</strong>
                            <p>Chief Mechanic - {{ ($approvalSignatures['ppic']['status'] ?? 'waiting') === 'approved' ? $formatDate($approvalSignatures['ppic']['approved_at'] ?? null) : 'Belum approve' }}</p>
                        </div>
                        <div class="signature-line">{{ $approvalSignatures['ppic']['name'] ?? 'Chief Mechanic' }}</div>
                    </div>
                    <div class="signature">
                        <div>
                            <strong>Diketahui Oleh</strong>
                            <p>Manager Camp - {{ ($approvalSignatures['director']['status'] ?? 'waiting') === 'approved' ? $formatDate($approvalSignatures['director']['approved_at'] ?? null) : 'Belum approve' }}</p>
                        </div>
                        <div class="signature-line">{{ $approvalSignatures['director']['name'] ?? 'Manager Camp' }}</div>
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-head">
                    <div>
                        <h2>Timeline Dokumen</h2>
                    </div>
                </div>
                <div class="timeline">
                    @forelse (($timelineLogs ?? collect()) as $log)
                        <div class="step">
                            <div class="step-head">
                                <h3>{{ $log->description }}</h3>
                                <span class="pill info">{{ $log->event }}</span>
                            </div>
                            <p>{{ $log->user?->name ?? 'System' }} - {{ $formatDate($log->created_at) }}</p>
                        </div>
                    @empty
                        <div class="step">
                            <p>Belum ada timeline aktivitas untuk dokumen ini.</p>
                        </div>
                    @endforelse
                </div>
            </section>
        </div>

        <aside class="side-column">
            <section class="panel">
                <div class="panel-head"><h2>Alur Approval</h2></div>
                <div class="timeline">
                    @foreach ($approvalSignatures as $signature)
                        <div class="step">
                            <div class="step-head">
                                <h3>{{ $signature['label'] }}</h3>
                                <span class="pill {{ $statusClass($signature['status']) }}">{{ str_replace('_', ' ', $signature['status']) }}</span>
                            </div>
                            <p>{{ $signature['name'] ?: 'Nama approver belum terekam' }}</p>
                            <p>{{ $formatDate($signature['approved_at']) }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

        </aside>
    </div>
</main>
</body>
</html>
