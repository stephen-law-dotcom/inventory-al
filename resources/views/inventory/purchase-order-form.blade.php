<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tambah Purchase Order</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=2">
    <style>
        :root {
            --bg:#f4f7f9; --panel:#fff; --ink:#172534; --navy:#102a43; --muted:#667687;
            --line:#dce4e9; --accent:#087f78; --accent-dark:#06645f; --amber:#e3a72f;
            --green:#16794b; --danger:#b42318; --shadow:0 4px 16px rgba(28,46,61,.08);
        }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; background:var(--bg); color:var(--ink); font-family:Inter, "Segoe UI", Roboto, Arial, sans-serif; font-size:14px; line-height:1.45; }
        a { color:inherit; text-decoration:none; }
        .page { width:min(1240px, calc(100% - 32px)); margin:0 auto; padding:20px 0 38px; display:grid; gap:14px; }
        .topbar, section { background:var(--panel); border:1px solid var(--line); border-radius:7px; box-shadow:var(--shadow); }
        .topbar { padding:17px 19px; display:flex; align-items:flex-start; justify-content:space-between; gap:16px; border-top:4px solid var(--accent); }
        h1 { margin:0; color:var(--navy); font-size:24px; letter-spacing:0; }
        h2 { margin:0; color:var(--navy); font-size:16px; letter-spacing:0; }
        h3 { margin:0; color:var(--navy); font-size:15px; letter-spacing:0; }
        p { margin:5px 0 0; color:var(--muted); }
        section { padding:19px; }
        .button, button { display:inline-flex; align-items:center; justify-content:center; min-height:40px; border:1px solid transparent; border-radius:6px; padding:9px 14px; background:var(--accent); color:#fff; font:inherit; font-weight:800; cursor:pointer; }
        .button:hover, button:hover { background:var(--accent-dark); }
        .ghost { background:#f3f6f8; color:#24394d; border-color:var(--line); }
        .form-grid { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:12px; }
        label { display:grid; gap:6px; color:#4d6070; font-size:12px; font-weight:800; }
        input, select, textarea { width:100%; min-height:41px; border:1px solid var(--line); border-radius:6px; padding:10px 11px; background:#fff; color:var(--ink); font:inherit; outline:none; }
        input:focus, select:focus, textarea:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(8,127,120,.12); }
        textarea { min-height:72px; resize:vertical; }
        .full { grid-column:1 / -1; }
        .notice { padding:12px 14px; border:1px solid #f0b8b4; border-radius:7px; background:#fff4f2; color:var(--danger); font-weight:700; }
        .flow { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:10px; }
        .flow-card { padding:13px; border:1px solid var(--line); border-radius:7px; background:#fbfdfe; }
        .flow-card strong { display:block; color:var(--navy); }
        .request { margin-top:14px; border:1px solid var(--line); border-radius:7px; overflow:hidden; }
        .request-head { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:13px 15px; background:#edf4f6; }
        .site { color:var(--muted); font-size:12px; }
        .vendor-group { border-top:1px solid var(--line); }
        .vendor-head { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:11px 12px; background:#f8fbfc; }
        .vendor-title { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
        .pill { display:inline-flex; align-items:center; min-height:23px; padding:3px 8px; border-radius:999px; background:#e8efef; color:#244047; font-size:11px; font-weight:900; }
        .ok { background:#dcf7ee; color:var(--green); }
        .wait { background:#fff1d6; color:#8a5a00; }
        .table-wrap { overflow-x:auto; }
        table { width:100%; min-width:860px; border-collapse:collapse; }
        th, td { padding:11px 12px; border-top:1px solid var(--line); text-align:left; vertical-align:middle; }
        th { color:#4b6172; background:#f9fbfc; font-size:11px; text-transform:uppercase; white-space:nowrap; }
        tbody tr.selected { background:#f0faf8; }
        .check { width:18px; height:18px; accent-color:var(--accent); }
        .item-name { font-weight:800; }
        .spec { margin-top:2px; color:var(--muted); font-size:12px; }
        .price { font-variant-numeric:tabular-nums; white-space:nowrap; }
        .summary { position:sticky; bottom:0; z-index:20; display:flex; align-items:center; justify-content:space-between; gap:16px; border-top:4px solid var(--accent); }
        .summary strong { color:var(--navy); font-size:18px; }
        .actions { display:flex; gap:9px; justify-content:flex-end; flex-wrap:wrap; }
        .empty { padding:28px; border:1px dashed #bbcbd3; border-radius:7px; color:var(--muted); text-align:center; }
        @media (max-width:860px) {
            .page { width:calc(100% - 20px); padding-top:10px; }
            .topbar, .request-head, .vendor-head, .summary { align-items:flex-start; flex-direction:column; }
            .form-grid, .flow { grid-template-columns:1fr; }
            .full { grid-column:auto; }
            .actions, .actions .button, .actions button { width:100%; }
        }

        /* Page polish */
        body { background:linear-gradient(180deg, #fbfcfd 0, var(--bg) 240px); }
        .topbar, section, .request, .flow-card { border-color:#d7e2e8; }
        .topbar > div { min-width:0; }
        .topbar h1, .topbar p { overflow-wrap:anywhere; }
        section { position:relative; overflow:visible; }
        section::before { content:""; position:absolute; inset:0 0 auto; height:3px; background:linear-gradient(90deg, var(--accent), var(--amber)); }
        .flow-card, .request { background:#fff; transition:border-color .16s ease, box-shadow .16s ease; }
        .flow-card:hover, .request:hover { border-color:#c7d2da; }
        .request { box-shadow:0 2px 8px rgba(28,46,61,.05); }
        .request-head, .vendor-head { border-bottom:1px solid var(--line); }
        .table-wrap { border-radius:6px; }
        tbody tr:hover { background:#edf8f7; }
        input[readonly] { background:#f8fafb; color:var(--muted); }
        button, .button { white-space:nowrap; }
        .summary { border-radius:7px; }
        @media (max-width:860px) {
            section { padding:16px; }
            .table-wrap table { min-width:820px; }
        }
    </style>
</head>
<body>
@php
    $companyQuery = ! empty($activeCompanyId) ? ['company_id' => $activeCompanyId] : [];
@endphp
<main class="page">
    <header class="topbar">
        <div>
            <h1>Tambah Purchase Order</h1>
        </div>
        <a class="button ghost" href="{{ route('purchase-orders', $companyQuery) }}">Kembali</a>
    </header>

    @if ($errors->any())
        <div class="notice">{{ $errors->first() }}</div>
    @endif

    <form method="post" action="{{ route('purchase-orders.store') }}" data-po-form>
        @csrf
        @if (! empty($activeCompanyId))
            <input type="hidden" name="company_id" value="{{ $activeCompanyId }}">
        @endif
        <section>
            <div class="form-grid">
                <label>Tanggal PO
                    <input type="date" name="ordered_at" value="{{ old('ordered_at', now()->toDateString()) }}" required>
                </label>
                <label>Estimasi tiba
                    <input type="date" name="expected_at" value="{{ old('expected_at') }}">
                </label>
                <label>Tujuan pengiriman
                    <select name="delivery_destination" required>
                        <option value="warehouse" @selected(old('delivery_destination', 'warehouse') === 'warehouse')>Gudang kantor</option>
                        <option value="direct_site" @selected(old('delivery_destination') === 'direct_site')>Langsung ke site</option>
                    </select>
                </label>
                <label>Barang dipilih
                    <input value="0 item" readonly data-selection-count>
                </label>
                <label>Estimasi PO draft
                    <input value="0 PO" readonly data-po-count>
                </label>
                @if ($canSeePrices)
                    <label>Diskon (%)
                        <input type="number" name="discount_percent" value="{{ old('discount_percent', '0.00') }}" min="0" max="100" step="0.01" data-discount-percent>
                    </label>
                    <label>PPN (%)
                        <input type="number" name="ppn_percent" value="{{ old('ppn_percent', '0.00') }}" min="0" max="100" step="0.01" data-ppn-percent>
                    </label>
                @endif
                <label class="full">Catatan PO
                    <textarea name="notes">{{ old('notes') }}</textarea>
                </label>
            </div>
        </section>

        <section>
            <h2>Barang Siap Dibuatkan PO</h2>
            @forelse ($purchaseRequests as $purchaseRequest)
                @php
                    $poLines = $purchaseRequest->items
                        ->flatMap(fn ($item) => $item->selectedQuotations->map(fn ($quotation) => [
                            'item' => $item,
                            'quotation' => $quotation,
                        ]))
                        ->values();
                @endphp
                <article class="request">
                    <div class="request-head">
                        <div>
                            <h3>{{ $purchaseRequest->number }} - {{ $purchaseRequest->camp->company_name }}</h3>
                            <div class="site">{{ $purchaseRequest->camp->name }} - {{ $purchaseRequest->requested_by }} - {{ $purchaseRequest->requested_at->format('d M Y') }}</div>
                        </div>
                        <strong>{{ $poLines->count() }} alokasi siap PO</strong>
                    </div>
                    @foreach ($poLines->groupBy(fn ($line) => $line['quotation']->vendor?->name ?? 'Vendor belum dipilih') as $vendorName => $vendorLines)
                        @php
                            $vendor = $vendorLines->first()['quotation']->vendor;
                            $groupKey = 'vendor-group-'.$purchaseRequest->id.'-'.$loop->index;
                        @endphp
                        <div class="vendor-group" data-vendor-group="{{ $groupKey }}">
                            <div class="vendor-head">
                                <div class="vendor-title">
                                    <span class="pill ok">Vendor final</span>
                                    <strong>{{ $vendorName }}</strong>
                                    <span class="pill">{{ $vendorLines->count() }} alokasi</span>
                                </div>
                                <button type="button" class="ghost" data-select-group="{{ $groupKey }}">Pilih semua vendor ini</button>
                            </div>
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                    <tr>
                                        <th>Pilih</th>
                                        <th>Unit</th>
                                        <th>Barang</th>
                                        <th>Qty</th>
                                        <th>Merek / No Part</th>
                                        <th>Lead Time</th>
                                        @if ($canSeePrices)<th>Harga</th><th>Total</th>@endif
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach ($vendorLines as $line)
                                        @php
                                            $item = $line['item'];
                                            $quotation = $line['quotation'];
                                            $allocatedQuantity = (int) ($quotation->selected_quantity ?: $item->approvedQuantity());
                                            $rowTotal = (float) $quotation->unit_price * $allocatedQuantity;
                                        @endphp
                                        <tr data-item-row>
                                            <td>
                                                <input class="check" type="checkbox" name="selected_quotations[]" value="{{ $quotation->id }}" data-item-check data-allocation-item="{{ $item->id }}" data-group="{{ $groupKey }}" data-po-group="{{ $groupKey }}" @checked(in_array($quotation->id, old('selected_quotations', [])))>
                                            </td>
                                            <td>{{ $item->unit?->code ?: '-' }}</td>
                                            <td>
                                                <div class="item-name">{{ $item->inventoryItem->sku }} - {{ $item->inventoryItem->name }}</div>
                                                @if ($item->specification)<div class="spec">{{ $item->specification }}</div>@endif
                                            </td>
                                            <td>{{ number_format($allocatedQuantity, 0, ',', '.') }} {{ $item->uom }}</td>
                                            <td>{{ $quotation?->brand_name ?: '-' }}<div class="spec">{{ $quotation?->part_number ?: 'No part belum diisi' }}</div></td>
                                            <td>{{ $quotation?->lead_time_days ?? 0 }} hari</td>
                                            @if ($canSeePrices)
                                                <td class="price"><input type="number" name="unit_prices[{{ $quotation->id }}]" value="{{ old('unit_prices.'.$quotation->id, number_format((float) $quotation->unit_price, 2, '.', '')) }}" min="0.01" step="0.01" data-unit-price data-quantity="{{ $allocatedQuantity }}" aria-label="Harga satuan {{ $item->inventoryItem->name }}"></td>
                                                <td><strong data-row-total data-total="{{ $rowTotal }}">Rp0</strong></td>
                                            @endif
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                </article>
            @empty
                <div class="empty">Belum ada barang yang selesai flow dan siap dibuatkan PO.</div>
            @endforelse
        </section>

        @if ($purchaseRequests->isNotEmpty())
            <section class="summary">
                <div>
                    <p>Total pilihan lintas vendor</p>
                    <strong data-summary>0 item{{ $canSeePrices ? ', Rp0' : '' }}</strong>
                    @if ($canSeePrices)<p data-financial-summary>Diskon Rp0 · PPN Rp0 · Grand total Rp0</p>@endif
                </div>
                <div class="actions">
                    <a class="button ghost" href="{{ route('purchase-orders', $companyQuery) }}">Batal</a>
                    <button class="ghost" type="submit" name="action" value="draft">Simpan Draft</button>
                    <button type="submit" name="action" value="post">Posting</button>
                </div>
            </section>
        @endif
    </form>
</main>
<script>
    const form = document.querySelector('[data-po-form]');
    const currency = new Intl.NumberFormat('id-ID');
    const canSeePrices = {{ $canSeePrices ? 'true' : 'false' }};

    function refreshSummary() {
        let count = 0;
        let total = 0;
        const selectedPoGroups = new Set();

        form.querySelectorAll('[data-item-row]').forEach((row) => {
            const check = row.querySelector('[data-item-check]');
            const totalLabel = row.querySelector('[data-row-total]');
            const priceInput = row.querySelector('[data-unit-price]');
            const rowTotal = priceInput
                ? Number(priceInput.value || 0) * Number(priceInput.dataset.quantity || 0)
                : Number(totalLabel?.dataset.total || 0);

            row.classList.toggle('selected', check.checked);
            if (totalLabel) {
                totalLabel.textContent = check.checked ? `Rp${currency.format(rowTotal)}` : 'Rp0';
            }
            if (check.checked) {
                count++;
                total += rowTotal;
                selectedPoGroups.add(check.dataset.poGroup);
            }
        });

        const selectionCount = form.querySelector('[data-selection-count]');
        const poCount = form.querySelector('[data-po-count]');
        const summary = form.querySelector('[data-summary]');
        if (selectionCount) {
            selectionCount.value = `${count} item`;
        }
        if (poCount) {
            poCount.value = `${selectedPoGroups.size} PO`;
        }
        if (summary) {
            const poText = `${selectedPoGroups.size} PO akan dibuat`;
            summary.textContent = canSeePrices
                ? `${count} item, ${poText}, Rp${currency.format(total)}`
                : `${count} item, ${poText}`;
        }
        const discountPercent = Number(form.querySelector('[data-discount-percent]')?.value || 0);
        const ppnPercent = Number(form.querySelector('[data-ppn-percent]')?.value || 0);
        const discount = total * discountPercent / 100;
        const ppn = Math.max(0, total - discount) * ppnPercent / 100;
        const financialSummary = form.querySelector('[data-financial-summary]');
        if (financialSummary) {
            financialSummary.textContent = `Diskon Rp${currency.format(discount)} · PPN Rp${currency.format(ppn)} · Grand total Rp${currency.format(total - discount + ppn)}`;
        }
    }

    function setItemAllocations(itemId, checked) {
        form.querySelectorAll(`[data-item-check][data-allocation-item="${itemId}"]`).forEach((check) => {
            check.checked = checked;
        });
    }

    form?.addEventListener('change', (event) => {
        const check = event.target.closest('[data-item-check]');
        if (check) {
            setItemAllocations(check.dataset.allocationItem, check.checked);
        }
        refreshSummary();
    });
    form?.addEventListener('input', (event) => {
        if (event.target.matches('[data-unit-price], [data-discount-percent], [data-ppn-percent]')) {
            refreshSummary();
        }
    });
    form?.addEventListener('click', (event) => {
        const groupButton = event.target.closest('[data-select-group]');
        if (! groupButton) {
            return;
        }

        const group = groupButton.dataset.selectGroup;
        const checks = form.querySelectorAll(`[data-item-check][data-group="${group}"]`);
        const shouldCheck = Array.from(checks).some((check) => ! check.checked);
        checks.forEach((check) => {
            setItemAllocations(check.dataset.allocationItem, shouldCheck);
        });
        refreshSummary();
    });
    form?.addEventListener('submit', (event) => {
        const selectedItems = form.querySelectorAll('[data-item-check]:checked');
        if (! selectedItems.length) {
            event.preventDefault();
            alert('Pilih minimal satu barang untuk dibuatkan PO.');
            return;
        }

        if (event.submitter?.value === 'post') {
            const poCount = new Set(Array.from(selectedItems, (check) => check.dataset.poGroup)).size;
            const confirmed = window.confirm(`Posting ${poCount} Purchase Order dari ${selectedItems.length} item? Setelah diposting, seluruh PO tidak dapat diedit dan akan masuk tahap approval.`);
            if (! confirmed) {
                event.preventDefault();
            }
        }
    });
    form?.querySelectorAll('[data-item-check]:checked').forEach((check) => setItemAllocations(check.dataset.allocationItem, true));
    refreshSummary();
</script>
</body>
</html>
