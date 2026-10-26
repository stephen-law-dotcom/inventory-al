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
            grid-template-columns:
                minmax(160px, 1.2fr)
                minmax(140px, 1fr)
                minmax(140px, 1fr)
                minmax(110px, .9fr)
                minmax(110px, .85fr)
                minmax(100px, .8fr)
                minmax(160px, 1.1fr)
                168px;
            gap: 8px;
            align-items: end;
            padding: 10px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fbfdfe;
        }
        .vendor-row > button { width: 100%; }
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
        .qty-choice { display: grid; gap: 7px; min-width: 160px; }
        .qty-picker { display: none; grid-template-columns: 86px auto; gap: 7px; align-items: center; margin-top: 7px; }
        .qty-picker.is-open { display: grid; }
        .qty-display { display: inline-flex; align-items: center; min-height: 24px; font-weight: 900; }
        .mini-button { min-height: 32px; padding: 6px 9px; font-size: 12px; }
        .link-button { min-height: auto; padding: 0; border: 0; background: transparent; color: var(--red); box-shadow: none; font-size: 12px; }
        .link-button:hover { background: transparent; text-decoration: underline; }
        .is-hidden { display: none !important; }
        @media (max-width: 1220px) {
            .vendor-row { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 1050px) {
            .grid, .stat-grid, .signature-grid { grid-template-columns: 1fr; }
            table { display: block; overflow-x: auto; }
            th, td { min-width: 130px; }
        }
        @media (max-width: 680px) {
            .topbar, .panel-head { flex-direction: column; }
            .vendor-row { grid-template-columns: 1fr; }
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
    $rankedQuotations = $requestItem->quotations->sortBy([
        ['unit_price', 'asc'],
        ['available_quantity', 'desc'],
        ['lead_time_days', 'asc'],
    ])->values();
    $recommendedQuotation = $rankedQuotations->first(fn ($quotation) => $quotation->available_quantity >= $requestItem->quantity);
    $selectedQuotations = $requestItem->selectedQuotations;
    $selectedQuotation = $requestItem->selectedQuotation;
    $selectedQuantity = (int) $selectedQuotations->sum('selected_quantity');
    $selectedTotal = $selectedQuotations->sum(fn ($quotation) => $quotation->unit_price * $quotation->selected_quantity);
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
    $hasOrphanPurchaseOrderStatus = $requestItem->status === 'po_created' && ! $hasPurchaseOrder;
    $vendorPricingCompleted = $hasPurchaseOrder || in_array($procurementStatus, ['approved', 'rejected'], true);
    $canEditQuotations = ! $vendorPricingCompleted
        && in_array($procurementStatus, ['draft', 'not_required'], true)
        && in_array($requestItem->status, ['ready_for_quotation', 'vendor_selected', 'waiting_director'], true);
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
@endphp
<main class="page">
    <header class="topbar">
        <div>
            <p class="eyebrow">Detail Harga Vendor</p>
            <h1>{{ $requestItem->inventoryItem->sku }} - {{ $requestItem->inventoryItem->name }}</h1>
            <p>PR {{ $purchaseRequest->number }} - {{ $purchaseRequest->camp->company_name }} / {{ $purchaseRequest->camp->name }}</p>
            <p>Unit {{ $requestItem->unit?->code }} - Diminta camp {{ $requestItem->quantity }} {{ $requestItem->uom ?? $requestItem->inventoryItem->uom }}{{ $requestItem->specification ? ' - '.$requestItem->specification : '' }}</p>
        </div>
        <div class="actions">
            <a class="button ghost" href="{{ route('vendor-prices.print', $requestItem) }}" target="_blank">Cetak</a>
            <a class="button ghost" href="{{ $returnUrl }}">Kembali</a>
            <span class="pill {{ $procurementStatus === 'rejected' ? 'bad' : ($vendorPricingCompleted ? 'ok' : ($requestItem->status === 'waiting_director' ? 'wait' : '')) }}">{{ $procurementStatus === 'rejected' ? 'Ditolak' : ($vendorPricingCompleted ? 'Selesai' : str_replace('_', ' ', $requestItem->status)) }}</span>
        </div>
    </header>

    @if (session('success'))
        <div class="notice success">{{ session('success') }}</div>
    @endif
    @if (session('error') || $errors->any())
        <div class="notice error">{{ session('error') ?: $errors->first() }}</div>
    @endif

    <section class="stat-grid">
        <div class="stat"><span>Vendor pembanding</span><strong>{{ $rankedQuotations->count() }}</strong></div>
        <div class="stat"><span>Harga termurah cukup stok</span><strong>{{ $recommendedQuotation?->vendor?->name ?? '-' }}</strong></div>
        <div class="stat"><span>Qty pengadaan</span><strong>{{ $procurementStatus === 'approved' ? $requestItem->approvedQuantity().' '.($requestItem->uom ?? $requestItem->inventoryItem->uom) : ($selectedQuantity > 0 ? $selectedQuantity.' '.($requestItem->uom ?? $requestItem->inventoryItem->uom) : '-') }}</strong></div>
        <div class="stat"><span>Approval PPG</span><strong>{{ $procurementStatusLabel }}</strong></div>
    </section>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <div>
                <h2>Approval Permintaan Pengadaan Barang (PPG)</h2>
            </div>
            <span class="pill {{ $procurementStatus === 'approved' ? 'ok' : ($procurementStatus === 'rejected' ? 'bad' : 'wait') }}">{{ $procurementStatusLabel }}</span>
        </div>
        @if ($orderRequestApprovalCompleted && in_array($procurementStatus, ['not_required', 'draft'], true) && ! $requestItem->procurement_created_at)
            <div class="notice success">Approval Order Request sudah selesai. Approval PPG belum diajukan karena pilihan vendor belum ditetapkan.</div>
        @endif
        <table>
            <thead><tr><th>Tahap</th><th>Nama</th><th>Qty</th><th>Waktu</th><th>Status</th></tr></thead>
            <tbody>
            <tr><td>Dibuat oleh Admin Logistic</td><td>{{ $requestItem->procurement_created_by_name ?: '-' }}</td><td>{{ $requestItem->quantity }} {{ $requestItem->uom ?? $requestItem->inventoryItem->uom }}</td><td>{{ $requestItem->procurement_created_at ? \Illuminate\Support\Carbon::parse($requestItem->procurement_created_at)->format('d M Y H:i') : '-' }}</td><td>{{ $requestItem->procurement_created_at ? 'Selesai' : 'Belum' }}</td></tr>
            <tr><td>Diperiksa oleh Peralatan</td><td>{{ $equipmentDisplayName ?: '-' }}</td><td>{{ $equipmentDisplayAt ? $requestItem->equipmentApprovedQuantity().' '.($requestItem->uom ?? $requestItem->inventoryItem->uom) : '-' }}</td><td>{{ $equipmentDisplayAt ? \Illuminate\Support\Carbon::parse($equipmentDisplayAt)->format('d M Y H:i') : '-' }}</td><td>{{ $equipmentDisplayStatus }}</td></tr>
            <tr><td>Disetujui oleh Direktur</td><td>{{ $directorDisplayName ?: '-' }}</td><td>{{ $directorDisplayAt ? $requestItem->approvedQuantity().' '.($requestItem->uom ?? $requestItem->inventoryItem->uom) : '-' }}</td><td>{{ $directorDisplayAt ? \Illuminate\Support\Carbon::parse($directorDisplayAt)->format('d M Y H:i') : '-' }}</td><td>{{ $directorDisplayStatus }}</td></tr>
            </tbody>
        </table>
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
                            <label>Harga <input name="quotations[0][unit_price]" type="number" min="1" required placeholder="0"></label>
                            <label>Stok Vendor <input name="quotations[0][available_quantity]" type="number" min="0" required placeholder="0"></label>
                            <label>Lead Time <input name="quotations[0][lead_time_days]" type="number" min="0" value="0"></label>
                            <label>Catatan <input name="quotations[0][notes]" placeholder="Garansi, indent, dsb"></label>
                            <button type="button" class="ghost" data-add-vendor>+ Tambah Vendor</button>
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
                            <label>Harga <input name="quotations[__INDEX__][unit_price]" type="number" min="1" required placeholder="0"></label>
                            <label>Stok Vendor <input name="quotations[__INDEX__][available_quantity]" type="number" min="0" required placeholder="0"></label>
                            <label>Lead Time <input name="quotations[__INDEX__][lead_time_days]" type="number" min="0" value="0"></label>
                            <label>Catatan <input name="quotations[__INDEX__][notes]" placeholder="Garansi, indent, dsb"></label>
                            <button type="button" class="ghost" data-remove-vendor>Hapus</button>
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
            </div>
            @if ($requestItem->status === 'waiting_director')
                <span class="pill wait">Menunggu approval direktur</span>
            @endif
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
            <table>
                <thead><tr><th>Rank</th><th>Vendor</th><th>Merek / No Part</th><th>Harga</th><th>Stok Vendor</th><th>Lead Time</th><th>Qty Dipilih</th><th>Total</th><th>Status</th><th>Aksi</th><th>Catatan</th></tr></thead>
                <tbody>
                @foreach ($rankedQuotations as $quotation)
                    @php
                        $allocatedQuantity = (int) $quotation->selected_quantity;
                        $hasEnoughStock = $quotation->available_quantity >= max(1, $allocatedQuantity);
                        $quotationTotal = $quotation->unit_price * ($allocatedQuantity ?: $requestItem->quantity);
                    @endphp
                    <tr>
                        <td>#{{ $loop->iteration }}</td>
                        <td>{{ $quotation->vendor->name }}</td>
                        <td>{{ $quotation->brand_name ?: '-' }}<br><span class="muted">{{ $quotation->part_number ?: $requestItem->inventoryItem->sku }}</span></td>
                        <td class="price">{{ $canSeePrices ? 'Rp'.number_format($quotation->unit_price, 0, ',', '.') : 'Disembunyikan' }}</td>
                        <td><span class="pill {{ $quotation->available_quantity > 0 ? 'ok' : 'bad' }}">{{ $quotation->available_quantity }} {{ $requestItem->uom ?? $requestItem->inventoryItem->uom }}</span></td>
                        <td>{{ $quotation->lead_time_days }} hari</td>
                        <td>
                            @if ($canChooseVendor)
                                <input name="allocations[{{ $quotation->id }}]" type="hidden" value="{{ old('allocations.'.$quotation->id, $allocatedQuantity) }}" data-allocation-input data-initial-value="{{ $allocatedQuantity }}" data-price="{{ $quotation->unit_price }}" data-uom="{{ $requestItem->uom ?? $requestItem->inventoryItem->uom }}">
                                <span class="qty-display" data-allocation-display>{{ $allocatedQuantity > 0 ? $allocatedQuantity.' '.($requestItem->uom ?? $requestItem->inventoryItem->uom) : '-' }}</span>
                            @else
                                {{ $allocatedQuantity ?: '-' }}
                            @endif
                        </td>
                        <td class="price">{{ $canSeePrices ? 'Rp'.number_format($quotationTotal, 0, ',', '.') : 'Disembunyikan' }}</td>
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
                        <td>
                            @if ($quotation->is_selected)
                                <div class="qty-choice">
                                    <span class="pill ok">Vendor digunakan</span>
                                    @if ($canChooseVendor)
                                        <button type="button" class="ghost mini-button" data-open-qty>Ubah Qty</button>
                                        <div class="qty-picker" data-qty-picker>
                                            <input type="number" min="0" max="{{ $quotation->available_quantity }}" value="{{ $allocatedQuantity }}" data-qty-value>
                                            <button type="button" class="mini-button" data-apply-qty>Terapkan</button>
                                            <button type="button" class="link-button" data-clear-qty>Kosongkan</button>
                                        </div>
                                    @endif
                                </div>
                            @elseif ($canChooseVendor && $quotation->available_quantity > 0)
                                <div class="qty-choice">
                                    <button type="button" class="mini-button" data-open-qty>Pilih Vendor</button>
                                    <div class="qty-picker" data-qty-picker>
                                        <input type="number" min="0" max="{{ $quotation->available_quantity }}" value="0" data-qty-value>
                                        <button type="button" class="mini-button" data-apply-qty>Terapkan</button>
                                        <button type="button" class="link-button" data-clear-qty>Kosongkan</button>
                                    </div>
                                </div>
                            @elseif ($quotation->available_quantity <= 0)
                                <span class="muted">Stok kosong</span>
                            @else
                                <span class="muted">Tidak ada akses pilih</span>
                            @endif
                        </td>
                        <td>{{ $quotation->notes ?: '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
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

    <section class="panel">
        <div class="panel-head">
            <div>
                <h2>Timeline Harga Vendor</h2>
            </div>
        </div>
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
    </section>
</main>
@include('inventory.components.confirm-submit')
<script>
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
        });

        form.addEventListener('click', (event) => {
            if (! event.target.matches('[data-remove-vendor]')) {
                return;
            }

            event.target.closest('[data-vendor-row]').remove();
        });
    });

    document.querySelectorAll('[data-open-qty]').forEach((button) => {
        button.addEventListener('click', () => {
            const picker = button.closest('td').querySelector('[data-qty-picker]');
            picker?.classList.toggle('is-open');
            picker?.querySelector('[data-qty-value]')?.focus();
        });
    });

    document.querySelectorAll('[data-apply-qty]').forEach((button) => {
        button.addEventListener('click', () => {
            const row = button.closest('tr');
            const picker = button.closest('[data-qty-picker]');
            const valueInput = picker.querySelector('[data-qty-value]');
            const hiddenInput = row.querySelector('[data-allocation-input]');
            const display = row.querySelector('[data-allocation-display]');
            const uom = hiddenInput?.dataset.uom || '';
            const value = Math.max(0, Number(valueInput.value || 0));
            const max = Number(valueInput.max || 0);
            const finalValue = Math.min(value, max);

            valueInput.value = finalValue;
            hiddenInput.value = finalValue;
            display.textContent = finalValue > 0 ? `${finalValue} ${uom}` : '-';
            picker.classList.remove('is-open');
            syncApprovalActions();
        });
    });

    document.querySelectorAll('[data-clear-qty]').forEach((button) => {
        button.addEventListener('click', () => {
            const row = button.closest('tr');
            const picker = button.closest('[data-qty-picker]');
            const valueInput = picker.querySelector('[data-qty-value]');
            const hiddenInput = row.querySelector('[data-allocation-input]');
            const display = row.querySelector('[data-allocation-display]');

            valueInput.value = 0;
            hiddenInput.value = 0;
            display.textContent = '-';
            picker.classList.remove('is-open');
            syncApprovalActions();
        });
    });

    syncApprovalActions();
</script>
</body>
</html>
