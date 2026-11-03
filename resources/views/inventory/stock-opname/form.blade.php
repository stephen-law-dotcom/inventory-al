<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $stockOpname ? 'Edit' : 'Buat' }} Stock Opname</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=2">
    <style>
        :root {
            --bg:#f2f7f9; --panel:#fff; --ink:#172534; --navy:#102a43; --muted:#667687;
            --line:#d8e4ea; --accent:#087f78; --accent-dark:#06645f; --danger:#b42318;
            --shadow:0 8px 24px rgba(16,42,67,.08);
        }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; background:var(--bg); color:var(--ink); font-family:Inter,"Segoe UI",Roboto,Arial,sans-serif; font-size:14px; line-height:1.45; }
        a { color:inherit; text-decoration:none; }
        .page { width:min(1400px, calc(100% - 32px)); margin:0 auto; padding:20px 0 40px; display:grid; gap:14px; }
        .topbar, section { background:var(--panel); border:1px solid var(--line); border-radius:7px; box-shadow:var(--shadow); }
        .topbar { min-height:88px; padding:18px 20px; display:flex; align-items:center; justify-content:space-between; gap:16px; border-top:4px solid var(--accent); }
        h1 { margin:0; color:var(--navy); font-size:25px; letter-spacing:0; }
        h2 { margin:0; color:var(--navy); font-size:17px; letter-spacing:0; }
        p { margin:4px 0 0; color:var(--muted); }
        section { padding:20px; }
        .button, button { display:inline-flex; align-items:center; justify-content:center; min-height:42px; padding:9px 15px; border:1px solid transparent; border-radius:6px; background:var(--accent); color:#fff; font:inherit; font-weight:800; cursor:pointer; }
        button:hover { background:var(--accent-dark); }
        .ghost { background:#f4f7f9; color:#24394d; border-color:var(--line); }
        .form-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px; align-items:start; }
        label { display:grid; gap:6px; color:#4d6070; font-size:12px; font-weight:800; }
        input, select, textarea { width:100%; min-height:43px; border:1px solid var(--line); border-radius:6px; padding:10px 11px; background:#fff; color:var(--ink); font:inherit; outline:none; }
        input:focus, select:focus, textarea:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(8,127,120,.12); }
        textarea { min-height:86px; resize:vertical; }
        .full { grid-column:1 / -1; }
        .field { position:relative; display:grid; gap:6px; min-width:0; }
        .field-label { color:#4d6070; font-size:12px; font-weight:800; }
        .multi-trigger { width:100%; min-height:43px; justify-content:space-between; background:#fff; color:var(--ink); border-color:var(--line); font-weight:500; text-align:left; }
        .multi-trigger:hover, .multi-trigger[aria-expanded="true"] { background:#f7fbfb; border-color:var(--accent); }
        .multi-trigger span:first-child { min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .multi-panel { position:absolute; z-index:30; top:calc(100% + 5px); left:0; right:0; max-height:260px; overflow:auto; padding:6px; border:1px solid var(--line); border-radius:6px; background:#fff; box-shadow:0 14px 34px rgba(16,42,67,.18); }
        .multi-option { display:flex; align-items:center; gap:9px; min-height:38px; padding:7px 9px; border-radius:5px; color:var(--ink); font-size:13px; font-weight:500; cursor:pointer; }
        .multi-option:hover { background:#edf8f7; }
        .multi-option input { flex:0 0 auto; width:18px; min-height:18px; height:18px; margin:0; accent-color:var(--accent); }
        .multi-option small { margin-left:auto; color:var(--muted); }
        .section-head { display:flex; align-items:center; justify-content:space-between; gap:14px; margin-bottom:14px; }
        .search { width:min(340px,100%); }
        .table-wrap { overflow:auto; border:1px solid var(--line); border-radius:6px; }
        table { width:100%; min-width:900px; border-collapse:collapse; }
        th, td { padding:11px 12px; border-bottom:1px solid var(--line); text-align:left; vertical-align:middle; }
        th { background:#edf3f6; color:#43596b; font-size:11px; text-transform:uppercase; white-space:nowrap; }
        tbody tr:last-child td { border-bottom:0; }
        tbody tr:hover { background:#f1faf9; }
        .item-name { font-weight:800; color:var(--navy); }
        .muted { color:var(--muted); font-size:12px; }
        .stock { font-weight:800; white-space:nowrap; }
        .select-item { width:18px; min-height:18px; height:18px; accent-color:var(--accent); }
        tr:not(.is-selected) [data-physical-stock], tr:not(.is-selected) [data-item-notes] { background:#f4f7f9; color:var(--muted); }
        .workflow-note { margin-bottom:14px; padding:11px 13px; border-left:4px solid var(--accent); background:#edf8f7; color:#31515a; }
        .notice { padding:12px 14px; border:1px solid #f0b8b4; border-radius:7px; background:#fff4f2; color:var(--danger); font-weight:700; }
        .summary { position:sticky; bottom:0; z-index:10; display:flex; justify-content:flex-end; gap:10px; border-top:4px solid var(--accent); }
        [hidden] { display:none !important; }
        @media (max-width:860px) {
            .page { width:calc(100% - 20px); padding-top:10px; }
            .topbar, .section-head, .summary { align-items:stretch; flex-direction:column; }
            .form-grid { grid-template-columns:1fr; }
            .full { grid-column:auto; }
            .search, .button, button { width:100%; }
        }
    </style>
</head>
<body>
@php
    $returnQuery = array_filter(['company_id' => $activeCompanyId]);
    $isEdit = (bool) $stockOpname;
    $existingItems = $stockOpname?->items?->keyBy('inventory_item_id') ?? collect();
    $oldItems = collect(old('items', []))->keyBy(fn ($item) => (int) ($item['inventory_item_id'] ?? 0));
    $selectedOfficerIds = collect(old('officer_ids', $stockOpname?->officers?->pluck('id')->all() ?? [auth()->id()]))->map(fn ($id) => (int) $id)->all();
    $formLocationType = old('location_type', $stockOpname?->location_type ?? 'warehouse');
    $formCampId = old('camp_id', $stockOpname?->camp_id);
    $selectedLocation = $formLocationType === 'site' && $formCampId
        ? 'site:'.$formCampId
        : 'warehouse';
@endphp
<main class="page">
    <header class="topbar">
        <div>
            <h1>{{ $isEdit ? 'Edit Draft '.$stockOpname->number : 'Buat Stock Opname' }}</h1>
            <p>Pilih barang yang dihitung. Saldo stok baru berubah setelah review dan posting final.</p>
        </div>
        <a class="button ghost" href="{{ route('stock-opname', $returnQuery) }}">Kembali</a>
    </header>

    @if ($errors->any())
        <div class="notice">{{ $errors->first() }}</div>
    @endif

    <form method="post" action="{{ $isEdit ? route('stock-opnames.update', $stockOpname) : route('stock-opnames.store') }}" data-opname-form>
        @csrf
        @if ($isEdit) @method('PUT') @endif
        @if ($activeCompanyId)<input type="hidden" name="company_id" value="{{ $activeCompanyId }}">@endif
        <input type="hidden" name="location_type" value="{{ old('location_type', 'warehouse') }}" data-location-type>
        <input type="hidden" name="camp_id" value="{{ old('camp_id') }}" data-camp-id>

        <section>
            <div class="workflow-note">Draft dapat disimpan tanpa mengubah stok. Saat diajukan, seluruh selisih wajib diperiksa oleh Logistik atau Chief Mechanic.</div>
            <div class="form-grid">
                <label>Tanggal opname
                    <input name="opname_date" type="date" value="{{ old('opname_date', now()->toDateString()) }}" required>
                </label>
                <label>Lokasi
                    <select data-location required>
                        <option value="warehouse" @selected($selectedLocation === 'warehouse')>Gudang Kantor</option>
                        @foreach ($camps as $camp)
                            <option value="site:{{ $camp->id }}" @selected($selectedLocation === 'site:'.$camp->id)>{{ $camp->name }} - {{ $camp->company?->name ?? $camp->company_name }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="field" data-multi-select>
                    <span class="field-label">Petugas hitung</span>
                    <button class="multi-trigger" type="button" aria-expanded="false" data-multi-trigger>
                        <span data-multi-summary>Pilih petugas</span><span aria-hidden="true">&#9662;</span>
                    </button>
                    <div class="multi-panel" data-multi-panel hidden>
                        @foreach ($officers as $officer)
                            <label class="multi-option" data-officer-row data-company-ids='@json($officer->scopedCompanyIds())' data-camp-ids='@json($officer->scopedCampIds())' data-global="{{ $officer->isAdmin() || ($officer->scopedCompanyIds() === [] && $officer->scopedCampIds() === []) ? '1' : '0' }}">
                                <input type="checkbox" name="officer_ids[]" value="{{ $officer->id }}" @checked(in_array($officer->id, $selectedOfficerIds, true)) data-officer-option>
                                <span>{{ $officer->name }}</span>
                                @if ($officer->role?->name)<small>{{ $officer->role->name }}</small>@endif
                            </label>
                        @endforeach
                    </div>
                </div>
                <label class="full">Catatan umum
                    <textarea name="notes">{{ old('notes') }}</textarea>
                </label>
            </div>
        </section>

        <section>
            <div class="section-head">
                <h2>Daftar Barang</h2>
                <input class="search" type="search" placeholder="Cari SKU atau barang..." data-search>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Pilih</th><th>SKU</th><th>Barang</th><th>Stok Sistem</th><th>Stok Fisik</th><th>Catatan Selisih</th></tr></thead>
                    <tbody>
                    @foreach ($items as $item)
                        @php
                            $oldItem = $oldItems->get($item->id);
                            $existingItem = $existingItems->get($item->id);
                            $isSelected = $oldItem ? (bool) ($oldItem['selected'] ?? false) : (bool) $existingItem;
                            $physicalValue = $oldItem['physical_stock'] ?? $existingItem?->physical_stock ?? $item->stock_on_hand;
                            $itemNotes = $oldItem['notes'] ?? $existingItem?->notes;
                        @endphp
                        <tr class="{{ $isSelected ? 'is-selected' : '' }}" data-item-row data-item-id="{{ $item->id }}" data-search-value="{{ strtolower($item->sku.' '.$item->name) }}">
                            <td><input class="select-item" type="checkbox" name="items[{{ $loop->index }}][selected]" value="1" @checked($isSelected) data-item-selected></td>
                            <td>{{ $item->sku }}</td>
                            <td>
                                <div class="item-name">{{ $item->name }}</div>
                                <div class="muted">{{ $item->category }} - {{ $item->uom }}{{ $item->shrinkage_tolerance_percent > 0 ? ' - toleransi '.$item->shrinkage_tolerance_percent.'%' : '' }}</div>
                            </td>
                            <td class="stock"><span data-system-stock>{{ $item->stock_on_hand }}</span> {{ $item->uom }}</td>
                            <td>
                                <input type="hidden" name="items[{{ $loop->index }}][inventory_item_id]" value="{{ $item->id }}">
                                <input name="items[{{ $loop->index }}][physical_stock]" type="number" min="0" step="0.01" value="{{ $physicalValue }}" data-physical-stock>
                            </td>
                            <td><input name="items[{{ $loop->index }}][notes]" value="{{ $itemNotes }}" data-item-notes></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="summary">
            <a class="button ghost" href="{{ route('stock-opname', $returnQuery) }}">Batal</a>
            <button class="ghost" type="submit" name="action" value="save_draft">Simpan Draft</button>
            <button type="submit" name="action" value="submit_review">Ajukan Pemeriksaan</button>
        </section>
    </form>
</main>

<script>
(() => {
    const centralStocks = @json($items->mapWithKeys(fn ($item) => [(string) $item->id => (float) $item->stock_on_hand]));
    const campStocks = @json($campStocks);
    const location = document.querySelector('[data-location]');
    const locationType = document.querySelector('[data-location-type]');
    const campId = document.querySelector('[data-camp-id]');
    const rows = [...document.querySelectorAll('[data-item-row]')];
    const activeCompanyId = @json($activeCompanyId ? (int) $activeCompanyId : null);

    const applyLocation = (resetPhysical = true) => {
        const [type, selectedCampId] = location.value.split(':');
        locationType.value = type === 'site' ? 'site' : 'warehouse';
        campId.value = type === 'site' ? selectedCampId : '';
        const stocks = type === 'site' ? (campStocks[selectedCampId] || {}) : centralStocks;

        rows.forEach((row) => {
            const quantity = Number(stocks[row.dataset.itemId] || 0);
            row.querySelector('[data-system-stock]').textContent = quantity.toLocaleString('id-ID');
            if (resetPhysical) row.querySelector('[data-physical-stock]').value = quantity;
        });
        filterOfficers(type === 'site' ? Number(selectedCampId) : null, activeCompanyId);
    };

    const multiSelect = document.querySelector('[data-multi-select]');
    const multiTrigger = multiSelect.querySelector('[data-multi-trigger]');
    const multiPanel = multiSelect.querySelector('[data-multi-panel]');
    const officerOptions = [...multiSelect.querySelectorAll('[data-officer-option]')];
    const multiSummary = multiSelect.querySelector('[data-multi-summary]');

    const filterOfficers = (selectedCampId, selectedCompanyId) => {
        officerOptions.forEach((option) => {
            const row = option.closest('[data-officer-row]');
            const companyIds = JSON.parse(row.dataset.companyIds || '[]').map(Number);
            const campIds = JSON.parse(row.dataset.campIds || '[]').map(Number);
            const isGlobal = row.dataset.global === '1';
            const allowed = isGlobal
                || (selectedCampId && (campIds.includes(selectedCampId) || (campIds.length === 0 && (!selectedCompanyId || companyIds.includes(selectedCompanyId)))))
                || (!selectedCampId && (!selectedCompanyId || companyIds.includes(selectedCompanyId)));
            row.hidden = !allowed;
            if (!allowed) option.checked = false;
        });
        updateOfficerSummary();
    };

    const updateOfficerSummary = () => {
        const selected = officerOptions.filter((option) => option.checked);
        multiSummary.textContent = selected.length === 0
            ? 'Pilih petugas'
            : selected.length === 1
                ? selected[0].closest('label').querySelector('span').textContent.trim()
                : `${selected.length} petugas dipilih`;
    };
    location.addEventListener('change', () => applyLocation(true));
    applyLocation(false);
    const closeOfficerPanel = () => {
        multiPanel.hidden = true;
        multiTrigger.setAttribute('aria-expanded', 'false');
    };

    multiTrigger.addEventListener('click', () => {
        const willOpen = multiPanel.hidden;
        multiPanel.hidden = !willOpen;
        multiTrigger.setAttribute('aria-expanded', String(willOpen));
    });
    officerOptions.forEach((option) => option.addEventListener('change', updateOfficerSummary));
    document.addEventListener('click', (event) => {
        if (!multiSelect.contains(event.target)) closeOfficerPanel();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeOfficerPanel();
    });
    updateOfficerSummary();

    document.querySelector('[data-search]').addEventListener('input', (event) => {
        const term = event.target.value.trim().toLowerCase();
        rows.forEach((row) => row.hidden = term !== '' && !row.dataset.searchValue.includes(term));
    });

    rows.forEach((row) => {
        const checkbox = row.querySelector('[data-item-selected]');
        const physical = row.querySelector('[data-physical-stock]');
        const notes = row.querySelector('[data-item-notes]');
        const updateSelection = () => {
            row.classList.toggle('is-selected', checkbox.checked);
            physical.required = checkbox.checked;
            physical.disabled = !checkbox.checked;
            notes.disabled = !checkbox.checked;
        };
        checkbox.addEventListener('change', updateSelection);
        updateSelection();
    });

    document.querySelector('[data-opname-form]').addEventListener('submit', (event) => {
        if (!rows.some((row) => row.querySelector('[data-item-selected]').checked)) {
            event.preventDefault();
            alert('Pilih minimal satu barang untuk stock opname.');
            return;
        }
        if (!officerOptions.some((option) => option.checked)) {
            event.preventDefault();
            multiPanel.hidden = false;
            multiTrigger.setAttribute('aria-expanded', 'true');
            multiTrigger.focus();
            return;
        }
        if (event.submitter?.value === 'submit_review' && !confirm('Ajukan hasil hitung untuk pemeriksaan selisih? Stok belum akan berubah.')) event.preventDefault();
    });
})();
</script>
</body>
</html>
