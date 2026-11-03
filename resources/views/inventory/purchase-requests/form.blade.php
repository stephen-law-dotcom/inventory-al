<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $purchaseRequest ? 'Edit' : 'Tambah' }} Order Request</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=2">
    <style>
        :root { --bg:#eef6f8; --panel:#fff; --ink:#17212b; --muted:#667788; --line:#dce8ee; --accent:#0b8f8a; --accent-dark:#066d70; --danger:#b42318; --shadow:0 16px 36px rgba(16,42,67,.11); }
        * { box-sizing: border-box; }
        body { margin:0; min-height:100vh; background:linear-gradient(135deg, rgba(11,143,138,.11), transparent 35%), linear-gradient(315deg, rgba(227,167,47,.10), transparent 32%), var(--bg); color:var(--ink); font-family:Inter, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; font-size:14px; line-height:1.45; -webkit-font-smoothing:antialiased; text-rendering:optimizeLegibility; }
        a { color:inherit; text-decoration:none; }
        .page { width:min(1120px, calc(100% - 28px)); margin:0 auto; padding:24px 0; display:grid; gap:16px; }
        .topbar, section { background:var(--panel); border:1px solid var(--line); border-radius:8px; box-shadow:var(--shadow); }
        .topbar { padding:18px; display:flex; align-items:center; justify-content:space-between; gap:12px; background:linear-gradient(135deg, rgba(255,255,255,.96), rgba(255,255,255,.86)), linear-gradient(90deg, rgba(11,143,138,.13), rgba(227,167,47,.12)); }
        h1 { margin:0; font-size:26px; color:#102a43; font-weight:900; }
        h2 { margin:0 0 14px; font-size:17px; color:#102a43; font-weight:900; }
        p { margin:6px 0 0; color:var(--muted); line-height:1.45; }
        section { padding:18px; }
        form, .stack { display:grid; gap:12px; }
        label { display:grid; gap:6px; color:#526677; font-size:12px; font-weight:800; }
        input, select, textarea { width:100%; border:1px solid var(--line); border-radius:8px; padding:11px 12px; background:#fff; color:var(--ink); font:inherit; outline:none; }
        input:focus, select:focus, textarea:focus { border-color:var(--accent); box-shadow:0 0 0 4px rgba(11,143,138,.14); }
        textarea { min-height:86px; resize:vertical; }
        button, .button { display:inline-flex; align-items:center; justify-content:center; min-height:40px; border:0; border-radius:8px; padding:10px 14px; background:linear-gradient(135deg, var(--accent), #17b8ad); color:#fff; font-weight:800; cursor:pointer; box-shadow:0 10px 18px rgba(11,143,138,.22); }
        .ghost { background:#edf4f7; color:#24394d; box-shadow:none; border:1px solid var(--line); }
        .row { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:12px; }
        .detail-row { display:grid; grid-template-columns:1.1fr 1.35fr .45fr .7fr 1.35fr auto; gap:10px; align-items:end; padding:12px; border:1px solid var(--line); border-radius:8px; background:#f8fbfc; }
        .item-actions { display:flex; gap:8px; align-items:center; }
        .item-actions button { padding-inline:12px; }
        .readonly-value { min-height:41px; display:flex; align-items:center; padding:10px 11px; border:1px solid var(--line); border-radius:6px; background:#eef3f5; color:#34495a; font-weight:800; }
        .item-select-wrap { display:grid; gap:8px; align-items:end; }
        .quick-item { grid-column:1 / -1; display:none; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:10px; padding:12px; border:1px dashed var(--accent); border-radius:6px; background:#f4fbfa; }
        .quick-item.is-open { display:grid; }
        .quick-item .wide { grid-column:span 2; }
        .hint { color:var(--muted); font-size:12px; }
        .actions { display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end; }
        .notice { padding:12px 14px; border-radius:8px; border:1px solid #f4aaa5; color:var(--danger); background:#fff6f4; font-weight:700; }
        :root { --bg:#f4f7f9; --ink:#172534; --muted:#667687; --line:#dce4e9; --accent:#087f78; --accent-dark:#06645f; --shadow:0 4px 16px rgba(28,46,61,.08); }
        body { background:var(--bg); }
        .page { width:min(1180px, calc(100% - 32px)); padding:20px 0 36px; gap:14px; }
        .topbar, section { border-radius:7px; box-shadow:var(--shadow); }
        .topbar { padding:16px 18px; background:#fff; }
        h1 { font-size:23px; font-weight:800; }
        h2 { font-size:15px; padding-bottom:10px; border-bottom:1px solid var(--line); }
        section { padding:20px; }
        form, .stack { gap:14px; }
        label { color:#4d6070; font-weight:700; }
        input, select, textarea { min-height:41px; border-radius:6px; padding:10px 11px; }
        input:focus, select:focus, textarea:focus { box-shadow:0 0 0 3px rgba(8,127,120,.12); }
        button, .button { min-height:39px; border-radius:6px; background:var(--accent); box-shadow:none; font-size:13px; }
        button:hover, .button:hover { background:var(--accent-dark); }
        .ghost { background:#f3f6f8; }
        .detail-row { padding:14px; border-radius:6px; background:#f8fafb; }
        .actions { padding-top:12px; border-top:1px solid var(--line); }
        @media (max-width: 860px) { .detail-row, .quick-item { grid-template-columns:1fr; } .quick-item .wide { grid-column:auto; } .item-actions { justify-content:flex-end; } }
        @media (max-width: 720px) { .topbar { align-items:flex-start; flex-direction:column; } .row { grid-template-columns:1fr; } .actions { align-items:stretch; flex-direction:column; } button, .button { width:100%; } }

        /* Form polish */
        body { background: linear-gradient(180deg, #fbfcfd 0, var(--bg) 240px); }
        .page { width:min(1240px, calc(100% - 32px)); }
        .topbar {
            align-items:flex-start;
            border-top:4px solid var(--accent);
        }
        .topbar > div { min-width:0; }
        .topbar h1,
        .topbar p { overflow-wrap:anywhere; }
        section {
            position:relative;
            overflow:hidden;
        }
        section::before {
            content:"";
            position:absolute;
            inset:0 0 auto;
            height:3px;
            background:linear-gradient(90deg, var(--accent), #e3a72f);
        }
        .detail-row {
            border-color:#d7e2e8;
            background:#fbfcfd;
            align-items:end;
            transition:border-color .16s ease, background .16s ease;
        }
        .detail-row:hover {
            border-color:#c7d2da;
            background:#fff;
        }
        .quick-item {
            border-color:#73bbb5;
            background:#f3faf9;
        }
        input,
        select,
        textarea {
            transition:border-color .16s ease, box-shadow .16s ease, background .16s ease;
        }
        button,
        .button {
            white-space:nowrap;
        }
        @media (max-width: 720px) {
            .page { width:min(100% - 24px, 1240px); padding-top:14px; }
            section { padding:16px; }
            .detail-row { padding:12px; }
        }
    </style>
</head>
<body>
@php
    $isEdit = (bool) $purchaseRequest;
    $hasOldItems = old('items') !== null;
    $oldRows = collect(old('items', []))->map(fn ($row) => (object) array_merge([
        'id' => null,
        'unit_id' => null,
        'inventory_item_id' => null,
        'quantity' => 1,
        'uom' => null,
        'specification' => null,
    ], $row));
    $rows = $hasOldItems ? $oldRows : ($isEdit ? $purchaseRequest->items : collect([null]));
    $defaultItemId = $hasOldItems ? old('items.0.inventory_item_id') : ($isEdit ? null : $items->first()?->id);
    $companyQuery = ! empty($activeCompanyId) ? ['company_id' => $activeCompanyId] : [];
@endphp
<div class="page">
    <header class="topbar">
        <div>
            <h1>{{ $isEdit ? 'Edit Draft Order Request' : 'Tambah Order Request' }}</h1>
            <p>Nomor OR: <strong>{{ $isEdit ? $purchaseRequest->number : 'Dibuat setelah disimpan' }}</strong></p>
        </div>
        <a class="button ghost" href="{{ route('requests', $companyQuery) }}">Kembali</a>
    </header>

    @if ($errors->any())
        <div class="notice">{{ $errors->first() }}</div>
    @endif

    <section>
        <h2>Informasi Pengajuan</h2>
        <form method="post" action="{{ $isEdit ? route('purchase-requests.update', $purchaseRequest) : route('purchase-requests.store') }}" data-request-form>
            @csrf
            @if ($isEdit)
                @method('put')
            @endif
            @if (! empty($activeCompanyId))
                <input type="hidden" name="company_id" value="{{ $activeCompanyId }}">
            @endif
            <div class="row">
                <label>Site Pengaju
                    <select name="camp_id" required data-camp-select>
                        @foreach ($camps as $camp)
                            <option value="{{ $camp->id }}" @selected((int) old('camp_id', $purchaseRequest->camp_id ?? 0) === $camp->id)>{{ $camp->company_name }} - {{ $camp->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Diajukan oleh
                    <input value="{{ $purchaseRequest?->requested_by ?? auth()->user()?->name ?? '-' }}" readonly aria-readonly="true">
                </label>
            </div>
            <label>Keperluan <textarea name="purpose">{{ old('purpose', $purchaseRequest->purpose ?? '') }}</textarea></label>

            <h2>Detail Unit dan Barang</h2>
            <div class="stack" data-item-list>
                @foreach ($rows as $requestItem)
                    @php
                        $selectedInventoryItemId = old("items.{$loop->index}.inventory_item_id", $requestItem->inventory_item_id ?? $defaultItemId);
                        $selectedInventoryItem = $items->firstWhere('id', (int) $selectedInventoryItemId);
                        $selectedUom = $selectedInventoryItem?->uom ?? $requestItem?->uom ?? '';
                    @endphp
                    <div class="detail-row" data-item-row>
                        @if ($requestItem?->id)
                            <input type="hidden" name="items[{{ $loop->index }}][id]" value="{{ $requestItem->id }}">
                        @endif
                        <label>Unit
                            <select name="items[{{ $loop->index }}][unit_id]" required data-unit-select>
                                @foreach ($units as $unit)
                                    <option value="{{ $unit->id }}" data-camp-id="{{ $unit->camp_id }}" @selected((int) old("items.{$loop->parent->index}.unit_id", $requestItem->unit_id ?? 0) === $unit->id)>{{ $unit->code }} - {{ $unit->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Barang
                            <select name="items[{{ $loop->index }}][inventory_item_id]" data-item-select>
                                <option value="" @selected($selectedInventoryItemId === '' || $selectedInventoryItemId === null)>Barang baru / belum ada master</option>
                                @foreach ($items as $item)
                                    <option value="{{ $item->id }}" data-uom="{{ $item->uom }}" @selected((int) $selectedInventoryItemId === $item->id)>{{ $item->sku }} - {{ $item->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Qty <input name="items[{{ $loop->index }}][quantity]" type="number" min="1" value="{{ old("items.{$loop->index}.quantity", $requestItem->quantity ?? 1) }}" required></label>
                        <label>Satuan <span class="readonly-value" data-uom-display>{{ $selectedUom ?: 'Pilih barang' }}</span></label>
                        <label>Spesifikasi <input name="items[{{ $loop->index }}][specification]" value="{{ old("items.{$loop->index}.specification", $requestItem->specification ?? '') }}"></label>
                        <div class="item-actions">
                            <button type="button" class="ghost" data-add-item>Tambah Barang</button>
                            <button type="button" class="ghost" data-remove-item>Hapus</button>
                        </div>
                        <div class="quick-item" data-new-item-fields>
                            <label>Part Number <input name="items[{{ $loop->index }}][new_part_number]" value="{{ old("items.{$loop->index}.new_part_number") }}" placeholder="Contoh: EL-BAT-100"></label>
                            <label>Nama Barang <input name="items[{{ $loop->index }}][new_item_name]" value="{{ old("items.{$loop->index}.new_item_name") }}" placeholder="Contoh: Battery N100 12V"></label>
                            <label>Kategori
                                <select name="items[{{ $loop->index }}][new_item_category_id]">
                                    @foreach ($itemCategories as $category)
                                        <option value="{{ $category->id }}" @selected((int) old("items.{$loop->parent->index}.new_item_category_id", $itemCategories->first()?->id) === $category->id)>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>Satuan
                                <select name="items[{{ $loop->index }}][new_uom]">
                                    @foreach ($uoms as $uom)
                                        <option value="{{ $uom->code }}" @selected(old("items.{$loop->parent->index}.new_uom", old("items.{$loop->parent->index}.uom", $uoms->first()?->code)) === $uom->code)>{{ $uom->code }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>Deskripsi <input name="items[{{ $loop->index }}][new_description]" value="{{ old("items.{$loop->index}.new_description") }}" placeholder="Keterangan singkat"></label>
                            <div class="hint wide">Jika barang belum ada di master, lengkapi data barang dan satuannya di sini.</div>
                        </div>
                    </div>
                @endforeach
            </div>

            <template data-item-template>
                <div class="detail-row" data-item-row>
                    <label>Unit
                        <select name="items[__INDEX__][unit_id]" required data-unit-select>@foreach ($units as $unit)<option value="{{ $unit->id }}" data-camp-id="{{ $unit->camp_id }}">{{ $unit->code }} - {{ $unit->name }}</option>@endforeach</select>
                    </label>
                    <label>Barang
                        <select name="items[__INDEX__][inventory_item_id]" data-item-select><option value="">Barang baru / belum ada master</option>@foreach ($items as $item)<option value="{{ $item->id }}" data-uom="{{ $item->uom }}" @selected($loop->first)>{{ $item->sku }} - {{ $item->name }}</option>@endforeach</select>
                    </label>
                    <label>Qty <input name="items[__INDEX__][quantity]" type="number" min="1" value="1" required></label>
                    <label>Satuan <span class="readonly-value" data-uom-display>{{ $items->first()?->uom ?? 'Pilih barang' }}</span></label>
                    <label>Spesifikasi <input name="items[__INDEX__][specification]"></label>
                    <div class="item-actions">
                        <button type="button" class="ghost" data-add-item>Tambah Barang</button>
                        <button type="button" class="ghost" data-remove-item>Hapus</button>
                    </div>
                    <div class="quick-item" data-new-item-fields>
                        <label>Part Number <input name="items[__INDEX__][new_part_number]" placeholder="Contoh: EL-BAT-100"></label>
                        <label>Nama Barang <input name="items[__INDEX__][new_item_name]" placeholder="Contoh: Battery N100 12V"></label>
                        <label>Kategori
                            <select name="items[__INDEX__][new_item_category_id]">@foreach ($itemCategories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select>
                        </label>
                        <label>Satuan <select name="items[__INDEX__][new_uom]">@foreach ($uoms as $uom)<option value="{{ $uom->code }}">{{ $uom->code }}</option>@endforeach</select></label>
                        <label>Deskripsi <input name="items[__INDEX__][new_description]" placeholder="Keterangan singkat"></label>
                        <div class="hint wide">Jika barang belum ada di master, lengkapi data barang dan satuannya di sini.</div>
                    </div>
                </div>
            </template>

            <div class="actions">
                <button name="action" value="draft">Simpan Draft</button>
                <button name="action" value="post">Posting</button>
            </div>
        </form>
    </section>
</div>
<script>
    document.querySelectorAll('[data-request-form]').forEach((form) => {
        const list = form.querySelector('[data-item-list]');
        const template = form.querySelector('[data-item-template]');
        const campSelect = form.querySelector('[data-camp-select]');
        let nextItemIndex = list.querySelectorAll('[data-item-row]').length;

        const syncUnitOptions = () => {
            const campId = campSelect.value;
            list.querySelectorAll('[data-unit-select]').forEach((select) => {
                const currentOption = select.selectedOptions[0];
                const currentIsValid = currentOption?.dataset.campId === campId;

                Array.from(select.options).forEach((option) => {
                    const isVisible = option.dataset.campId === campId;
                    option.disabled = ! isVisible;
                    option.hidden = ! isVisible;
                });

                if (! currentIsValid) {
                    const firstValidOption = Array.from(select.options).find((option) => ! option.disabled);
                    select.value = firstValidOption?.value || '';
                }
            });
        };

        campSelect.addEventListener('change', syncUnitOptions);

        form.addEventListener('click', (event) => {
            if (event.target.matches('[data-add-item]')) {
                const currentRow = event.target.closest('[data-item-row]');
                currentRow.insertAdjacentHTML('afterend', template.innerHTML.replaceAll('__INDEX__', nextItemIndex++));
                syncUnitOptions();
                return;
            }

            if (! event.target.matches('[data-remove-item]')) {
                return;
            }

            const rows = list.querySelectorAll('[data-item-row]');
            if (rows.length > 1) {
                event.target.closest('[data-item-row]').remove();
            }
        });

        form.addEventListener('change', (event) => {
            if (! event.target.matches('[data-item-select]')) {
                return;
            }

            const row = event.target.closest('[data-item-row]');
            const panel = row.querySelector('[data-new-item-fields]');
            const uomDisplay = row.querySelector('[data-uom-display]');
            const isNewItem = event.target.value === '';

            panel.classList.toggle('is-open', isNewItem);
            uomDisplay.textContent = isNewItem
                ? 'Diisi pada barang baru'
                : event.target.selectedOptions[0]?.dataset.uom || '-';
            if (isNewItem) {
                panel.querySelector('input')?.focus();
            }
        });

        list.querySelectorAll('[data-item-select]').forEach((select) => {
            const row = select.closest('[data-item-row]');
            const panel = row.querySelector('[data-new-item-fields]');
            const isNewItem = select.value === '';
            panel.classList.toggle('is-open', isNewItem);
            row.querySelector('[data-uom-display]').textContent = isNewItem
                ? 'Diisi pada barang baru'
                : select.selectedOptions[0]?.dataset.uom || '-';
        });

        form.addEventListener('submit', (event) => {
            if (event.submitter?.value !== 'post') {
                return;
            }

            if (! window.confirm('Posting Order Request sekarang? Dokumen akan masuk ke proses approval dan tidak lagi berstatus draft.')) {
                event.preventDefault();
            }
        });

        syncUnitOptions();
    });
</script>
</body>
</html>
