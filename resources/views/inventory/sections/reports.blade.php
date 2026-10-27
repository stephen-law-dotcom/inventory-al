            @if ($activeMenu === 'reports')
                @php
                    $reportType = $activeReport;
                    $fromDate = request('from_date');
                    $toDate = request('to_date');
                    $company = request('company');
                    $campId = request('camp_id');
                    $unitId = request('unit_id');
                    $itemId = request('item_id');
                    $vendorId = request('vendor_id');
                    $status = request('status');
                    $companies = $camps->pluck('company_name')->filter()->unique()->sort()->values();
                    $statusOptionsByReport = [
                        'procurement_flow' => collect(['waiting_po', 'po_process', 'warehouse_receipt', 'in_transit', 'site_receipt', 'issue']),
                        'requests' => $purchaseRequests->pluck('status')->merge($purchaseRequests->flatMap->items->pluck('status'))->filter()->unique()->sort()->values(),
                        'purchase_orders' => $purchaseOrders->pluck('status')->filter()->unique()->sort()->values(),
                        'receipts' => collect(['good', 'quantity_short', 'damaged', 'wrong_item', 'spec_mismatch', 'other']),
                        'credit_notes' => $creditNotes->pluck('status')->filter()->unique()->sort()->values(),
                        'dispatches' => $dispatches->pluck('status')->filter()->unique()->sort()->values(),
                        'opnames' => $stockOpnames->pluck('status')->filter()->unique()->sort()->values(),
                        'stock_movements' => $stockMovements->pluck('type')->filter()->unique()->sort()->values(),
                        'activity_logs' => $activityLogs->pluck('event')->filter()->unique()->sort()->values(),
                    ];
                    $statusOptions = $statusOptionsByReport[$reportType] ?? collect();
                    $reportFilters = [
                        'procurement_flow' => ['date', 'company', 'camp', 'unit', 'item', 'vendor', 'status'],
                        'requests' => ['date', 'company', 'camp', 'unit', 'item', 'status'],
                        'vendors' => ['date', 'company', 'camp', 'item', 'vendor'],
                        'purchase_orders' => ['date', 'company', 'camp', 'item', 'vendor', 'status'],
                        'receipts' => ['date', 'company', 'camp', 'item', 'vendor', 'status'],
                        'credit_notes' => ['date', 'company', 'camp', 'item', 'vendor', 'status'],
                        'stock' => ['item'],
                        'usage' => ['date', 'company', 'camp', 'unit', 'item'],
                        'dispatches' => ['date', 'company', 'camp', 'item', 'status'],
                        'opnames' => ['date', 'item', 'status'],
                        'stock_movements' => ['date', 'item', 'status'],
                        'inventory_planning' => ['item'],
                        'activity_logs' => ['date', 'status'],
                    ];
                    $filterFields = $reportFilters[$reportType] ?? [];
                    $activeFilterLabels = collect([
                        $fromDate || $toDate ? 'Tanggal' : null,
                        $company ? 'Perusahaan' : null,
                        $campId ? 'Site' : null,
                        $unitId ? 'Unit' : null,
                        $itemId ? 'Barang' : null,
                        $vendorId ? 'Vendor' : null,
                        $status ? 'Status' : null,
                    ])->filter();

                    $dateInRange = function ($date) use ($fromDate, $toDate) {
                        if (! $date) {
                            return true;
                        }

                        $value = $date->toDateString();

                        return (! $fromDate || $value >= $fromDate) && (! $toDate || $value <= $toDate);
                    };

                    $campMatches = fn ($camp) => (! $company || $camp?->company_name === $company) && (! $campId || (int) $camp?->id === (int) $campId);

                    $filteredRequests = $purchaseRequests->filter(function ($pr) use ($dateInRange, $campMatches, $unitId, $itemId, $vendorId, $status, $reportType) {
                        if (! $dateInRange($pr->requested_at) || ! $campMatches($pr->camp)) {
                            return false;
                        }

                        if ($status && $reportType !== 'procurement_flow' && $pr->status !== $status && ! $pr->items->contains('status', $status)) {
                            return false;
                        }

                        if ($unitId && ! $pr->items->contains(fn ($requestItem) => (int) $requestItem->unit_id === (int) $unitId)) {
                            return false;
                        }

                        if ($itemId && ! $pr->items->contains(fn ($requestItem) => (int) $requestItem->inventory_item_id === (int) $itemId)) {
                            return false;
                        }

                        if ($vendorId && ! $pr->items->contains(fn ($requestItem) => $requestItem->quotations->contains(fn ($quotation) => (int) $quotation->vendor_id === (int) $vendorId))) {
                            return false;
                        }

                        return true;
                    });

                    $filteredRequestItems = $filteredRequests->flatMap->items->filter(function ($requestItem) use ($unitId, $itemId, $vendorId, $status, $reportType) {
                        if ($unitId && (int) $requestItem->unit_id !== (int) $unitId) {
                            return false;
                        }

                        if ($itemId && (int) $requestItem->inventory_item_id !== (int) $itemId) {
                            return false;
                        }

                        if ($status && $reportType !== 'procurement_flow' && $requestItem->status !== $status && $requestItem->purchaseRequest->status !== $status) {
                            return false;
                        }

                        if ($vendorId && ! $requestItem->quotations->contains(fn ($quotation) => (int) $quotation->vendor_id === (int) $vendorId)) {
                            return false;
                        }

                        return true;
                    });

                    $filteredDispatches = $dispatches->filter(fn ($dispatch) => $dispatch->status !== 'draft' && $dateInRange($dispatch->sent_at) && $campMatches($dispatch->camp) && (! $itemId || $dispatch->items->contains(fn ($dispatchItem) => (int) $dispatchItem->inventory_item_id === (int) $itemId)) && (! $status || $dispatch->status === $status));
                    $filteredPurchaseOrders = $purchaseOrders->filter(fn ($po) => (in_array($reportType, ['procurement_flow', 'receipts'], true) || $dateInRange($po->ordered_at)) && $campMatches($po->purchaseRequest?->camp) && (! $vendorId || (int) $po->vendor_id === (int) $vendorId) && (! $itemId || $po->items->contains(fn ($poItem) => (int) $poItem->inventory_item_id === (int) $itemId)) && (! $status || in_array($reportType, ['procurement_flow', 'receipts'], true) || $po->status === $status));
                    $filteredCreditNotes = $creditNotes->filter(function ($creditNote) use ($dateInRange, $campMatches, $itemId, $vendorId, $status) {
                        $camp = $creditNote->purchaseOrder?->purchaseRequest?->camp ?? $creditNote->siteReceipt?->siteDispatch?->camp ?? $creditNote->camp;

                        return $dateInRange($creditNote->credit_note_date)
                            && $campMatches($camp)
                            && (! $vendorId || (int) $creditNote->vendor_id === (int) $vendorId)
                            && (! $itemId || $creditNote->items->contains(fn ($creditNoteItem) => (int) $creditNoteItem->inventory_item_id === (int) $itemId))
                            && (! $status || $creditNote->status === $status);
                    });
                    $filteredUsageLogs = $usageLogs->filter(fn ($log) => $dateInRange($log->used_at) && $campMatches($log->unit?->camp) && (! $unitId || (int) $log->unit_id === (int) $unitId) && (! $itemId || (int) $log->inventory_item_id === (int) $itemId));
                    $filteredOpnames = $stockOpnames->filter(fn ($opname) => $dateInRange($opname->opname_date) && (! $itemId || $opname->items->contains(fn ($opnameItem) => (int) $opnameItem->inventory_item_id === (int) $itemId)) && (! $status || $opname->status === $status));
                    $filteredItems = $items->filter(fn ($item) => ! $itemId || (int) $item->id === (int) $itemId);
                    $criticalItems = $filteredItems->filter(fn ($item) => $item->shouldReorder());
                    $selectedVendorAllocations = $filteredRequestItems->flatMap(fn ($item) => $item->selectedQuotations
                        ->filter(fn ($quotation) => ! $vendorId || (int) $quotation->vendor_id === (int) $vendorId)
                        ->map(fn ($quotation) => ['request_item' => $item, 'quotation' => $quotation]));
                    $selectedRequestItems = $selectedVendorAllocations->pluck('request_item')->unique('id')->values();
                    $opnameDifferences = $filteredOpnames->flatMap->items->filter(fn ($item) => $item->difference !== 0 && (! $itemId || (int) $item->inventory_item_id === (int) $itemId));
                    $activeRequests = $filteredRequests->whereNotIn('status', ['draft', 'rejected']);
                    $selectedVendorTotal = $selectedVendorAllocations->sum(fn ($allocation) => $allocation['quotation']->unit_price * $allocation['quotation']->selected_quantity);
                    $filteredStockMovements = $stockMovements->filter(fn ($movement) => $dateInRange($movement->created_at) && (! $itemId || (int) $movement->inventory_item_id === (int) $itemId) && (! $status || $movement->type === $status));
                    $filteredActivityLogs = $activityLogs->filter(fn ($log) => $dateInRange($log->created_at) && (! $status || $log->event === $status));

                    $flowStatusLabels = [
                        'waiting_po' => 'Menunggu PO',
                        'po_process' => 'Proses PO',
                        'warehouse_receipt' => 'Diterima Gudang',
                        'in_transit' => 'Dalam Pengiriman',
                        'site_receipt' => 'Diterima Site',
                        'issue' => 'Ada Masalah',
                    ];
                    $receiptConditionLabels = [
                        'good' => 'Baik',
                        'quantity_short' => 'Jumlah Kurang',
                        'damaged' => 'Rusak',
                        'wrong_item' => 'Barang Tidak Sesuai',
                        'spec_mismatch' => 'Spesifikasi Tidak Sesuai',
                        'other' => 'Lainnya',
                    ];
                    $reportStatusLabels = [
                        'procurement_flow' => $flowStatusLabels,
                        'receipts' => $receiptConditionLabels,
                    ];

                    $procurementRows = $filteredRequestItems->flatMap(function ($requestItem) use ($vendorId, $status) {
                        $purchaseOrderItems = $requestItem->purchaseOrderItems;
                        if ($purchaseOrderItems->isEmpty()) {
                            $purchaseOrderItems = collect([null]);
                        }

                        return $purchaseOrderItems->map(function ($purchaseOrderItem) use ($requestItem) {
                            $purchaseOrder = $purchaseOrderItem?->purchaseOrder;
                            $dispatchItems = $purchaseOrderItem?->dispatchItems ?? collect();
                            $receiptItems = $dispatchItems->flatMap->receiptItems;
                            $creditNoteItems = $purchaseOrderItem?->creditNoteItems ?? collect();
                            $activeIssue = $creditNoteItems->contains(fn ($creditNoteItem) => ! in_array($creditNoteItem->creditNote?->status, ['credited', 'closed'], true));
                            $warehouseReceived = (float) ($purchaseOrderItem?->goodsReceiptItems?->sum('quantity_received') ?? 0);
                            $sent = (float) $dispatchItems->sum('quantity_sent');
                            $siteReceived = (float) $receiptItems->sum('quantity_received');

                            $stage = match (true) {
                                $activeIssue => 'issue',
                                ! $purchaseOrderItem => 'waiting_po',
                                $sent > 0 && $siteReceived >= $sent => 'site_receipt',
                                $sent > $siteReceived => 'in_transit',
                                $purchaseOrder?->delivery_destination !== 'direct_site' && $warehouseReceived > 0 => 'warehouse_receipt',
                                default => 'po_process',
                            };

                            return [
                                'request_item' => $requestItem,
                                'purchase_order_item' => $purchaseOrderItem,
                                'purchase_order' => $purchaseOrder,
                                'ordered' => (float) ($purchaseOrderItem?->quantity_ordered ?? $requestItem->quantity),
                                'warehouse_received' => $warehouseReceived,
                                'sent' => $sent,
                                'site_received' => $siteReceived,
                                'problem' => (float) $creditNoteItems->sum('quantity_problem'),
                                'stage' => $stage,
                            ];
                        });
                    })->filter(fn ($row) => (! $vendorId || (int) ($row['purchase_order']?->vendor_id) === (int) $vendorId) && (! $status || $row['stage'] === $status))->values();

                    $warehouseReceiptRows = $filteredPurchaseOrders->flatMap(function ($purchaseOrder) use ($dateInRange, $itemId, $status) {
                        return $purchaseOrder->goodsReceipts->filter(fn ($receipt) => $dateInRange($receipt->received_at))->flatMap(function ($receipt) use ($purchaseOrder, $itemId, $status) {
                            return $receipt->items->filter(fn ($receiptItem) => (! $itemId || (int) $receiptItem->purchaseOrderItem?->inventory_item_id === (int) $itemId) && (! $status || $receiptItem->condition === $status))->map(fn ($receiptItem) => [
                                'source' => 'Gudang Kantor',
                                'document' => $receipt->number,
                                'invoice' => $receipt->invoice_number,
                                'date' => $receipt->received_at,
                                'purchase_order' => $purchaseOrder,
                                'purchase_order_item' => $receiptItem->purchaseOrderItem,
                                'quantity_received' => (float) $receiptItem->quantity_received,
                                'remaining' => max(0, (float) ($receiptItem->purchaseOrderItem?->quantity_ordered ?? 0) - (float) ($receiptItem->purchaseOrderItem?->goodsReceiptItems?->sum('quantity_received') ?? 0)),
                                'condition' => $receiptItem->condition,
                                'problem' => (float) $receiptItem->creditNoteItems->sum('quantity_problem'),
                                'receiver' => $receipt->received_by,
                            ]);
                        });
                    });
                    $directReceiptRows = $filteredPurchaseOrders->flatMap(function ($purchaseOrder) use ($dateInRange, $itemId, $status) {
                        $dispatch = $purchaseOrder->directSiteDispatch;
                        if (! $dispatch) {
                            return collect();
                        }

                        return $dispatch->siteReceipts->filter(fn ($receipt) => $dateInRange($receipt->received_at))->flatMap(function ($receipt) use ($purchaseOrder, $itemId, $status) {
                            return $receipt->items->filter(fn ($receiptItem) => (! $itemId || (int) $receiptItem->dispatchItem?->inventory_item_id === (int) $itemId) && (! $status || $receiptItem->condition === $status))->map(fn ($receiptItem) => [
                                'source' => 'Langsung ke Site',
                                'document' => $receipt->number,
                                'invoice' => $receipt->invoice_number,
                                'date' => $receipt->received_at,
                                'purchase_order' => $purchaseOrder,
                                'purchase_order_item' => $receiptItem->dispatchItem?->purchaseOrderItem,
                                'quantity_received' => (float) $receiptItem->quantity_received,
                                'remaining' => max(0, (float) ($receiptItem->dispatchItem?->purchaseOrderItem?->quantity_ordered ?? 0) - (float) ($receiptItem->dispatchItem?->purchaseOrderItem?->dispatchItems?->flatMap->receiptItems->sum('quantity_received') ?? 0)),
                                'condition' => $receiptItem->condition,
                                'problem' => (float) $receiptItem->creditNoteItems->sum('quantity_problem'),
                                'receiver' => $receipt->received_by,
                            ]);
                        });
                    });
                    $receiptRows = $warehouseReceiptRows->concat($directReceiptRows)->sortByDesc(fn ($row) => $row['date']?->format('Y-m-d'))->values();
                    $activeReportUrl = $reportNav[$reportType][1] ?? route('reports');
                    $reportKpis = match ($reportType) {
                        'procurement_flow' => [
                            ['label' => 'Detail pengadaan', 'value' => $procurementRows->count(), 'note' => $procurementRows->pluck('request_item.purchase_request_id')->unique()->count().' order request'],
                            ['label' => 'Belum sampai site', 'value' => $procurementRows->whereNotIn('stage', ['site_receipt'])->count(), 'note' => 'Masih dalam proses'],
                            ['label' => 'Ada masalah', 'value' => $procurementRows->where('stage', 'issue')->count(), 'note' => 'Perlu tindak lanjut'],
                        ],
                        'requests' => [
                            ['label' => 'Total OR', 'value' => $filteredRequests->count(), 'note' => $filteredRequestItems->count().' detail barang'],
                            ['label' => 'Menunggu approval', 'value' => $filteredRequestItems->whereIn('status', ['submitted', 'logistic_checked', 'waiting_manager_camp'])->count(), 'note' => 'Belum lengkap ttd camp'],
                            ['label' => 'Siap vendor', 'value' => $filteredRequestItems->where('status', 'ready_for_quotation')->count(), 'note' => 'Perlu harga vendor'],
                        ],
                        'vendors' => [
                            ['label' => 'Vendor terpilih', 'value' => $selectedVendorAllocations->pluck('quotation.vendor_id')->filter()->unique()->count(), 'note' => $selectedRequestItems->count().' item final'],
                            ['label' => 'Nilai final', 'value' => $canSeePrices ? 'Rp'.number_format($selectedVendorTotal, 0, ',', '.') : 'Disembunyikan', 'note' => 'Berdasarkan vendor dipilih'],
                            ['label' => 'Belum PO', 'value' => $selectedRequestItems->filter(fn ($item) => ! $item->purchaseOrderItem)->count(), 'note' => 'Masih bisa dibuat PO'],
                        ],
                        'purchase_orders' => [
                            ['label' => 'Total PO', 'value' => $filteredPurchaseOrders->count(), 'note' => $filteredPurchaseOrders->where('status', 'on_progress')->count().' on progress'],
                            ['label' => 'Submitted', 'value' => $filteredPurchaseOrders->whereIn('status', ['submitted', 'issued'])->count(), 'note' => 'Siap penerimaan'],
                            ['label' => 'Nilai PO', 'value' => $canSeePrices ? 'Rp'.number_format($filteredPurchaseOrders->sum('grand_total'), 0, ',', '.') : 'Disembunyikan', 'note' => 'Total sesuai filter'],
                        ],
                        'receipts' => [
                            ['label' => 'Dokumen penerimaan', 'value' => $receiptRows->pluck('document')->unique()->count(), 'note' => 'Sesuai filter'],
                            ['label' => 'Detail barang', 'value' => $receiptRows->count(), 'note' => $receiptRows->where('condition', 'good')->count().' kondisi baik'],
                            ['label' => 'Bermasalah', 'value' => $receiptRows->where('problem', '>', 0)->count(), 'note' => 'Terhubung ke credit note'],
                        ],
                        'credit_notes' => [
                            ['label' => 'Total CN', 'value' => $filteredCreditNotes->count(), 'note' => $filteredCreditNotes->where('status', 'draft')->count().' masih request'],
                            ['label' => 'Qty problem', 'value' => number_format($filteredCreditNotes->flatMap->items->sum('quantity_problem'), 0, ',', '.'), 'note' => 'Barang bermasalah'],
                            ['label' => 'Nilai credit', 'value' => $canSeePrices ? 'Rp'.number_format($filteredCreditNotes->flatMap->items->sum('total'), 0, ',', '.') : 'Disembunyikan', 'note' => 'Estimasi refund'],
                        ],
                        'usage' => [
                            ['label' => 'Transaksi pakai', 'value' => $filteredUsageLogs->count(), 'note' => $filteredUsageLogs->pluck('unit_id')->unique()->count().' unit'],
                            ['label' => 'Qty keluar', 'value' => number_format($filteredUsageLogs->sum('quantity'), 0, ',', '.'), 'note' => 'Total pemakaian'],
                            ['label' => 'Barang dipakai', 'value' => $filteredUsageLogs->pluck('inventory_item_id')->unique()->count(), 'note' => 'Jenis barang'],
                        ],
                        'stock' => [
                            ['label' => 'Total item', 'value' => $filteredItems->count(), 'note' => 'Sesuai akses data'],
                            ['label' => 'Stok kritis', 'value' => $criticalItems->count(), 'note' => 'Perlu order'],
                            ['label' => 'Aman', 'value' => $filteredItems->count() - $criticalItems->count(), 'note' => 'Belum perlu order'],
                        ],
                        default => [
                            ['label' => 'Total stok item', 'value' => $filteredItems->count(), 'note' => $criticalItems->count().' item kritis'],
                            ['label' => 'Order Request aktif', 'value' => $activeRequests->count(), 'note' => $filteredRequests->where('status', 'ready_for_po')->count().' siap PO'],
                            ['label' => 'Nilai vendor terpilih', 'value' => $canSeePrices ? 'Rp'.number_format($selectedVendorTotal, 0, ',', '.') : 'Disembunyikan', 'note' => $selectedRequestItems->count().' item punya rekomendasi'],
                        ],
                    };
                @endphp

                <section class="filter-panel">
                    <div class="panel-head">
                        <div class="report-filter-heading">
                            <span class="section-kicker">REPORT OPERASIONAL</span>
                            <h2>{{ $reportNav[$reportType][0] ?? 'Report' }}</h2>
                        </div>
                        <div class="filter-chips">
                            @forelse ($activeFilterLabels as $filterLabel)
                                <span class="filter-chip">{{ $filterLabel }} aktif</span>
                            @empty
                                <span class="filter-chip">Semua data</span>
                            @endforelse
                        </div>
                    </div>
                    <form method="get" action="{{ $activeReportUrl }}">
                        <div class="filter-grid">
                            @if (in_array('date', $filterFields, true))
                                <label>Dari tanggal <input name="from_date" type="date" value="{{ $fromDate }}"></label>
                                <label>Sampai tanggal <input name="to_date" type="date" value="{{ $toDate }}"></label>
                            @endif
                            @if (in_array('company', $filterFields, true))
                                <label>Perusahaan
                                    <select name="company">
                                        <option value="">Semua perusahaan</option>
                                        @foreach ($companies as $companyName)
                                            <option value="{{ $companyName }}" @selected($company === $companyName)>{{ $companyName }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            @endif
                            @if (in_array('camp', $filterFields, true))
                                <label>Site
                                    <select name="camp_id">
                                        <option value="">Semua site</option>
                                        @foreach ($camps as $camp)
                                            <option value="{{ $camp->id }}" @selected((int) $campId === $camp->id)>{{ $camp->company_name }} - {{ $camp->name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            @endif
                            @if (in_array('unit', $filterFields, true))
                                <label>Unit
                                    <select name="unit_id">
                                        <option value="">Semua unit</option>
                                        @foreach ($units as $unit)
                                            <option value="{{ $unit->id }}" @selected((int) $unitId === $unit->id)>{{ $unit->code }} - {{ $unit->name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            @endif
                            @if (in_array('item', $filterFields, true))
                                <label>Barang
                                    <select name="item_id">
                                        <option value="">Semua barang</option>
                                        @foreach ($items as $item)
                                            <option value="{{ $item->id }}" @selected((int) $itemId === $item->id)>{{ $item->sku }} - {{ $item->name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            @endif
                            @if (in_array('vendor', $filterFields, true))
                                <label>Vendor
                                    <select name="vendor_id">
                                        <option value="">Semua vendor</option>
                                        @foreach ($vendors as $vendor)
                                            <option value="{{ $vendor->id }}" @selected((int) $vendorId === $vendor->id)>{{ $vendor->name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            @endif
                            @if (in_array('status', $filterFields, true))
                                <label>{{ $reportType === 'activity_logs' ? 'Event' : ($reportType === 'stock_movements' ? 'Tipe mutasi' : 'Status') }}
                                    <select name="status">
                                        <option value="">Semua {{ $reportType === 'activity_logs' ? 'event' : ($reportType === 'stock_movements' ? 'tipe mutasi' : 'status') }}</option>
                                        @foreach ($statusOptions as $statusOption)
                                            <option value="{{ $statusOption }}" @selected($status === $statusOption)>{{ $reportStatusLabels[$reportType][$statusOption] ?? (in_array($reportType, ['activity_logs', 'stock_movements'], true) ? $statusOption : $workflowStatusLabel($statusOption)) }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            @endif
                        </div>
                        <div class="actions filter-actions">
                            <a class="button ghost" href="{{ $activeReportUrl }}">Reset</a>
                            <button type="button" class="ghost" data-export-report>Export CSV</button>
                            <button>Tampilkan Report</button>
                        </div>
                    </form>
                </section>

                <div class="report-grid">
                    @foreach ($reportKpis as $reportKpi)
                        <div class="card stat"><span>{{ $reportKpi['label'] }}</span><strong>{{ $reportKpi['value'] }}</strong><span>{{ $reportKpi['note'] }}</span></div>
                    @endforeach
                </div>

                @if ($reportType === 'procurement_flow')
                <section>
                    <h2>Proses Pengadaan End-to-End</h2>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr><th>Order Request / PO</th><th>Perusahaan / Site</th><th>Unit / Barang</th><th>Vendor / Jalur</th><th>Dipesan</th><th>Gudang</th><th>Dikirim</th><th>Diterima Site</th><th>Masalah</th><th>Posisi</th></tr>
                            </thead>
                            <tbody>
                            @forelse ($procurementRows as $row)
                                @php
                                    $requestItem = $row['request_item'];
                                    $purchaseOrder = $row['purchase_order'];
                                    $purchaseOrderItem = $row['purchase_order_item'];
                                    $uom = $purchaseOrderItem?->uom ?? $requestItem->uom ?? $requestItem->inventoryItem?->uom;
                                    $stageClass = $row['stage'] === 'issue' ? 'bad' : (in_array($row['stage'], ['site_receipt', 'warehouse_receipt'], true) ? 'ok' : 'wait');
                                @endphp
                                <tr>
                                    <td>
                                        <a href="{{ route('purchase-requests.review', [
                                            'purchaseRequest' => $requestItem->purchaseRequest,
                                            'from' => 'reports',
                                        ]) }}"><strong>{{ $requestItem->purchaseRequest->number }}</strong></a>
                                        <div class="muted">
                                            @if ($purchaseOrder)
                                                <a href="{{ route('purchase-orders.review', ['purchaseOrder' => $purchaseOrder, 'from' => 'reports']) }}">{{ $purchaseOrder->number }}</a>
                                            @else
                                                Belum dibuat PO
                                            @endif
                                        </div>
                                    </td>
                                    <td>{{ $requestItem->purchaseRequest->camp?->company_name }}<div class="muted">{{ $requestItem->purchaseRequest->camp?->name }}</div></td>
                                    <td>{{ $requestItem->unit?->code ?: '-' }}<div>{{ $requestItem->inventoryItem?->sku }} - {{ $requestItem->inventoryItem?->name }}</div></td>
                                    <td>{{ $purchaseOrder?->vendor?->name ?: '-' }}<div class="muted">{{ $purchaseOrder?->delivery_destination === 'direct_site' ? 'Vendor langsung ke site' : ($purchaseOrder ? 'Melalui gudang kantor' : '-') }}</div></td>
                                    <td>{{ number_format($row['ordered'], 0, ',', '.') }} {{ $uom }}</td>
                                    <td>{{ $purchaseOrder?->delivery_destination === 'direct_site' ? '-' : number_format($row['warehouse_received'], 0, ',', '.').' '.$uom }}</td>
                                    <td>{{ number_format($row['sent'], 0, ',', '.') }} {{ $uom }}</td>
                                    <td>{{ number_format($row['site_received'], 0, ',', '.') }} {{ $uom }}</td>
                                    <td>
                                        @if ($row['problem'] > 0)
                                            <span class="pill bad">{{ number_format($row['problem'], 0, ',', '.') }} {{ $uom }}</span>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td><span class="pill {{ $stageClass }}">{{ $flowStatusLabels[$row['stage']] }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="10">Belum ada proses pengadaan sesuai filter.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
                @endif

                @if ($reportType === 'receipts')
                <section>
                    <h2>Report Penerimaan Barang</h2>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr><th>Tanggal</th><th>Dokumen / Faktur</th><th>PO / Vendor</th><th>Perusahaan / Site</th><th>Barang</th><th>Dipesan</th><th>Diterima</th><th>Sisa PO</th><th>Kondisi</th><th>Penerima</th></tr>
                            </thead>
                            <tbody>
                            @forelse ($receiptRows as $row)
                                @php
                                    $purchaseOrder = $row['purchase_order'];
                                    $purchaseOrderItem = $row['purchase_order_item'];
                                    $uom = $purchaseOrderItem?->uom ?? $purchaseOrderItem?->inventoryItem?->uom;
                                    $conditionClass = $row['condition'] === 'good' ? 'ok' : 'bad';
                                @endphp
                                <tr>
                                    <td>{{ $row['date']?->format('d M Y') }}</td>
                                    <td><strong>{{ $row['document'] }}</strong><div class="muted">Faktur: {{ $row['invoice'] ?: '-' }}</div><div class="muted">{{ $row['source'] }}</div></td>
                                    <td><a href="{{ route('purchase-orders.review', ['purchaseOrder' => $purchaseOrder, 'from' => 'reports']) }}">{{ $purchaseOrder->number }}</a><div class="muted">{{ $purchaseOrder->vendor?->name }}</div></td>
                                    <td>{{ $purchaseOrder->purchaseRequest?->camp?->company_name }}<div class="muted">{{ $purchaseOrder->purchaseRequest?->camp?->name }}</div></td>
                                    <td>{{ $purchaseOrderItem?->inventoryItem?->sku }}<div>{{ $purchaseOrderItem?->inventoryItem?->name }}</div></td>
                                    <td>{{ number_format((float) ($purchaseOrderItem?->quantity_ordered ?? 0), 0, ',', '.') }} {{ $uom }}</td>
                                    <td>{{ number_format($row['quantity_received'], 0, ',', '.') }} {{ $uom }}</td>
                                    <td>{{ number_format($row['remaining'], 0, ',', '.') }} {{ $uom }}</td>
                                    <td><span class="pill {{ $conditionClass }}">{{ $receiptConditionLabels[$row['condition']] ?? $workflowStatusLabel($row['condition']) }}</span>@if ($row['problem'] > 0)<div class="muted">CN {{ number_format($row['problem'], 0, ',', '.') }} {{ $uom }}</div>@endif</td>
                                    <td>{{ $row['receiver'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="10">Belum ada penerimaan sesuai filter.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
                @endif

                @if ($reportType === 'stock')
                <section>
                    <h2>Report Stok Kritis</h2>
                    <table>
                        <thead><tr><th>SKU</th><th>Barang</th><th>Kategori</th><th>Stok</th><th>Minimum</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse ($criticalItems as $item)
                            <tr>
                                <td>{{ $item->sku }}</td>
                                <td>{{ $item->name }}</td>
                                <td>{{ $item->category }}</td>
                                <td>{{ $item->stock_on_hand }} {{ $item->uom }}</td>
                                <td>{{ $item->minimumStockLevel() }} {{ $item->uom }}</td>
                                <td><span class="pill bad">Perlu Order</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="6">Tidak ada stok kritis.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </section>
                @endif

                @if ($reportType === 'inventory_planning')
                <section>
                    <h2>Perencanaan EOQ / ROP Consumable</h2>
                    <table>
                        <thead><tr><th>SKU</th><th>Barang</th><th>Demand/Tahun</th><th>Lead Time</th><th>Safety Stock</th><th>EOQ</th><th>ROP / Min</th><th>Max</th><th>Stok</th><th>Rekomendasi</th></tr></thead>
                        <tbody>
                        @forelse ($filteredItems->filter(fn ($item) => $item->usesInventoryPlanning()) as $item)
                            <tr>
                                <td>{{ $item->sku }}</td>
                                <td>{{ $item->name }}</td>
                                <td>{{ number_format($item->annualDemand(), 0, ',', '.') }} {{ $item->uom }}</td>
                                <td>{{ $item->lead_time_days }} hari</td>
                                <td>{{ number_format((float) $item->safety_stock, 0, ',', '.') }} {{ $item->uom }}</td>
                                <td>{{ number_format($item->eoq(), 0, ',', '.') }} {{ $item->uom }}</td>
                                <td>{{ $item->minimumStockLevel() }} {{ $item->uom }}</td>
                                <td>{{ $item->maximumStockLevel() }} {{ $item->uom }}</td>
                                <td>{{ $item->stock_on_hand }} {{ $item->uom }}</td>
                                <td><span class="pill {{ $item->recommendedOrderQuantity() > 0 ? 'bad' : 'ok' }}">{{ $item->recommendedOrderQuantity() > 0 ? $item->recommendedOrderQuantity() . ' ' . $item->uom : 'Belum perlu' }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="10">Belum ada barang consumable dengan parameter EOQ / ROP.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </section>
                @endif

                @if ($reportType === 'requests')
                <div class="grid-2">
                    <section>
                        <h2>Report Order Request</h2>
                        <table>
                            <thead><tr><th>Status</th><th>Jumlah</th><th>Total Final</th></tr></thead>
                            <tbody>
                            @forelse ($filteredRequests->groupBy('status') as $requestStatus => $requestsByStatus)
                                <tr>
                                    <td><span class="pill">{{ $requestStatus }}</span></td>
                                    <td>{{ $requestsByStatus->count() }}</td>
                                    <td class="price">{{ $canSeePrices ? 'Rp'.number_format($requestsByStatus->sum('final_total'), 0, ',', '.') : 'Disembunyikan' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3">Tidak ada pengajuan sesuai filter.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </section>
                </div>
                @endif

                @if ($reportType === 'vendors')
                <section>
                    <h2>Report Vendor Terpilih</h2>
                    <table>
                        <thead><tr><th>Order Request</th><th>Jenis / Referensi</th><th>Barang</th><th>Vendor</th><th>Qty</th><th>Nilai</th></tr></thead>
                        <tbody>
                        @forelse ($selectedVendorAllocations as $allocation)
                            @php
                                $requestItem = $allocation['request_item'];
                                $selectedQuotation = $allocation['quotation'];
                                $sourceCreditNote = $requestItem->sourceCreditNoteItem?->creditNote;
                            @endphp
                            <tr>
                                <td>{{ $requestItem->purchaseRequest->number }}</td>
                                <td>
                                    @if ($sourceCreditNote)
                                        <span class="pill">Pengajuan Ulang</span>
                                        <div class="muted">
                                            @if ($currentUser->hasPermission('credit_notes.view'))
                                                <a href="{{ route('credit-notes.review', ['creditNote' => $sourceCreditNote, 'from' => 'reports']) }}">CN {{ $sourceCreditNote->number }}</a>
                                            @else
                                                CN {{ $sourceCreditNote->number }}
                                            @endif
                                        </div>
                                    @else
                                        <span class="pill">Pengadaan Awal</span>
                                    @endif
                                </td>
                                <td>{{ $requestItem->inventoryItem->name }}</td>
                                <td>{{ $selectedQuotation->vendor->name }}<div class="muted">{{ $selectedQuotation->brand_name ?: '-' }}</div></td>
                                <td>{{ $selectedQuotation->selected_quantity }} {{ $requestItem->uom ?? $requestItem->inventoryItem->uom }}</td>
                                <td class="price">{{ $canSeePrices ? 'Rp'.number_format($selectedQuotation->unit_price * $selectedQuotation->selected_quantity, 0, ',', '.') : 'Disembunyikan' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6">Belum ada vendor terpilih sesuai filter.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </section>
                @endif

                @if ($reportType === 'purchase_orders')
                <section>
                    <h2>Report Purchase Order</h2>
                    <table>
                        <thead><tr><th>No PO</th><th>Tanggal</th><th>Vendor</th><th>Order Request</th><th>Jenis / Referensi</th><th>Site</th><th>Status</th><th>Nilai</th></tr></thead>
                        <tbody>
                        @forelse ($filteredPurchaseOrders as $purchaseOrder)
                            @php
                                $sourceCreditNotes = $purchaseOrder->items
                                    ->map(fn ($item) => $item->requestItem?->sourceCreditNoteItem?->creditNote)
                                    ->filter()
                                    ->unique('id')
                                    ->values();
                            @endphp
                            <tr>
                                <td>{{ $purchaseOrder->number }}</td>
                                <td>{{ $purchaseOrder->ordered_at->format('d M Y') }}</td>
                                <td>{{ $purchaseOrder->vendor->name }}</td>
                                <td>{{ $purchaseOrder->purchaseRequest->number }}</td>
                                <td>
                                    @if ($sourceCreditNotes->isNotEmpty())
                                        <span class="pill">Pengajuan Ulang</span>
                                        @foreach ($sourceCreditNotes as $sourceCreditNote)
                                            <div class="muted">
                                                @if ($currentUser->hasPermission('credit_notes.view'))
                                                    <a href="{{ route('credit-notes.review', ['creditNote' => $sourceCreditNote, 'from' => 'reports']) }}">CN {{ $sourceCreditNote->number }}</a>
                                                @else
                                                    CN {{ $sourceCreditNote->number }}
                                                @endif
                                            </div>
                                        @endforeach
                                    @else
                                        <span class="pill">Pengadaan Awal</span>
                                    @endif
                                </td>
                                <td>{{ $purchaseOrder->purchaseRequest->camp->name }}</td>
                                <td><span class="pill">{{ $purchaseOrderStatusLabel($purchaseOrder->status) }}</span></td>
                                <td class="price">{{ $canSeePrices ? 'Rp'.number_format($purchaseOrder->grand_total, 0, ',', '.') : 'Disembunyikan' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8">Belum ada purchase order sesuai filter.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </section>
                @endif

                @if ($reportType === 'credit_notes')
                <section>
                    <h2>Report Credit Note</h2>
                    <table>
                        <thead><tr><th>No CN</th><th>Tanggal</th><th>Sumber</th><th>Perusahaan / Site</th><th>Vendor</th><th>Barang</th><th>Status</th><th>Nilai</th></tr></thead>
                        <tbody>
                        @forelse ($filteredCreditNotes as $creditNote)
                            @php
                                $creditNoteCamp = $creditNote->purchaseOrder?->purchaseRequest?->camp ?? $creditNote->siteReceipt?->siteDispatch?->camp ?? $creditNote->camp;
                                $creditSource = [
                                    'goods_receipt' => 'Penerimaan Gudang',
                                    'site_request' => 'Request Camp',
                                    'manual_return' => 'Manual Return',
                                ][$creditNote->source_type] ?? $workflowStatusLabel($creditNote->source_type);
                            @endphp
                            <tr class="clickable-row" data-href="{{ route('credit-notes.review', ['creditNote' => $creditNote, 'from' => 'reports']) }}">
                                <td>{{ $creditNote->number }}</td>
                                <td>{{ $creditNote->credit_note_date?->format('d M Y') }}</td>
                                <td>{{ $creditSource }}</td>
                                <td>{{ $creditNoteCamp?->company_name ?: '-' }}<div class="muted">{{ $creditNoteCamp?->name ?: '-' }}</div></td>
                                <td>{{ $creditNote->vendor?->name ?: '-' }}</td>
                                <td>
                                    @foreach ($creditNote->items as $creditNoteItem)
                                        <div>{{ $creditNoteItem->inventoryItem?->name }} x {{ $creditNoteItem->quantity_problem }} {{ $creditNoteItem->purchaseOrderItem?->uom ?? $creditNoteItem->inventoryItem?->uom }}</div>
                                    @endforeach
                                </td>
                                <td><span class="pill {{ in_array($creditNote->status, ['credited', 'closed'], true) ? 'ok' : 'wait' }}">{{ $creditNoteStatusLabel($creditNote->status) }}</span></td>
                                <td class="price">{{ $canSeePrices ? 'Rp'.number_format($creditNote->items->sum('total'), 0, ',', '.') : 'Disembunyikan' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8">Belum ada credit note sesuai filter.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </section>
                @endif

                @if ($reportType === 'requests')
                <section>
                    <h2>Detail Order Request</h2>
                    <table>
                        <thead><tr><th>No</th><th>Tanggal</th><th>Perusahaan</th><th>Site</th><th>Pemohon</th><th>Status</th><th>Total</th></tr></thead>
                        <tbody>
                        @forelse ($filteredRequests as $pr)
                            <tr>
                                <td>{{ $pr->number }}</td>
                                <td>{{ $pr->requested_at->format('d M Y') }}</td>
                                <td>{{ $pr->camp->company_name }}</td>
                                <td>{{ $pr->camp->name }}</td>
                                <td>{{ $pr->requested_by }}</td>
                                <td><span class="pill">{{ $workflowStatusLabel($pr->status) }}</span></td>
                                <td class="price">{{ $canSeePrices ? 'Rp'.number_format($pr->final_total, 0, ',', '.') : 'Disembunyikan' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7">Tidak ada detail pengajuan sesuai filter.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </section>
                @endif

                @if ($reportType === 'usage')
                <section>
                    <h2>Report Pemakaian Unit</h2>
                    <table>
                        <thead><tr><th>Tanggal</th><th>Perusahaan</th><th>Site</th><th>Unit</th><th>Barang</th><th>Qty</th></tr></thead>
                        <tbody>
                        @forelse ($filteredUsageLogs as $log)
                            <tr>
                                <td>{{ $log->used_at->format('d M Y') }}</td>
                                <td>{{ $log->unit?->camp?->company_name }}</td>
                                <td>{{ $log->unit?->camp?->name }}</td>
                                <td>{{ $log->unit->name }}</td>
                                <td>{{ ucfirst($log->usage_type) }} {{ $log->inventoryItem?->name }}</td>
                                <td>{{ $log->quantity }} {{ $log->uom }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6">Belum ada pemakaian sesuai filter.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </section>
                @endif

                @if ($reportType === 'opnames')
                <section>
                    <h2>Report Selisih Opname</h2>
                    <table>
                        <thead><tr><th>No Opname</th><th>Tanggal</th><th>Barang</th><th>Selisih</th><th>Status</th><th>Catatan</th></tr></thead>
                        <tbody>
                        @forelse ($opnameDifferences as $opnameItem)
                            <tr>
                                <td>{{ $opnameItem->stockOpname->number }}</td>
                                <td>{{ $opnameItem->stockOpname->opname_date->format('d M Y') }}</td>
                                <td>{{ $opnameItem->inventoryItem->name }}</td>
                                <td><span class="pill {{ $opnameItem->variance_status === 'out_of_tolerance' ? 'bad' : 'wait' }}">{{ $opnameItem->difference > 0 ? '+' : '' }}{{ $opnameItem->difference }}</span></td>
                                <td>
                                    <span class="pill {{ $opnameItem->variance_status === 'out_of_tolerance' || $opnameItem->tolerance_review_status === 'rejected' ? 'bad' : ($opnameItem->tolerance_review_status === 'pending' ? 'wait' : 'ok') }}">
                                        {{ $opnameItem->variance_status === 'matched' ? 'Sesuai' : ($opnameItem->tolerance_review_status === 'pending' ? 'Menunggu review' : ($opnameItem->tolerance_review_status === 'accepted' ? 'Toleransi diterima' : ($opnameItem->tolerance_review_status === 'rejected' ? 'Perlu cek' : ($opnameItem->variance_status === 'within_tolerance' ? 'Dalam toleransi' : 'Perlu cek')))) }}
                                    </span>
                                    @if ($opnameItem->tolerance_quantity > 0)
                                        <div class="muted">Toleransi {{ $opnameItem->tolerance_quantity }} {{ $opnameItem->inventoryItem->uom }}</div>
                                    @endif
                                </td>
                                <td>{{ $opnameItem->notes }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6">Belum ada selisih opname sesuai filter.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </section>
                @endif

                @if ($reportType === 'dispatches')
                <section>
                    <h2>Report Pengiriman Site</h2>
                    <table>
                        <thead><tr><th>No Kirim</th><th>Tanggal</th><th>Perusahaan</th><th>Site</th><th>Status</th><th>Barang</th></tr></thead>
                        <tbody>
                        @forelse ($filteredDispatches as $dispatch)
                            <tr>
                                <td>{{ $dispatch->number }}</td>
                                <td>{{ $dispatch->sent_at?->format('d M Y') ?? '-' }}</td>
                                <td>{{ $dispatch->camp->company_name }}</td>
                                <td>{{ $dispatch->camp->name }}</td>
                                <td><span class="pill">{{ $dispatch->status }}</span></td>
                                <td>
                                    @foreach ($dispatch->items as $dispatchItem)
                                        <div>{{ $dispatchItem->inventoryItem->name }} x {{ $dispatchItem->quantity_sent }}</div>
                                    @endforeach
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6">Belum ada pengiriman sesuai filter.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </section>
                @endif

                @if ($reportType === 'stock_movements')
                <section>
                    <h2>Kartu Stok</h2>
                    <table>
                        <thead><tr><th>Waktu</th><th>Barang</th><th>Tipe</th><th>Qty</th><th>Sebelum</th><th>Sesudah</th><th>User</th><th>Catatan</th></tr></thead>
                        <tbody>
                        @forelse ($filteredStockMovements as $movement)
                            <tr>
                                <td>{{ $movement->created_at->format('d M Y H:i') }}</td>
                                <td>{{ $movement->inventoryItem?->name }}</td>
                                <td><span class="pill">{{ $movement->type }}</span></td>
                                <td><span class="pill {{ (float) $movement->quantity < 0 ? 'bad' : 'ok' }}">{{ (float) $movement->quantity > 0 ? '+' : '' }}{{ number_format((float) $movement->quantity, 0, ',', '.') }}</span></td>
                                <td>{{ number_format((float) $movement->stock_before, 0, ',', '.') }}</td>
                                <td>{{ number_format((float) $movement->stock_after, 0, ',', '.') }}</td>
                                <td>{{ $movement->user?->name ?? 'Sistem' }}</td>
                                <td>{{ $movement->notes }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8">Belum ada mutasi stok sesuai filter.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </section>
                @endif

                @if ($reportType === 'activity_logs')
                <section>
                    <h2>Log User</h2>
                    <table>
                        <thead><tr><th>Waktu</th><th>User</th><th>Event</th><th>Keterangan</th><th>IP</th></tr></thead>
                        <tbody>
                        @forelse ($filteredActivityLogs as $log)
                            <tr>
                                <td>{{ $log->created_at->format('d M Y H:i') }}</td>
                                <td>{{ $log->user?->name ?? 'Guest/Sistem' }}</td>
                                <td><span class="pill">{{ $log->event }}</span></td>
                                <td>{{ $log->description }}</td>
                                <td>{{ $log->ip_address }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5">Belum ada log user sesuai filter.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </section>
                @endif
            @endif
