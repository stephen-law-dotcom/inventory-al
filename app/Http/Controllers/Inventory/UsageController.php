<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\CampStock;
use App\Models\InventoryItem;
use App\Models\Unit;
use App\Models\UnitOfMeasure;
use App\Models\UsageLog;
use App\Services\AuditLogger;
use App\Services\CampStockService;
use App\Services\InventoryPageContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UsageController extends Controller
{
    public function __construct(private readonly InventoryPageContextService $pages) {}

    public function index()
    {
        $this->ensurePermission('usage.view');

        return view('inventory.usage.index', $this->pageData());
    }

    public function create()
    {
        $this->ensurePermission('usage.manage');

        return view('inventory.usage.index', $this->pageData(['createUsage' => true]));
    }

    /** Record unit usage and reduce the related camp stock atomically. */
    public function storeUsageLog(Request $request)
    {
        $this->ensurePermission('usage.manage');

        $data = $request->validate([
            'used_at' => ['required', 'date'],
            'unit_id' => ['required', 'exists:units,id'],
            'inventory_item_id' => ['required', 'exists:inventory_items,id'],
            'usage_type' => ['required', 'in:sparepart,solar,oli,bbm'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'uom' => ['required', 'exists:unit_of_measures,code'],
            'location' => ['nullable', 'string', 'max:255'],
            'shift' => ['nullable', 'string', 'max:50'],
            'meter_reading' => ['nullable', 'numeric', 'min:0'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'mechanic_or_operator' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $unit = Unit::with('camp')->findOrFail($data['unit_id']);
        abort_unless($unit->camp && auth()->user()?->loadMissing('role')->canAccessCamp($unit->camp_id, $unit->camp->company_id), 403);

        if ($data['usage_type'] === 'sparepart' && floor((float) $data['quantity']) !== (float) $data['quantity']) {
            throw ValidationException::withMessages([
                'quantity' => 'Qty sparepart harus bilangan bulat.',
            ]);
        }

        $usageLog = DB::transaction(function () use ($data, $unit) {
            $usageLog = UsageLog::create($data);
            CampStockService::decrease(
                $unit->camp_id,
                (int) $data['inventory_item_id'],
                (float) $data['quantity'],
                'usage',
                $usageLog,
                'Pemakaian oleh unit '.$unit->code.(! empty($data['reference_number']) ? ' / Ref. '.$data['reference_number'] : ''),
            );

            return $usageLog;
        });

        AuditLogger::log('usage.created', 'Pemakaian barang/unit dicatat dan stok camp dikurangi.', $usageLog);

        return redirect()->route('usage')->with('success', 'Pemakaian dicatat dan stok camp dikurangi.');
    }

    private function ensurePermission(string $permission): void
    {
        abort_unless(auth()->user()?->loadMissing('role')->hasPermission($permission), 403);
    }

    private function pageData(array $extra = []): array
    {
        $scope = $this->pages->scope();
        $visibleCampIds = $scope['visibleCampIds'];
        $restrict = $scope['restrict'];
        $dateFrom = (string) request('date_from', '');
        $dateTo = (string) request('date_to', '');
        $filters = [
            'search' => mb_substr(trim((string) request('search', '')), 0, 100),
            'status' => '',
            'date_from' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) ? $dateFrom : null,
            'date_to' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) ? $dateTo : null,
        ];
        $baseQuery = UsageLog::with(['unit.camp.company', 'inventoryItem'])
            ->when($restrict && $visibleCampIds, fn ($query) => $query->whereHas('unit', fn ($inner) => $inner->whereIn('camp_id', $visibleCampIds)))
            ->when($restrict && ! $visibleCampIds, fn ($query) => $query->whereRaw('1 = 0'));
        $summary = [
            'today_count' => (clone $baseQuery)->whereDate('used_at', now()->toDateString())->count(),
            'month_count' => (clone $baseQuery)->whereBetween('used_at', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])->count(),
            'month_items' => (clone $baseQuery)->whereBetween('used_at', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])->distinct()->count('inventory_item_id'),
            'latest' => (clone $baseQuery)->latest('used_at')->latest('id')->first(),
        ];
        $query = $baseQuery
            ->when(request('tab') === 'history',
                fn ($query) => $query->whereDate('used_at', '<', now()->startOfMonth()->toDateString()),
                fn ($query) => $query->whereDate('used_at', '>=', now()->startOfMonth()->toDateString()))
            ->when($filters['search'], fn ($query, $search) => $query->where(function ($inner) use ($search) {
                $like = '%'.$search.'%';
                $inner->where('mechanic_or_operator', 'like', $like)
                    ->orWhereHas('inventoryItem', fn ($itemQuery) => $itemQuery->where('name', 'like', $like)->orWhere('sku', 'like', $like));
            }))
            ->when($filters['date_from'], fn ($query, $date) => $query->whereDate('used_at', '>=', $date))
            ->when($filters['date_to'], fn ($query, $date) => $query->whereDate('used_at', '<=', $date));
        $paginator = $query->latest('used_at')->latest('id')->paginate(20)->withQueryString();

        return array_merge($this->pages->base('usage', $scope), [
            'transactionFilters' => $filters,
            'transactionPaginator' => $paginator,
            'usageLogs' => $paginator->getCollection(),
            'usageSummary' => $summary,
            'camps' => $scope['camps'],
            'units' => Unit::with('camp')
                ->when($restrict && $visibleCampIds, fn ($query) => $query->whereIn('camp_id', $visibleCampIds))
                ->when($restrict && ! $visibleCampIds, fn ($query) => $query->whereRaw('1 = 0'))
                ->orderBy('name')
                ->get(),
            'items' => InventoryItem::with('itemCategory')->orderBy('name')->get(),
            'uoms' => UnitOfMeasure::orderBy('code')->get(),
            'campStocks' => CampStock::with(['camp', 'inventoryItem'])
                ->when($restrict && $visibleCampIds, fn ($query) => $query->whereIn('camp_id', $visibleCampIds))
                ->when($restrict && ! $visibleCampIds, fn ($query) => $query->whereRaw('1 = 0'))
                ->where('quantity', '>', 0)
                ->get(),
        ], $extra);
    }
}
