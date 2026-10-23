<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Review Approval {{ $purchaseRequest->number }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=2">
    <style>
        :root {
            --bg: #f3f7f8;
            --panel: #ffffff;
            --ink: #102a43;
            --muted: #627789;
            --line: #d9e5ea;
            --teal: #087f78;
            --teal-dark: #06645f;
            --amber: #e7a629;
            --green: #16794b;
            --red: #b42318;
            --shadow: 0 12px 30px rgba(22, 48, 65, .09);
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background: linear-gradient(135deg, #f8fbfc 0%, var(--bg) 100%);
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
        .eyebrow { margin: 0 0 4px; color: var(--teal); font-size: 12px; font-weight: 900; text-transform: uppercase; }
        h1, h2, h3, p { margin: 0; }
        h1 { font-size: 28px; line-height: 1.2; font-weight: 900; }
        h2 { font-size: 17px; font-weight: 900; }
        p { margin-top: 5px; color: var(--muted); }
        .actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        .button, button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            border: 1px solid transparent;
            border-radius: 6px;
            padding: 9px 12px;
            background: var(--teal);
            color: #fff;
            font: inherit;
            font-weight: 900;
            cursor: pointer;
        }
        .button:hover, button:hover { background: var(--teal-dark); }
        .ghost { border-color: var(--line); background: #f4f7f9; color: var(--ink); }
        .ghost:hover { background: #e9f0f3; }
        .danger { background: #fff7f6; color: var(--red); border-color: #f4aaa5; }
        .danger:hover { background: #fee7e2; }
        .stat-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-top: 16px; }
        .stat { min-height: 104px; padding: 16px; border-left: 4px solid var(--teal); }
        .stat:nth-child(2) { border-left-color: var(--amber); }
        .stat:nth-child(3) { border-left-color: var(--green); }
        .stat:nth-child(4) { border-left-color: var(--red); }
        .stat span { display: block; color: var(--muted); font-size: 12px; font-weight: 800; }
        .stat strong { display: block; margin-top: 8px; font-size: 24px; font-weight: 900; }
        .panel { margin-top: 16px; padding: 18px; overflow: visible; }
        .panel-head { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; margin-bottom: 12px; }
        .pill {
            display: inline-flex;
            align-items: center;
            min-height: 24px;
            padding: 4px 9px;
            border-radius: 999px;
            background: #e8efef;
            color: #244047;
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }
        .ok { background: #dcf7ee; color: var(--green); }
        .wait { background: #fff1d6; color: #8a5a00; }
        .bad { background: #fee7e2; color: var(--red); }
        .muted { color: var(--muted); }
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border: 1px solid var(--line);
            border-radius: 8px;
            overflow: hidden;
            background: #fff;
            font-size: 13px;
        }
        th, td { border-bottom: 1px solid var(--line); padding: 10px; text-align: left; vertical-align: top; }
        tr:last-child td { border-bottom: 0; }
        th { background: #eef7f8; color: #4d6273; font-size: 12px; font-weight: 900; text-transform: uppercase; }
        tbody tr:hover { background: #f7fbfc; }
        input[type="checkbox"] { width: 18px; height: 18px; accent-color: var(--teal); }
        input[type="text"] {
            min-height: 36px;
            border: 1px solid var(--line);
            border-radius: 6px;
            padding: 8px 9px;
            font: inherit;
        }
        .notice { margin-top: 14px; padding: 12px 14px; border-radius: 8px; border: 1px solid var(--line); background: #fff; box-shadow: var(--shadow); font-weight: 800; }
        .success { border-color: #9bd8c2; color: var(--green); background: #f0fbf6; }
        .error { border-color: #f4aaa5; color: var(--red); background: #fff6f4; }
        .empty { padding: 18px; border: 1px dashed var(--line); border-radius: 8px; color: var(--muted); background: #fbfdfe; text-align: center; font-weight: 800; }
        .signature-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
        .signature { min-height: 142px; padding: 14px; border: 1px solid var(--line); border-radius: 8px; background: #fbfcfd; display: flex; flex-direction: column; justify-content: space-between; }
        .signature strong { display: block; color: var(--ink); font-weight: 900; }
        .signature-line { border-top: 1px solid #708494; padding-top: 7px; font-weight: 900; text-align: center; }
        @media (max-width: 900px) {
            .topbar, .panel-head { flex-direction: column; }
            .stat-grid, .signature-grid { grid-template-columns: 1fr; }
            table { display: block; overflow-x: auto; }
            th, td { min-width: 135px; }
        }

        /* Review polish */
        body { background: linear-gradient(180deg, #fbfcfd 0, var(--bg) 250px); }
        .page { display: grid; gap: 16px; }
        .topbar,
        .panel,
        .stat,
        .signature {
            border-color: #d7e2e8;
        }
        .topbar > div { min-width: 0; }
        .topbar h1,
        .topbar p { overflow-wrap: anywhere; }
        .panel,
        .stat {
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
        .signature {
            background: #fbfcfd;
        }
        table {
            overflow: hidden;
        }
        tbody tr:hover {
            background: #f2fbfb;
        }
        button,
        .button,
        .pill {
            white-space: nowrap;
        }
        @media (max-width: 900px) {
            .page { width: min(100% - 24px, 1240px); padding-top: 14px; }
            .panel { padding: 16px; }
        }
    </style>
</head>
<body>
@php
    $approvableItems = $purchaseRequest->items->filter(function ($requestItem) use ($currentUser) {
        return ($currentUser->canApproveLogistic() && $requestItem->status === 'submitted' && $requestItem->logistic_approval_status === 'pending')
            || ($currentUser->canApproveChiefMechanic() && $requestItem->status === 'logistic_checked' && $requestItem->chief_mechanic_approval_status === 'pending')
            || ($currentUser->canApproveManagerCamp() && $requestItem->status === 'waiting_manager_camp' && $requestItem->manager_camp_approval_status === 'pending');
    });
    $returnContext = request()->query('from');
    $returnCompanyId = request()->integer('company_id') ?: null;
    $returnContext = in_array($returnContext, ['approval', 'approvals', 'dashboard'], true)
        ? $returnContext
        : 'approvals';
    $returnUrl = match ($returnContext) {
        'approval' => route('requests', array_filter([
            'tab' => 'approval',
            'company_id' => $returnCompanyId,
        ])),
        'dashboard' => route('dashboard', array_filter(['company_id' => $returnCompanyId])),
        default => route('approvals', array_filter(['company_id' => $returnCompanyId])),
    };
@endphp
<main class="page">
    <header class="topbar">
        <div>
            <p class="eyebrow">Review Approval Order Request</p>
            <h1>{{ $purchaseRequest->number }} - {{ $purchaseRequest->camp->name }}</h1>
            <p>{{ $purchaseRequest->camp->company_name }} - {{ $purchaseRequest->requested_at->format('d M Y') }} oleh {{ $purchaseRequest->requested_by }}</p>
            <p>{{ $purchaseRequest->purpose }}</p>
        </div>
        <div class="actions">
            <a class="button ghost" href="{{ route('purchase-requests.print', $purchaseRequest) }}" target="_blank">Cetak / PDF</a>
            <a class="button ghost" href="{{ $returnUrl }}">Kembali</a>
            @if ($approvableItems->isNotEmpty())
                <button form="bulk-approval-form">Approve Checklist</button>
            @endif
        </div>
    </header>

    @if (session('success'))
        <div class="notice success">{{ session('success') }}</div>
    @endif
    @if (session('error') || $errors->any())
        <div class="notice error">{{ session('error') ?: $errors->first() }}</div>
    @endif

    <section class="stat-grid">
        <div class="stat"><span>Total item</span><strong>{{ $purchaseRequest->items->count() }}</strong></div>
        <div class="stat"><span>Siap approval Anda</span><strong>{{ $approvableItems->count() }}</strong></div>
        <div class="stat"><span>Sudah lolos</span><strong>{{ $purchaseRequest->items->whereIn('status', ['ready_for_quotation', 'vendor_selected', 'po_created', 'po_issued'])->count() }}</strong></div>
        <div class="stat"><span>Rejected</span><strong>{{ $purchaseRequest->items->where('status', 'rejected')->count() }}</strong></div>
    </section>

    <section class="panel">
        <div class="panel-head">
            <div>
                <h2>Detail Barang Approval</h2>
            </div>
            <div class="actions">
                <span class="pill">{{ $purchaseRequest->status }}</span>
                <span class="pill {{ $purchaseRequest->logistic_approval_status === 'approved' ? 'ok' : 'wait' }}">Logistik {{ $purchaseRequest->logistic_approval_status }}</span>
                <span class="pill {{ $purchaseRequest->chief_mechanic_approval_status === 'approved' ? 'ok' : 'wait' }}">Chief Mechanic {{ $purchaseRequest->chief_mechanic_approval_status }}</span>
                <span class="pill {{ $purchaseRequest->manager_camp_approval_status === 'approved' ? 'ok' : 'wait' }}">Manager Camp {{ $purchaseRequest->manager_camp_approval_status }}</span>
            </div>
        </div>

        <form id="bulk-approval-form" method="post" action="{{ route('approvals.bulk-approve', $purchaseRequest) }}" data-confirm="Setujui semua item Order Request yang dipilih pada tahap Anda sekarang?">
            @csrf
        </form>

        <table>
            <thead>
                <tr>
                    <th><input type="checkbox" data-check-all aria-label="Pilih semua item siap approval"></th>
                    <th>Unit</th>
                    <th>Barang</th>
                    <th>Qty</th>
                    <th>Status Detail</th>
                    <th>Approval</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($purchaseRequest->items as $requestItem)
                @php
                    $canApproveLogistic = $currentUser->canApproveLogistic() && $requestItem->status === 'submitted' && $requestItem->logistic_approval_status === 'pending';
                    $canApproveChiefMechanic = $currentUser->canApproveChiefMechanic() && $requestItem->status === 'logistic_checked' && $requestItem->chief_mechanic_approval_status === 'pending';
                    $canApproveManagerCamp = $currentUser->canApproveManagerCamp() && $requestItem->status === 'waiting_manager_camp' && $requestItem->manager_camp_approval_status === 'pending';
                    $canApprove = $canApproveLogistic || $canApproveChiefMechanic || $canApproveManagerCamp;
                @endphp
                <tr>
                    <td>
                        @if ($canApprove)
                            <input form="bulk-approval-form" type="checkbox" name="request_item_ids[]" value="{{ $requestItem->id }}" data-approval-check aria-label="Pilih {{ $requestItem->inventoryItem->name }}">
                        @else
                            <span class="muted">-</span>
                        @endif
                    </td>
                    <td>{{ $requestItem->unit?->code }}<br><span class="muted">{{ $requestItem->unit?->name }}</span></td>
                    <td>
                        {{ $requestItem->inventoryItem->name }}<br>
                        <span class="muted">SKU {{ $requestItem->inventoryItem->sku }}{{ $requestItem->specification ? ' - '.$requestItem->specification : '' }}</span>
                    </td>
                    <td>
                        <strong>{{ $requestItem->quantity }} {{ $requestItem->uom ?? $requestItem->inventoryItem->uom }}</strong>
                        <div class="muted">Diminta camp</div>
                        @if ($requestItem->procurement_approval_status === 'pending_director' && $requestItem->equipment_approved_quantity)
                            <div class="muted">Rekomendasi Peralatan: {{ $requestItem->equipmentApprovedQuantity() }} {{ $requestItem->uom ?? $requestItem->inventoryItem->uom }}</div>
                        @elseif ($requestItem->procurement_approval_status === 'approved')
                            <div class="muted">Final: {{ $requestItem->approvedQuantity() }} {{ $requestItem->uom ?? $requestItem->inventoryItem->uom }}</div>
                        @endif
                    </td>
                    <td>
                        <span class="pill {{ $requestItem->status === 'rejected' ? 'bad' : (in_array($requestItem->status, ['ready_for_quotation', 'vendor_selected'], true) ? 'ok' : 'wait') }}">{{ $requestItem->status }}</span>
                        @if ($requestItem->rejection_note)
                            <div class="muted">{{ $requestItem->rejection_note }}</div>
                        @endif
                    </td>
                    <td>
                        <div class="actions">
                            <span class="pill {{ $requestItem->logistic_approval_status === 'approved' ? 'ok' : ($requestItem->logistic_approval_status === 'rejected' ? 'bad' : 'wait') }}">Logistik {{ $requestItem->logistic_approval_status }}</span>
                            <span class="pill {{ $requestItem->chief_mechanic_approval_status === 'approved' ? 'ok' : ($requestItem->chief_mechanic_approval_status === 'rejected' ? 'bad' : 'wait') }}">Chief Mechanic {{ $requestItem->chief_mechanic_approval_status }}</span>
                            <span class="pill {{ $requestItem->manager_camp_approval_status === 'approved' ? 'ok' : ($requestItem->manager_camp_approval_status === 'rejected' ? 'bad' : 'wait') }}">Manager Camp {{ $requestItem->manager_camp_approval_status }}</span>
                        </div>
                    </td>
                    <td>
                        @if ($canApproveLogistic)
                            <div class="actions">
                                <form method="post" action="{{ route('request-items.approve-equipment', $requestItem) }}" data-confirm="Setujui {{ $requestItem->inventoryItem->name }} pada tahap Logistik?">@csrf<button>Approve</button></form>
                                <form method="post" action="{{ route('request-items.reject-equipment', $requestItem) }}" data-confirm="Tolak {{ $requestItem->inventoryItem->name }} pada tahap Logistik?">@csrf<input type="text" name="rejection_note" placeholder="Catatan reject" required><button class="danger">Reject</button></form>
                            </div>
                        @elseif ($canApproveChiefMechanic)
                            <div class="actions">
                                <form method="post" action="{{ route('request-items.approve-ppic', $requestItem) }}" data-confirm="Setujui {{ $requestItem->inventoryItem->name }} pada tahap Chief Mechanic?">@csrf<button>Approve</button></form>
                                <form method="post" action="{{ route('request-items.reject-ppic', $requestItem) }}" data-confirm="Tolak {{ $requestItem->inventoryItem->name }} pada tahap Chief Mechanic?">@csrf<input type="text" name="rejection_note" placeholder="Catatan reject" required><button class="danger">Reject</button></form>
                            </div>
                        @elseif ($canApproveManagerCamp)
                            <div class="actions">
                                <form method="post" action="{{ route('request-items.approve-manager-camp', $requestItem) }}" data-confirm="Berikan persetujuan akhir Manager Camp untuk {{ $requestItem->inventoryItem->name }}?">@csrf<button>Approve</button></form>
                                <form method="post" action="{{ route('request-items.reject-manager-camp', $requestItem) }}" data-confirm="Tolak {{ $requestItem->inventoryItem->name }} pada tahap Manager Camp?">@csrf<input type="text" name="rejection_note" placeholder="Catatan reject" required><button class="danger">Reject</button></form>
                            </div>
                        @else
                            <span class="muted">Tidak ada aksi.</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7">Tidak ada detail barang.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    @php
        $formatDate = fn ($date) => $date ? \Illuminate\Support\Carbon::parse($date)->format('d M Y H:i') : '-';
    @endphp
    <section class="panel">
        <div class="panel-head">
            <div>
                <h2>Tanda Tangan Order Request</h2>
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
</main>
@include('inventory.components.confirm-submit')
<script>
    document.querySelector('[data-check-all]')?.addEventListener('change', function () {
        document.querySelectorAll('[data-approval-check]').forEach((checkbox) => {
            checkbox.checked = this.checked;
        });
    });
</script>
</body>
</html>
