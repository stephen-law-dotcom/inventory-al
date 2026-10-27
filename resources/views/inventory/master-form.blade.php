<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $mode === 'create' ? 'Tambah' : 'Edit' }} Master Data</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=2">
    <style>
        :root {
            --bg: #eef6f8;
            --panel: #ffffff;
            --ink: #17212b;
            --muted: #667788;
            --line: #dce8ee;
            --accent: #0b8f8a;
            --accent-dark: #066d70;
            --amber: #e3a72f;
            --danger: #b42318;
            --shadow: 0 16px 36px rgba(16, 42, 67, .11);
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background:
                linear-gradient(135deg, rgba(11,143,138,.11), transparent 35%),
                linear-gradient(315deg, rgba(227,167,47,.10), transparent 32%),
                var(--bg);
            color: var(--ink);
            font-family: Inter, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 14px;
            line-height: 1.45;
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }
        a { color: inherit; text-decoration: none; }
        .page { width: min(920px, calc(100% - 28px)); margin: 0 auto; padding: 24px 0; display: grid; gap: 16px; }
        .topbar, section {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: var(--shadow);
        }
        .topbar { padding: 18px; display: flex; align-items: center; justify-content: space-between; gap: 12px; background: linear-gradient(135deg, rgba(255,255,255,.96), rgba(255,255,255,.86)), linear-gradient(90deg, rgba(11,143,138,.13), rgba(227,167,47,.12)); }
        h1 { margin: 0; font-size: 26px; color: #102a43; font-weight: 900; }
        p { margin: 6px 0 0; color: var(--muted); line-height: 1.45; }
        section { padding: 18px; }
        form { display: grid; gap: 12px; }
        label { display: grid; gap: 6px; color: #526677; font-size: 12px; font-weight: 800; }
        input, select, textarea {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 11px 12px;
            background: #fff;
            color: var(--ink);
            font: inherit;
            outline: none;
        }
        input[type="checkbox"] { width: auto; }
        input:focus, select:focus, textarea:focus { border-color: var(--accent); box-shadow: 0 0 0 4px rgba(11,143,138,.14); }
        textarea { min-height: 92px; resize: vertical; }
        button, .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            border: 0;
            border-radius: 8px;
            padding: 10px 14px;
            background: linear-gradient(135deg, var(--accent), #17b8ad);
            color: #fff;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 10px 18px rgba(11,143,138,.22);
        }
        .ghost { background: #edf4f7; color: #24394d; box-shadow: none; border: 1px solid var(--line); }
        .row { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        [data-inventory-planning-fields] { display: grid; gap: 12px; }
        [data-inventory-planning-fields][hidden] { display: none; }
        .permission-groups { display: grid; gap: 14px; }
        .permission-group { border: 1px solid var(--line); border-radius: 6px; padding: 14px; background: #f9fbfc; }
        .permission-group h3 { margin: 0 0 11px; color: #23384d; font-size: 14px; }
        .permission-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px 14px; }
        .permission-option {
            display: flex;
            grid-template-columns: none;
            align-items: flex-start;
            gap: 9px;
            min-height: 36px;
            padding: 8px 9px;
            border: 1px solid transparent;
            border-radius: 5px;
            color: #31495d;
            background: #fff;
            cursor: pointer;
        }
        .permission-option:hover { border-color: var(--line-strong); }
        .permission-option input, .admin-option input {
            width: 17px;
            height: 17px;
            min-height: 0;
            margin: 1px 0 0;
            padding: 0;
            accent-color: var(--accent);
            box-shadow: none;
        }
        .admin-option {
            display: flex;
            grid-template-columns: none;
            align-items: center;
            gap: 9px;
            padding: 12px;
            border: 1px solid #b9ddd9;
            border-radius: 6px;
            background: #eff9f7;
            color: var(--accent-dark);
        }
        .permission-groups.is-disabled { opacity: .48; pointer-events: none; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end; }
        .notice { padding: 12px 14px; border-radius: 8px; border: 1px solid #f4aaa5; color: var(--danger); background: #fff6f4; font-weight: 700; }
        :root {
            --bg: #f4f7f9;
            --ink: #172534;
            --muted: #667687;
            --line: #dce4e9;
            --accent: #087f78;
            --accent-dark: #06645f;
            --shadow: 0 4px 16px rgba(28,46,61,.08);
        }
        body { background: var(--bg); }
        .page { width: min(960px, calc(100% - 32px)); padding: 20px 0 36px; gap: 14px; }
        .topbar, section { border-radius: 7px; box-shadow: var(--shadow); }
        .topbar { padding: 16px 18px; background: #fff; }
        h1 { font-size: 23px; font-weight: 800; }
        section { padding: 20px; }
        form { gap: 16px; }
        label { color: #4d6070; font-weight: 700; }
        input, select, textarea { min-height: 41px; border-radius: 6px; padding: 10px 11px; }
        input:focus, select:focus, textarea:focus { box-shadow: 0 0 0 3px rgba(8,127,120,.12); }
        button, .button { min-height: 39px; border-radius: 6px; background: var(--accent); box-shadow: none; font-size: 13px; }
        button:hover, .button:hover { background: var(--accent-dark); }
        .ghost { background: #f3f6f8; }
        .actions { padding-top: 4px; border-top: 1px solid var(--line); }
        @media (max-width: 720px) {
            .topbar { align-items: flex-start; flex-direction: column; }
            .row { grid-template-columns: 1fr; }
            .permission-grid { grid-template-columns: 1fr; }
            .actions { align-items: stretch; flex-direction: column; }
            button, .button { width: 100%; }
        }

        /* Form polish */
        body { background: linear-gradient(180deg, #fbfcfd 0, var(--bg) 240px); }
        .page { width: min(1040px, calc(100% - 32px)); }
        .topbar {
            border-top: 4px solid var(--accent);
            align-items: flex-start;
        }
        .topbar > div { min-width: 0; }
        .topbar h1,
        .topbar p { overflow-wrap: anywhere; }
        section {
            position: relative;
            overflow: hidden;
        }
        section::before {
            content: "";
            position: absolute;
            inset: 0 0 auto;
            height: 3px;
            background: linear-gradient(90deg, var(--accent), #e3a72f);
        }
        form {
            padding-top: 2px;
        }
        label {
            min-width: 0;
        }
        input,
        select,
        textarea {
            transition: border-color .16s ease, box-shadow .16s ease, background .16s ease;
        }
        input[readonly] {
            background: #f8fafb;
            color: var(--muted);
        }
        .permission-group {
            transition: border-color .16s ease, background .16s ease;
        }
        .permission-group:hover {
            border-color: #c9d8df;
            background: #fbfcfd;
        }
        .permission-help {
            margin: -3px 0 12px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.45;
        }
        .actions {
            margin-top: 2px;
            padding-top: 14px;
        }
        button,
        .button {
            white-space: nowrap;
        }
        @media (max-width: 720px) {
            .page { width: min(100% - 24px, 1040px); padding-top: 14px; }
            section { padding: 16px; }
        }
    </style>
</head>
<body>
@php
    $labels = [
        'companies' => ['Perusahaan', route('master.companies')],
        'camps' => ['Camp / Site', route('master.camps')],
        'vendors' => ['Vendor', route('master.vendors')],
        'units' => ['Unit', route('master.units')],
        'item-categories' => ['Kategori Barang', route('master.item-categories')],
        'items' => ['Barang', route('master.items')],
        'uoms' => ['Satuan', route('master.uoms')],
        'users' => ['User', route('master.users')],
        'roles' => ['Role', route('master.roles')],
    ];
    [$label, $backUrl] = $labels[$type];
    $isEdit = $mode === 'edit';
    $action = match ($type) {
        'companies' => $isEdit ? route('companies.update', $record) : route('companies.store'),
        'camps' => $isEdit ? route('camps.update', $record) : route('camps.store'),
        'vendors' => $isEdit ? route('vendors.update', $record) : route('vendors.store'),
        'units' => $isEdit ? route('units.update', $record) : route('units.store'),
        'item-categories' => $isEdit ? route('item-categories.update', $record) : route('item-categories.store'),
        'items' => $isEdit ? route('items.update', $record) : route('items.store'),
        'uoms' => $isEdit ? route('uoms.update', $record) : route('uoms.store'),
        'users' => $isEdit ? route('users.update', $record) : route('users.store'),
        'roles' => $isEdit ? route('roles.update', $record) : route('roles.store'),
    };
@endphp

<div class="page">
    <header class="topbar">
        <div>
            <h1>{{ $isEdit ? 'Edit' : 'Tambah' }} {{ $label }}</h1>
        </div>
        <a class="button ghost" href="{{ $backUrl }}">Kembali</a>
    </header>

    @if ($errors->any())
        <div class="notice">{{ $errors->first() }}</div>
    @endif

    <section>
        <form method="post" action="{{ $action }}">
            @csrf
            @if ($isEdit)
                @method('put')
            @endif

            @if ($type === 'camps')
                <div class="row">
                    <label>Kode site <input name="code" value="{{ old('code', $record->code ?? '') }}" required placeholder="AL-MHU-01"></label>
                    <label>Nama site <input name="name" value="{{ old('name', $record->name ?? '') }}" required placeholder="Site Separi"></label>
                </div>
                <label>Perusahaan pemilik site
                    <select name="company_id" required>
                        @foreach ($companies as $company)
                            <option value="{{ $company->id }}" @selected((int) old('company_id', $record->company_id ?? 0) === $company->id)>{{ $company->name }} - {{ $company->order_request_code }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Lokasi <input name="location" value="{{ old('location', $record->location ?? '') }}"></label>
            @endif

            @if ($type === 'companies')
                <div class="row">
                    <label>Kode perusahaan <input name="code" value="{{ old('code', $record->code ?? '') }}" required placeholder="AML"></label>
                    <label>Nama perusahaan <input name="name" value="{{ old('name', $record->name ?? '') }}" required placeholder="PT Adimitra Lestari"></label>
                </div>
                <label>Kode dokumen OR / PO
                    <input name="order_request_code" value="{{ old('order_request_code', $record->order_request_code ?? '') }}" required placeholder="AML-OR">
                </label>
                <div class="row">
                    <label>Telepon <input name="phone" value="{{ old('phone', $record->phone ?? '') }}"></label>
                    <label>Alamat <input name="address" value="{{ old('address', $record->address ?? '') }}"></label>
                </div>
            @endif

            @if ($type === 'vendors')
                <div class="row">
                    <label>Kode vendor
                        <input value="{{ $isEdit ? $record->code : 'Dibuat otomatis setelah disimpan' }}" disabled>
                    </label>
                    <label>Nama vendor <input name="name" value="{{ old('name', $record->name ?? '') }}" required></label>
                </div>
                <div class="row">
                    <label>Kontak <input name="contact_person" value="{{ old('contact_person', $record->contact_person ?? '') }}"></label>
                    <label>Telepon <input name="phone" value="{{ old('phone', $record->phone ?? '') }}"></label>
                </div>
                <label>Alamat <textarea name="address">{{ old('address', $record->address ?? '') }}</textarea></label>
            @endif

            @if ($type === 'units')
                <div class="row">
                    <label>Kode unit <input name="code" value="{{ old('code', $record->code ?? '') }}" required placeholder="EXC-01"></label>
                    <label>Nama unit <input name="name" value="{{ old('name', $record->name ?? '') }}" required></label>
                </div>
                <div class="row">
                    <label>Tipe <input name="type" value="{{ old('type', $record->type ?? '') }}"></label>
                    <label>Serial number <input name="serial_number" value="{{ old('serial_number', $record->serial_number ?? '') }}"></label>
                </div>
                <label>Site
                    <select name="camp_id">
                        <option value="">Belum ditempatkan</option>
                        @foreach ($camps as $camp)
                            <option value="{{ $camp->id }}" @selected((int) old('camp_id', $record->camp_id ?? 0) === $camp->id)>{{ $camp->company_name }} - {{ $camp->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            @if ($type === 'item-categories')
                <div class="row">
                    <label>Kode kategori <input name="code" value="{{ old('code', $record->code ?? '') }}" required placeholder="SPP"></label>
                    <label>Nama kategori <input name="name" value="{{ old('name', $record->name ?? '') }}" required placeholder="Sparepart"></label>
                </div>
                <div class="row">
                    <label class="admin-option">
                        <input name="uses_inventory_planning" type="checkbox" value="1" @checked(old('uses_inventory_planning', $record->uses_inventory_planning ?? false))>
                        <span><strong>Pakai EOQ / ROP</strong><br>Untuk consumable, solar, oli, dan barang terencana.</span>
                    </label>
                    <label class="admin-option">
                        <input name="uses_shrinkage_tolerance" type="checkbox" value="1" @checked(old('uses_shrinkage_tolerance', $record->uses_shrinkage_tolerance ?? false))>
                        <span><strong>Pakai toleransi susut</strong><br>Untuk solar, oli, bensin, atau cairan.</span>
                    </label>
                </div>
                <label>Default toleransi susut (%)
                    <input name="default_shrinkage_tolerance_percent" type="number" min="0" max="100" step="0.01" value="{{ old('default_shrinkage_tolerance_percent', $record->default_shrinkage_tolerance_percent ?? 0) }}">
                </label>
                <label>Deskripsi <textarea name="description">{{ old('description', $record->description ?? '') }}</textarea></label>
            @endif

            @if ($type === 'items')
                <div class="row">
                    <label>Part Number <input name="sku" value="{{ old('sku', $record->sku ?? '') }}" required placeholder="P550060 / 600-211-1340"></label>
                    <label>Nama barang <input name="name" value="{{ old('name', $record->name ?? '') }}" required></label>
                </div>
                <label>Deskripsi <textarea name="description" placeholder="Detail fungsi barang, spesifikasi umum, atau catatan pemakaian">{{ old('description', $record->description ?? '') }}</textarea></label>
                <div class="row">
                    <label>Kategori
                        <select name="item_category_id" data-item-category required>
                            @foreach ($itemCategories as $category)
                                <option value="{{ $category->id }}" data-uses-planning="{{ $category->uses_inventory_planning ? '1' : '0' }}" data-tolerance="{{ $category->default_shrinkage_tolerance_percent }}" @selected((int) old('item_category_id', $record->item_category_id ?? $itemCategories->first()?->id) === $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Satuan
                        <select name="uom" required>
                            @foreach ($uoms as $uom)
                                <option value="{{ $uom->code }}" @selected(old('uom', $record->uom ?? '') === $uom->code)>{{ $uom->code }} - {{ $uom->name }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <label data-manual-minimum-field>Minimum stok manual <input name="minimum_stock" type="number" min="0" value="{{ old('minimum_stock', $record->minimum_stock ?? 0) }}"></label>
                <div data-inventory-planning-fields>
                    <div class="row">
                        <label>Forecast kebutuhan tahunan <input name="forecast_annual_demand" type="number" min="0" step="0.01" value="{{ old('forecast_annual_demand', $record->forecast_annual_demand ?? 0) }}"></label>
                        <label>Lead time pembelian (hari) <input name="lead_time_days" type="number" min="0" value="{{ old('lead_time_days', $record->lead_time_days ?? 0) }}"></label>
                    </div>
                    <div class="row">
                        <label>Safety stock <input name="safety_stock" type="number" min="0" step="0.01" value="{{ old('safety_stock', $record->safety_stock ?? 0) }}"></label>
                        <label>Biaya pemesanan <input name="ordering_cost" type="number" min="0" step="0.01" value="{{ old('ordering_cost', $record->ordering_cost ?? 0) }}"></label>
                    </div>
                    <label>Biaya simpan/unit/tahun <input name="holding_cost_per_unit" type="number" min="0" step="0.01" value="{{ old('holding_cost_per_unit', $record->holding_cost_per_unit ?? 0) }}"></label>
                </div>
                <label>Toleransi susut cairan (%)
                    <input name="shrinkage_tolerance_percent" type="number" min="0" max="100" step="0.01" value="{{ old('shrinkage_tolerance_percent', $record->shrinkage_tolerance_percent ?? 0) }}" placeholder="Contoh: 0.50">
                </label>
            @endif

            @if ($type === 'uoms')
                <div class="row">
                    <label>Kode satuan <input name="code" value="{{ old('code', $record->code ?? '') }}" required placeholder="pcs"></label>
                    <label>Nama satuan <input name="name" value="{{ old('name', $record->name ?? '') }}" required placeholder="Pieces"></label>
                </div>
                <label>Deskripsi <textarea name="description">{{ old('description', $record->description ?? '') }}</textarea></label>
            @endif

            @if ($type === 'users')
                <div class="row">
                    <label>Nama user <input name="name" value="{{ old('name', $record->name ?? '') }}" required></label>
                    <label>Email <input name="email" type="email" value="{{ old('email', $record->email ?? '') }}" required></label>
                </div>
                <label>Role
                    <select name="role_id" required>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" @selected((int) old('role_id', $record->role_id ?? 0) === $role->id)>{{ $role->name }}{{ $role->hasPermission('prices.view') ? '' : ' - tanpa nominal harga' }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="row">
                    <label>Password <input name="password" type="password" autocomplete="new-password" {{ $isEdit ? '' : 'required' }} placeholder="{{ $isEdit ? 'Kosongkan jika tidak diganti' : 'Minimal 8 karakter' }}"></label>
                    <label>Konfirmasi password <input name="password_confirmation" type="password" autocomplete="new-password" {{ $isEdit ? '' : 'required' }}></label>
                </div>
                @php
                    $selectedCompanyIds = collect(old('company_ids', $record->company_ids ?? []))->map(fn ($id) => (int) $id)->all();
                    $selectedCampIds = collect(old('camp_ids', $record->camp_ids ?? []))->map(fn ($id) => (int) $id)->all();
                @endphp
                <div class="permission-groups" data-data-scope>
                    <div class="permission-group">
                        <h3>Akses Data Perusahaan</h3>
                        <p class="muted">Kosongkan bila user boleh melihat semua perusahaan. Jika dipilih, data user dibatasi pada perusahaan tersebut.</p>
                        <div class="permission-grid">
                            @foreach ($companies as $company)
                                <label class="permission-option">
                                    <input name="company_ids[]" type="checkbox" value="{{ $company->id }}" @checked(in_array($company->id, $selectedCompanyIds, true))>
                                    <span>{{ $company->name }} <small>{{ $company->order_request_code }}</small></span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="permission-group">
                        <h3>Akses Data Camp / Site</h3>
                        <p class="muted">Kosongkan bila user boleh melihat semua camp pada perusahaan yang dipilih. Jika dipilih, akses user dibatasi hanya ke camp tersebut.</p>
                        <div class="permission-grid">
                            @foreach ($camps as $camp)
                                <label class="permission-option">
                                    <input name="camp_ids[]" type="checkbox" value="{{ $camp->id }}" @checked(in_array($camp->id, $selectedCampIds, true))>
                                    <span>{{ $camp->company_name }} - {{ $camp->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            @if ($type === 'roles')
                @php
                    $selectedPermissions = old('permissions', $record->permissions ?? []);
                    $roleIsAdmin = (bool) old('is_admin', $record->is_admin ?? false);
                    if ($roleIsAdmin || in_array('*', $selectedPermissions, true)) {
                        $selectedPermissions = \App\Models\Role::permissionKeys();
                    }
                @endphp
                <div class="row">
                    <label>Nama role <input name="name" value="{{ old('name', $record->name ?? '') }}" required placeholder="User Site / Camp"></label>
                    <label>Slug <input name="slug" value="{{ old('slug', $record->slug ?? '') }}" required placeholder="camp"></label>
                </div>
                <label>Deskripsi <textarea name="description">{{ old('description', $record->description ?? '') }}</textarea></label>
                <label class="admin-option">
                    <input name="is_admin" type="checkbox" value="1" data-admin-permission @checked($roleIsAdmin)>
                    <span><strong>Administrator</strong><br>Memberikan akses penuh ke seluruh fitur dan aksi.</span>
                </label>
                <div class="permission-groups" data-permission-groups>
                    @foreach ($permissionGroups as $groupName => $permissions)
                        <div class="permission-group">
                            <h3>{{ $groupName }}</h3>
                            @if ($groupName === 'Harga dan Finansial')
                                <p class="permission-help">Atur apakah role dapat melihat harga satuan, nilai vendor, diskon, PPN, total PO, dan nilai Credit Note.</p>
                            @endif
                            <div class="permission-grid">
                                @foreach ($permissions as $permissionKey => $permissionLabel)
                                    <label class="permission-option">
                                        <input name="permissions[]" type="checkbox" value="{{ $permissionKey }}" data-permission-key="{{ $permissionKey }}" @checked(in_array($permissionKey, $selectedPermissions, true))>
                                        <span>{{ $permissionLabel }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="actions">
                <a class="button ghost" href="{{ $backUrl }}">Batal</a>
                <button>{{ $isEdit ? 'Simpan Perubahan' : 'Simpan Data' }}</button>
            </div>
        </form>
    </section>
</div>
@if ($type === 'items')
<script>
    const categoryInput = document.querySelector('[data-item-category]');
    const planningFields = document.querySelector('[data-inventory-planning-fields]');
    const manualMinimumField = document.querySelector('[data-manual-minimum-field]');
    const manualMinimumInput = manualMinimumField?.querySelector('input');

    const syncPlanningFields = () => {
        const option = categoryInput?.selectedOptions[0];
        const usesPlanning = option?.dataset.usesPlanning === '1';

        planningFields.hidden = ! usesPlanning;
        manualMinimumField.hidden = usesPlanning;
        manualMinimumInput.disabled = usesPlanning;
        manualMinimumInput.required = ! usesPlanning;
    };

    categoryInput?.addEventListener('change', syncPlanningFields);
    syncPlanningFields();
</script>
@endif
@if ($type === 'roles')
<script>
    const adminPermission = document.querySelector('[data-admin-permission]');
    const permissionGroups = document.querySelector('[data-permission-groups]');
    const permissionInputs = permissionGroups?.querySelectorAll('input[type="checkbox"]') ?? [];

    const syncAdminPermission = () => {
        permissionGroups.classList.toggle('is-disabled', adminPermission.checked);
        permissionInputs.forEach((input) => {
            input.disabled = adminPermission.checked;
        });
    };

    adminPermission?.addEventListener('change', syncAdminPermission);
    syncAdminPermission();
</script>
@endif
</body>
</html>
