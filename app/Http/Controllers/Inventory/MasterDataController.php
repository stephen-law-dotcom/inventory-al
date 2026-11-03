<?php

namespace App\Http\Controllers\Inventory;

use App\Exports\OperationalReportExport;
use App\Http\Controllers\Controller;
use App\Imports\MasterDataRowsImport;
use App\Models\Camp;
use App\Models\Company;
use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Models\PurchaseRequestItem;
use App\Models\Role;
use App\Models\SiteDispatch;
use App\Models\StockOpname;
use App\Models\Unit;
use App\Models\UnitOfMeasure;
use App\Models\UsageLog;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AuditLogger;
use App\Services\DocumentNumberService;
use App\Services\MasterDataTransferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class MasterDataController extends Controller
{
    public function __construct(
        private readonly MasterDataTransferService $masterDataTransferService,
    ) {}

    public function companies()
    {
        return $this->index('companies', 'master.view');
    }

    public function camps()
    {
        return $this->index('camps', 'master.view');
    }

    public function vendors()
    {
        return $this->index('vendors', 'master.view');
    }

    public function units()
    {
        return $this->index('units', 'master.view');
    }

    public function itemCategories()
    {
        return $this->index('item-categories', 'master.view');
    }

    public function items()
    {
        return $this->index('items', 'master.view');
    }

    public function uoms()
    {
        return $this->index('uoms', 'master.view');
    }

    public function users()
    {
        return $this->index('users', 'users.manage');
    }

    public function roles()
    {
        return $this->index('roles', 'roles.manage');
    }

    public function export(string $type)
    {
        $this->assertMasterType($type);

        if (in_array($type, ['users', 'roles'], true)) {
            $this->ensureMasterPermission($type);
        } else {
            $this->ensurePermission('master.view');
        }

        $export = $this->masterDataTransferService->export($type);
        AuditLogger::log('master.exported', 'Master data diekspor.', null, ['type' => $type]);

        return Excel::download(
            new OperationalReportExport($export['headings'], $export['rows']),
            'master-'.$type.'-'.now()->format('Ymd-His').'.xlsx'
        );
    }

    public function import(Request $request, string $type)
    {
        $this->assertMasterType($type);
        $this->ensureMasterPermission($type);

        $request->validate([
            'import_file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ], [
            'import_file.required' => 'Pilih file Excel atau CSV yang akan diimpor.',
            'import_file.mimes' => 'Format import harus XLSX, XLS, atau CSV.',
            'import_file.max' => 'Ukuran file import maksimal 5 MB.',
        ]);

        $import = new MasterDataRowsImport;
        Excel::import($import, $request->file('import_file'));
        $count = $this->masterDataTransferService->import($type, $import->rows);
        AuditLogger::log('master.imported', 'Master data diimpor.', null, [
            'type' => $type,
            'row_count' => $count,
        ]);

        return redirect()->route('master.'.$type)->with('success', $count.' baris master data berhasil diimpor.');
    }

    public function create(string $type)
    {
        $this->assertMasterType($type);
        $this->ensureMasterPermission($type);

        return $this->renderForm($type, 'create');
    }

    public function edit(string $type, int $id)
    {
        $this->assertMasterType($type);
        $this->ensureMasterPermission($type);
        $model = $this->masterModel($type)::findOrFail($id);

        return $this->renderForm($type, 'edit', $model);
    }

    public function storeCamp(Request $request)
    {
        $this->ensureCanManageMasterData();

        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:camps,code'],
            'name' => ['required', 'string', 'max:255'],
            'company_id' => ['required', 'exists:companies,id'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);
        $data['company_name'] = Company::findOrFail($data['company_id'])->name;

        $camp = Camp::create($data);
        AuditLogger::log('master.created', 'Master site ditambahkan.', $camp);

        return redirect()->route('master.camps')->with('success', 'Master camp ditambahkan.');
    }

    public function storeCompany(Request $request)
    {
        $this->ensureCanManageMasterData();

        $company = Company::create($request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:companies,code'],
            'name' => ['required', 'string', 'max:255', 'unique:companies,name'],
            'order_request_code' => ['required', 'string', 'max:50', 'unique:companies,order_request_code'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]));
        AuditLogger::log('master.created', 'Master perusahaan ditambahkan.', $company);

        return redirect()->route('master.companies')->with('success', 'Master perusahaan ditambahkan.');
    }

    public function storeUser(Request $request)
    {
        $this->ensurePermission('users.manage');

        $user = User::create($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role_id' => ['required', 'exists:roles,id'],
            'company_ids' => ['nullable', 'array'],
            'company_ids.*' => ['integer', 'exists:companies,id'],
            'camp_ids' => ['nullable', 'array'],
            'camp_ids.*' => ['integer', 'exists:camps,id'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]));
        AuditLogger::log('master.created', 'Master user ditambahkan.', $user);

        return redirect()->route('master.users')->with('success', 'Master user ditambahkan.');
    }

    public function updateUser(Request $request, User $user)
    {
        $this->ensurePermission('users.manage');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'role_id' => ['required', 'exists:roles,id'],
            'company_ids' => ['nullable', 'array'],
            'company_ids.*' => ['integer', 'exists:companies,id'],
            'camp_ids' => ['nullable', 'array'],
            'camp_ids.*' => ['integer', 'exists:camps,id'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'role_id' => $data['role_id'],
            'company_ids' => collect($data['company_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all(),
            'camp_ids' => collect($data['camp_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all(),
        ]);

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();
        AuditLogger::log('master.updated', 'Master user diperbarui.', $user);

        return redirect()->route('master.users')->with('success', 'Master user diperbarui.');
    }

    public function destroyUser(User $user)
    {
        $this->ensurePermission('users.manage');

        if ($user->id === auth()->id()) {
            return back()->with('error', 'User yang sedang login tidak bisa dihapus.');
        }

        AuditLogger::log('master.deleted', 'Master user dihapus.', $user);
        $user->delete();

        return back()->with('success', 'Master user dihapus.');
    }

    public function storeRole(Request $request)
    {
        $this->ensurePermission('roles.manage');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:50', 'unique:roles,slug'],
            'description' => ['nullable', 'string'],
            'is_admin' => ['nullable', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(Role::permissionKeys())],
        ]);
        $permissions = Role::normalizePermissions($data['permissions'] ?? []);
        $isAdmin = $request->boolean('is_admin');

        $role = Role::create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'permissions' => $isAdmin ? ['*'] : $permissions,
            'can_see_prices' => $isAdmin || in_array('prices.view', $permissions, true),
            'is_admin' => $isAdmin,
        ]);
        AuditLogger::log('master.created', 'Master role ditambahkan.', $role);

        return redirect()->route('master.roles')->with('success', 'Master role ditambahkan.');
    }

    public function updateRole(Request $request, Role $role)
    {
        $this->ensurePermission('roles.manage');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:50', Rule::unique('roles', 'slug')->ignore($role)],
            'description' => ['nullable', 'string'],
            'is_admin' => ['nullable', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(Role::permissionKeys())],
        ]);
        $permissions = Role::normalizePermissions($data['permissions'] ?? []);
        $isAdmin = $request->boolean('is_admin');

        $role->update([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'permissions' => $isAdmin ? ['*'] : $permissions,
            'can_see_prices' => $isAdmin || in_array('prices.view', $permissions, true),
            'is_admin' => $isAdmin,
        ]);
        AuditLogger::log('master.updated', 'Master role diperbarui.', $role);

        return redirect()->route('master.roles')->with('success', 'Master role diperbarui.');
    }

    public function destroyRole(Role $role)
    {
        $this->ensurePermission('roles.manage');

        if ($role->users()->exists()) {
            return back()->with('error', 'Role tidak bisa dihapus karena masih dipakai user.');
        }

        AuditLogger::log('master.deleted', 'Master role dihapus.', $role);
        $role->delete();

        return back()->with('success', 'Master role dihapus.');
    }

    public function updateCamp(Request $request, Camp $camp)
    {
        $this->ensureCanManageMasterData();

        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('camps', 'code')->ignore($camp)],
            'name' => ['required', 'string', 'max:255'],
            'company_id' => ['required', 'exists:companies,id'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);
        $data['company_name'] = Company::findOrFail($data['company_id'])->name;

        $camp->update($data);
        AuditLogger::log('master.updated', 'Master site diperbarui.', $camp);

        return redirect()->route('master.camps')->with('success', 'Master camp diperbarui.');
    }

    public function updateCompany(Request $request, Company $company)
    {
        $this->ensureCanManageMasterData();

        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('companies', 'code')->ignore($company)],
            'name' => ['required', 'string', 'max:255', Rule::unique('companies', 'name')->ignore($company)],
            'order_request_code' => ['required', 'string', 'max:50', Rule::unique('companies', 'order_request_code')->ignore($company)],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        DB::transaction(function () use ($company, $data) {
            $company->update($data);
            Camp::where('company_id', $company->id)->update(['company_name' => $company->name]);
        });

        AuditLogger::log('master.updated', 'Master perusahaan diperbarui.', $company);

        return redirect()->route('master.companies')->with('success', 'Master perusahaan diperbarui.');
    }

    public function destroyCompany(Company $company)
    {
        $this->ensureCanManageMasterData();

        if ($company->camps()->exists()) {
            return back()->with('error', 'Perusahaan tidak bisa dihapus karena masih dipakai site.');
        }

        AuditLogger::log('master.deleted', 'Master perusahaan dihapus.', $company);
        $company->delete();

        return back()->with('success', 'Master perusahaan dihapus.');
    }

    public function destroyCamp(Camp $camp)
    {
        $this->ensureCanManageMasterData();

        if ($camp->units()->exists() || $camp->purchaseRequests()->exists() || SiteDispatch::where('camp_id', $camp->id)->exists()) {
            return back()->with('error', 'Camp tidak bisa dihapus karena sudah dipakai transaksi.');
        }

        AuditLogger::log('master.deleted', 'Master site dihapus.', $camp);
        $camp->delete();

        return back()->with('success', 'Master camp dihapus.');
    }

    public function storeVendor(Request $request)
    {
        $this->ensureCanManageMasterData();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
        ]);

        $vendor = DB::transaction(fn () => Vendor::create([
            'code' => DocumentNumberService::nextVendorCode(),
            ...$data,
        ]));
        AuditLogger::log('master.created', 'Master vendor ditambahkan.', $vendor);

        return redirect()->route('master.vendors')->with('success', 'Master vendor ditambahkan.');
    }

    public function updateVendor(Request $request, Vendor $vendor)
    {
        $this->ensureCanManageMasterData();

        $vendor->update($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
        ]));
        AuditLogger::log('master.updated', 'Master vendor diperbarui.', $vendor);

        return redirect()->route('master.vendors')->with('success', 'Master vendor diperbarui.');
    }

    public function destroyVendor(Vendor $vendor)
    {
        $this->ensureCanManageMasterData();

        if ($vendor->quotations()->exists()) {
            return back()->with('error', 'Vendor tidak bisa dihapus karena sudah dipakai harga pembanding.');
        }

        AuditLogger::log('master.deleted', 'Master vendor dihapus.', $vendor);
        $vendor->delete();

        return back()->with('success', 'Master vendor dihapus.');
    }

    public function storeUnit(Request $request)
    {
        $this->ensureCanManageMasterData();

        $unit = Unit::create($request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:units,code'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'camp_id' => ['nullable', 'exists:camps,id'],
        ]));
        AuditLogger::log('master.created', 'Master unit ditambahkan.', $unit);

        return redirect()->route('master.units')->with('success', 'Master unit ditambahkan.');
    }

    public function updateUnit(Request $request, Unit $unit)
    {
        $this->ensureCanManageMasterData();

        $unit->update($request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('units', 'code')->ignore($unit)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'camp_id' => ['nullable', 'exists:camps,id'],
        ]));
        AuditLogger::log('master.updated', 'Master unit diperbarui.', $unit);

        return redirect()->route('master.units')->with('success', 'Master unit diperbarui.');
    }

    public function destroyUnit(Unit $unit)
    {
        $this->ensureCanManageMasterData();

        if (PurchaseRequestItem::where('unit_id', $unit->id)->exists() || UsageLog::where('unit_id', $unit->id)->exists()) {
            return back()->with('error', 'Unit tidak bisa dihapus karena sudah dipakai transaksi.');
        }

        AuditLogger::log('master.deleted', 'Master unit dihapus.', $unit);
        $unit->delete();

        return back()->with('success', 'Master unit dihapus.');
    }

    public function storeItemCategory(Request $request)
    {
        $this->ensureCanManageMasterData();

        $itemCategory = ItemCategory::create($this->validateItemCategory($request));
        AuditLogger::log('master.created', 'Master kategori barang ditambahkan.', $itemCategory);

        return redirect()->route('master.item-categories')->with('success', 'Master kategori barang ditambahkan.');
    }

    public function updateItemCategory(Request $request, ItemCategory $itemCategory)
    {
        $this->ensureCanManageMasterData();
        $data = $this->validateItemCategory($request, $itemCategory);

        DB::transaction(function () use ($data, $itemCategory) {
            $itemCategory->update($data);
            InventoryItem::where('item_category_id', $itemCategory->id)->update(['category' => $itemCategory->name]);
        });

        AuditLogger::log('master.updated', 'Master kategori barang diperbarui.', $itemCategory);

        return redirect()->route('master.item-categories')->with('success', 'Master kategori barang diperbarui.');
    }

    public function destroyItemCategory(ItemCategory $itemCategory)
    {
        $this->ensureCanManageMasterData();

        if ($itemCategory->items()->exists()) {
            return back()->with('error', 'Kategori tidak bisa dihapus karena sudah dipakai barang.');
        }

        AuditLogger::log('master.deleted', 'Master kategori barang dihapus.', $itemCategory);
        $itemCategory->delete();

        return back()->with('success', 'Master kategori barang dihapus.');
    }

    public function storeItem(Request $request)
    {
        $this->ensureCanManageMasterData();

        $inventoryItem = InventoryItem::create($this->validateInventoryItem($request));
        AuditLogger::log('master.created', 'Master barang ditambahkan.', $inventoryItem);

        return redirect()->route('master.items')->with('success', 'Master barang ditambahkan.');
    }

    public function updateItem(Request $request, InventoryItem $inventoryItem)
    {
        $this->ensureCanManageMasterData();

        $inventoryItem->update($this->validateInventoryItem($request, $inventoryItem));
        AuditLogger::log('master.updated', 'Master barang diperbarui.', $inventoryItem);

        return redirect()->route('master.items')->with('success', 'Master barang diperbarui.');
    }

    public function destroyItem(InventoryItem $inventoryItem)
    {
        $this->ensureCanManageMasterData();

        if ($inventoryItem->requestItems()->exists()
            || UsageLog::where('inventory_item_id', $inventoryItem->id)->exists()
            || StockOpname::whereHas('items', fn ($query) => $query->where('inventory_item_id', $inventoryItem->id))->exists()) {
            return back()->with('error', 'Barang tidak bisa dihapus karena sudah dipakai transaksi.');
        }

        AuditLogger::log('master.deleted', 'Master barang dihapus.', $inventoryItem);
        $inventoryItem->delete();

        return back()->with('success', 'Master barang dihapus.');
    }

    public function storeUom(Request $request)
    {
        $this->ensureCanManageMasterData();

        $unitOfMeasure = UnitOfMeasure::create($request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:unit_of_measures,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]));
        AuditLogger::log('master.created', 'Master satuan ditambahkan.', $unitOfMeasure);

        return redirect()->route('master.uoms')->with('success', 'Master satuan ditambahkan.');
    }

    public function updateUom(Request $request, UnitOfMeasure $unitOfMeasure)
    {
        $this->ensureCanManageMasterData();

        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('unit_of_measures', 'code')->ignore($unitOfMeasure)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($data, $unitOfMeasure) {
            $oldCode = $unitOfMeasure->code;
            $unitOfMeasure->update($data);

            if ($oldCode !== $data['code']) {
                InventoryItem::where('uom', $oldCode)->update(['uom' => $data['code']]);
            }
        });
        AuditLogger::log('master.updated', 'Master satuan diperbarui.', $unitOfMeasure);

        return redirect()->route('master.uoms')->with('success', 'Master satuan diperbarui.');
    }

    public function destroyUom(UnitOfMeasure $unitOfMeasure)
    {
        $this->ensureCanManageMasterData();

        if (InventoryItem::where('uom', $unitOfMeasure->code)->exists()) {
            return back()->with('error', 'Satuan tidak bisa dihapus karena sudah dipakai barang.');
        }

        AuditLogger::log('master.deleted', 'Master satuan dihapus.', $unitOfMeasure);
        $unitOfMeasure->delete();

        return back()->with('success', 'Master satuan dihapus.');
    }

    private function index(string $activeMaster, string $permission)
    {
        $this->ensurePermission($permission);
        $user = auth()->user()->loadMissing('role');
        [$companyIds, $campIds] = $this->scopedIds($user);

        $companies = Company::query()
            ->when(! $user->isAdmin() && $companyIds, fn ($query) => $query->whereIn('id', $companyIds))
            ->orderBy('name')
            ->get();

        $activeCompanyId = (int) request('company_id', 0) ?: null;
        if ($activeCompanyId) {
            abort_unless($companies->contains('id', $activeCompanyId), 403);
        }

        $campsQuery = Camp::with('company')
            ->when(! $user->isAdmin() && $campIds, fn ($query) => $query->whereIn('id', $campIds))
            ->when(! $user->isAdmin() && ! $campIds && $companyIds, fn ($query) => $query->whereIn('company_id', $companyIds))
            ->when($activeCompanyId, fn ($query) => $query->where('company_id', $activeCompanyId));
        $visibleCampIds = (clone $campsQuery)->pluck('id')->all();

        return view('inventory.master-data.index', [
            'activeMenu' => 'master',
            'activeMaster' => $activeMaster,
            'activeCompanyId' => $activeCompanyId,
            'companies' => $companies,
            'camps' => $campsQuery->orderBy('name')->get(),
            'vendors' => Vendor::orderBy('name')->get(),
            'units' => Unit::with('camp')
                ->when((! $user->isAdmin() || $activeCompanyId) && $visibleCampIds, fn ($query) => $query->whereIn('camp_id', $visibleCampIds))
                ->when((! $user->isAdmin() || $activeCompanyId) && ! $visibleCampIds, fn ($query) => $query->whereRaw('1 = 0'))
                ->orderBy('name')
                ->get(),
            'uoms' => UnitOfMeasure::orderBy('code')->get(),
            'itemCategories' => ItemCategory::orderBy('name')->get(),
            'items' => InventoryItem::with('itemCategory')->orderBy('name')->get(),
            'roles' => ($user->hasPermission('users.manage') || $user->hasPermission('roles.manage'))
                ? Role::orderBy('name')->get()
                : collect(),
            'users' => $user->hasPermission('users.manage')
                ? User::with('role')->orderBy('name')->get()
                : collect(),
            'notifications' => $user->systemNotifications()->latest()->limit(8)->get(),
            'unreadNotificationsCount' => $user->systemNotifications()->whereNull('read_at')->count(),
        ]);
    }

    private function renderForm(string $type, string $mode, mixed $record = null)
    {
        $user = auth()->user()->loadMissing('role');
        [$companyIds, $campIds] = $this->scopedIds($user);

        return view('inventory.master-data.form', [
            'type' => $type,
            'mode' => $mode,
            'record' => $record,
            'companies' => Company::query()
                ->when(! $user->isAdmin() && $companyIds, fn ($query) => $query->whereIn('id', $companyIds))
                ->orderBy('name')
                ->get(),
            'camps' => Camp::with('company')
                ->when(! $user->isAdmin() && $campIds, fn ($query) => $query->whereIn('id', $campIds))
                ->when(! $user->isAdmin() && ! $campIds && $companyIds, fn ($query) => $query->whereIn('company_id', $companyIds))
                ->orderBy('name')
                ->get(),
            'uoms' => UnitOfMeasure::orderBy('code')->get(),
            'itemCategories' => ItemCategory::orderBy('name')->get(),
            'roles' => Role::orderBy('name')->get(),
            'permissionGroups' => Role::permissionGroups(),
        ]);
    }

    private function validateItemCategory(Request $request, ?ItemCategory $itemCategory = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', $itemCategory ? Rule::unique('item_categories', 'code')->ignore($itemCategory) : Rule::unique('item_categories', 'code')],
            'name' => ['required', 'string', 'max:255', $itemCategory ? Rule::unique('item_categories', 'name')->ignore($itemCategory) : Rule::unique('item_categories', 'name')],
            'uses_inventory_planning' => ['nullable', 'boolean'],
            'uses_shrinkage_tolerance' => ['nullable', 'boolean'],
            'default_shrinkage_tolerance_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);

        $data['uses_inventory_planning'] = $request->boolean('uses_inventory_planning');
        $data['uses_shrinkage_tolerance'] = $request->boolean('uses_shrinkage_tolerance');
        $data['default_shrinkage_tolerance_percent'] = (float) ($data['default_shrinkage_tolerance_percent'] ?? 0);

        return $data;
    }

    private function validateInventoryItem(Request $request, ?InventoryItem $inventoryItem = null): array
    {
        $itemCategory = ItemCategory::findOrFail($request->input('item_category_id'));
        $usesInventoryPlanning = (bool) $itemCategory->uses_inventory_planning;

        $data = $request->validate([
            'sku' => ['required', 'string', 'max:50', $inventoryItem ? Rule::unique('inventory_items', 'sku')->ignore($inventoryItem) : Rule::unique('inventory_items', 'sku')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'item_category_id' => ['required', 'exists:item_categories,id'],
            'uom' => ['required', 'exists:unit_of_measures,code'],
            'minimum_stock' => [Rule::requiredIf(! $usesInventoryPlanning), 'nullable', 'integer', 'min:0'],
            'maximum_stock' => [Rule::requiredIf(! $usesInventoryPlanning), 'nullable', 'integer', 'min:0', 'gte:minimum_stock'],
            'forecast_annual_demand' => ['nullable', 'numeric', 'min:0'],
            'lead_time_days' => ['nullable', 'integer', 'min:0'],
            'safety_stock' => ['nullable', 'numeric', 'min:0'],
            'ordering_cost' => ['nullable', 'numeric', 'min:0'],
            'holding_cost_per_unit' => ['nullable', 'numeric', 'min:0'],
            'shrinkage_tolerance_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $data['category'] = $itemCategory->name;
        $data['minimum_stock'] = $usesInventoryPlanning ? 0 : (int) $data['minimum_stock'];
        $data['maximum_stock'] = $usesInventoryPlanning ? 0 : (int) $data['maximum_stock'];

        if (! $usesInventoryPlanning) {
            $data['forecast_annual_demand'] = 0;
            $data['lead_time_days'] = 0;
            $data['safety_stock'] = 0;
            $data['ordering_cost'] = 0;
            $data['holding_cost_per_unit'] = 0;
        }

        $data['shrinkage_tolerance_percent'] = (float) ($data['shrinkage_tolerance_percent'] ?? $itemCategory->default_shrinkage_tolerance_percent);

        return $data;
    }

    private function scopedIds(User $user): array
    {
        $companyIds = $user->scopedCompanyIds();
        $campIds = $user->scopedCampIds();

        if (! $companyIds && $campIds) {
            $companyIds = Camp::whereIn('id', $campIds)
                ->pluck('company_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        return [$companyIds, $campIds];
    }

    private function assertMasterType(string $type): void
    {
        abort_unless(array_key_exists($type, $this->masterTypes()), 404);
    }

    private function masterModel(string $type): string
    {
        return $this->masterTypes()[$type];
    }

    private function masterTypes(): array
    {
        return [
            'companies' => Company::class,
            'camps' => Camp::class,
            'vendors' => Vendor::class,
            'units' => Unit::class,
            'item-categories' => ItemCategory::class,
            'items' => InventoryItem::class,
            'uoms' => UnitOfMeasure::class,
            'users' => User::class,
            'roles' => Role::class,
        ];
    }

    private function ensureMasterPermission(string $type): void
    {
        $this->ensurePermission(match ($type) {
            'users' => 'users.manage',
            'roles' => 'roles.manage',
            default => 'master.manage',
        });
    }

    private function ensurePermission(string $permission): void
    {
        abort_unless(auth()->user()?->loadMissing('role')->hasPermission($permission), 403);
    }

    private function ensureCanManageMasterData(): void
    {
        abort_unless(auth()->user()?->loadMissing('role')->canManageMasterData(), 403);
    }
}
