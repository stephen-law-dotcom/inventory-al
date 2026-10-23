<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit {{ $purchaseOrder->number }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=2">
    <style>
        :root { --bg:#f4f7f9; --panel:#fff; --ink:#172534; --navy:#102a43; --muted:#667687; --line:#dce4e9; --accent:#087f78; --accent-dark:#06645f; --green:#16794b; --danger:#b42318; --shadow:0 4px 16px rgba(28,46,61,.08); }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; background:var(--bg); color:var(--ink); font-family:Inter, "Segoe UI", Roboto, Arial, sans-serif; font-size:14px; line-height:1.45; }
        a { color:inherit; text-decoration:none; }
        .page { width:min(1180px, calc(100% - 32px)); margin:0 auto; padding:20px 0 38px; display:grid; gap:14px; }
        .topbar, section { background:var(--panel); border:1px solid var(--line); border-radius:7px; box-shadow:var(--shadow); }
        .topbar { padding:17px 19px; display:flex; align-items:flex-start; justify-content:space-between; gap:16px; border-top:4px solid var(--accent); }
        h1 { margin:0; color:var(--navy); font-size:24px; }
        h2 { margin:0 0 12px; color:var(--navy); font-size:16px; }
        p { margin:5px 0 0; color:var(--muted); }
        section { padding:19px; }
        .button, button { display:inline-flex; align-items:center; justify-content:center; min-height:40px; border:1px solid transparent; border-radius:6px; padding:9px 14px; background:var(--accent); color:#fff; font:inherit; font-weight:800; cursor:pointer; }
        .button:hover, button:hover { background:var(--accent-dark); }
        .ghost { background:#f3f6f8; color:#24394d; border-color:var(--line); }
        .form-grid { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:12px; }
        label { display:grid; gap:6px; color:#4d6070; font-size:12px; font-weight:800; }
        input, select, textarea { width:100%; min-height:41px; border:1px solid var(--line); border-radius:6px; padding:10px 11px; background:#fff; color:var(--ink); font:inherit; outline:none; }
        textarea { min-height:72px; resize:vertical; }
        .full { grid-column:1 / -1; }
        .notice { padding:12px 14px; border:1px solid #f0b8b4; border-radius:7px; background:#fff4f2; color:var(--danger); font-weight:700; }
        .meta { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:10px; }
        .meta div { padding:12px; border:1px solid var(--line); border-radius:7px; background:#fbfdfe; }
        .meta span { display:block; color:var(--muted); font-size:11px; font-weight:900; text-transform:uppercase; }
        .meta strong { display:block; margin-top:3px; }
        .table-wrap { overflow-x:auto; }
        table { width:100%; min-width:860px; border-collapse:collapse; }
        th, td { padding:11px 12px; border-top:1px solid var(--line); text-align:left; vertical-align:middle; }
        th { color:#4b6172; background:#f9fbfc; font-size:11px; text-transform:uppercase; white-space:nowrap; }
        tbody tr.selected { background:#f0faf8; }
        .check { width:18px; height:18px; accent-color:var(--accent); }
        .item-name { font-weight:800; }
        .spec, .muted { color:var(--muted); font-size:12px; }
        .price { font-variant-numeric:tabular-nums; white-space:nowrap; }
        .summary { position:sticky; bottom:0; z-index:20; display:flex; align-items:center; justify-content:space-between; gap:16px; border-top:4px solid var(--accent); }
        .summary strong { color:var(--navy); font-size:18px; }
        .actions { display:flex; gap:9px; justify-content:flex-end; flex-wrap:wrap; }
        .empty { padding:24px; border:1px dashed #bbcbd3; border-radius:7px; color:var(--muted); text-align:center; }
        @media (max-width:820px) {
            .topbar, .summary { align-items:flex-start; flex-direction:column; }
            .form-grid, .meta { grid-template-columns:1fr; }
            .full { grid-column:auto; }
            .actions, .actions .button, .actions button { width:100%; }
        }

        /* Edit polish */
        body { background:linear-gradient(180deg, #fbfcfd 0, var(--bg) 240px); }
        .topbar, section, .meta div { border-color:#d7e2e8; }
        .topbar > div { min-width:0; }
        .topbar h1, .topbar p { overflow-wrap:anywhere; }
        section { position:relative; overflow:visible; }
        section::before { content:""; position:absolute; inset:0 0 auto; height:3px; background:linear-gradient(90deg, var(--accent), #e3a72f); }
        .meta div { background:#fbfcfd; }
        .meta div:hover { border-color:#c7d2da; background:#fff; }
        input:focus, select:focus, textarea:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(8,127,120,.12); }
        tbody tr:hover { background:#edf8f7; }
        button, .button { white-space:nowrap; }
        .summary { border-radius:7px; }
        @media (max-width:820px) {
            .page { width:min(100% - 24px, 1180px); padding-top:14px; }
            section { padding:16px; }
        }
    </style>
</head>
<body>
@php
    $returnContext = request()->query('from');
    $returnCompanyId = request()->integer('company_id') ?: null;
    $returnContext = in_array($returnContext, ['active', 'approval', 'history', 'approvals', 'dashboard', 'reports'], true)
        ? $returnContext
        : 'active';
    $contextQuery = array_filter(['from' => $returnContext, 'company_id' => $returnCompanyId]);
    $reviewUrl = route('purchase-orders.review', array_merge(['purchaseOrder' => $purchaseOrder], $contextQuery));
    $listUrl = match ($returnContext) {
        'approvals' => route('approvals', array_filter(['company_id' => $returnCompanyId])),
        'dashboard' => route('dashboard', array_filter(['company_id' => $returnCompanyId])),
        'reports' => route('reports'),
        default => route('purchase-orders', array_filter([
            'tab' => $returnContext === 'active' ? null : $returnContext,
            'company_id' => $returnCompanyId,
        ])),
    };
@endphp
<main class="page">
    <header class="topbar">
        <div>
            <h1>Edit Draft Purchase Order</h1>
            <p>{{ $purchaseOrder->number }} hanya bisa diedit selama status masih draft. Vendor PO tetap {{ $purchaseOrder->vendor->name }}.</p>
        </div>
        <div class="actions">
            <a class="button ghost" href="{{ $reviewUrl }}">Kembali Review</a>
            <a class="button ghost" href="{{ $listUrl }}">Daftar PO</a>
        </div>
    </header>

    @if ($errors->any())
        <div class="notice">{{ $errors->first() }}</div>
    @endif

    <section>
        <div class="meta">
            <div><span>Nomor PO</span><strong>{{ $purchaseOrder->number }}</strong></div>
            <div><span>Vendor</span><strong>{{ $purchaseOrder->vendor->name }}</strong></div>
            <div><span>Order Request</span><strong>{{ $purchaseOrder->purchaseRequest->number }} - {{ $purchaseOrder->purchaseRequest->camp->name }}</strong></div>
        </div>
    </section>

    <form method="post" action="{{ route('purchase-orders.update', array_merge(['purchaseOrder' => $purchaseOrder], $contextQuery)) }}" data-po-form>
        @csrf
        @method('put')
        <section>
            <div class="form-grid">
                <label>Tanggal PO
                    <input type="date" name="ordered_at" value="{{ old('ordered_at', $purchaseOrder->ordered_at->toDateString()) }}" required>
                </label>
                <label>Estimasi tiba
                    <input type="date" name="expected_at" value="{{ old('expected_at', $purchaseOrder->expected_at?->toDateString()) }}">
                </label>
                <label>Tujuan pengiriman
                    <select name="delivery_destination" required>
                        <option value="warehouse" @selected(old('delivery_destination', $purchaseOrder->delivery_destination) === 'warehouse')>Gudang kantor</option>
                        <option value="direct_site" @selected(old('delivery_destination', $purchaseOrder->delivery_destination) === 'direct_site')>Langsung ke site</option>
                    </select>
                </label>
                <label>Barang dipilih
                    <input value="{{ $purchaseOrder->items->count() }} item" readonly>
                </label>
                @if ($canSeePrices)
                    <label>Diskon (%)
                        <input type="number" name="discount_percent" value="{{ old('discount_percent', number_format((float) $purchaseOrder->discount_percent, 2, '.', '')) }}" min="0" max="100" step="0.01" data-discount-percent>
                    </label>
                    <label>PPN (%)
                        <input type="number" name="ppn_percent" value="{{ old('ppn_percent', number_format((float) $purchaseOrder->ppn_percent, 2, '.', '')) }}" min="0" max="100" step="0.01" data-ppn-percent>
                    </label>
                @endif
                <label class="full">Catatan PO
                    <textarea name="notes">{{ old('notes', $purchaseOrder->notes) }}</textarea>
                </label>
            </div>
        </section>

        <section>
            <h2>Item PO</h2>
            @if ($purchaseOrder->items->isNotEmpty())
                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>Unit</th>
                            <th>Barang</th>
                            <th>Qty</th>
                            <th>Merek / No Part</th>
                            <th>Lead Time</th>
                            @if ($canSeePrices)<th>Harga</th><th>Total</th>@endif
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($purchaseOrder->items as $item)
                            <tr>
                                <td>{{ $item->requestItem?->unit?->code ?: '-' }}</td>
                                <td>
                                    <div class="item-name">{{ $item->inventoryItem->sku }} - {{ $item->inventoryItem->name }}</div>
                                    @if ($item->requestItem?->specification)<div class="spec">{{ $item->requestItem->specification }}</div>@endif
                                </td>
                                <td>{{ number_format($item->quantity_ordered, 0, ',', '.') }} {{ $item->uom }}</td>
                                <td>{{ $item->brand_name ?: '-' }}<div class="spec">{{ $item->part_number ?: 'No part belum diisi' }}</div></td>
                                <td>{{ $purchaseOrder->ordered_at->diffInDays($purchaseOrder->expected_at ?? $purchaseOrder->ordered_at) }} hari</td>
                                @if ($canSeePrices)
                                    <td class="price"><input type="number" name="unit_prices[{{ $item->id }}]" value="{{ old('unit_prices.'.$item->id, number_format((float) $item->unit_price, 2, '.', '')) }}" min="0.01" step="0.01" data-unit-price data-quantity="{{ $item->quantity_ordered }}" aria-label="Harga satuan {{ $item->inventoryItem->name }}"></td>
                                    <td><strong data-row-total>Rp{{ number_format((float) $item->total, 0, ',', '.') }}</strong></td>
                                @endif
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty">PO belum memiliki item.</div>
            @endif
        </section>

        <section class="summary">
            <div>
                <p>Item mengikuti alokasi vendor yang telah disetujui</p>
                <strong data-summary>{{ $purchaseOrder->items->count() }} item{{ $canSeePrices ? ', Rp'.number_format((float) $purchaseOrder->grand_total, 0, ',', '.') : '' }}</strong>
                @if ($canSeePrices)<p data-financial-summary></p>@endif
            </div>
            <div class="actions">
                <a class="button ghost" href="{{ $reviewUrl }}">Batal</a>
                <button type="submit">Simpan Perubahan Draft</button>
            </div>
        </section>
    </form>
</main>
@if ($canSeePrices)
<script>
    const form = document.querySelector('[data-po-form]');
    const currency = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
    function refreshTotals() {
        let subtotal = 0;
        form.querySelectorAll('[data-unit-price]').forEach((input) => {
            const total = Number(input.value || 0) * Number(input.dataset.quantity || 0);
            subtotal += total;
            input.closest('tr').querySelector('[data-row-total]').textContent = `Rp${currency.format(total)}`;
        });
        const discount = subtotal * Number(form.querySelector('[data-discount-percent]').value || 0) / 100;
        const ppn = Math.max(0, subtotal - discount) * Number(form.querySelector('[data-ppn-percent]').value || 0) / 100;
        form.querySelector('[data-summary]').textContent = `{{ $purchaseOrder->items->count() }} item, Rp${currency.format(subtotal - discount + ppn)}`;
        form.querySelector('[data-financial-summary]').textContent = `Subtotal Rp${currency.format(subtotal)} · Diskon Rp${currency.format(discount)} · PPN Rp${currency.format(ppn)}`;
    }
    form.addEventListener('input', refreshTotals);
    refreshTotals();
</script>
@endif
</body>
</html>
