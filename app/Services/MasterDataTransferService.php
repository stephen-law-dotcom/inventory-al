<?php

namespace App\Services;

use App\Models\Camp;
use App\Models\Company;
use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Models\Role;
use App\Models\Unit;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MasterDataTransferService
{
    public function export(string $type): array
    {
        return match ($type) {
            'companies' => $this->exportCompanies(),
            'camps' => $this->exportCamps(),
            'vendors' => $this->exportVendors(),
            'units' => $this->exportUnits(),
            'item-categories' => $this->exportItemCategories(),
            'items' => $this->exportItems(),
            'uoms' => $this->exportUoms(),
            'users' => $this->exportUsers(),
            'roles' => $this->exportRoles(),
            default => throw ValidationException::withMessages(['type' => 'Jenis master data tidak didukung.']),
        };
    }

    public function import(string $type, Collection $rows): int
    {
        $rows = $rows
            ->map(fn ($row) => collect($row)->map(fn ($value) => is_string($value) ? trim($value) : $value)->all())
            ->filter(fn (array $row) => collect($row)->contains(fn ($value) => filled($value)))
            ->values();

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['import_file' => 'File import tidak memiliki baris data.']);
        }

        DB::transaction(function () use ($type, $rows): void {
            foreach ($rows as $index => $row) {
                try {
                    $this->importRow($type, $row);
                } catch (ValidationException $exception) {
                    $message = collect($exception->errors())->flatten()->first() ?? 'Data tidak valid.';

                    throw ValidationException::withMessages([
                        'import_file' => 'Baris '.($index + 2).': '.$message,
                    ]);
                }
            }
        });

        return $rows->count();
    }

    private function importRow(string $type, array $row): void
    {
        match ($type) {
            'companies' => $this->importCompany($row),
            'camps' => $this->importCamp($row),
            'vendors' => $this->importVendor($row),
            'units' => $this->importUnit($row),
            'item-categories' => $this->importItemCategory($row),
            'items' => $this->importItem($row),
            'uoms' => $this->importUom($row),
            'users' => $this->importUser($row),
            'roles' => $this->importRole($row),
            default => throw ValidationException::withMessages(['type' => 'Jenis master data tidak didukung.']),
        };
    }

    private function importCompany(array $row): void
    {
        $existing = Company::where('code', $row['code'] ?? null)->first();
        $data = Validator::make($row, [
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255', Rule::unique('companies', 'name')->ignore($existing)],
            'order_request_code' => ['required', 'string', 'max:50', Rule::unique('companies', 'order_request_code')->ignore($existing)],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
        ])->validate();

        $company = Company::updateOrCreate(['code' => $data['code']], $data);
        Camp::where('company_id', $company->id)->update(['company_name' => $company->name]);
    }

    private function importCamp(array $row): void
    {
        $data = Validator::make($row, [
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'company_code' => ['required', 'string', 'exists:companies,code'],
            'location' => ['nullable', 'string', 'max:255'],
        ])->validate();
        $company = Company::where('code', $data['company_code'])->firstOrFail();

        Camp::updateOrCreate(['code' => $data['code']], [
            'name' => $data['name'],
            'company_id' => $company->id,
            'company_name' => $company->name,
            'location' => $data['location'] ?? null,
        ]);
    }

    private function importVendor(array $row): void
    {
        $data = Validator::make($row, [
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
        ])->validate();

        Vendor::updateOrCreate(['code' => $data['code']], $data);
    }

    private function importUnit(array $row): void
    {
        $data = Validator::make($row, [
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'camp_code' => ['nullable', 'string', 'exists:camps,code'],
        ])->validate();

        Unit::updateOrCreate(['code' => $data['code']], [
            'name' => $data['name'],
            'type' => $data['type'] ?? null,
            'serial_number' => $data['serial_number'] ?? null,
            'camp_id' => filled($data['camp_code'] ?? null) ? Camp::where('code', $data['camp_code'])->value('id') : null,
        ]);
    }

    private function importItemCategory(array $row): void
    {
        $existing = ItemCategory::where('code', $row['code'] ?? null)->first();
        $data = Validator::make($row, [
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:255', Rule::unique('item_categories', 'name')->ignore($existing)],
            'uses_inventory_planning' => ['nullable'],
            'uses_shrinkage_tolerance' => ['nullable'],
            'default_shrinkage_tolerance_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'description' => ['nullable', 'string'],
        ])->validate();

        $category = ItemCategory::updateOrCreate(['code' => $data['code']], [
            'name' => $data['name'],
            'uses_inventory_planning' => $this->boolean($data['uses_inventory_planning'] ?? false),
            'uses_shrinkage_tolerance' => $this->boolean($data['uses_shrinkage_tolerance'] ?? false),
            'default_shrinkage_tolerance_percent' => (float) ($data['default_shrinkage_tolerance_percent'] ?? 0),
            'description' => $data['description'] ?? null,
        ]);
        InventoryItem::where('item_category_id', $category->id)->update(['category' => $category->name]);
    }

    private function importItem(array $row): void
    {
        $data = Validator::make($row, [
            'sku' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category_code' => ['required', 'string', 'exists:item_categories,code'],
            'uom' => ['required', 'string', 'exists:unit_of_measures,code'],
            'minimum_stock' => ['nullable', 'integer', 'min:0'],
            'forecast_annual_demand' => ['nullable', 'numeric', 'min:0'],
            'lead_time_days' => ['nullable', 'integer', 'min:0'],
            'safety_stock' => ['nullable', 'numeric', 'min:0'],
            'ordering_cost' => ['nullable', 'numeric', 'min:0'],
            'holding_cost_per_unit' => ['nullable', 'numeric', 'min:0'],
            'shrinkage_tolerance_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ])->validate();
        $category = ItemCategory::where('code', $data['category_code'])->firstOrFail();
        $usesPlanning = (bool) $category->uses_inventory_planning;

        if (! $usesPlanning && ! isset($data['minimum_stock'])) {
            throw ValidationException::withMessages(['minimum_stock' => 'Minimum stock wajib untuk kategori tanpa EOQ/ROP.']);
        }

        InventoryItem::updateOrCreate(['sku' => $data['sku']], [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'item_category_id' => $category->id,
            'category' => $category->name,
            'uom' => $data['uom'],
            'minimum_stock' => $usesPlanning ? 0 : (int) $data['minimum_stock'],
            'forecast_annual_demand' => $usesPlanning ? (float) ($data['forecast_annual_demand'] ?? 0) : 0,
            'lead_time_days' => $usesPlanning ? (int) ($data['lead_time_days'] ?? 0) : 0,
            'safety_stock' => $usesPlanning ? (float) ($data['safety_stock'] ?? 0) : 0,
            'ordering_cost' => $usesPlanning ? (float) ($data['ordering_cost'] ?? 0) : 0,
            'holding_cost_per_unit' => $usesPlanning ? (float) ($data['holding_cost_per_unit'] ?? 0) : 0,
            'shrinkage_tolerance_percent' => (float) ($data['shrinkage_tolerance_percent'] ?? $category->default_shrinkage_tolerance_percent),
        ]);
    }

    private function importUom(array $row): void
    {
        $data = Validator::make($row, [
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ])->validate();

        UnitOfMeasure::updateOrCreate(['code' => $data['code']], $data);
    }

    private function importUser(array $row): void
    {
        $existing = User::where('email', $row['email'] ?? null)->first();
        $data = Validator::make($row, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'role_slug' => ['required', 'string', 'exists:roles,slug'],
            'company_codes' => ['nullable', 'string'],
            'camp_codes' => ['nullable', 'string'],
            'password' => [$existing ? 'nullable' : 'required', 'nullable', 'string', 'min:8'],
        ])->validate();
        $companyCodes = $this->list($data['company_codes'] ?? null);
        $campCodes = $this->list($data['camp_codes'] ?? null);
        $companyIds = Company::whereIn('code', $companyCodes)->pluck('id');
        $campIds = Camp::whereIn('code', $campCodes)->pluck('id');

        if ($companyIds->count() !== count($companyCodes) || $campIds->count() !== count($campCodes)) {
            throw ValidationException::withMessages(['scope' => 'Ada company_codes atau camp_codes yang tidak ditemukan.']);
        }

        $values = [
            'name' => $data['name'],
            'role_id' => Role::where('slug', $data['role_slug'])->value('id'),
            'company_ids' => $companyIds->values()->all(),
            'camp_ids' => $campIds->values()->all(),
        ];
        if (filled($data['password'] ?? null)) {
            $values['password'] = $data['password'];
        }

        User::updateOrCreate(['email' => $data['email']], $values);
    }

    private function importRole(array $row): void
    {
        $data = Validator::make($row, [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'is_admin' => ['nullable'],
            'permissions' => ['nullable', 'string'],
        ])->validate();
        $isAdmin = $this->boolean($data['is_admin'] ?? false);
        $permissions = $this->list($data['permissions'] ?? null);
        $unknownPermissions = array_diff($permissions, Role::permissionKeys());

        if (! $isAdmin && $unknownPermissions) {
            throw ValidationException::withMessages(['permissions' => 'Permission tidak dikenal: '.implode(', ', $unknownPermissions).'.']);
        }

        $permissions = $isAdmin ? ['*'] : Role::normalizePermissions($permissions);
        Role::updateOrCreate(['slug' => $data['slug']], [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_admin' => $isAdmin,
            'permissions' => $permissions,
            'can_see_prices' => $isAdmin || in_array('prices.view', $permissions, true),
        ]);
    }

    private function exportCompanies(): array
    {
        return $this->payload(
            ['code', 'name', 'order_request_code', 'phone', 'address'],
            Company::orderBy('code')->get()->map(fn ($item) => [$item->code, $item->name, $item->order_request_code, $item->phone, $item->address])
        );
    }

    private function exportCamps(): array
    {
        return $this->payload(
            ['code', 'name', 'company_code', 'location'],
            Camp::with('company')->orderBy('code')->get()->map(fn ($item) => [$item->code, $item->name, $item->company?->code, $item->location])
        );
    }

    private function exportVendors(): array
    {
        return $this->payload(
            ['code', 'name', 'contact_person', 'phone', 'address'],
            Vendor::orderBy('code')->get()->map(fn ($item) => [$item->code, $item->name, $item->contact_person, $item->phone, $item->address])
        );
    }

    private function exportUnits(): array
    {
        return $this->payload(
            ['code', 'name', 'type', 'serial_number', 'camp_code'],
            Unit::with('camp')->orderBy('code')->get()->map(fn ($item) => [$item->code, $item->name, $item->type, $item->serial_number, $item->camp?->code])
        );
    }

    private function exportItemCategories(): array
    {
        return $this->payload(
            ['code', 'name', 'uses_inventory_planning', 'uses_shrinkage_tolerance', 'default_shrinkage_tolerance_percent', 'description'],
            ItemCategory::orderBy('code')->get()->map(fn ($item) => [$item->code, $item->name, (int) $item->uses_inventory_planning, (int) $item->uses_shrinkage_tolerance, $item->default_shrinkage_tolerance_percent, $item->description])
        );
    }

    private function exportItems(): array
    {
        return $this->payload(
            ['sku', 'name', 'description', 'category_code', 'uom', 'minimum_stock', 'forecast_annual_demand', 'lead_time_days', 'safety_stock', 'ordering_cost', 'holding_cost_per_unit', 'shrinkage_tolerance_percent'],
            InventoryItem::with('itemCategory')->orderBy('sku')->get()->map(fn ($item) => [$item->sku, $item->name, $item->description, $item->itemCategory?->code, $item->uom, $item->minimum_stock, $item->forecast_annual_demand, $item->lead_time_days, $item->safety_stock, $item->ordering_cost, $item->holding_cost_per_unit, $item->shrinkage_tolerance_percent])
        );
    }

    private function exportUoms(): array
    {
        return $this->payload(
            ['code', 'name', 'description'],
            UnitOfMeasure::orderBy('code')->get()->map(fn ($item) => [$item->code, $item->name, $item->description])
        );
    }

    private function exportUsers(): array
    {
        return $this->payload(
            ['name', 'email', 'role_slug', 'company_codes', 'camp_codes', 'password'],
            User::with('role')->orderBy('email')->get()->map(fn ($item) => [
                $item->name,
                $item->email,
                $item->role?->slug,
                Company::whereIn('id', $item->company_ids ?? [])->pluck('code')->implode(';'),
                Camp::whereIn('id', $item->camp_ids ?? [])->pluck('code')->implode(';'),
                '',
            ])
        );
    }

    private function exportRoles(): array
    {
        return $this->payload(
            ['name', 'slug', 'description', 'is_admin', 'permissions'],
            Role::orderBy('slug')->get()->map(fn ($item) => [$item->name, $item->slug, $item->description, (int) $item->is_admin, implode(';', $item->permissions ?? [])])
        );
    }

    private function payload(array $headings, Collection $rows): array
    {
        return ['headings' => $headings, 'rows' => $rows->values()->all()];
    }

    private function boolean(mixed $value): bool
    {
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'ya', 'y'], true);
    }

    private function list(mixed $value): array
    {
        if (! filled($value)) {
            return [];
        }

        return collect(preg_split('/[;,|]+/', (string) $value))
            ->map(fn ($entry) => trim($entry))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
