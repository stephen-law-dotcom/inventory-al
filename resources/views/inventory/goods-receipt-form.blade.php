<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Catat Penerimaan Gudang</title>
    <style>
        :root { --ink: #102a43; --muted: #63788d; --line: #d5e2e9; --soft: #f5f9fa; --accent: #087f78; --accent-dark: #056660; --warning: #9a6500; --warning-bg: #fff7df; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f1f6f8; color: var(--ink); font: 14px/1.45 Arial, sans-serif; }
        .page { width: min(1120px, calc(100% - 32px)); margin: 0 auto; padding: 28px 0; }
        .panel { overflow: hidden; border: 1px solid var(--line); border-radius: 8px; background: #fff; box-shadow: 0 12px 28px rgba(22, 48, 65, .08); }
        .panel-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 20px; padding: 24px 26px 18px; border-bottom: 1px solid var(--line); }
        .eyebrow { margin: 0 0 5px; color: var(--accent-dark); font-size: 12px; font-weight: 800; text-transform: uppercase; }
        h1, h2, p { margin: 0; }
        h1 { font-size: 28px; line-height: 1.2; }
        .document-number { margin-top: 8px; color: var(--muted); font-size: 15px; }
        .button, button { display: inline-flex; align-items: center; justify-content: center; min-height: 40px; border: 1px solid var(--line); border-radius: 6px; padding: 9px 15px; background: #f7fafb; color: var(--ink); font: inherit; font-weight: 800; text-decoration: none; cursor: pointer; }
        button { border-color: var(--accent); background: var(--accent); color: #fff; }
        button:hover { background: var(--accent-dark); }
        .summary { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1px; border-bottom: 1px solid var(--line); background: var(--line); }
        .summary-item { min-height: 74px; padding: 13px 18px; background: #fff; }
        .summary-item span { display: block; margin-bottom: 4px; color: var(--muted); font-size: 11px; font-weight: 800; text-transform: uppercase; }
        .summary-item strong { display: block; color: var(--ink); line-height: 1.35; }
        form { padding: 22px 26px 26px; }
        .notice { display: flex; gap: 10px; margin-bottom: 18px; padding: 11px 13px; border: 1px solid #f2d385; border-radius: 6px; background: var(--warning-bg); color: var(--warning); }
        .field-card { margin-bottom: 18px; padding: 17px; border: 1px solid var(--line); border-radius: 8px; background: var(--soft); }
        .field-card h2 { margin-bottom: 14px; font-size: 16px; }
        .field-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        label { display: grid; gap: 6px; color: var(--ink); font-weight: 800; }
        label span { font-size: 13px; }
        input, select { width: 100%; min-height: 42px; border: 1px solid #cbdbe3; border-radius: 6px; padding: 9px 11px; background: #fff; color: var(--ink); font: inherit; }
        input:focus, select:focus { outline: 2px solid #9eddd8; border-color: var(--accent); }
        .items-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: 22px 0 10px; }
        .items-head h2 { font-size: 17px; }
        .items-head span { color: var(--muted); font-weight: 700; }
        .item-card { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 13px; margin-top: 10px; padding: 16px; border: 1px solid var(--line); border-radius: 8px; background: #fff; }
        .item-number { display: grid; place-items: center; width: 30px; height: 30px; border-radius: 6px; background: #e4f5f2; color: var(--accent-dark); font-size: 12px; font-weight: 900; }
        .item-content { min-width: 0; }
        .item-title { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 13px; }
        .item-title h3 { margin: 0; font-size: 15px; line-height: 1.35; }
        .part-number { margin-top: 3px; color: var(--muted); font-size: 12px; }
        .remaining { flex: 0 0 auto; padding: 4px 8px; border: 1px solid #f2d385; border-radius: 999px; background: var(--warning-bg); color: var(--warning); font-size: 12px; font-weight: 800; }
        .item-fields { display: grid; grid-template-columns: minmax(130px, .65fr) minmax(180px, 1fr) minmax(200px, 1.35fr); gap: 12px; }
        .actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 22px; padding-top: 18px; border-top: 1px solid var(--line); }
        .errors { margin-bottom: 18px; padding: 12px 14px; border: 1px solid #ecb1aa; border-radius: 6px; background: #fff4f2; color: #a62d25; }
        .errors ul { margin: 6px 0 0; padding-left: 18px; }
        @media (max-width: 760px) {
            .page { width: min(100% - 22px, 1120px); padding: 12px 0; }
            .panel-head { padding: 20px 18px 16px; }
            .panel-head, .item-title { align-items: stretch; flex-direction: column; }
            h1 { font-size: 24px; }
            .summary, .field-grid, .item-fields { grid-template-columns: 1fr; }
            form { padding: 18px; }
            .item-card { grid-template-columns: 1fr; }
            .item-number { width: 28px; height: 28px; }
            .remaining { align-self: flex-start; }
            .actions { flex-direction: column-reverse; }
            .actions .button, .actions button { width: 100%; }
        }
    </style>
</head>
<body>
    <main class="page">
        <section class="panel">
            <header class="panel-head">
                <div>
                    <p class="eyebrow">Penerimaan Gudang</p>
                    <h1>Catat Penerimaan</h1>
                    <p class="document-number">{{ $purchaseOrder->number }} | {{ $purchaseOrder->vendor?->name }} | OR {{ $purchaseOrder->purchaseRequest?->number }}</p>
                </div>
                <a class="button" href="{{ route('receipts', array_filter(['company_id' => request()->integer('company_id') ?: null])) }}">Kembali</a>
            </header>

            <div class="summary">
                <div class="summary-item">
                    <span>Vendor</span>
                    <strong>{{ $purchaseOrder->vendor?->name ?? '-' }}</strong>
                </div>
                <div class="summary-item">
                    <span>Tujuan</span>
                    <strong>{{ $purchaseOrder->purchaseRequest?->camp?->name ?? 'Gudang Kantor' }}</strong>
                </div>
                <div class="summary-item">
                    <span>Referensi OR</span>
                    <strong>{{ $purchaseOrder->purchaseRequest?->number ?? '-' }}</strong>
                </div>
            </div>

            <form method="post" action="{{ route('goods-receipts.store', $purchaseOrder) }}" data-confirm="Simpan penerimaan {{ $purchaseOrder->number }}? Stok barang berkondisi baik akan bertambah dan barang bermasalah akan dibuatkan draft Credit Note.">
                @csrf

                @if ($errors->any())
                    <div class="errors">
                        Data belum dapat disimpan.
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <section class="field-card">
                    <h2>Informasi Penerimaan</h2>
                    <div class="field-grid">
                        <label>
                            <span>No. faktur vendor</span>
                            <input name="invoice_number" value="{{ old('invoice_number') }}" required placeholder="Contoh: INV-2026-001">
                        </label>
                        <label>
                            <span>Diterima oleh</span>
                            <input name="received_by" value="{{ old('received_by', auth()->user()?->name) }}" required>
                        </label>
                    </div>
                </section>

                <div class="notice">Barang dengan kondisi selain baik akan dicatat sebagai masalah penerimaan dan dibuatkan draft pengajuan Credit Note.</div>

                <div class="items-head">
                    <h2>Rincian Barang</h2>
                    <span>{{ $purchaseOrder->items->count() }} jenis barang</span>
                </div>

                @foreach ($purchaseOrder->items as $purchaseOrderItem)
                    @php
                        $receivedQuantity = $purchaseOrderItem->goodsReceiptItems->sum('quantity_received');
                        $remainingQuantity = max(0, $purchaseOrderItem->quantity_ordered - $receivedQuantity);
                    @endphp
                    <article class="item-card">
                        <div class="item-number">{{ $loop->iteration }}</div>
                        <div class="item-content">
                            <div class="item-title">
                                <div>
                                    <h3>{{ $purchaseOrderItem->inventoryItem?->name ?? 'Barang' }}</h3>
                                    <p class="part-number">{{ $purchaseOrderItem->inventoryItem?->sku ?? '-' }}</p>
                                </div>
                                <span class="remaining">Sisa {{ $remainingQuantity }} {{ $purchaseOrderItem->uom }}</span>
                            </div>

                            <input type="hidden" name="items[{{ $loop->index }}][purchase_order_item_id]" value="{{ $purchaseOrderItem->id }}">
                            <div class="item-fields">
                                <label>
                                    <span>Qty diterima</span>
                                    <input name="items[{{ $loop->index }}][quantity_received]" type="number" min="0" max="{{ $remainingQuantity }}" value="{{ old('items.'.$loop->index.'.quantity_received', $remainingQuantity) }}">
                                </label>
                                <label>
                                    <span>Kondisi</span>
                                    <select name="items[{{ $loop->index }}][condition]" data-condition>
                                        <option value="good" @selected(old('items.'.$loop->index.'.condition', 'good') === 'good')>Baik</option>
                                        <option value="damaged" @selected(old('items.'.$loop->index.'.condition') === 'damaged')>Rusak</option>
                                        <option value="wrong_item" @selected(old('items.'.$loop->index.'.condition') === 'wrong_item')>Salah barang</option>
                                        <option value="quantity_short" @selected(old('items.'.$loop->index.'.condition') === 'quantity_short')>Qty kurang</option>
                                        <option value="spec_mismatch" @selected(old('items.'.$loop->index.'.condition') === 'spec_mismatch')>Tidak sesuai spesifikasi</option>
                                        <option value="other" @selected(old('items.'.$loop->index.'.condition') === 'other')>Lainnya</option>
                                    </select>
                                </label>
                                <label>
                                    <span>Catatan barang</span>
                                    <input name="items[{{ $loop->index }}][notes]" value="{{ old('items.'.$loop->index.'.notes') }}" placeholder="Opsional untuk kondisi baik" data-issue-note>
                                </label>
                            </div>
                        </div>
                    </article>
                @endforeach

                <div class="actions">
                    <a class="button" href="{{ route('receipts', array_filter(['company_id' => request()->integer('company_id') ?: null])) }}">Batal</a>
                    <button type="submit">Simpan Penerimaan</button>
                </div>
            </form>
        </section>
    </main>
    <script>
        document.querySelectorAll('.item-card').forEach((card) => {
            const condition = card.querySelector('[data-condition]');
            const note = card.querySelector('[data-issue-note]');
            const syncNote = () => {
                note.required = condition.value !== 'good';
                note.placeholder = note.required ? 'Jelaskan kondisi barang' : 'Opsional untuk kondisi baik';
            };
            condition.addEventListener('change', syncNote);
            syncNote();
        });
    </script>
    @include('inventory.components.confirm-submit')
</body>
</html>
