<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detail Harga Vendor {{ $requestItem->inventoryItem->sku }}</title>
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
        h3 { font-size: 14px; font-weight: 900; }
        p { margin-top: 5px; color: var(--muted); }
        .actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        [data-approval-decisions] form,
        [data-reject-form] { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        [data-reject-form] input { width: min(320px, 100%); }
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
        .stat-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-top: 16px; }
        .stat { min-height: 104px; padding: 16px; border-left: 4px solid var(--teal); }
        .stat:nth-child(2) { border-left-color: var(--amber); }
        .stat:nth-child(3) { border-left-color: var(--green); }
        .stat:nth-child(4) { border-left-color: var(--red); }
        .stat span { display: block; color: var(--muted); font-size: 12px; font-weight: 800; }
        .stat strong { display: block; margin-top: 8px; font-size: 23px; font-weight: 900; }
        .grid { display: grid; grid-template-columns: minmax(0, 1fr); gap: 14px; margin-top: 16px; }
        .panel { padding: 18px; overflow: visible; }
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
        .price { font-variant-numeric: tabular-nums; white-space: nowrap; }
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
        form { display: grid; gap: 12px; }
        .vendor-row {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            align-items: start;
            padding: 14px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fbfdfe;
        }
        .vendor-row > button { width: 100%; align-self: end; }
        .vendor-notes { grid-column: span 2; }
        .rate-meta {
            grid-column: 1 / -1;
            min-height: 42px;
            display: flex;
            align-items: center;
            padding: 10px 12px;
            border: 1px solid #b9dcda;
            border-left: 4px solid var(--teal);
            border-radius: 6px;
            background: #eef8f7;
            color: #315c5a;
            font-size: 12px;
            font-weight: 800;
            line-height: 1.45;
        }
        .rate-warning { border-color: #efd090; border-left-color: var(--amber); background: #fff8e9; color: #7a5200; }
        .original-price { display: block; font-weight: 900; }
        .idr-equivalent { display: block; color: var(--muted); font-size: 12px; margin-top: 2px; }
        label { display: grid; gap: 6px; color: #445666; font-size: 12px; font-weight: 800; }
        input, select {
            min-height: 38px;
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 6px;
            padding: 8px 9px;
            font: inherit;
            background: #fff;
            color: var(--ink);
        }
        .readonly-value {
            display: flex;
            align-items: center;
            min-height: 38px;
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 6px;
            padding: 8px 9px;
            background: #eef3f5;
            color: #34495a;
            font-weight: 800;
        }
        input:focus, select:focus { outline: 0; border-color: var(--teal); box-shadow: 0 0 0 3px rgba(8,127,120,.12); }
        input[readonly] { background: #f1f5f7; color: #607383; }
        button:focus-visible, .button:focus-visible { outline: 3px solid rgba(8,127,120,.2); outline-offset: 2px; }
        .notice { margin-top: 14px; padding: 12px 14px; border-radius: 8px; border: 1px solid var(--line); background: #fff; box-shadow: var(--shadow); font-weight: 800; }
        .success { border-color: #9bd8c2; color: var(--green); background: #f0fbf6; }
        .error { border-color: #f4aaa5; color: var(--red); background: #fff6f4; }
        .empty { padding: 18px; border: 1px dashed var(--line); border-radius: 8px; color: var(--muted); background: #fbfdfe; text-align: center; font-weight: 800; }
        .flow { display: grid; gap: 9px; }
        .flow div { padding: 10px; border: 1px solid var(--line); border-radius: 8px; background: #fbfdfe; }
        .signature-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-top: 14px; }
        .signature { min-height: 134px; padding: 14px; border: 1px solid var(--line); border-radius: 8px; background: #fbfdfe; display: flex; flex-direction: column; justify-content: space-between; }
        .signature strong { display: block; color: var(--ink); font-weight: 900; }
        .signature-line { border-top: 1px solid #708494; padding-top: 7px; font-weight: 900; text-align: center; }
        .workflow-overview { display: grid; gap: 12px; padding: 18px; }
        .workflow-steps { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 8px; }
        .workflow-step { min-height: 58px; padding: 10px 12px; border: 1px solid var(--line); border-radius: 6px; background: #f7fafb; color: var(--muted); font-size: 12px; font-weight: 900; }
        .workflow-step::before { content: ""; display: block; width: 9px; height: 9px; margin-bottom: 7px; border-radius: 50%; background: #aab9c1; }
        .workflow-step.is-done { border-color: #9bd8c2; background: #f0fbf6; color: var(--green); }
        .workflow-step.is-done::before { background: var(--green); }
        .workflow-step.is-active { border-color: #e9c36d; background: #fff8e9; color: #7a5200; }
        .workflow-step.is-active::before { background: var(--amber); }
        .summary-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); border: 1px solid var(--line); border-radius: 6px; overflow: hidden; }
        .summary-item { min-width: 0; padding: 12px 14px; background: #fff; border-right: 1px solid var(--line); }
        .summary-item:last-child { border-right: 0; }
        .summary-item span { display: block; color: var(--muted); font-size: 11px; font-weight: 800; text-transform: uppercase; }
        .summary-item strong { display: block; margin-top: 4px; overflow-wrap: anywhere; font-size: 16px; font-weight: 900; }
        .table-scroll { width: 100%; overflow-x: auto; border-radius: 8px; }
        .table-scroll table { min-width: 980px; }
        .allocation-control { display: grid; grid-template-columns: minmax(76px, 100px) auto; gap: 7px; align-items: center; min-width: 150px; }
        .allocation-control input { font-weight: 900; }
        .allocation-control span { color: var(--muted); font-size: 12px; }
        .allocation-summary { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); margin-top: 12px; border: 1px solid var(--line); border-radius: 8px; overflow: hidden; background: #f8fbfc; }
        .allocation-summary > div { min-width: 0; padding: 12px 14px; border-right: 1px solid var(--line); }
        .allocation-summary > div:last-child { border-right: 0; }
        .allocation-summary span { display: block; color: var(--muted); font-size: 11px; font-weight: 800; text-transform: uppercase; }
        .allocation-summary strong { display: block; margin-top: 4px; overflow-wrap: anywhere; font-size: 16px; font-weight: 900; }
        .allocation-message { grid-column: 1 / -1; border-top: 1px solid var(--line); border-right: 0 !important; color: #7a5200; font-weight: 800; }
        .allocation-message.is-complete { color: var(--green); }
        details.timeline-panel { padding: 0; }
        .timeline-summary { display: flex; justify-content: space-between; gap: 12px; align-items: center; padding: 18px; cursor: pointer; list-style: none; }
        .timeline-summary::-webkit-details-marker { display: none; }
        .timeline-summary::after { content: "+"; color: var(--teal); font-size: 22px; font-weight: 900; }
        details[open] .timeline-summary::after { content: "-"; }
        .timeline-content { padding: 0 18px 18px; }
        .is-hidden { display: none !important; }
        @media (max-width: 1220px) {
            .vendor-row { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .vendor-action { grid-column: span 2; }
        }
        @media (max-width: 1050px) {
            .grid, .stat-grid, .signature-grid { grid-template-columns: 1fr; }
            .workflow-steps { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .summary-grid, .allocation-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .summary-item:nth-child(2) { border-right: 0; }
            .summary-item:nth-child(-n+2) { border-bottom: 1px solid var(--line); }
            .allocation-summary > div:nth-child(2) { border-right: 0; }
            .allocation-summary > div:nth-child(-n+2) { border-bottom: 1px solid var(--line); }
        }
        @media (max-width: 680px) {
            .topbar, .panel-head { flex-direction: column; }
            .vendor-row { grid-template-columns: 1fr; }
            .vendor-notes { grid-column: auto; }
            .vendor-action { grid-column: auto; }
            .workflow-steps { grid-template-columns: 1fr; }
            .summary-grid, .allocation-summary { grid-template-columns: 1fr; }
            .summary-item,
            .allocation-summary > div { border-right: 0; border-bottom: 1px solid var(--line); }
            .summary-item:last-child,
            .allocation-summary > div:last-child { border-bottom: 0; }
            .timeline-summary { align-items: flex-start; }
        }

        /* Review polish */
        body { background: linear-gradient(180deg, #fbfcfd 0, var(--bg) 250px); }
        .page { display: grid; gap: 16px; }
        .topbar,
        .panel,
        .stat,
        .flow div,
        .signature,
        .vendor-row {
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
        .flow div,
        .signature,
        .vendor-row {
            background: #fbfcfd;
        }
        .vendor-row:hover,
        .flow div:hover {
            border-color: #c7d2da;
            background: #fff;
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
        @media (max-width: 680px) {
            .page { width: min(100% - 24px, 1240px); padding-top: 14px; }
            .panel { padding: 16px; }
        }
    </style>
</head>
<body>
@php
    $purchaseRequest = $requestItem->purchaseRequest;
    $rankedQuotations = $requestItem->quotations
        ->sortBy(fn ($quotation) => sprintf('%020.4f:%010d:%010d', $quotation->unitPriceIdr(), 999999999 - $quotation->available_quantity, $quotation->lead_time_days))
        ->values();
    $recommendedQuotation = $rankedQuotations->first(fn ($quotation) => $quotation->available_quantity >= $requestItem->quantity);
    $selectedQuotations = $requestItem->selectedQuotations;
    $selectedQuotation = $requestItem->selectedQuotation;
    $selectedQuantity = (int) $selectedQuotations->sum('selected_quantity');
    $selectedTotal = $selectedQuotations->sum(fn ($quotation) => $quotation->totalIdr());
    $selectedVendorNames = $selectedQuotations->pluck('vendor.name')->filter()->unique()->values()->implode(', ');
    $procurementStatus = $requestItem->procurement_approval_status ?? 'not_required';
    $procurementStatusLabel = match ($procurementStatus) {
        'draft' => 'Draft',
        'pending_equipment' => 'Menunggu Peralatan',
        'pending_director' => 'Menunggu Direktur',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        default => 'Belum diajukan',
    };
    $orderRequestApprovalCompleted = collect([
        $requestItem->logistic_approval_status,
        $requestItem->chief_mechanic_approval_status,
        $requestItem->manager_camp_approval_status,
        $requestItem->equipment_approval_status,
        $requestItem->ppic_approval_status,
    ])->every(fn ($status) => $status === 'approved')
        && in_array($requestItem->director_approval_status, ['approved', 'not_required'], true);
    $procurementRejectionLog = ($timelineLogs ?? collect())->where('event', 'procurement_request.rejected')->last();
    $procurementRejectorName = $procurementRejectionLog?->user?->name ?? 'User sistem';
    $procurementRejectedAt = $procurementRejectionLog?->created_at;
    $procurementRejectedStage = data_get($procurementRejectionLog?->properties, 'stage')
        ?: ($requestItem->procurement_equipment_checked_at ? 'director' : 'equipment');
    $rejectedAtEquipment = $procurementStatus === 'rejected' && $procurementRejectedStage === 'equipment';
    $rejectedAtDirector = $procurementStatus === 'rejected' && $procurementRejectedStage === 'director';
    $equipmentDisplayName = $rejectedAtEquipment ? $procurementRejectorName : $requestItem->procurement_equipment_checked_by_name;
    $equipmentDisplayAt = $rejectedAtEquipment ? $procurementRejectedAt : $requestItem->procurement_equipment_checked_at;
    $equipmentDisplayStatus = $rejectedAtEquipment ? 'Ditolak' : ($requestItem->procurement_equipment_checked_at ? 'Selesai' : 'Belum');
    $directorDisplayName = $rejectedAtDirector ? $procurementRejectorName : $requestItem->procurement_director_approved_by_name;
    $directorDisplayAt = $rejectedAtDirector ? $procurementRejectedAt : $requestItem->procurement_director_approved_at;
    $directorDisplayStatus = $rejectedAtDirector ? 'Ditolak' : ($procurementStatus === 'approved' ? 'Selesai' : 'Belum');
    $hasPurchaseOrder = $requestItem->purchaseOrderItems->isNotEmpty();
    $sourceCreditNote = $requestItem->sourceCreditNoteItem?->creditNote;
    $sourcePurchaseOrder = $requestItem->sourceCreditNoteItem?->purchaseOrderItem?->purchaseOrder;
    $hasOrphanPurchaseOrderStatus = $requestItem->status === 'po_created' && ! $hasPurchaseOrder;
    $vendorPricingCompleted = $hasPurchaseOrder || in_array($procurementStatus, ['approved', 'rejected'], true);
    $canEditQuotations = ! $vendorPricingCompleted
        && in_array($procurementStatus, ['draft', 'not_required'], true)
        && in_array($requestItem->status, ['ready_for_quotation', 'vendor_selected'], true);
    $isEquipmentAllocationReview = ! $hasPurchaseOrder
        && auth()->user()?->hasPermission('approvals.equipment')
        && $procurementStatus === 'pending_equipment';
    $isDirectorAllocationReview = ! $hasPurchaseOrder
        && auth()->user()?->hasPermission('approvals.director')
        && $procurementStatus === 'pending_director';
    $isApprovalAllocationReview = $isEquipmentAllocationReview || $isDirectorAllocationReview;
    $canChooseVendor = $isApprovalAllocationReview || ($canEditQuotations && ($canManageVendorPrices
        || auth()->user()?->hasPermission('approvals.equipment')
        || auth()->user()?->hasPermission('approvals.ppic')));
    $returnContext = request()->query('from');
    $returnCompanyId = request()->integer('company_id') ?: null;
    $defaultReturnContext = in_array($procurementStatus, ['pending_equipment', 'pending_director'], true)
        ? 'approval'
        : ($vendorPricingCompleted ? 'history' : 'active');
    $returnContext = in_array($returnContext, ['active', 'approval', 'history', 'approvals'], true)
        ? $returnContext
        : $defaultReturnContext;
    $returnUrl = $returnContext === 'approvals'
        ? route('approvals', array_filter(['company_id' => $returnCompanyId]))
        : route('vendor-prices', array_filter([
            'tab' => $returnContext === 'active' ? null : $returnContext,
            'company_id' => $returnCompanyId,
        ]));
    $uom = $requestItem->uom ?? $requestItem->inventoryItem->uom;
    $allocationLimit = $requestItem->allocationLimit();
    $remainingQuantity = max(0, $requestItem->quantity - $selectedQuantity);
@endphp
<main class="page">
    <header class="topbar">
        <div>
            <p class="eyebrow">Detail Harga Vendor</p>
            <h1>{{ $requestItem->inventoryItem->sku }} - {{ $requestItem->inventoryItem->name }}</h1>
            <p>PR {{ $purchaseRequest->number }} - {{ $purchaseRequest->camp->company_name }} / {{ $purchaseRequest->camp->name }}</p>
            <p>Unit {{ $requestItem->unit?->code }} - Diminta camp {{ $requestItem->quantity }} {{ $requestItem->uom ?? $requestItem->inventoryItem->uom }}{{ $requestItem->specification ? ' - '.$requestItem->specification : '' }}</p>
            @if ($sourceCreditNote)
                <p>
                    <span class="pill">Pengajuan Ulang</span>
                    dari
                    @if (auth()->user()?->hasPermission('credit_notes.view'))
                        <a href="{{ route('credit-notes.review', $sourceCreditNote) }}">CN {{ $sourceCreditNote->number }}</a>
                    @else
                        CN {{ $sourceCreditNote->number }}
                    @endif
                    @if ($sourcePurchaseOrder)
                        / PO asal {{ $sourcePurchaseOrder->number }}
                    @endif
                </p>
            @endif
        </div>
        <div class="actions">
            <a class="button ghost" href="{{ route('vendor-prices.print', $requestItem) }}" target="_blank">Cetak</a>
            <a class="button ghost" href="{{ $returnUrl }}">Kembali</a>
            <span class="pill {{ $procurementStatus === 'rejected' ? 'bad' : ($vendorPricingCompleted ? 'ok' : '') }}">{{ $procurementStatus === 'rejected' ? 'Ditolak' : ($vendorPricingCompleted ? 'Selesai' : str_replace('_', ' ', $requestItem->status)) }}</span>
        </div>
    </header>

    @if (session('success'))
        <div class="notice success">{{ session('success') }}</div>
    @endif
    @if (session('error') || $errors->any())
        <div class="notice error">{{ session('error') ?: $errors->first() }}</div>
    @endif

    <section class="panel workflow-overview">
        <div class="workflow-steps" aria-label="Tahapan proses harga vendor">
            <div @class(['workflow-step', 'is-done' => $orderRequestApprovalCompleted, 'is-active' => ! $orderRequestApprovalCompleted])>OR Disetujui</div>
            <div @class(['workflow-step', 'is-done' => $rankedQuotations->isNotEmpty(), 'is-active' => $orderRequestApprovalCompleted && $rankedQuotations->isEmpty()])>Penawaran Vendor</div>
            <div @class(['workflow-step', 'is-done' => $selectedQuantity > 0, 'is-active' => $rankedQuotations->isNotEmpty() && $selectedQuantity === 0])>Alokasi Vendor</div>
            <div @class(['workflow-step', 'is-done' => $procurementStatus === 'approved', 'is-active' => in_array($procurementStatus, ['draft', 'pending_equipment', 'pending_director'], true) && $selectedQuantity > 0])>Approval PPG</div>
            <div @class(['workflow-step', 'is-done' => $hasPurchaseOrder, 'is-active' => $procurementStatus === 'approved' && ! $hasPurchaseOrder])>Siap Purchase Order</div>
        </div>
        <div class="summary-grid">
            <div class="summary-item"><span>Vendor Pembanding</span><strong>{{ $rankedQuotations->count() }}</strong></div>
            <div class="summary-item"><span>Rekomendasi</span><strong>{{ $recommendedQuotation?->vendor?->name ?? '-' }}</strong></div>
            <div class="summary-item"><span>Qty Dialokasikan</span><strong>{{ $selectedQuantity }} {{ $uom }}</strong></div>
            <div class="summary-item"><span>Status PPG</span><strong>{{ $procurementStatusLabel }}</strong></div>
        </div>
    </section>

    <section class="panel">
        <div class="panel-head">
            <div>
                <h2>Approval Permintaan Pengadaan Barang (PPG)</h2>
            </div>
            <span class="pill {{ $procurementStatus === 'approved' ? 'ok' : ($procurementStatus === 'rejected' ? 'bad' : 'wait') }}">{{ $procurementStatusLabel }}</span>
        </div>
        @if ($orderRequestApprovalCompleted && in_array($procurementStatus, ['not_required', 'draft'], true) && ! $requestItem->procurement_created_at)
            <div class="notice success">Approval Order Request sudah selesai. Approval PPG belum diajukan karena pilihan vendor belum ditetapkan.</div>
        @endif
        <div class="table-scroll">
        <table>
            <thead><tr><th>Tahap</th><th>Nama</th><th>Qty</th><th>Waktu</th><th>Status</th></tr></thead>
            <tbody>
            <tr><td>Dibuat oleh Admin Logistic</td><td>{{ $requestItem->procurement_created_by_name ?: '-' }}</td><td>{{ $requestItem->quantity }} {{ $requestItem->uom ?? $requestItem->inventoryItem->uom }}</td><td>{{ $requestItem->procurement_created_at ? \Illuminate\Support\Carbon::parse($requestItem->procurement_created_at)->format('d M Y H:i') : '-' }}</td><td>{{ $requestItem->procurement_created_at ? 'Selesai' : 'Belum' }}</td></tr>
            <tr><td>Diperiksa oleh Peralatan</td><td>{{ $equipmentDisplayName ?: '-' }}</td><td>{{ $equipmentDisplayAt ? $requestItem->equipmentApprovedQuantity().' '.($requestItem->uom ?? $requestItem->inventoryItem->uom) : '-' }}</td><td>{{ $equipmentDisplayAt ? \Illuminate\Support\Carbon::parse($equipmentDisplayAt)->format('d M Y H:i') : '-' }}</td><td>{{ $equipmentDisplayStatus }}</td></tr>
            <tr><td>Disetujui oleh Direktur</td><td>{{ $directorDisplayName ?: '-' }}</td><td>{{ $directorDisplayAt ? $requestItem->approvedQuantity().' '.($requestItem->uom ?? $requestItem->inventoryItem->uom) : '-' }}</td><td>{{ $directorDisplayAt ? \Illuminate\Support\Carbon::parse($directorDisplayAt)->format('d M Y H:i') : '-' }}</td><td>{{ $directorDisplayStatus }}</td></tr>
            </tbody>
        </table>
        </div>
        @if ($requestItem->quantity_adjustment_reason)
            <div class="notice">Alasan penyesuaian qty: {{ $requestItem->quantity_adjustment_reason }}</div>
        @endif
        @if ($requestItem->procurement_rejection_note)
            <div class="notice error">{{ $requestItem->procurement_rejection_note }}</div>
        @endif
        <div class="actions" style="margin-top:12px">
            @if ($canManageVendorPrices && $requestItem->status === 'vendor_selected' && in_array($requestItem->director_approval_status, ['approved', 'not_required'], true) && $selectedQuotations->isNotEmpty() && $procurementStatus === 'draft')
                <form method="post" action="{{ route('request-items.procurement-submit', $requestItem) }}" data-confirm="Ajukan pilihan vendor ke proses approval PPG sekarang?">@csrf<button>Ajukan Approval PPG</button></form>
            @endif
        </div>
        @if ($requestItem->procurement_created_at || in_array($procurementStatus, ['pending_equipment', 'pending_director', 'approved', 'rejected'], true))
            <div class="signature-grid">
                <div class="signature">
                    <div>
                        <strong>Dibuat Oleh</strong>
                        <p>Admin Logistik</p>
                    </div>
                    <div class="signature-line">{{ $requestItem->procurement_created_by_name ?: 'Admin Logistik' }}</div>
                </div>
                <div class="signature">
                    <div>
                        <strong>Diperiksa Oleh</strong>
                        <p>Peralatan</p>
                    </div>
                    <div class="signature-line">{{ $equipmentDisplayName ?: 'Peralatan' }}{{ $rejectedAtEquipment ? ' (Menolak)' : '' }}</div>
                </div>
                <div class="signature">
                    <div>
                        <strong>Disetujui Oleh</strong>
                        <p>Direktur</p>
                    </div>
                    <div class="signature-line">{{ $directorDisplayName ?: 'Direktur' }}{{ $rejectedAtDirector ? ' (Menolak)' : '' }}</div>
                </div>
            </div>
        @endif
    </section>

    <section class="grid">
        <article class="panel">
            <div class="panel-head">
                <div>
                    <h2>Tambah Penawaran Vendor</h2>
                    <p>Masukkan harga, ketersediaan stok, dan lead time sebagai bahan perbandingan.</p>
                </div>
            </div>
            @if ($canManageVendorPrices && $canEditQuotations)
                <form method="post" action="{{ route('quotations.store', $requestItem) }}" data-vendor-form>
                    @csrf
                    <div class="flow" data-vendor-list>
                        <div class="vendor-row" data-vendor-row>
                            <label>Vendor
                                <select name="quotations[0][vendor_id]" required>
                                    <option value="">Pilih vendor</option>
                                    @foreach ($vendors as $vendor)
                                        <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>Merek <input name="quotations[0][brand_name]" placeholder="Contoh: Sakura"></label>
                            <label>No Part <span class="readonly-value">{{ $requestItem->inventoryItem->sku }}</span></label>
                            <label>Mata Uang
                                <select name="quotations[0][currency_code]" data-currency required>
                                    @foreach ($currencies as $code => $name)<option value="{{ $code }}">{{ $code }} - {{ $name }}</option>@endforeach
                                </select>
                            </label>
                            <label>Harga Penawaran <input name="quotations[0][unit_price]" type="number" min="0.01" step="0.01" required placeholder="0" data-original-price></label>
                            <label>Kurs Digunakan (IDR) <input name="quotations[0][exchange_rate]" type="number" min="0.0001" step="0.0001" value="1.0000" required data-exchange-rate @readonly(! $canOverrideExchangeRate)></label>
                            <input name="quotations[0][reference_exchange_rate]" type="hidden" value="1" data-reference-rate>
                            <input name="quotations[0][exchange_rate_date]" type="hidden" value="{{ now()->toDateString() }}" data-rate-date>
                            <label>Alasan Kurs Manual <input name="quotations[0][exchange_rate_override_reason]" placeholder="{{ $canOverrideExchangeRate ? 'Hanya jika kurs diubah' : 'Khusus Purchasing / Administrator' }}" data-rate-reason @readonly(! $canOverrideExchangeRate)></label>
                            <label>Stok Tersedia <input name="quotations[0][available_quantity]" type="number" min="0" required placeholder="0"></label>
                            <label>Lead Time (hari) <input name="quotations[0][lead_time_days]" type="number" min="0" value="0"></label>
                            <label class="vendor-notes">Catatan <input name="quotations[0][notes]" placeholder="Garansi, indent, atau ketentuan vendor"></label>
                            <button type="button" class="ghost vendor-action" data-add-vendor>+ Tambah Vendor</button>
                            <div class="rate-meta" data-rate-status role="status">IDR tidak memerlukan konversi kurs.</div>
                        </div>
                    </div>
                    <template data-vendor-template>
                        <div class="vendor-row" data-vendor-row>
                            <label>Vendor
                                <select name="quotations[__INDEX__][vendor_id]" required>
                                    <option value="">Pilih vendor</option>
                                    @foreach ($vendors as $vendor)
                                        <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>Merek <input name="quotations[__INDEX__][brand_name]" placeholder="Contoh: Sakura"></label>
                            <label>No Part <span class="readonly-value">{{ $requestItem->inventoryItem->sku }}</span></label>
                            <label>Mata Uang
                                <select name="quotations[__INDEX__][currency_code]" data-currency required>
                                    @foreach ($currencies as $code => $name)<option value="{{ $code }}">{{ $code }} - {{ $name }}</option>@endforeach
                                </select>
                            </label>
                            <label>Harga Penawaran <input name="quotations[__INDEX__][unit_price]" type="number" min="0.01" step="0.01" required placeholder="0" data-original-price></label>
                            <label>Kurs Digunakan (IDR) <input name="quotations[__INDEX__][exchange_rate]" type="number" min="0.0001" step="0.0001" value="1.0000" required data-exchange-rate @readonly(! $canOverrideExchangeRate)></label>
                            <input name="quotations[__INDEX__][reference_exchange_rate]" type="hidden" value="1" data-reference-rate>
                            <input name="quotations[__INDEX__][exchange_rate_date]" type="hidden" value="{{ now()->toDateString() }}" data-rate-date>
                            <label>Alasan Kurs Manual <input name="quotations[__INDEX__][exchange_rate_override_reason]" placeholder="{{ $canOverrideExchangeRate ? 'Hanya jika kurs diubah' : 'Khusus Purchasing / Administrator' }}" data-rate-reason @readonly(! $canOverrideExchangeRate)></label>
                            <label>Stok Tersedia <input name="quotations[__INDEX__][available_quantity]" type="number" min="0" required placeholder="0"></label>
                            <label>Lead Time (hari) <input name="quotations[__INDEX__][lead_time_days]" type="number" min="0" value="0"></label>
                            <label class="vendor-notes">Catatan <input name="quotations[__INDEX__][notes]" placeholder="Garansi, indent, atau ketentuan vendor"></label>
                            <button type="button" class="ghost vendor-action" data-remove-vendor>Hapus</button>
                            <div class="rate-meta" data-rate-status role="status">IDR tidak memerlukan konversi kurs.</div>
                        </div>
                    </template>
                    <div class="actions">
                        <button>Simpan dan Ranking Ulang</button>
                    </div>
                </form>
            @else
                <div class="empty">{{ $hasPurchaseOrder ? 'Penawaran sudah masuk Purchase Order dan hanya dapat dilihat.' : ($hasOrphanPurchaseOrderStatus ? 'Data tidak konsisten: item tercatat sudah dibuatkan PO, tetapi item PO tidak ditemukan. Periksa integritas data sebelum melanjutkan.' : ($procurementStatus === 'rejected' ? 'Pengajuan ditolak dan proses dihentikan.' : ($procurementStatus === 'approved' ? 'Proses harga vendor sudah selesai dan hanya dapat dilihat.' : ($canManageVendorPrices ? 'Penawaran dikunci karena sudah masuk proses approval pengadaan.' : 'Role Anda hanya bisa melihat harga vendor.')))) }}</div>
            @endif
        </article>
    </section>

    <section class="panel">
        <div class="panel-head">
            <div>
                <h2>Ranking Penawaran Vendor</h2>
                <p>Bandingkan penawaran lalu isi quantity pada vendor yang digunakan.</p>
            </div>
        </div>
        @if ($rankedQuotations->isNotEmpty())
            <div class="notice">
                Diminta camp: <strong>{{ $requestItem->quantity }} {{ $requestItem->uom ?? $requestItem->inventoryItem->uom }}</strong>.
                Alokasi saat ini: <strong>{{ $selectedQuantity }} {{ $requestItem->uom ?? $requestItem->inventoryItem->uom }}</strong>.
                @if ($selectedQuantity > 0 && $selectedQuantity < $requestItem->quantity)
                    Selisih {{ $requestItem->quantity - $selectedQuantity }} akan tercatat sebagai tidak disetujui setelah approval final.
                @endif
            </div>
            @if ($canChooseVendor)
                <form method="post" action="{{ route('vendor-allocations.update', $requestItem) }}" data-confirm="{{ $isApprovalAllocationReview ? 'Simpan perubahan pilihan vendor ini?' : 'Simpan vendor dan pembagian qty final untuk barang ini?' }}" data-allocation-form>
                @csrf
            @endif
            <div class="table-scroll">
            <table>
                <thead><tr><th>Rank</th><th>Vendor</th><th>Merek / No Part</th><th>Harga</th><th>Stok Vendor</th><th>Lead Time</th><th>Qty Dipilih (Pilih Vendor / Ubah Qty)</th><th>Total Estimasi</th><th>Status</th><th>Catatan</th></tr></thead>
                <tbody>
                @foreach ($rankedQuotations as $quotation)
                    @php
                        $allocatedQuantity = (int) $quotation->selected_quantity;
                        $hasEnoughStock = $quotation->available_quantity >= max(1, $allocatedQuantity);
                        $displayQuantity = $allocatedQuantity ?: $requestItem->quantity;
                        $quotationTotal = $quotation->unit_price * $displayQuantity;
                        $quotationTotalIdr = $quotation->totalIdr($displayQuantity);
                    @endphp
                    <tr>
                        <td>#{{ $loop->iteration }}</td>
                        <td>{{ $quotation->vendor->name }}</td>
                        <td>{{ $quotation->brand_name ?: '-' }}<br><span class="muted">{{ $quotation->part_number ?: $requestItem->inventoryItem->sku }}</span></td>
                        <td class="price">
                            @if ($canSeePrices)
                                <span class="original-price">{{ \App\Support\Currency::format($quotation->unit_price, $quotation->currency_code) }}</span>
                                @if ($quotation->currency_code !== 'IDR')
                                    <span class="idr-equivalent">≈ {{ \App\Support\Currency::format($quotation->unitPriceIdr()) }} · kurs {{ number_format($quotation->exchange_rate, 2, ',', '.') }}</span>
                                @endif
                            @else Disembunyikan @endif
                        </td>
                        <td><span class="pill {{ $quotation->available_quantity > 0 ? 'ok' : 'bad' }}">{{ $quotation->available_quantity }} {{ $requestItem->uom ?? $requestItem->inventoryItem->uom }}</span></td>
                        <td>{{ $quotation->lead_time_days }} hari</td>
                        <td>
                            @if ($canChooseVendor)
                                <label class="allocation-control">
                                    <input name="allocations[{{ $quotation->id }}]" type="number" min="0" max="{{ $quotation->available_quantity }}" step="1" value="{{ old('allocations.'.$quotation->id, $allocatedQuantity) }}" aria-label="Alokasi quantity vendor {{ $quotation->vendor->name }}" data-allocation-input data-initial-value="{{ $allocatedQuantity }}" data-price="{{ $canSeePrices ? $quotation->unitPriceIdr() : 0 }}" @disabled($quotation->available_quantity <= 0)>
                                    <span>{{ $uom }}</span>
                                </label>
                            @else
                                <strong>{{ $allocatedQuantity ?: '-' }}</strong> {{ $allocatedQuantity > 0 ? $uom : '' }}
                            @endif
                        </td>
                        <td class="price">
                            @if ($canSeePrices)
                                <span class="original-price">{{ \App\Support\Currency::format($quotationTotal, $quotation->currency_code) }}</span>
                                @if ($quotation->currency_code !== 'IDR')<span class="idr-equivalent">≈ {{ \App\Support\Currency::format($quotationTotalIdr) }}</span>@endif
                            @else Disembunyikan @endif
                        </td>
                        <td>
                            @if ($quotation->is_selected)
                                <span class="pill ok">Dipilih {{ $allocatedQuantity }} {{ $requestItem->uom ?? $requestItem->inventoryItem->uom }}</span>
                                @if ($recommendedQuotation?->id === $quotation->id)
                                    <span class="pill">Termurah cukup stok</span>
                                @endif
                            @elseif ($recommendedQuotation?->id === $quotation->id)
                                <span class="pill wait">Termurah cukup stok</span>
                            @elseif (! $hasEnoughStock)
                                <span class="pill bad">Stok kurang</span>
                            @else
                                <span class="pill">Pembanding</span>
                            @endif
                        </td>
                        <td>{{ $quotation->notes ?: '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>
            <div class="allocation-summary" data-allocation-summary data-required-quantity="{{ $requestItem->quantity }}" data-allocation-limit="{{ $allocationLimit }}" data-uom="{{ $uom }}">
                <div><span>Kebutuhan Camp</span><strong>{{ $requestItem->quantity }} {{ $uom }}</strong></div>
                <div><span>Dialokasikan</span><strong data-allocation-quantity>{{ $selectedQuantity }} {{ $uom }}</strong></div>
                <div><span>Sisa Kebutuhan</span><strong data-allocation-remaining>{{ $remainingQuantity }} {{ $uom }}</strong></div>
                <div><span>Nilai Alokasi</span><strong data-allocation-total>{{ $canSeePrices ? \App\Support\Currency::format($selectedTotal) : 'Disembunyikan' }}</strong></div>
                <div class="allocation-message {{ $remainingQuantity === 0 ? 'is-complete' : '' }}" data-allocation-message>
                    {{ $remainingQuantity === 0 ? 'Seluruh kebutuhan sudah dialokasikan.' : 'Masih ada '.$remainingQuantity.' '.$uom.' yang belum dialokasikan.' }}
                </div>
            </div>
            @if ($canChooseVendor)
                <div class="actions" style="margin-top:12px">
                    <button @class(['is-hidden' => $isApprovalAllocationReview]) @if ($isApprovalAllocationReview) data-save-approval-selection @endif>{{ $isApprovalAllocationReview ? 'Simpan Pilihan' : 'Simpan Alokasi Vendor' }}</button>
                </div>
                </form>
            @endif
            @if ($isApprovalAllocationReview)
                <div class="actions" style="margin-top:12px" data-approval-decisions>
                    <form method="post" action="{{ $isEquipmentAllocationReview ? route('request-items.procurement-approve-equipment', $requestItem) : route('request-items.procurement-approve-director', $requestItem) }}" data-confirm="{{ $isEquipmentAllocationReview ? 'Setujui pilihan vendor dan teruskan ke Direktur?' : 'Berikan persetujuan akhir Direktur untuk pilihan vendor ini?' }}" data-approval-form>
                        @csrf
                        <input name="quantity_adjustment_reason" value="{{ old('quantity_adjustment_reason', $requestItem->quantity_adjustment_reason) }}" placeholder="Alasan penyesuaian qty{{ $selectedQuantity < $requestItem->quantity ? ' (wajib)' : '' }}" @if ($selectedQuantity < $requestItem->quantity) required @endif>
                        <button>{{ $isEquipmentAllocationReview ? 'Setujui Peralatan' : 'Setujui Direktur' }}</button>
                    </form>
                    <form method="post" action="{{ route('request-items.procurement-reject', $requestItem) }}" data-confirm="Tolak pengajuan vendor ini dan hentikan prosesnya? Tindakan ini tidak dapat diajukan ulang." data-reject-form>
                        @csrf
                        <input name="procurement_rejection_note" required placeholder="Alasan penolakan">
                        <button class="ghost">Tolak</button>
                    </form>
                </div>
            @endif
        @else
            <div class="empty">Belum ada penawaran vendor untuk item ini.</div>
        @endif
    </section>

    <details class="panel timeline-panel">
        <summary class="timeline-summary">
            <div>
                <h2>Timeline Harga Vendor</h2>
                <p>Riwayat perubahan dan aktivitas pada proses ini.</p>
            </div>
            <span class="pill">{{ ($timelineLogs ?? collect())->count() }} aktivitas</span>
        </summary>
        <div class="timeline-content">
            <div class="table-scroll">
                <table>
                    <thead><tr><th>Waktu</th><th>User</th><th>Aktivitas</th><th>Event</th></tr></thead>
                    <tbody>
                    @forelse (($timelineLogs ?? collect()) as $log)
                        <tr>
                            <td>{{ $log->created_at?->format('d M Y H:i') }}</td>
                            <td>{{ $log->user?->name ?? 'System' }}</td>
                            <td>{{ $log->description }}</td>
                            <td><span class="pill">{{ $log->event }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4">Belum ada timeline harga vendor.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </details>
</main>
@include('inventory.components.confirm-submit')
<script>
    const exchangeRateUrl = @json(route('exchange-rates.latest'));
    const exchangeRateWarningThreshold = {{ $exchangeRateWarningThreshold }};
    const canOverrideExchangeRate = @json($canOverrideExchangeRate);
    const idrFormatter = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
    const rateFormatter = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
    const originalPriceFormatter = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
    const ratesDiffer = (first, second) => Number(first).toFixed(4) !== Number(second).toFixed(4);

    const formatRateDate = (value) => {
        if (! value) return '-';
        return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
            .format(new Date(`${value}T00:00:00`));
    };

    const syncCurrencyRow = (row) => {
        const currency = row.querySelector('[data-currency]')?.value || 'IDR';
        const price = Number(row.querySelector('[data-original-price]')?.value || 0);
        const rateInput = row.querySelector('[data-exchange-rate]');
        const referenceRate = Number(row.querySelector('[data-reference-rate]')?.value || 0);
        const appliedRate = Number(rateInput?.value || 0);
        const reason = row.querySelector('[data-rate-reason]');
        const status = row.querySelector('[data-rate-status]');
        const manual = currency !== 'IDR' && (referenceRate <= 0 || ratesDiffer(appliedRate, referenceRate));
        const deviation = referenceRate > 0 ? Math.abs(appliedRate - referenceRate) / referenceRate * 100 : 0;

        reason.required = manual;
        reason.readOnly = ! canOverrideExchangeRate || ! manual;
        reason.placeholder = manual
            ? (canOverrideExchangeRate ? 'Jelaskan alasan penggunaan kurs manual' : 'Khusus Purchasing / Administrator')
            : 'Tidak diperlukan untuk kurs referensi';
        if (row.dataset.rateError && appliedRate <= 0) {
            status.textContent = canOverrideExchangeRate
                ? `${row.dataset.rateError} Isi kurs manual dan alasan wajib.`
                : `${row.dataset.rateError} Hubungi Purchasing atau Administrator.`;
            status.classList.add('rate-warning');
            return;
        }

        status.classList.toggle('rate-warning', deviation > exchangeRateWarningThreshold || row.dataset.rateStale === '1');
        if (currency === 'IDR') {
            status.textContent = price > 0
                ? `Mata uang IDR | Nilai penawaran Rp${idrFormatter.format(price)} | Tidak memerlukan konversi kurs`
                : 'Mata uang IDR | Tidak memerlukan konversi kurs';
        } else {
            const source = referenceRate > 0 && row.dataset.rateDate
                ? `Frankfurter${row.dataset.rateStale === '1' ? ' (kurs terakhir)' : ''} | ${formatRateDate(row.dataset.rateDate)}`
                : 'Kurs manual';
            const warning = deviation > exchangeRateWarningThreshold ? ` | peringatan selisih ${deviation.toFixed(2)}%` : '';
            const conversion = appliedRate > 0 ? `1 ${currency} = Rp${rateFormatter.format(appliedRate)}` : '';
            const estimate = price > 0 && appliedRate > 0
                ? `${currency} ${originalPriceFormatter.format(price)} = sekitar Rp${idrFormatter.format(price * appliedRate)}`
                : 'Isi harga penawaran untuk melihat estimasi IDR';
            status.textContent = appliedRate > 0
                ? `${source} | ${conversion} | ${estimate}${warning}`
                : 'Isi kurs manual dan alasannya.';
        }
    };

    const loadReferenceRate = async (row, refresh = false) => {
        const currency = row.querySelector('[data-currency]').value;
        const rateInput = row.querySelector('[data-exchange-rate]');
        const referenceRateInput = row.querySelector('[data-reference-rate]');
        const dateInput = row.querySelector('[data-rate-date]');
        const reasonInput = row.querySelector('[data-rate-reason]');
        const status = row.querySelector('[data-rate-status]');

        if (currency === 'IDR') {
            delete row.dataset.rateError;
            delete row.dataset.rateDate;
            delete row.dataset.rateStale;
            rateInput.value = '1';
            referenceRateInput.value = '1';
            dateInput.value = new Date().toISOString().slice(0, 10);
            reasonInput.value = '';
            syncCurrencyRow(row);
            return;
        }

        delete row.dataset.rateError;
        delete row.dataset.rateDate;
        delete row.dataset.rateStale;
        rateInput.value = '';
        referenceRateInput.value = '';
        dateInput.value = '';
        reasonInput.value = '';
        status.textContent = 'Mengambil kurs referensi Frankfurter terbaru...';
        try {
            const url = new URL(exchangeRateUrl, window.location.origin);
            url.searchParams.set('currency', currency);
            if (refresh) url.searchParams.set('refresh', '1');
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (! response.ok) throw new Error(data.message || 'Kurs referensi tidak tersedia.');

            rateInput.value = Number(data.rate).toFixed(4);
            referenceRateInput.value = Number(data.rate).toFixed(6);
            dateInput.value = data.date;
            reasonInput.value = '';
            row.dataset.rateDate = data.date;
            row.dataset.rateStale = data.stale ? '1' : '0';
        } catch (error) {
            rateInput.value = '';
            referenceRateInput.value = '';
            dateInput.value = '';
            row.dataset.rateError = error.message || 'Kurs referensi tidak tersedia.';
        }
        syncCurrencyRow(row);
    };

    const bindCurrencyRow = (row) => {
        row.querySelector('[data-currency]')?.addEventListener('change', () => loadReferenceRate(row));
        row.querySelector('[data-exchange-rate]')?.addEventListener('input', (event) => {
            if (Number(event.currentTarget.value || 0) > 0) {
                delete row.dataset.rateError;
            }
            syncCurrencyRow(row);
        });
        row.querySelector('[data-original-price]')?.addEventListener('input', () => syncCurrencyRow(row));
        syncCurrencyRow(row);
    };

    const approvalAllocationForm = document.querySelector('[data-save-approval-selection]')?.closest('form');
    const approvalSaveButton = document.querySelector('[data-save-approval-selection]');
    const approvalDecisions = document.querySelector('[data-approval-decisions]');

    const syncApprovalActions = () => {
        if (! approvalAllocationForm || ! approvalSaveButton || ! approvalDecisions) {
            return;
        }

        const changed = [...approvalAllocationForm.querySelectorAll('[data-allocation-input]')]
            .some((input) => String(input.value) !== String(input.dataset.initialValue));

        approvalSaveButton.classList.toggle('is-hidden', ! changed);
        approvalDecisions.classList.toggle('is-hidden', changed);
    };

    document.querySelectorAll('[data-vendor-form]').forEach((form) => {
        const list = form.querySelector('[data-vendor-list]');
        const template = form.querySelector('[data-vendor-template]');

        form.querySelector('[data-add-vendor]')?.addEventListener('click', () => {
            const index = list.querySelectorAll('[data-vendor-row]').length;
            const html = template.innerHTML.replaceAll('__INDEX__', index);
            list.insertAdjacentHTML('beforeend', html);
            bindCurrencyRow(list.lastElementChild);
        });

        form.addEventListener('click', (event) => {
            if (! event.target.matches('[data-remove-vendor]')) {
                return;
            }

            event.target.closest('[data-vendor-row]').remove();
        });

        list.querySelectorAll('[data-vendor-row]').forEach(bindCurrencyRow);
    });

    const allocationSummary = document.querySelector('[data-allocation-summary]');
    const allocationInputs = [...document.querySelectorAll('[data-allocation-input]')];

    const syncAllocationSummary = () => {
        if (! allocationSummary || allocationInputs.length === 0) return;

        const required = Number(allocationSummary.dataset.requiredQuantity || 0);
        const limit = Number(allocationSummary.dataset.allocationLimit || required);
        const uom = allocationSummary.dataset.uom || '';
        const allocated = allocationInputs.reduce((sum, input) => sum + Math.max(0, Number(input.value || 0)), 0);
        const remaining = Math.max(0, required - allocated);
        const total = allocationInputs.reduce((sum, input) => sum + (Math.max(0, Number(input.value || 0)) * Number(input.dataset.price || 0)), 0);
        const message = allocationSummary.querySelector('[data-allocation-message]');

        allocationSummary.querySelector('[data-allocation-quantity]').textContent = `${allocated} ${uom}`;
        allocationSummary.querySelector('[data-allocation-remaining]').textContent = `${remaining} ${uom}`;
        @if ($canSeePrices)
            allocationSummary.querySelector('[data-allocation-total]').textContent = `Rp${idrFormatter.format(total)}`;
        @endif

        allocationInputs.forEach((input) => input.setCustomValidity(''));
        message.classList.remove('is-complete');
        if (allocated > limit) {
            message.textContent = `Total alokasi melebihi batas tahap ini (${limit} ${uom}).`;
            allocationInputs.find((input) => ! input.disabled)?.setCustomValidity(`Total alokasi maksimal ${limit} ${uom}.`);
        } else if (remaining > 0) {
            message.textContent = `Masih ada ${remaining} ${uom} yang belum dialokasikan.`;
        } else {
            message.textContent = 'Seluruh kebutuhan sudah dialokasikan.';
            message.classList.add('is-complete');
        }
    };

    allocationInputs.forEach((input) => {
        input.addEventListener('input', () => {
            syncAllocationSummary();
            syncApprovalActions();
        });
    });

    syncAllocationSummary();
    syncApprovalActions();
</script>
</body>
</html>
