            @if ($activeMenu === 'master')
                @if ($activeMaster === 'companies')
                    <div class="stack">
                        @include('inventory.partials.master-transfer-toolbar', ['type' => 'companies', 'canImport' => $canManageMasterData, 'createUrl' => route('master.create', 'companies'), 'createLabel' => 'Tambah Perusahaan'])
                        <section>
                            <h2>List Perusahaan</h2>
                            <table>
                                <thead><tr><th>Kode</th><th>Nama Perusahaan</th><th>Kode Dokumen</th><th>Telepon</th><th>Alamat</th><th>Site</th>@if ($canManageMasterData)<th>Aksi</th>@endif</tr></thead>
                                <tbody>
                                @forelse ($companies as $company)
                                    <tr>
                                        <td>{{ $company->code }}</td>
                                        <td>{{ $company->name }}</td>
                                        <td><span class="pill ok">{{ $company->order_request_code }}</span></td>
                                        <td>{{ $company->phone ?: '-' }}</td>
                                        <td>{{ $company->address ?: '-' }}</td>
                                        <td>{{ $camps->where('company_id', $company->id)->count() }} site</td>
                                        @if ($canManageMasterData)
                                            <td>
                                                <details class="action-menu">
                                                    <summary>Aksi</summary>
                                                    <div class="menu">
                                                        <a class="button" href="{{ route('master.edit', ['companies', $company->id]) }}">Edit</a>
                                                        <form method="post" action="{{ route('companies.destroy', $company) }}" data-confirm="Hapus perusahaan {{ $company->name }}?">@csrf @method('delete')<button class="ghost">Hapus</button></form>
                                                    </div>
                                                </details>
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr><td colspan="{{ $canManageMasterData ? 7 : 6 }}">Belum ada perusahaan.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </section>
                    </div>
                @endif

                @if ($activeMaster === 'camps')
                    <div class="stack">
                        @include('inventory.partials.master-transfer-toolbar', ['type' => 'camps', 'canImport' => $canManageMasterData, 'createUrl' => route('master.create', 'camps'), 'createLabel' => 'Tambah Camp'])
                        <section>
                            <h2>List Camp</h2>
                            <table>
                                <thead><tr><th>Kode</th><th>Nama Site</th><th>Perusahaan</th><th>Lokasi</th>@if ($canManageMasterData)<th>Aksi</th>@endif</tr></thead>
                                <tbody>
                                @forelse ($camps as $camp)
                                    <tr>
                                        <td>{{ $camp->code }}</td>
                                        <td>{{ $camp->name }}</td>
                                        <td>{{ $camp->company_name }}</td>
                                        <td>{{ $camp->location }}</td>
                                        @if ($canManageMasterData)
                                            <td>
                                                <details class="action-menu">
                                                    <summary>Aksi</summary>
                                                    <div class="menu">
                                                        <a class="button" href="{{ route('master.edit', ['camps', $camp->id]) }}">Edit</a>
                                                        <form method="post" action="{{ route('camps.destroy', $camp) }}" data-confirm="Hapus site {{ $camp->name }}?">@csrf @method('delete')<button class="ghost">Hapus</button></form>
                                                    </div>
                                                </details>
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr><td colspan="{{ $canManageMasterData ? 5 : 4 }}">Belum ada camp.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </section>
                    </div>
                @endif

                @if ($activeMaster === 'vendors')
                    <div class="stack">
                        @include('inventory.partials.master-transfer-toolbar', ['type' => 'vendors', 'canImport' => $canManageMasterData, 'createUrl' => route('master.create', 'vendors'), 'createLabel' => 'Tambah Vendor'])
                        <section>
                            <h2>List Vendor</h2>
                            <table>
                                <thead><tr><th>Kode</th><th>Nama</th><th>Kontak</th><th>Telepon</th><th>Alamat</th>@if ($canManageMasterData)<th>Aksi</th>@endif</tr></thead>
                                <tbody>
                                @forelse ($vendors as $vendor)
                                    <tr>
                                        <td>{{ $vendor->code }}</td>
                                        <td>{{ $vendor->name }}</td>
                                        <td>{{ $vendor->contact_person }}</td>
                                        <td>{{ $vendor->phone }}</td>
                                        <td>{{ $vendor->address }}</td>
                                        @if ($canManageMasterData)
                                            <td>
                                                <details class="action-menu">
                                                    <summary>Aksi</summary>
                                                    <div class="menu">
                                                        <a class="button" href="{{ route('master.edit', ['vendors', $vendor->id]) }}">Edit</a>
                                                        <form method="post" action="{{ route('vendors.destroy', $vendor) }}" data-confirm="Hapus vendor {{ $vendor->name }}?">@csrf @method('delete')<button class="ghost">Hapus</button></form>
                                                    </div>
                                                </details>
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr><td colspan="{{ $canManageMasterData ? 6 : 5 }}">Belum ada vendor.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </section>
                    </div>
                @endif

                @if ($activeMaster === 'units')
                    <div class="stack">
                        @include('inventory.partials.master-transfer-toolbar', ['type' => 'units', 'canImport' => $canManageMasterData, 'createUrl' => route('master.create', 'units'), 'createLabel' => 'Tambah Unit'])
                        <section>
                            <h2>List Unit</h2>
                            <table>
                                <thead><tr><th>Kode</th><th>Nama</th><th>Tipe</th><th>Serial</th><th>Camp</th>@if ($canManageMasterData)<th>Aksi</th>@endif</tr></thead>
                                <tbody>
                                @forelse ($units as $unit)
                                    <tr>
                                        <td>{{ $unit->code }}</td>
                                        <td>{{ $unit->name }}</td>
                                        <td>{{ $unit->type }}</td>
                                        <td>{{ $unit->serial_number }}</td>
                                        <td>{{ $unit->camp?->company_name }}{{ $unit->camp ? ' - ' : '' }}{{ $unit->camp?->name }}</td>
                                        @if ($canManageMasterData)
                                            <td>
                                                <details class="action-menu">
                                                    <summary>Aksi</summary>
                                                    <div class="menu">
                                                        <a class="button" href="{{ route('master.edit', ['units', $unit->id]) }}">Edit</a>
                                                        <form method="post" action="{{ route('units.destroy', $unit) }}" data-confirm="Hapus unit {{ $unit->name }}?">@csrf @method('delete')<button class="ghost">Hapus</button></form>
                                                    </div>
                                                </details>
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr><td colspan="{{ $canManageMasterData ? 6 : 5 }}">Belum ada unit.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </section>
                    </div>
                @endif

                @if ($activeMaster === 'item-categories')
                    <div class="stack">
                        @include('inventory.partials.master-transfer-toolbar', ['type' => 'item-categories', 'canImport' => $canManageMasterData, 'createUrl' => route('master.create', 'item-categories'), 'createLabel' => 'Tambah Kategori'])
                        <section>
                            <h2>List Kategori Barang</h2>
                            <table>
                                <thead><tr><th>Kode</th><th>Nama</th><th>EOQ / ROP</th><th>Toleransi Susut</th><th>Deskripsi</th>@if ($canManageMasterData)<th>Aksi</th>@endif</tr></thead>
                                <tbody>
                                @forelse ($itemCategories as $category)
                                    <tr>
                                        <td>{{ $category->code }}</td>
                                        <td>{{ $category->name }}</td>
                                        <td><span class="pill {{ $category->uses_inventory_planning ? 'ok' : '' }}">{{ $category->uses_inventory_planning ? 'Ya' : 'Tidak' }}</span></td>
                                        <td><span class="pill {{ $category->uses_shrinkage_tolerance ? 'ok' : '' }}">{{ $category->uses_shrinkage_tolerance ? $category->default_shrinkage_tolerance_percent.'%' : 'Tidak' }}</span></td>
                                        <td>{{ $category->description ?: '-' }}</td>
                                        @if ($canManageMasterData)
                                            <td>
                                                <details class="action-menu">
                                                    <summary>Aksi</summary>
                                                    <div class="menu">
                                                        <a class="button" href="{{ route('master.edit', ['item-categories', $category->id]) }}">Edit</a>
                                                        <form method="post" action="{{ route('item-categories.destroy', $category) }}" data-confirm="Hapus kategori {{ $category->name }}?">@csrf @method('delete')<button class="ghost">Hapus</button></form>
                                                    </div>
                                                </details>
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr><td colspan="{{ $canManageMasterData ? 6 : 5 }}">Belum ada kategori.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </section>
                    </div>
                @endif

                @if ($activeMaster === 'items')
                    <div class="stack">
                        @include('inventory.partials.master-transfer-toolbar', ['type' => 'items', 'canImport' => $canManageMasterData, 'createUrl' => route('master.create', 'items'), 'createLabel' => 'Tambah Barang'])
                        <section>
                            <h2>List Barang</h2>
                            <table>
                                <thead><tr><th>Part Number</th><th>Nama</th><th>Deskripsi</th><th>Kategori</th><th>Satuan</th><th>Stok</th><th>Minimum</th>@if ($canManageMasterData)<th>Aksi</th>@endif</tr></thead>
                                <tbody>
                                @forelse ($items as $item)
                                    <tr>
                                        <td>{{ $item->sku }}</td>
                                        <td>{{ $item->name }}</td>
                                        <td>{{ $item->description ?: '-' }}</td>
                                        <td>{{ $item->category }}</td>
                                        <td>{{ $item->uom }}</td>
                                        <td>{{ $item->stock_on_hand }} {{ $item->uom }}</td>
                                        <td>{{ $item->usesInventoryPlanning() ? 'Otomatis (ROP)' : $item->minimum_stock . ' ' . $item->uom }}</td>
                                        @if ($canManageMasterData)
                                            <td>
                                                <details class="action-menu">
                                                    <summary>Aksi</summary>
                                                    <div class="menu">
                                                        <a class="button" href="{{ route('master.edit', ['items', $item->id]) }}">Edit</a>
                                                        <form method="post" action="{{ route('items.destroy', $item) }}" data-confirm="Hapus barang {{ $item->name }}?">@csrf @method('delete')<button class="ghost">Hapus</button></form>
                                                    </div>
                                                </details>
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr><td colspan="{{ $canManageMasterData ? 8 : 7 }}">Belum ada barang.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </section>
                    </div>
                @endif

                @if ($activeMaster === 'uoms')
                    <div class="stack">
                        @include('inventory.partials.master-transfer-toolbar', ['type' => 'uoms', 'canImport' => $canManageMasterData, 'createUrl' => route('master.create', 'uoms'), 'createLabel' => 'Tambah Satuan'])
                        <section>
                            <h2>List Satuan</h2>
                            <table>
                                <thead><tr><th>Kode</th><th>Nama</th><th>Deskripsi</th>@if ($canManageMasterData)<th>Aksi</th>@endif</tr></thead>
                                <tbody>
                                @forelse ($uoms as $uom)
                                    <tr>
                                        <td>{{ $uom->code }}</td>
                                        <td>{{ $uom->name }}</td>
                                        <td>{{ $uom->description }}</td>
                                        @if ($canManageMasterData)
                                            <td>
                                                <details class="action-menu">
                                                    <summary>Aksi</summary>
                                                    <div class="menu">
                                                        <a class="button" href="{{ route('master.edit', ['uoms', $uom->id]) }}">Edit</a>
                                                        <form method="post" action="{{ route('uoms.destroy', $uom) }}" data-confirm="Hapus satuan {{ $uom->code }}?">@csrf @method('delete')<button class="ghost">Hapus</button></form>
                                                    </div>
                                                </details>
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr><td colspan="{{ $canManageMasterData ? 4 : 3 }}">Belum ada satuan.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </section>
                    </div>
                @endif

                @if ($activeMaster === 'users' && $currentUser->hasPermission('users.manage'))
                    <div class="stack">
                        @include('inventory.partials.master-transfer-toolbar', ['type' => 'users', 'canImport' => true, 'createUrl' => route('master.create', 'users'), 'createLabel' => 'Tambah User'])
                        <section>
                            <h2>List User</h2>
                            <table>
                                <thead><tr><th>Nama</th><th>Email</th><th>Role</th><th>Scope Data</th><th>Akses Harga</th><th>Aksi</th></tr></thead>
                                <tbody>
                                @forelse ($users as $user)
                                    <tr>
                                        <td>{{ $user->name }}</td>
                                        <td>{{ $user->email }}</td>
                                        <td><span class="pill">{{ $user->role?->name ?? 'Tanpa Role' }}</span></td>
                                        <td>
                                            @if ($user->isAdmin())
                                                <span class="pill ok">Semua data</span>
                                            @elseif ($user->scopedCampIds())
                                                <span class="pill wait">{{ count($user->scopedCampIds()) }} camp</span>
                                            @elseif ($user->scopedCompanyIds())
                                                <span class="pill wait">{{ count($user->scopedCompanyIds()) }} perusahaan</span>
                                            @else
                                                <span class="pill ok">Semua data</span>
                                            @endif
                                        </td>
                                        <td><span class="pill {{ $user->canSeePrices() ? 'ok' : 'bad' }}">{{ $user->canSeePrices() ? 'Bisa lihat harga' : 'Harga disembunyikan' }}</span></td>
                                        <td>
                                            <details class="action-menu">
                                                <summary>Aksi</summary>
                                                <div class="menu">
                                                    <a class="button" href="{{ route('master.edit', ['users', $user->id]) }}">Edit</a>
                                                    <form method="post" action="{{ route('users.destroy', $user) }}" data-confirm="Hapus user {{ $user->name }}?">@csrf @method('delete')<button class="ghost">Hapus</button></form>
                                                </div>
                                            </details>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6">Belum ada user.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </section>
                    </div>
                @endif

                @if ($activeMaster === 'roles' && $currentUser->hasPermission('roles.manage'))
                    <div class="stack">
                        @include('inventory.partials.master-transfer-toolbar', ['type' => 'roles', 'canImport' => true, 'createUrl' => route('master.create', 'roles'), 'createLabel' => 'Tambah Role'])
                        <section>
                            <h2>List Role</h2>
                            <table>
                                <thead><tr><th>Role</th><th>Slug</th><th>Deskripsi</th><th>Akses Harga</th><th>Admin</th><th>Aksi</th></tr></thead>
                                <tbody>
                                @forelse ($roles as $role)
                                    <tr>
                                        <td>{{ $role->name }}</td>
                                        <td>{{ $role->slug }}</td>
                                        <td>{{ $role->description }}</td>
                                        <td><span class="pill {{ $role->hasPermission('prices.view') ? 'ok' : 'bad' }}">{{ $role->hasPermission('prices.view') ? 'Bisa lihat harga' : 'Harga disembunyikan' }}</span></td>
                                        <td><span class="pill {{ $role->is_admin ? 'ok' : '' }}">{{ $role->is_admin ? 'Admin' : 'Bukan admin' }}</span></td>
                                        <td>
                                            <details class="action-menu">
                                                <summary>Aksi</summary>
                                                <div class="menu">
                                                    <a class="button" href="{{ route('master.edit', ['roles', $role->id]) }}">Edit</a>
                                                    <form method="post" action="{{ route('roles.destroy', $role) }}" data-confirm="Hapus role {{ $role->name }}?">@csrf @method('delete')<button class="ghost">Hapus</button></form>
                                                </div>
                                            </details>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6">Belum ada role.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </section>
                    </div>
                @endif
            @endif

