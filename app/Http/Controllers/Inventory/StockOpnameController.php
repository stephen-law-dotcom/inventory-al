<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Camp;
use App\Models\CampStock;
use App\Models\Company;
use App\Models\InventoryItem;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\CampStockService;
use App\Services\DocumentNumberService;
use App\Services\InventoryPageContextService;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StockOpnameController extends Controller
{
    public function __construct(private readonly InventoryPageContextService $pages) {}

    public function stockOpname()
    {
        $this->ensurePermission('stock_opname.view');

        return view('inventory.stock-opname.index', $this->pageData());
    }

    public function createStockOpname()
    {
        $this->ensurePermission('stock_opname.manage');

        $user = auth()->user()?->loadMissing('role');
        $companyIds = $user?->scopedCompanyIds() ?? [];
        $campIds = $user?->scopedCampIds() ?? [];
        $activeCompanyId = request()->integer('company_id') ?: null;
        if (! $activeCompanyId) {
            $availableCompanyIds = Company::query()
                ->when(! $user?->isAdmin() && $companyIds, fn ($query) => $query->whereIn('id', $companyIds))
                ->pluck('id');
            $activeCompanyId = $availableCompanyIds->count() === 1 ? (int) $availableCompanyIds->first() : null;
        }

        $camps = Camp::with('company')
            ->when(! $user?->isAdmin() && $campIds, fn ($query) => $query->whereIn('id', $campIds))
            ->when(! $user?->isAdmin() && ! $campIds && $companyIds, fn ($query) => $query->whereIn('company_id', $companyIds))
            ->when($activeCompanyId, fn ($query) => $query->where('company_id', $activeCompanyId))
            ->orderBy('name')
            ->get();

        $campStocks = CampStock::whereIn('camp_id', $camps->pluck('id'))
            ->get()
            ->groupBy('camp_id')
            ->map(fn ($stocks) => $stocks->mapWithKeys(fn ($stock) => [
                (string) $stock->inventory_item_id => (float) $stock->quantity,
            ]));

        return view('inventory.stock-opname.form', [
            'items' => InventoryItem::orderBy('name')->get(),
            'camps' => $camps,
            'campStocks' => $campStocks,
            'officers' => User::with('role')->orderBy('name')->get(),
            'activeCompanyId' => $activeCompanyId,
            'stockOpname' => null,
        ]);
    }

    public function editStockOpname(StockOpname $stockOpname)
    {
        $this->ensurePermission('stock_opname.manage');
        $this->ensureCanAccess($stockOpname);
        abort_unless($stockOpname->isEditable(), 409, 'Stock opname hanya dapat diubah saat draft atau perlu hitung ulang.');

        $stockOpname->load(['items.inventoryItem', 'officers', 'camp.company']);
        $user = auth()->user()?->loadMissing('role');
        $companyIds = $user?->scopedCompanyIds() ?? [];
        $campIds = $user?->scopedCampIds() ?? [];
        $activeCompanyId = request()->integer('company_id') ?: $stockOpname->camp?->company_id;
        if (! $activeCompanyId) {
            $availableCompanyIds = Company::query()
                ->when(! $user?->isAdmin() && $companyIds, fn ($query) => $query->whereIn('id', $companyIds))
                ->pluck('id');
            $activeCompanyId = $availableCompanyIds->count() === 1 ? (int) $availableCompanyIds->first() : null;
        }
        $camps = Camp::with('company')
            ->when(! $user?->isAdmin() && $campIds, fn ($query) => $query->whereIn('id', $campIds))
            ->when(! $user?->isAdmin() && ! $campIds && $companyIds, fn ($query) => $query->whereIn('company_id', $companyIds))
            ->when($activeCompanyId, fn ($query) => $query->where('company_id', $activeCompanyId))
            ->orderBy('name')
            ->get();
        $campStocks = CampStock::whereIn('camp_id', $camps->pluck('id'))
            ->get()
            ->groupBy('camp_id')
            ->map(fn ($stocks) => $stocks->mapWithKeys(fn ($stock) => [
                (string) $stock->inventory_item_id => (float) $stock->quantity,
            ]));

        return view('inventory.stock-opname.form', [
            'items' => InventoryItem::orderBy('name')->get(),
            'camps' => $camps,
            'campStocks' => $campStocks,
            'officers' => User::with('role')->orderBy('name')->get(),
            'activeCompanyId' => $activeCompanyId,
            'stockOpname' => $stockOpname,
        ]);
    }

    public function stockOpnameItemHistory(InventoryItem $inventoryItem)
    {
        $this->ensurePermission('stock_opname.view');

        $inventoryItem->load([
            'usageLogs.unit.camp',
            'stockMovements.user',
            'campStockMovements.camp',
            'campStockMovements.user',
        ]);

        return view('inventory.stock-opname.item-history', [
            'item' => $inventoryItem,
            'usageLogs' => $inventoryItem->usageLogs->sortByDesc('used_at')->values(),
            'stockMovements' => $inventoryItem->stockMovements->sortByDesc('created_at')->values(),
            'campStockMovements' => $inventoryItem->campStockMovements->sortByDesc('created_at')->values(),
        ]);
    }

    public function storeStockOpname(Request $request)
    {
        $this->ensurePermission('stock_opname.manage');
        [$stockOpname, $data] = $this->saveDraft($request);

        return $this->saveResponse($stockOpname, $data);
    }

    public function updateStockOpname(Request $request, StockOpname $stockOpname)
    {
        $this->ensurePermission('stock_opname.manage');
        $this->ensureCanAccess($stockOpname);
        abort_unless($stockOpname->isEditable(), 409, 'Stock opname ini tidak dapat diubah.');
        [$stockOpname, $data] = $this->saveDraft($request, $stockOpname);

        return $this->saveResponse($stockOpname, $data);
    }

    public function submitStockOpname(StockOpname $stockOpname)
    {
        $this->ensurePermission('stock_opname.manage');
        $this->ensureCanAccess($stockOpname);
        abort_unless($stockOpname->status === StockOpname::STATUS_DRAFT, 409, 'Hanya draft yang dapat diajukan.');

        $changedItems = DB::transaction(function () use ($stockOpname) {
            $locked = StockOpname::with('items.inventoryItem')->lockForUpdate()->findOrFail($stockOpname->id);
            $changedItems = [];

            // Recheck every balance because stock may change after the physical count.
            foreach ($locked->items as $item) {
                $current = $this->lockedBalance($locked, $item->inventoryItem);
                if (abs($current - (float) $item->system_stock) > 0.004) {
                    $changedItems[] = $item->inventoryItem->name;
                }
            }
            if ($changedItems !== []) {
                return $changedItems;
            }

            foreach ($locked->items as $item) {
                $item->update([
                    'tolerance_review_status' => abs((float) $item->difference) > 0.004 ? 'pending' : 'not_required',
                    'tolerance_reviewed_by' => null,
                    'tolerance_reviewed_at' => null,
                    'tolerance_review_notes' => null,
                ]);
            }
            $locked->update([
                'status' => $locked->items->contains(fn ($item) => abs((float) $item->difference) > 0.004)
                    ? StockOpname::STATUS_PENDING_REVIEW
                    : StockOpname::STATUS_READY_TO_POST,
                'submitted_by' => auth()->id(),
                'submitted_at' => now(),
            ]);

            return [];
        });

        if ($changedItems !== []) {
            return back()->with('error', 'Stok sistem berubah untuk: '.implode(', ', $changedItems).'. Buka dan simpan ulang draft setelah penghitungan ulang.');
        }

        AuditLogger::log('stock_opname.submitted', 'Stock opname diajukan untuk pemeriksaan.', $stockOpname);

        return redirect()->route('stock-opname')->with('success', 'Stock opname diajukan untuk pemeriksaan.');
    }

    public function acceptStockOpnameTolerance(Request $request, StockOpnameItem $stockOpnameItem)
    {
        $stockOpnameItem->loadMissing('stockOpname');
        $this->ensureCanReview($stockOpnameItem->stockOpname);
        $data = $request->validate([
            'tolerance_review_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $stockOpnameItem = DB::transaction(function () use ($stockOpnameItem, $data) {
            $lockedItem = StockOpnameItem::lockForUpdate()->findOrFail($stockOpnameItem->id);
            $lockedItem->loadMissing('stockOpname');
            if ($lockedItem->stockOpname->status !== StockOpname::STATUS_PENDING_REVIEW || $lockedItem->tolerance_review_status !== 'pending') {
                return null;
            }

            $lockedItem->update([
                'tolerance_review_status' => 'accepted',
                'tolerance_reviewed_by' => auth()->id(),
                'tolerance_reviewed_at' => now(),
                'tolerance_review_notes' => $data['tolerance_review_notes'] ?? null,
            ]);

            if (! $lockedItem->stockOpname->items()->where('tolerance_review_status', 'pending')->exists()) {
                $lockedItem->stockOpname->update(['status' => StockOpname::STATUS_READY_TO_POST]);
            }

            return $lockedItem->refresh();
        });

        if (! $stockOpnameItem) {
            return back()->with('error', 'Selisih ini sudah diproses atau tidak memerlukan pemeriksaan.');
        }

        AuditLogger::log('stock_opname.variance_accepted', 'Selisih stock opname diterima.', $stockOpnameItem);

        return back()->with('success', 'Selisih diterima. Stok belum berubah sampai posting final.');
    }

    public function rejectStockOpnameTolerance(Request $request, StockOpnameItem $stockOpnameItem)
    {
        $stockOpnameItem->loadMissing('stockOpname');
        $this->ensureCanReview($stockOpnameItem->stockOpname);
        $data = $request->validate([
            'tolerance_review_notes' => ['required', 'string', 'max:1000'],
        ]);

        $stockOpnameItem = DB::transaction(function () use ($stockOpnameItem, $data) {
            $lockedItem = StockOpnameItem::lockForUpdate()->findOrFail($stockOpnameItem->id);
            $lockedItem->loadMissing('stockOpname');
            if ($lockedItem->stockOpname->status !== StockOpname::STATUS_PENDING_REVIEW || $lockedItem->tolerance_review_status !== 'pending') {
                return null;
            }

            $lockedItem->update([
                'tolerance_review_status' => 'rejected',
                'tolerance_reviewed_by' => auth()->id(),
                'tolerance_reviewed_at' => now(),
                'tolerance_review_notes' => $data['tolerance_review_notes'],
            ]);
            $lockedItem->stockOpname->update(['status' => StockOpname::STATUS_REVISION_REQUIRED]);

            return $lockedItem->refresh();
        });

        if (! $stockOpnameItem) {
            return back()->with('error', 'Selisih ini sudah diproses atau tidak memerlukan pemeriksaan.');
        }

        AuditLogger::log('stock_opname.variance_rejected', 'Selisih stock opname ditolak dan dikembalikan untuk hitung ulang.', $stockOpnameItem);

        return back()->with('success', 'Selisih ditolak. Dokumen dikembalikan untuk hitung ulang dan stok tidak berubah.');
    }

    public function postStockOpname(StockOpname $stockOpname)
    {
        $this->ensureCanPost($stockOpname);
        abort_unless($stockOpname->status === StockOpname::STATUS_READY_TO_POST, 409, 'Stock opname belum siap diposting.');

        $result = DB::transaction(function () use ($stockOpname) {
            $locked = StockOpname::with('items.inventoryItem')->lockForUpdate()->findOrFail($stockOpname->id);
            if ($locked->status !== StockOpname::STATUS_READY_TO_POST) {
                return ['state_error' => true];
            }

            $changed = [];
            foreach ($locked->items as $item) {
                $current = $this->lockedBalance($locked, $item->inventoryItem);
                if (abs($current - (float) $item->system_stock) > 0.004) {
                    $changed[] = $item;
                }
            }
            if ($changed !== []) {
                foreach ($changed as $item) {
                    $item->update([
                        'tolerance_review_status' => 'rejected',
                        'tolerance_reviewed_by' => auth()->id(),
                        'tolerance_reviewed_at' => now(),
                        'tolerance_review_notes' => 'Saldo sistem berubah setelah penghitungan. Lakukan hitung ulang.',
                    ]);
                }
                $locked->update(['status' => StockOpname::STATUS_REVISION_REQUIRED]);

                return ['changed' => collect($changed)->pluck('inventoryItem.name')->all()];
            }

            foreach ($locked->items as $item) {
                $physicalStock = (float) $item->physical_stock;
                if ($locked->camp_id) {
                    CampStockService::adjustTo($locked->camp_id, $item->inventory_item_id, $physicalStock, 'stock_opname', $item, 'Stock opname '.$locked->number);
                } else {
                    StockService::adjustTo($item->inventory_item_id, $physicalStock, 'stock_opname', $item, 'Stock opname '.$locked->number);
                }
            }
            $locked->update([
                'status' => StockOpname::STATUS_POSTED,
                'posted_by' => auth()->id(),
                'posted_at' => now(),
            ]);

            return ['posted' => true];
        });

        if (isset($result['state_error'])) {
            return back()->with('error', 'Stock opname sudah diproses oleh pengguna lain.');
        }
        if (isset($result['changed'])) {
            return back()->with('error', 'Posting diblokir karena stok sistem berubah untuk: '.implode(', ', $result['changed']).'. Lakukan hitung ulang.');
        }

        AuditLogger::log('stock_opname.posted', 'Stock opname diposting dan stok lokasi disesuaikan.', $stockOpname);

        return redirect()->route('stock-opname')->with('success', 'Stock opname berhasil diposting dan stok lokasi disesuaikan.');
    }

    private function saveDraft(Request $request, ?StockOpname $stockOpname = null): array
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['save_draft', 'submit_review'])],
            'opname_date' => ['required', 'date'],
            'location_type' => ['required', Rule::in(['warehouse', 'site'])],
            'camp_id' => ['nullable', 'required_if:location_type,site', 'integer', 'exists:camps,id'],
            'officer_ids' => ['required', 'array', 'min:1'],
            'officer_ids.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.selected' => ['nullable', 'boolean'],
            'items.*.inventory_item_id' => ['required', 'distinct', 'exists:inventory_items,id'],
            'items.*.physical_stock' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $selectedItems = collect($data['items'])->filter(fn ($item) => (bool) ($item['selected'] ?? false))->values();
        if ($selectedItems->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Pilih minimal satu barang untuk stock opname.']);
        }
        if ($selectedItems->contains(fn ($item) => ! array_key_exists('physical_stock', $item) || $item['physical_stock'] === null || $item['physical_stock'] === '')) {
            throw ValidationException::withMessages(['items' => 'Stok fisik wajib diisi untuk setiap barang yang dipilih.']);
        }

        $camp = null;
        if ($data['location_type'] === 'site') {
            $camp = Camp::with('company')->findOrFail($data['camp_id']);
            abort_unless(auth()->user()?->loadMissing('role')->canAccessCamp($camp->id, $camp->company_id), 403);
        }

        $officers = User::with('role')->whereIn('id', $data['officer_ids'])->orderBy('name')->get();
        $companyId = $camp?->company_id ?? ($data['company_id'] ?? null);
        foreach ($officers as $officer) {
            $allowed = $camp
                ? $officer->canAccessCamp($camp->id, $camp->company_id)
                : ($officer->isAdmin() || ($officer->scopedCampIds() === [] && ($companyId === null || $officer->canAccessCompany((int) $companyId))));
            if (! $allowed) {
                throw ValidationException::withMessages([
                    'officer_ids' => "Petugas {$officer->name} tidak memiliki akses ke lokasi stock opname.",
                ]);
            }
        }

        $stockOpname = DB::transaction(function () use ($data, $selectedItems, $camp, $officers, $stockOpname) {
            if ($stockOpname) {
                $stockOpname = StockOpname::lockForUpdate()->findOrFail($stockOpname->id);
                abort_unless($stockOpname->isEditable(), 409, 'Stock opname ini tidak dapat diubah.');
                $stockOpname->items()->delete();
            } else {
                $stockOpname = new StockOpname([
                    'number' => DocumentNumberService::nextStockOpname(),
                    'created_by' => auth()->id(),
                ]);
            }

            $stockOpname->fill([
                'opname_date' => $data['opname_date'],
                'counted_by' => $officers->pluck('name')->implode(', '),
                'location' => $camp ? $camp->name : 'Gudang Kantor',
                'location_type' => $data['location_type'],
                'camp_id' => $camp?->id,
                'notes' => $data['notes'] ?? null,
                'status' => StockOpname::STATUS_DRAFT,
                'submitted_by' => null,
                'submitted_at' => null,
                'posted_by' => null,
                'posted_at' => null,
            ])->save();
            $stockOpname->officers()->sync($officers->pluck('id'));

            $hasDifference = false;
            foreach ($selectedItems as $item) {
                $inventoryItem = InventoryItem::lockForUpdate()->findOrFail($item['inventory_item_id']);
                $systemStock = $this->lockedBalance($stockOpname, $inventoryItem);
                $physicalStock = round((float) $item['physical_stock'], 2);
                $difference = round($physicalStock - $systemStock, 2);
                $hasDifference = $hasDifference || abs($difference) > 0.004;

                $stockOpname->items()->create([
                    'inventory_item_id' => $inventoryItem->id,
                    'system_stock' => $systemStock,
                    'physical_stock' => $physicalStock,
                    'difference' => $difference,
                    'shrinkage_tolerance_percent' => (float) $inventoryItem->shrinkage_tolerance_percent,
                    'tolerance_quantity' => $inventoryItem->stockOpnameToleranceQuantity($systemStock),
                    'variance_status' => $inventoryItem->stockOpnameVarianceStatus($systemStock, $physicalStock),
                    'tolerance_review_status' => $data['action'] === 'submit_review' && abs($difference) > 0.004 ? 'pending' : 'not_required',
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            if ($data['action'] === 'submit_review') {
                $stockOpname->update([
                    'status' => $hasDifference ? StockOpname::STATUS_PENDING_REVIEW : StockOpname::STATUS_READY_TO_POST,
                    'submitted_by' => auth()->id(),
                    'submitted_at' => now(),
                ]);
            }

            return $stockOpname->refresh();
        });

        AuditLogger::log(
            $data['action'] === 'submit_review' ? 'stock_opname.submitted' : 'stock_opname.draft_saved',
            $data['action'] === 'submit_review' ? 'Stock opname diajukan untuk pemeriksaan.' : 'Draft stock opname disimpan.',
            $stockOpname,
            ['location_type' => $stockOpname->location_type, 'camp_id' => $stockOpname->camp_id, 'officer_ids' => $officers->pluck('id')->all()],
        );

        return [$stockOpname, $data];
    }

    private function saveResponse(StockOpname $stockOpname, array $data)
    {
        if ($data['action'] === 'save_draft') {
            return redirect()->route('stock-opnames.edit', $stockOpname)->with('success', 'Draft stock opname disimpan. Stok belum berubah.');
        }

        return redirect()->route('stock-opname')->with('success', 'Stock opname diajukan. Selisih harus diperiksa sebelum posting final.');
    }

    private function lockedBalance(StockOpname $stockOpname, InventoryItem $inventoryItem): float
    {
        if ($stockOpname->camp_id) {
            CampStock::firstOrCreate([
                'camp_id' => $stockOpname->camp_id,
                'inventory_item_id' => $inventoryItem->id,
            ], ['quantity' => 0]);

            return (float) CampStock::where('camp_id', $stockOpname->camp_id)
                ->where('inventory_item_id', $inventoryItem->id)
                ->lockForUpdate()
                ->value('quantity');
        }

        return (float) InventoryItem::whereKey($inventoryItem->id)->lockForUpdate()->value('stock_on_hand');
    }

    private function ensurePermission(string $permission): void
    {
        abort_unless(auth()->user()?->loadMissing('role')->hasPermission($permission), 403);
    }

    private function pageData(): array
    {
        $scope = $this->pages->scope();
        $visibleCampIds = $scope['visibleCampIds'];
        $restrict = $scope['restrict'];
        $stockOpnames = StockOpname::with(['camp.company', 'items.inventoryItem', 'officers'])
            ->when($restrict && $visibleCampIds, fn ($query) => $query->where(function ($access) use ($visibleCampIds) {
                $access->whereNull('camp_id')->orWhereIn('camp_id', $visibleCampIds);
            }))
            ->when($restrict && ! $visibleCampIds, fn ($query) => $query->whereNull('camp_id'))
            ->latest('opname_date')
            ->latest('id')
            ->limit(20)
            ->get();

        return array_merge(
            $this->pages->base('stock-opname', $scope),
            $this->pages->stockOpnameWorklist($scope),
            [
                'stockOpnames' => $stockOpnames,
                'items' => InventoryItem::with('itemCategory')->orderBy('name')->get(),
                'camps' => $scope['camps'],
            ],
        );
    }

    private function ensureCanAccess(StockOpname $stockOpname): void
    {
        $stockOpname->loadMissing('camp');
        if ($stockOpname->camp) {
            abort_unless(auth()->user()?->loadMissing('role')->canAccessCamp($stockOpname->camp_id, $stockOpname->camp->company_id), 403);
        }
    }

    private function ensureCanReview(StockOpname $stockOpname): void
    {
        abort_unless(auth()->user()?->loadMissing('role')->canReviewStockOpname(), 403);
        $this->ensureCanAccess($stockOpname);
    }

    private function ensureCanPost(StockOpname $stockOpname): void
    {
        abort_unless(auth()->user()?->loadMissing('role')->canPostStockOpname(), 403);
        $this->ensureCanAccess($stockOpname);
    }
}
