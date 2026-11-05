<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Camp;
use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\SiteDispatch;
use App\Models\User;
use App\Models\VendorQuotation;
use App\Services\AuditLogger;
use App\Services\DocumentNumberService;
use App\Services\ExchangeRateService;
use App\Services\PurchaseOrderPresentationService;
use App\Services\PurchaseOrderWorkflowService;
use App\Services\PurchaseRequestWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderWorkflowService $purchaseOrderWorkflow,
        private readonly PurchaseRequestWorkflowService $purchaseRequestWorkflow,
        private readonly PurchaseOrderPresentationService $presentation,
        private readonly ExchangeRateService $exchangeRateService,
    ) {}

    public function purchaseOrders()
    {
        $this->ensurePermission('purchase_orders.view');

        return view('inventory.purchase-orders.index', $this->indexData());
    }

    public function createPurchaseOrder()
    {
        $this->ensurePermission('purchase_orders.manage');
        $user = auth()->user()?->loadMissing('role');
        $companyIds = $user?->scopedCompanyIds() ?? [];
        $campIds = $user?->scopedCampIds() ?? [];
        if (! $companyIds && $campIds) {
            $companyIds = Camp::whereIn('id', $campIds)->pluck('company_id')->filter()->map(fn ($id) => (int) $id)->values()->all();
        }

        $companies = Company::query()
            ->when(! $user?->isAdmin() && $companyIds, fn ($query) => $query->whereIn('id', $companyIds))
            ->orderBy('name')
            ->get();
        $activeCompanyId = $this->selectedCompanyId($companies);
        $visibleCampIds = Camp::query()
            ->when(! $user?->isAdmin() && $campIds, fn ($query) => $query->whereIn('id', $campIds))
            ->when(! $user?->isAdmin() && ! $campIds && $companyIds, fn ($query) => $query->whereIn('company_id', $companyIds))
            ->when($activeCompanyId, fn ($query) => $query->where('company_id', $activeCompanyId))
            ->pluck('id')
            ->all();

        $purchaseRequests = PurchaseRequest::with([
            'camp',
            'items' => fn ($query) => $query
                ->where('status', 'vendor_selected')
                ->where('procurement_approval_status', 'approved')
                ->whereHas('selectedQuotations', fn ($quotationQuery) => $quotationQuery->whereDoesntHave('purchaseOrderItem')),
            'items.unit',
            'items.inventoryItem',
            'items.selectedQuotations' => fn ($query) => $query->whereDoesntHave('purchaseOrderItem'),
            'items.selectedQuotations.vendor',
        ])
            ->whereHas('items', fn ($query) => $query
                ->where('status', 'vendor_selected')
                ->where('procurement_approval_status', 'approved')
                ->whereHas('selectedQuotations', fn ($quotationQuery) => $quotationQuery->whereDoesntHave('purchaseOrderItem')))
            ->when((! $user?->isAdmin() || $activeCompanyId) && $visibleCampIds, fn ($query) => $query->whereIn('camp_id', $visibleCampIds))
            ->when((! $user?->isAdmin() || $activeCompanyId) && ! $visibleCampIds, fn ($query) => $query->whereRaw('1 = 0'))
            ->latest('requested_at')
            ->get();

        return view('inventory.purchase-orders.form', [
            'purchaseRequests' => $purchaseRequests,
            'canSeePrices' => auth()->user()->canSeePrices(),
            'canOverrideExchangeRate' => auth()->user()->hasPermission('exchange_rates.override'),
            'activeCompanyId' => $activeCompanyId,
            'currencies' => $this->exchangeRateService->currencies(),
            'exchangeRateWarningThreshold' => (float) config('currencies.warning_threshold_percent', 2),
        ]);
    }

    public function editPurchaseOrder(PurchaseOrder $purchaseOrder)
    {
        $this->ensurePermission('purchase_orders.manage');

        if ($purchaseOrder->status !== 'draft' || $purchaseOrder->proposed_by) {
            return redirect()->route('purchase-orders.review', $purchaseOrder)->with('error', 'PO yang sudah diajukan approval tidak bisa diedit.');
        }

        $purchaseOrder->load([
            'purchaseRequest.camp',
            'vendor',
            'items.requestItem.unit',
            'items.requestItem.inventoryItem',
            'items.requestItem.selectedQuotation.vendor',
            'items.inventoryItem',
            'items.vendorQuotation',
        ]);
        $this->ensureCanAccessCamp($purchaseOrder->purchaseRequest->camp);

        return view('inventory.purchase-orders.edit', [
            'purchaseOrder' => $purchaseOrder,
            'canSeePrices' => auth()->user()?->canSeePrices() ?? false,
            'draftQuantityLimits' => $purchaseOrder->items->mapWithKeys(fn ($item) => [
                $item->id => $this->purchaseOrderWorkflow->draftQuantityLimit($purchaseOrder, $item),
            ]),
        ]);
    }

    public function reviewPurchaseOrder(PurchaseOrder $purchaseOrder)
    {
        $this->ensurePermission('purchase_orders.view');

        $purchaseOrder->load([
            'purchaseRequest.camp',
            'vendor',
            'items.inventoryItem',
            'items.requestItem.unit',
            'createdBy',
            'proposedBy',
            'checkedBy',
            'ppicReviewedBy',
            'approvedBy',
            'rejectedBy',
            'submittedBy',
            'issuedBy',
            'directSiteDispatch.siteReceipts',
        ]);
        $this->ensureCanAccessCamp($purchaseOrder->purchaseRequest->camp);

        return view('inventory.purchase-orders.review', [
            'purchaseOrder' => $purchaseOrder,
            'timelineLogs' => $this->presentation->timeline($purchaseOrder),
            'canSeePrices' => auth()->user()->canSeePrices(),
            'canIssue' => auth()->user()->hasPermission('purchase_orders.issue'),
        ]);
    }

    public function printPurchaseOrder(PurchaseOrder $purchaseOrder)
    {
        $this->ensurePermission('purchase_orders.view');

        $purchaseOrder->load([
            'purchaseRequest.camp',
            'vendor',
            'items.inventoryItem',
            'items.requestItem.unit',
            'createdBy',
            'proposedBy',
            'checkedBy',
            'ppicReviewedBy',
            'approvedBy',
            'submittedBy',
            'issuedBy',
        ]);
        $this->ensureCanAccessCamp($purchaseOrder->purchaseRequest->camp);

        return view('inventory.purchase-orders.print', [
            'purchaseOrder' => $purchaseOrder,
            'canSeePrices' => auth()->user()->canSeePrices(),
        ]);
    }

    public function storePurchaseOrders(Request $request)
    {
        $this->ensurePermission('purchase_orders.manage');

        $data = $request->validate([
            'action' => ['nullable', 'in:draft,post'],
            'ordered_at' => ['required', 'date'],
            'expected_at' => ['nullable', 'date', 'after_or_equal:ordered_at'],
            'delivery_destination' => ['nullable', 'in:warehouse,direct_site'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'selected_items' => ['nullable', 'array'],
            'selected_items.*' => ['nullable', 'integer', 'distinct', 'exists:purchase_request_items,id'],
            'selected_quotations' => ['nullable', 'array'],
            'selected_quotations.*' => ['nullable', 'integer', 'distinct', 'exists:vendor_quotations,id'],
            'quotations' => ['nullable', 'array'],
            'quotations.*' => ['nullable', 'integer', 'exists:vendor_quotations,id'],
            'unit_prices' => ['nullable', 'array'],
            'unit_prices.*' => ['nullable', 'numeric', 'min:0'],
            'quantities' => ['nullable', 'array'],
            'quantities.*' => ['nullable', 'integer', 'min:1'],
            'exchange_rates' => ['nullable', 'array'],
            'exchange_rates.*' => ['nullable', 'numeric', 'gt:0'],
            'reference_exchange_rates' => ['nullable', 'array'],
            'reference_exchange_rates.*' => ['nullable', 'numeric', 'gt:0'],
            'exchange_rate_dates' => ['nullable', 'array'],
            'exchange_rate_dates.*' => ['nullable', 'date'],
            'exchange_rate_sources' => ['nullable', 'array'],
            'exchange_rate_sources.*' => ['nullable', 'in:frankfurter,BI,manual,system'],
            'exchange_rate_override_reasons' => ['nullable', 'array'],
            'exchange_rate_override_reasons.*' => ['nullable', 'string', 'max:1000'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'ppn_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        if (! auth()->user()?->canSeePrices()) {
            unset($data['unit_prices'], $data['discount_percent'], $data['ppn_percent']);
        }
        $isPosting = ($data['action'] ?? 'draft') === 'post';

        $purchaseOrders = DB::transaction(function () use ($data, $isPosting) {
            $selectedQuotationIds = collect($data['selected_quotations'] ?? [])->filter()->map(fn ($id) => (int) $id)->values();
            if ($selectedQuotationIds->isNotEmpty()) {
                $quotations = VendorQuotation::query()
                    ->with(['requestItem.purchaseRequest.camp', 'requestItem.inventoryItem', 'requestItem.purchaseOrderItems', 'vendor', 'purchaseOrderItem'])
                    ->whereIn('id', $selectedQuotationIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                if ($quotations->count() !== $selectedQuotationIds->count()) {
                    throw ValidationException::withMessages(['selected_quotations' => 'Sebagian alokasi vendor tidak ditemukan.']);
                }

                $selectedPoGroups = $quotations->map(fn (VendorQuotation $quotation) => implode(':', [
                    $quotation->requestItem->purchase_request_id,
                    $quotation->vendor_id,
                    $quotation->currency_code,
                ]))->unique();
                if ($selectedPoGroups->count() !== 1) {
                    throw ValidationException::withMessages([
                        'selected_quotations' => 'Satu proses hanya dapat membuat satu PO. Pilih alokasi dari OR, vendor, dan mata uang yang sama.',
                    ]);
                }

                $preparedItems = $selectedQuotationIds->map(function ($quotationId) use ($quotations, $data) {
                    $quotation = $quotations->get($quotationId);
                    $requestItem = $quotation->requestItem;

                    if ($requestItem->status !== 'vendor_selected' || $quotation->purchaseOrderItem) {
                        throw ValidationException::withMessages([
                            'selected_quotations' => 'Alokasi '.$requestItem->inventoryItem->name.' sudah masuk PO atau belum siap dibuatkan PO.',
                        ]);
                    }

                    if ($requestItem->procurement_approval_status !== 'approved') {
                        throw ValidationException::withMessages([
                            'selected_quotations' => 'Permintaan pengadaan barang '.$requestItem->inventoryItem->name.' belum disetujui direktur.',
                        ]);
                    }

                    if (! auth()->user()?->loadMissing('role')->canAccessCamp($requestItem->purchaseRequest->camp_id, $requestItem->purchaseRequest->camp?->company_id)) {
                        throw ValidationException::withMessages([
                            'selected_quotations' => $requestItem->inventoryItem->name.' berada di luar akses data role Anda.',
                        ]);
                    }

                    $allocatedQuantity = (int) $quotation->selected_quantity;
                    $quantity = (int) ($data['quantities'][$quotation->id] ?? $allocatedQuantity);
                    if (! $quotation->is_selected
                        || $allocatedQuantity <= 0
                        || $allocatedQuantity > $quotation->available_quantity
                        || $quantity <= 0
                        || $quantity > $allocatedQuantity
                        || (float) $quotation->unit_price <= 0) {
                        throw ValidationException::withMessages([
                            'selected_quotations' => 'Alokasi vendor final untuk '.$requestItem->inventoryItem->name.' belum valid atau stoknya tidak mencukupi.',
                        ]);
                    }

                    $unitPrice = (float) ($data['unit_prices'][$quotation->id] ?? $quotation->unit_price);
                    $itemTotal = $unitPrice * $quantity;

                    return compact('requestItem', 'quotation', 'itemTotal', 'quantity', 'unitPrice');
                });

            } else {
                if (empty($data['selected_items'])) {
                    throw ValidationException::withMessages(['selected_items' => 'Pilih minimal satu barang untuk dibuatkan PO.']);
                }

                $items = PurchaseRequestItem::query()
                    ->with(['purchaseRequest.camp', 'inventoryItem', 'purchaseOrderItems', 'quotations.vendor', 'selectedQuotation'])
                    ->whereIn('id', $data['selected_items'])
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                if ($items->count() !== count($data['selected_items'])) {
                    throw ValidationException::withMessages(['selected_items' => 'Sebagian barang tidak ditemukan.']);
                }

                $preparedItems = collect($data['selected_items'])->map(function ($itemId) use ($items, $data) {
                    $requestItem = $items->get((int) $itemId);

                    if ($requestItem->status !== 'vendor_selected' || $requestItem->purchaseOrderItems->isNotEmpty()) {
                        throw ValidationException::withMessages([
                            'selected_items' => $requestItem->inventoryItem->name.' sudah masuk PO atau belum siap dibuatkan PO.',
                        ]);
                    }

                    if ($requestItem->procurement_approval_status !== 'approved') {
                        throw ValidationException::withMessages([
                            'selected_items' => 'Permintaan pengadaan barang '.$requestItem->inventoryItem->name.' belum disetujui direktur.',
                        ]);
                    }

                    if (! auth()->user()?->loadMissing('role')->canAccessCamp($requestItem->purchaseRequest->camp_id, $requestItem->purchaseRequest->camp?->company_id)) {
                        throw ValidationException::withMessages([
                            'selected_items' => $requestItem->inventoryItem->name.' berada di luar akses data role Anda.',
                        ]);
                    }

                    $quotationId = (int) ($data['quotations'][$requestItem->id] ?? $requestItem->selectedQuotation?->id ?? 0);
                    $quotation = $requestItem->quotations->firstWhere('id', $quotationId);
                    $splitAllocationCount = $requestItem->selectedQuotations()->count();

                    if ($splitAllocationCount > 1) {
                        throw ValidationException::withMessages([
                            'selected_items' => $requestItem->inventoryItem->name.' memiliki beberapa alokasi vendor. Gunakan seluruh alokasi final saat membuat PO.',
                        ]);
                    }

                    if (! $quotation
                        || ! $quotation->is_selected
                        || (float) $quotation->unit_price <= 0
                        || $quotation->available_quantity < $requestItem->approvedQuantity()
                        || ((int) $quotation->selected_quantity > 0 && (int) $quotation->selected_quantity !== $requestItem->approvedQuantity())) {
                        throw ValidationException::withMessages([
                            'quotations' => 'Vendor final untuk '.$requestItem->inventoryItem->name.' belum valid atau stoknya tidak mencukupi.',
                        ]);
                    }

                    $allocatedQuantity = (int) ($quotation->selected_quantity ?: $requestItem->approvedQuantity());
                    $quantity = (int) ($data['quantities'][$quotation->id] ?? $allocatedQuantity);
                    if ($quantity <= 0 || $quantity > $allocatedQuantity) {
                        throw ValidationException::withMessages([
                            'quantities.'.$quotation->id => 'Qty PO '.$requestItem->inventoryItem->name.' minimal 1 dan tidak boleh melebihi alokasi vendor.',
                        ]);
                    }
                    $unitPrice = (float) ($data['unit_prices'][$quotation->id] ?? $quotation->unit_price);
                    $itemTotal = $unitPrice * $quantity;

                    return compact('requestItem', 'quotation', 'itemTotal', 'quantity', 'unitPrice');
                });
            }

            $preparedPoGroups = $preparedItems->map(fn ($item) => implode(':', [
                $item['requestItem']->purchase_request_id,
                $item['quotation']->vendor_id,
                $item['quotation']->currency_code,
            ]))->unique();
            if ($preparedPoGroups->count() !== 1) {
                throw ValidationException::withMessages([
                    $selectedQuotationIds->isNotEmpty() ? 'selected_quotations' : 'selected_items' => 'Satu proses hanya dapat membuat satu PO. Pilih barang dari OR, vendor, dan mata uang yang sama.',
                ]);
            }

            $groupedItems = $preparedItems
                ->groupBy(fn ($item) => $item['requestItem']->purchase_request_id.':'.$item['quotation']->vendor_id.':'.$item['quotation']->currency_code)
                ->values();

            return $groupedItems
                ->map(function ($items) use ($data, $isPosting) {
                    $firstItem = $items->first();
                    $purchaseRequest = $firstItem['requestItem']->purchaseRequest;
                    $vendorId = $firstItem['quotation']->vendor_id;
                    $currencySnapshot = $this->resolvePurchaseOrderCurrencySnapshot(
                        (string) $firstItem['quotation']->currency_code,
                        $data,
                        $firstItem['quotation']
                    );
                    $leadTimeDays = (int) $items->max(fn ($item) => $item['quotation']->lead_time_days);
                    $isRetryPurchaseOrder = $items->contains(fn ($item) => (bool) $item['requestItem']->source_credit_note_item_id);
                    $existingPoCount = PurchaseOrder::where('purchase_request_id', $purchaseRequest->id)
                        ->lockForUpdate()
                        ->count();
                    $vendorSequence = $existingPoCount + 1;
                    $hasMultipleVendors = $this->purchaseRequestHasMultiplePurchaseOrderGroups($purchaseRequest);
                    $shouldUseVendorSuffix = $hasMultipleVendors || (! $isRetryPurchaseOrder && $existingPoCount > 0);
                    $purchaseOrderNumber = DocumentNumberService::purchaseOrderNumber(
                        $purchaseRequest->number,
                        $vendorSequence,
                        $shouldUseVendorSuffix,
                        $isRetryPurchaseOrder
                    );

                    while (PurchaseOrder::where('number', $purchaseOrderNumber)->exists()) {
                        $vendorSequence++;
                        $purchaseOrderNumber = DocumentNumberService::purchaseOrderNumber(
                            $purchaseRequest->number,
                            $vendorSequence,
                            true,
                            $isRetryPurchaseOrder
                        );
                    }

                    $purchaseOrder = PurchaseOrder::create([
                        'number' => $purchaseOrderNumber,
                        'purchase_request_id' => $purchaseRequest->id,
                        'vendor_id' => $vendorId,
                        'ordered_at' => $data['ordered_at'],
                        'expected_at' => ($data['expected_at'] ?? null) ?: Carbon::parse($data['ordered_at'])->addDays($leadTimeDays)->toDateString(),
                        'delivery_destination' => $data['delivery_destination'] ?? 'warehouse',
                        'status' => 'draft',
                        'currency_code' => $currencySnapshot['currency_code'],
                        'reference_exchange_rate' => $currencySnapshot['reference_exchange_rate'],
                        'exchange_rate' => $currencySnapshot['exchange_rate'],
                        'exchange_rate_date' => $currencySnapshot['exchange_rate_date'],
                        'exchange_rate_source' => $currencySnapshot['exchange_rate_source'],
                        'exchange_rate_override_reason' => $currencySnapshot['exchange_rate_override_reason'],
                        'notes' => $data['notes'] ?? null,
                        'created_by' => auth()->id(),
                        'proposed_by' => $isPosting ? auth()->id() : null,
                        'proposed_at' => $isPosting ? now() : null,
                    ]);

                    $subtotal = 0;

                    foreach ($items as $item) {
                        $requestItem = $item['requestItem'];
                        $quotation = $item['quotation'];
                        if ($isPosting && (int) $item['quantity'] < (int) $quotation->selected_quantity) {
                            $this->purchaseOrderWorkflow->splitVendorQuotationAllocation(
                                $quotation,
                                (int) $item['quantity'],
                                'pembuatan PO '.$purchaseOrder->number
                            );
                        }
                        $purchaseOrder->items()->create([
                            'purchase_request_item_id' => $requestItem->id,
                            'vendor_quotation_id' => $quotation->id,
                            'inventory_item_id' => $requestItem->inventory_item_id,
                            'description' => $requestItem->inventoryItem->name,
                            'brand_name' => $quotation->brand_name,
                            'part_number' => $quotation->part_number,
                            'quantity_ordered' => $item['quantity'],
                            'uom' => $requestItem->uom ?? $requestItem->inventoryItem->uom,
                            'unit_price' => $item['unitPrice'],
                            'currency_code' => $currencySnapshot['currency_code'],
                            'exchange_rate' => $currencySnapshot['exchange_rate'],
                            'total' => $item['itemTotal'],
                        ]);

                        $subtotal += $item['itemTotal'];
                    }

                    $items->pluck('requestItem')->unique('id')->each(function (PurchaseRequestItem $requestItem) {
                        $hasPendingAllocation = $requestItem->selectedQuotations()
                            ->whereDoesntHave('purchaseOrderItem')
                            ->exists();
                        $requestItem->update(['status' => $hasPendingAllocation ? 'vendor_selected' : 'po_created']);
                    });

                    $purchaseOrder->update($this->calculatePurchaseOrderTotals(
                        $subtotal,
                        (float) ($data['discount_percent'] ?? 0),
                        (float) ($data['ppn_percent'] ?? 0)
                    ));
                    $this->syncPurchaseRequestAfterPurchaseOrderDraftChange($purchaseRequest->fresh('items'));

                    return $purchaseOrder;
                });
        });

        AuditLogger::log('purchase_order.created', $purchaseOrders->count().' purchase order dibuat dari pilihan barang dan vendor.', null, [
            'purchase_order_ids' => $purchaseOrders->pluck('id')->values()->all(),
            'selected_item_count' => count($data['selected_items'] ?? $data['selected_quotations'] ?? []),
        ]);

        if ($isPosting) {
            $purchaseOrders->each(fn (PurchaseOrder $purchaseOrder) => AuditLogger::log(
                'purchase_order.proposed',
                'Purchase order '.$purchaseOrder->number.' dibuat dan diposting untuk pemeriksaan Kepala Divisi Purchasing.',
                $purchaseOrder
            ));
        }

        return redirect()->route('purchase-orders', ! empty($data['company_id']) ? ['company_id' => $data['company_id']] : [])->with(
            'success',
            $isPosting
                ? $purchaseOrders->count().' PO berhasil dibuat dan diposting ke tahap approval.'
                : $purchaseOrders->count().' PO draft berhasil dibuat dan dipisahkan berdasarkan vendor.'
        );
    }

    public function updatePurchaseOrder(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->ensurePermission('purchase_orders.manage');
        $purchaseOrder->loadMissing('purchaseRequest.camp');
        $this->ensureCanAccessCamp($purchaseOrder->purchaseRequest->camp);

        $returnContext = in_array($request->query('from'), ['active', 'approval', 'history', 'approvals', 'dashboard', 'reports'], true)
            ? $request->query('from')
            : null;
        $reviewRouteParameters = array_filter([
            'purchaseOrder' => $purchaseOrder,
            'from' => $returnContext,
            'company_id' => $request->integer('company_id') ?: null,
        ]);

        if ($purchaseOrder->status !== 'draft' || $purchaseOrder->proposed_by) {
            return redirect()->route('purchase-orders.review', $reviewRouteParameters)->with('error', 'PO yang sudah diajukan approval tidak bisa diedit.');
        }

        $data = $request->validate([
            'ordered_at' => ['required', 'date'],
            'expected_at' => ['nullable', 'date', 'after_or_equal:ordered_at'],
            'delivery_destination' => ['nullable', 'in:warehouse,direct_site'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'unit_prices' => ['nullable', 'array'],
            'unit_prices.*' => ['required', 'numeric', 'min:0'],
            'quantities' => ['nullable', 'array'],
            'quantities.*' => ['required', 'integer', 'min:1'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'ppn_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'actual_exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'actual_exchange_rate_date' => ['nullable', 'date'],
        ]);

        if (! auth()->user()?->canSeePrices()) {
            unset($data['unit_prices'], $data['discount_percent'], $data['ppn_percent']);
        }
        if (! auth()->user()?->hasPermission('exchange_rates.override')) {
            unset($data['actual_exchange_rate'], $data['actual_exchange_rate_date']);
        }

        $quantityChanges = DB::transaction(function () use ($data, $purchaseOrder) {
            PurchaseOrder::whereKey($purchaseOrder->id)->lockForUpdate()->firstOrFail();
            $this->validatePurchaseOrderIntegrity($purchaseOrder);
            $quantityChanges = $this->purchaseOrderWorkflow->applyDraftQuantityAdjustments(
                $purchaseOrder,
                $data['quantities'] ?? []
            );
            $subtotal = 0.0;
            foreach ($purchaseOrder->items as $item) {
                $unitPrice = (float) ($data['unit_prices'][$item->id] ?? $item->unit_price);
                $total = $unitPrice * (int) $item->quantity_ordered;
                $item->update(['unit_price' => $unitPrice, 'total' => $total]);
                $subtotal += $total;
            }

            $purchaseOrder->update(array_merge([
                'ordered_at' => $data['ordered_at'],
                'expected_at' => $data['expected_at'] ?? null,
                'delivery_destination' => $data['delivery_destination'] ?? 'warehouse',
                'notes' => $data['notes'] ?? null,
                'actual_exchange_rate' => $data['actual_exchange_rate'] ?? $purchaseOrder->actual_exchange_rate,
                'actual_exchange_rate_date' => $data['actual_exchange_rate_date'] ?? $purchaseOrder->actual_exchange_rate_date,
                'checked_by' => null,
                'checked_at' => null,
                'ppic_reviewed_by' => null,
                'ppic_reviewed_at' => null,
                'approved_by' => null,
                'approved_at' => null,
                'approval_amount_idr' => null,
                'requires_main_director_approval' => false,
            ], $this->calculatePurchaseOrderTotals(
                $subtotal,
                (float) ($data['discount_percent'] ?? 0),
                (float) ($data['ppn_percent'] ?? 0)
            )));

            if ($quantityChanges !== []) {
                $this->syncPurchaseRequestAfterPurchaseOrderDraftChange($purchaseOrder->purchaseRequest()->firstOrFail());
            }
            $this->validatePurchaseOrderIntegrity($purchaseOrder->refresh());

            return $quantityChanges;
        });

        AuditLogger::log('purchase_order.updated', 'Draft purchase order diedit.', $purchaseOrder, [
            'quantity_changes' => $quantityChanges,
        ]);

        return redirect()->route('purchase-orders.review', $reviewRouteParameters)->with('success', 'Draft PO berhasil diperbarui.');
    }

    public function submitPurchaseOrder(PurchaseOrder $purchaseOrder)
    {
        $this->ensurePermission('purchase_orders.issue');
        $purchaseOrder->loadMissing('purchaseRequest.camp');
        $this->ensureCanAccessCamp($purchaseOrder->purchaseRequest->camp);

        if ($purchaseOrder->status !== 'draft') {
            return back()->with('error', 'Hanya PO draft yang sudah approved yang bisa di-launch.');
        }

        if (! $purchaseOrder->approved_by) {
            return back()->with('error', 'PO harus menyelesaikan persetujuan final sebelum di-launch.');
        }

        $this->validatePurchaseOrderIntegrity($purchaseOrder);

        $purchaseOrder->update([
            'status' => 'on_progress',
            'submitted_by' => auth()->id(),
            'submitted_at' => now(),
        ]);

        AuditLogger::log('purchase_order.on_progress', 'Purchase order di-launch dan masih menunggu pengajuan final.', $purchaseOrder);

        return back()->with('success', 'PO berhasil masuk on progress. PO belum diajukan final dan belum bisa diterima gudang.');
    }

    public function issuePurchaseOrder(PurchaseOrder $purchaseOrder)
    {
        $this->ensurePermission('purchase_orders.issue');
        $purchaseOrder->loadMissing('purchaseRequest.camp');
        $this->ensureCanAccessCamp($purchaseOrder->purchaseRequest->camp);

        if ($purchaseOrder->status !== 'on_progress') {
            return back()->with('error', 'PO harus on progress terlebih dahulu sebelum diajukan final.');
        }

        if (! $purchaseOrder->approved_by) {
            return back()->with('error', 'PO harus menyelesaikan persetujuan final sebelum diajukan final.');
        }

        $this->validatePurchaseOrderIntegrity($purchaseOrder);

        $directDispatch = DB::transaction(function () use ($purchaseOrder) {
            $purchaseOrder->update([
                'status' => 'submitted',
                'issued_by' => auth()->id(),
                'issued_at' => now(),
            ]);
            $purchaseRequest = $purchaseOrder->purchaseRequest()->firstOrFail();
            $this->syncPurchaseRequestAfterPurchaseOrderStatusChange($purchaseRequest);

            if ($purchaseOrder->delivery_destination !== 'direct_site') {
                return null;
            }

            $purchaseOrder->loadMissing(['items.requestItem', 'purchaseRequest.camp']);
            $dispatch = SiteDispatch::firstOrCreate(
                ['purchase_order_id' => $purchaseOrder->id],
                [
                    'number' => DocumentNumberService::nextSiteDispatch($purchaseOrder->purchaseRequest->camp),
                    'camp_id' => $purchaseOrder->purchaseRequest->camp_id,
                    'source_type' => 'direct_vendor',
                    'sent_at' => now()->toDateString(),
                    'driver_name' => $purchaseOrder->vendor?->name ?? 'Vendor',
                    'status' => 'sent',
                    'notes' => 'Pengiriman langsung vendor berdasarkan PO '.$purchaseOrder->number,
                    'dispatched_by' => auth()->id(),
                ]
            );

            if ($dispatch->items()->doesntExist()) {
                foreach ($purchaseOrder->items as $purchaseOrderItem) {
                    $dispatch->items()->create([
                        'inventory_item_id' => $purchaseOrderItem->inventory_item_id,
                        'purchase_order_item_id' => $purchaseOrderItem->id,
                        'purchase_request_item_id' => $purchaseOrderItem->purchase_request_item_id,
                        'unit_id' => $purchaseOrderItem->requestItem?->unit_id,
                        'quantity_sent' => $purchaseOrderItem->quantity_ordered,
                        'notes' => 'Dikirim langsung oleh vendor.',
                    ]);
                }
            }

            return $dispatch;
        });

        AuditLogger::log('purchase_order.submitted_final', 'Purchase order diajukan final untuk proses pembelian.', $purchaseOrder);

        return back()->with('success', $directDispatch
            ? 'Purchase order submitted. Pengiriman langsung ke site sudah dibuat dan menunggu penerimaan camp.'
            : 'Purchase order berhasil diajukan final dan siap diproses vendor.');
    }

    private function indexData(): array
    {
        $user = auth()->user()->loadMissing('role');
        [$companyIds, $campIds] = $this->scopedIds($user);
        $companies = Company::query()
            ->when(! $user->isAdmin() && $companyIds, fn ($query) => $query->whereIn('id', $companyIds))
            ->orderBy('name')
            ->get();
        $activeCompanyId = $this->selectedCompanyId($companies);
        $visibleCampIds = Camp::query()
            ->when(! $user->isAdmin() && $campIds, fn ($query) => $query->whereIn('id', $campIds))
            ->when(! $user->isAdmin() && ! $campIds && $companyIds, fn ($query) => $query->whereIn('company_id', $companyIds))
            ->when($activeCompanyId, fn ($query) => $query->where('company_id', $activeCompanyId))
            ->pluck('id')
            ->all();
        $restrict = ! $user->isAdmin() || $activeCompanyId;
        $tab = in_array(request('tab'), ['approval', 'history'], true) ? request('tab') : 'active';
        $filters = $this->transactionFilters();

        $purchaseOrdersQuery = PurchaseOrder::with([
            'purchaseRequest.camp',
            'vendor',
            'items.inventoryItem',
            'items.requestItem.unit',
            'createdBy',
            'proposedBy',
            'checkedBy',
            'ppicReviewedBy',
            'approvedBy',
        ])
            ->when($restrict && $visibleCampIds, fn ($query) => $query->whereHas('purchaseRequest', fn ($requestQuery) => $requestQuery->whereIn('camp_id', $visibleCampIds)))
            ->when($restrict && ! $visibleCampIds, fn ($query) => $query->whereRaw('1 = 0'));

        match ($tab) {
            'history' => $purchaseOrdersQuery->whereIn('status', ['received', 'rejected']),
            'approval' => $purchaseOrdersQuery
                ->where('status', 'draft')
                ->whereNotNull('proposed_by')
                ->where(fn ($query) => $query->whereNull('checked_by')->orWhereNull('approved_by')),
            default => $purchaseOrdersQuery->whereNotIn('status', ['received', 'rejected']),
        };
        $this->applyFilters($purchaseOrdersQuery, $filters);
        $paginator = $purchaseOrdersQuery->latest()->paginate(20)->withQueryString();

        $purchaseRequests = PurchaseRequest::with([
            'camp',
            'items.inventoryItem',
            'items.selectedQuotations.vendor',
            'items.purchaseOrderItems',
        ])
            ->when($restrict && $visibleCampIds, fn ($query) => $query->whereIn('camp_id', $visibleCampIds))
            ->when($restrict && ! $visibleCampIds, fn ($query) => $query->whereRaw('1 = 0'))
            ->whereHas('items', fn ($query) => $query
                ->where('status', 'vendor_selected')
                ->where('procurement_approval_status', 'approved')
                ->whereHas('selectedQuotations', fn ($quotationQuery) => $quotationQuery->whereDoesntHave('purchaseOrderItem')))
            ->get();

        return [
            'activeMenu' => 'purchase-orders',
            'activeMaster' => null,
            'companies' => $companies,
            'activeCompanyId' => $activeCompanyId,
            'companyTabCounts' => $this->companyCounts($companies, $user),
            'transactionFilters' => $filters,
            'transactionPaginator' => $paginator,
            'purchaseOrders' => $paginator->getCollection(),
            'purchaseRequests' => $purchaseRequests,
            'notifications' => $user->systemNotifications()->latest()->limit(8)->get(),
            'unreadNotificationsCount' => $user->systemNotifications()->whereNull('read_at')->count(),
            'canSeePrices' => $user->canSeePrices(),
        ];
    }

    private function resolvePurchaseOrderCurrencySnapshot(string $currencyCode, array $data, VendorQuotation $quotation): array
    {
        $currencyCode = strtoupper($currencyCode ?: 'IDR');
        if ($currencyCode === 'IDR') {
            return [
                'currency_code' => 'IDR',
                'reference_exchange_rate' => 1,
                'exchange_rate' => 1,
                'exchange_rate_date' => $data['exchange_rate_dates']['IDR'] ?? now()->toDateString(),
                'exchange_rate_source' => 'system',
                'exchange_rate_override_reason' => null,
            ];
        }

        $referenceRate = (float) ($data['reference_exchange_rates'][$currencyCode] ?? $quotation->reference_exchange_rate ?? 0);
        $appliedRate = (float) ($data['exchange_rates'][$currencyCode] ?? $quotation->exchange_rate ?? 0);
        if ($appliedRate <= 0) {
            throw ValidationException::withMessages(['exchange_rates' => 'Kurs '.$currencyCode.' wajib diisi sebelum PO dibuat.']);
        }

        $submittedSource = (string) ($data['exchange_rate_sources'][$currencyCode] ?? $quotation->exchange_rate_source ?? 'frankfurter');
        $isManual = $submittedSource === 'manual' || $referenceRate <= 0 || $this->exchangeRatesDiffer($appliedRate, $referenceRate);
        $reason = trim((string) ($data['exchange_rate_override_reasons'][$currencyCode] ?? $quotation->exchange_rate_override_reason ?? ''));
        $usesExistingManualSnapshot = $quotation->exchange_rate_source === 'manual'
            && $submittedSource === 'manual'
            && ! $this->exchangeRatesDiffer($appliedRate, (float) $quotation->exchange_rate)
            && ! $this->exchangeRatesDiffer($referenceRate, (float) $quotation->reference_exchange_rate);
        if ($isManual && ! $usesExistingManualSnapshot && ! auth()->user()?->hasPermission('exchange_rates.override')) {
            throw ValidationException::withMessages(['exchange_rates' => 'Anda tidak memiliki hak untuk mengubah kurs PO secara manual.']);
        }
        if ($isManual && $reason === '') {
            throw ValidationException::withMessages(['exchange_rate_override_reasons' => 'Alasan perubahan kurs '.$currencyCode.' wajib diisi.']);
        }

        return [
            'currency_code' => $currencyCode,
            'reference_exchange_rate' => $referenceRate > 0 ? $referenceRate : $appliedRate,
            'exchange_rate' => $appliedRate,
            'exchange_rate_date' => $data['exchange_rate_dates'][$currencyCode]
                ?? optional($quotation->exchange_rate_date)->toDateString()
                ?? now()->toDateString(),
            'exchange_rate_source' => $isManual ? 'manual' : ($submittedSource === 'BI' ? 'BI' : 'frankfurter'),
            'exchange_rate_override_reason' => $isManual ? $reason : null,
        ];
    }

    private function purchaseRequestHasMultiplePurchaseOrderGroups(PurchaseRequest $purchaseRequest): bool
    {
        return VendorQuotation::query()
            ->where('is_selected', true)
            ->where('selected_quantity', '>', 0)
            ->whereHas('requestItem', fn ($query) => $query->where('purchase_request_id', $purchaseRequest->id))
            ->get(['vendor_id', 'currency_code'])
            ->unique(fn (VendorQuotation $quotation) => $quotation->vendor_id.':'.$quotation->currency_code)
            ->count() > 1;
    }

    private function calculatePurchaseOrderTotals(float $subtotal, float $discountPercent, float $ppnPercent): array
    {
        return $this->purchaseOrderWorkflow->calculateTotals($subtotal, $discountPercent, $ppnPercent);
    }

    private function validatePurchaseOrderIntegrity(PurchaseOrder $purchaseOrder): void
    {
        $this->purchaseOrderWorkflow->validateIntegrity($purchaseOrder);
    }

    private function syncPurchaseRequestAfterPurchaseOrderDraftChange(PurchaseRequest $purchaseRequest): void
    {
        $this->purchaseRequestWorkflow->syncAfterPurchaseOrderDraftChange($purchaseRequest);
    }

    private function syncPurchaseRequestAfterPurchaseOrderStatusChange(PurchaseRequest $purchaseRequest): void
    {
        $this->purchaseRequestWorkflow->syncAfterPurchaseOrderStatusChange($purchaseRequest);
    }

    private function exchangeRatesDiffer(float $first, float $second): bool
    {
        return number_format($first, 4, '.', '') !== number_format($second, 4, '.', '');
    }

    private function transactionFilters(): array
    {
        $dateFrom = (string) request('date_from', '');
        $dateTo = (string) request('date_to', '');

        return [
            'search' => mb_substr(trim((string) request('search', '')), 0, 100),
            'status' => mb_substr(trim((string) request('status', '')), 0, 50),
            'date_from' => preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $dateFrom) ? $dateFrom : null,
            'date_to' => preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $dateTo) ? $dateTo : null,
        ];
    }

    private function applyFilters($query, array $filters): void
    {
        $query
            ->when($filters['search'], function ($query, $search) {
                $like = '%'.$search.'%';
                $query->where(function ($searchQuery) use ($like) {
                    $searchQuery
                        ->where('number', 'like', $like)
                        ->orWhereHas('vendor', fn ($vendorQuery) => $vendorQuery->where('name', 'like', $like))
                        ->orWhereHas('purchaseRequest', fn ($requestQuery) => $requestQuery->where('number', 'like', $like))
                        ->orWhereHas('items.inventoryItem', fn ($itemQuery) => $itemQuery->where('name', 'like', $like)->orWhere('sku', 'like', $like));
                });
            })
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->when($filters['date_from'], fn ($query, $date) => $query->whereDate('ordered_at', '>=', $date))
            ->when($filters['date_to'], fn ($query, $date) => $query->whereDate('ordered_at', '<=', $date));
    }

    private function companyCounts($companies, User $user): array
    {
        $counts = [];
        foreach ($companies as $company) {
            $campIds = Camp::where('company_id', $company->id)
                ->when(! $user->isAdmin() && $user->scopedCampIds(), fn ($query) => $query->whereIn('id', $user->scopedCampIds()))
                ->pluck('id');
            $counts[$company->id] = PurchaseOrder::query()
                ->whereHas('purchaseRequest', fn ($query) => $query->whereIn('camp_id', $campIds))
                ->whereNotIn('status', ['received', 'closed', 'cancelled'])
                ->count();
        }
        $counts['all'] = collect($counts)->sum();

        return $counts;
    }

    private function scopedIds(User $user): array
    {
        $companyIds = $user->scopedCompanyIds();
        $campIds = $user->scopedCampIds();
        if (! $companyIds && $campIds) {
            $companyIds = Camp::whereIn('id', $campIds)->pluck('company_id')->filter()->map(fn ($id) => (int) $id)->values()->all();
        }

        return [$companyIds, $campIds];
    }

    private function selectedCompanyId($companies): ?int
    {
        $requestedCompanyId = (int) request('company_id', 0);
        if (! $requestedCompanyId) {
            return null;
        }

        abort_unless($companies->contains('id', $requestedCompanyId), 403);

        return $requestedCompanyId;
    }

    private function ensurePermission(string $permission): void
    {
        abort_unless(auth()->user()?->loadMissing('role')->hasPermission($permission), 403);
    }

    private function ensureCanAccessCamp(?Camp $camp): void
    {
        abort_unless($camp && auth()->user()?->loadMissing('role')->canAccessCamp($camp->id, $camp->company_id), 403);
    }
}
