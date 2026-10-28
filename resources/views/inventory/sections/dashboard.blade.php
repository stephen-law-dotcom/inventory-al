            @if ($activeMenu === 'dashboard')
                @php
                    $canViewRequests = $currentUser->hasPermission('requests.view');
                    $canViewApprovals = $currentUser->hasPermission('approvals.view');
                    $canViewVendorPrices = $currentUser->hasPermission('vendor_prices.view');
                    $canViewPurchaseOrders = $currentUser->hasPermission('purchase_orders.view');
                    $canViewReceipts = $currentUser->hasPermission('receipts.view');
                    $canViewCreditNotes = $currentUser->hasPermission('credit_notes.view');
                    $canViewDispatches = $currentUser->hasPermission('dispatches.view');
                    $canViewUsage = $currentUser->hasPermission('usage.view');
                    $canViewStock = $currentUser->hasPermission('stock.view') || $currentUser->hasPermission('stock_opname.view');
                    $isCampScoped = in_array($currentUser->role?->slug, ['camp', 'manager_camp'], true)
                        && count($currentUser->scopedCampIds()) > 0;
                    $dashboardPeriodLabels = [
                        'today' => 'Hari Ini',
                        '7_days' => '7 Hari',
                        'month' => 'Bulan Ini',
                    ];
                    $dashboardPeriodLabel = $dashboardPeriodLabels[$dashboardPeriod] ?? $dashboardPeriodLabels['month'];
                    $dashboardPeriodEnd = now()->endOfDay();
                    $dashboardPeriodRange = $dashboardPeriodStart->isSameDay($dashboardPeriodEnd)
                        ? $dashboardPeriodEnd->format('d M Y')
                        : $dashboardPeriodStart->format('d M Y').' - '.$dashboardPeriodEnd->format('d M Y');
                    $inDashboardPeriod = fn ($date) => $date
                        && \Illuminate\Support\Carbon::parse($date)->between($dashboardPeriodStart, $dashboardPeriodEnd, true);
                    $periodPurchaseRequests = $purchaseRequests->filter(fn ($request) => $inDashboardPeriod($request->requested_at));
                    $periodPurchaseOrders = $purchaseOrders->filter(fn ($purchaseOrder) => $inDashboardPeriod($purchaseOrder->ordered_at));
                    $periodCreditNotes = $creditNotes->filter(fn ($creditNote) => $inDashboardPeriod($creditNote->credit_note_date));
                    $periodUsageLogs = $usageLogs->filter(fn ($usageLog) => $inDashboardPeriod($usageLog->used_at));
                    $requestStatusChart = $purchaseRequests->groupBy('status')->map->count();
                    $maxRequestStatus = max(1, $requestStatusChart->max() ?? 1);
                    $campQuantityByItem = $campStocks->groupBy('inventory_item_id')->map->sum('quantity');
                    $inventoryRows = $items->map(function ($item) use ($isCampScoped, $campQuantityByItem) {
                        $minimum = $item->minimumStockLevel();
                        $isPlanned = $item->usesInventoryPlanning();
                        $eoq = $isPlanned ? (int) ceil($item->eoq()) : null;
                        $parameterIncomplete = $isPlanned && ($eoq <= 0 || $minimum <= 0);

                        if ($isCampScoped) {
                            $position = (float) ($campQuantityByItem[$item->id] ?? 0);

                            return (object) [
                                'item' => $item,
                                'position' => $position,
                                'minimum' => $minimum,
                                'eoq' => $eoq,
                                'recommendation' => 0,
                                'status' => $position <= $minimum ? 'needs_distribution' : 'safe',
                                'parameter_incomplete' => $parameterIncomplete,
                            ];
                        }

                        $warehouse = $item->warehouseStock();
                        $camp = $item->campStock();
                        $transit = $item->inTransitStock();
                        $onOrder = $item->outstandingPurchaseOrderQuantity();
                        $available = $warehouse + $camp;
                        $position = $available + $transit + $onOrder;
                        $status = match (true) {
                            $position <= $minimum => 'needs_order',
                            $available + $transit <= $minimum && $onOrder > 0 => 'covered_po',
                            $available <= $minimum && $transit > 0 => 'in_transit',
                            default => 'safe',
                        };

                        return (object) [
                            'item' => $item,
                            'position' => $position,
                            'minimum' => $minimum,
                            'eoq' => $eoq,
                            'recommendation' => $status === 'needs_order' ? $item->recommendedOrderQuantity() : 0,
                            'status' => $status,
                            'parameter_incomplete' => $parameterIncomplete,
                        ];
                    })->values();
                    $criticalCount = $inventoryRows->whereIn('status', ['needs_order', 'needs_distribution'])->count();
                    $coveredPoCount = $inventoryRows->where('status', 'covered_po')->count();
                    $inTransitCount = $inventoryRows->where('status', 'in_transit')->count();
                    $incompleteParameterCount = $inventoryRows->where('parameter_incomplete', true)->count();
                    $safeCount = $inventoryRows->where('status', 'safe')->count();
                    $criticalAngle = $inventoryRows->count() > 0 ? round(($criticalCount / $inventoryRows->count()) * 360) : 0;
                    $inventoryReminderRows = $inventoryRows
                        ->filter(fn ($row) => $row->status !== 'safe' || $row->parameter_incomplete)
                        ->sortBy(fn ($row) => [
                            ['needs_order' => 0, 'needs_distribution' => 0, 'covered_po' => 1, 'in_transit' => 2][$row->status] ?? 9,
                            $row->position,
                        ])
                        ->take(10);
                    $stockReminderRoute = $currentUser->hasPermission('reports.view')
                        ? route('reports.show', 'stock')
                        : ($currentUser->hasPermission('stock.view') ? route('stock') : route('stock-opname'));
                    $usageTrend = match ($dashboardPeriod) {
                        'today' => collect([[
                            'label' => 'Hari ini',
                            'total' => $periodUsageLogs->sum('quantity'),
                        ]]),
                        '7_days' => collect(range(6, 0))->map(function ($daysAgo) use ($periodUsageLogs) {
                            $date = now()->subDays($daysAgo);

                            return [
                                'label' => $date->format('d M'),
                                'total' => $periodUsageLogs->filter(fn ($log) => $log->used_at->isSameDay($date))->sum('quantity'),
                            ];
                        }),
                        default => collect(range(1, (int) ceil(now()->day / 7)))->map(function ($week) use ($periodUsageLogs) {
                            $start = now()->startOfMonth()->addDays(($week - 1) * 7);
                            $end = $start->copy()->addDays(6)->min(now());

                            return [
                                'label' => $start->day.'-'.$end->day,
                                'total' => $periodUsageLogs->filter(fn ($log) => $log->used_at->between($start, $end, true))->sum('quantity'),
                            ];
                        }),
                    };
                    $maxUsageTrend = max(1, $usageTrend->max('total') ?? 1);
                    $approvalPendingItems = $purchaseRequests->flatMap->items->filter(fn ($item) => in_array($item->status, ['submitted', 'logistic_checked', 'waiting_manager_camp'], true));
                    $myApprovalItems = $approvalPendingItems->filter(fn ($item) =>
                        ($currentUser->hasPermission('approvals.logistic') && $item->logistic_approval_status === 'pending')
                        || ($currentUser->hasPermission('approvals.chief_mechanic') && $item->chief_mechanic_approval_status === 'pending')
                        || ($currentUser->hasPermission('approvals.manager_camp') && $item->manager_camp_approval_status === 'pending')
                    );
                    $vendorPendingItems = $purchaseRequests->flatMap->items->filter(fn ($item) =>
                        $item->purchaseOrderItems->isEmpty()
                        && (
                            $item->status === 'ready_for_quotation'
                            || ($item->status === 'vendor_selected' && $item->procurement_approval_status === 'draft')
                        )
                    );
                    $procurementPendingItems = $purchaseRequests->flatMap->items->filter(fn ($item) => in_array($item->procurement_approval_status, ['pending_equipment', 'pending_director'], true));
                    $myProcurementApprovalItems = $procurementPendingItems->filter(fn ($item) =>
                        ($currentUser->hasPermission('approvals.equipment') && $item->procurement_approval_status === 'pending_equipment')
                        || ($currentUser->hasPermission('approvals.director') && $item->procurement_approval_status === 'pending_director')
                    );
                    $readyPoItems = $purchaseRequests->flatMap->items->filter(fn ($item) => $item->status === 'vendor_selected' && ! $item->purchaseOrderItem && in_array($item->procurement_approval_status, ['approved', 'not_required'], true));
                    $activePo = $purchaseOrders->whereIn('status', ['draft', 'on_progress', 'submitted', 'issued', 'partial_received']);
                    $receivingPo = $purchaseOrders->whereIn('status', ['submitted', 'issued', 'partial_received']);
                    $openCreditNotes = $creditNotes->whereNotIn('status', ['closed']);
                    $openPoValue = $activePo->sum(fn ($purchaseOrder) => $purchaseOrder->grandTotalIdr());
                    $periodPoValue = $periodPurchaseOrders->sum(fn ($purchaseOrder) => $purchaseOrder->grandTotalIdr());
                    $periodUsageQty = $periodUsageLogs->sum('quantity');
                    $topUsageItems = $periodUsageLogs
                        ->groupBy('inventory_item_id')
                        ->map(fn ($logs) => [
                            'item' => $logs->first()->inventoryItem,
                            'total' => $logs->sum('quantity'),
                        ])
                        ->sortByDesc('total')
                        ->take(6);
                    $priorityTasks = collect([
                        ['code' => 'AP', 'label' => 'Approval Order Request', 'desc' => 'Order request menunggu keputusan Anda.', 'count' => $myApprovalItems->count(), 'route' => route('approvals'), 'allowed' => $currentUser->hasPermission('approvals.view')],
                        ['code' => 'VN', 'label' => 'Input Penawaran Vendor', 'desc' => 'Item siap dibandingkan penawaran vendornya.', 'count' => $vendorPendingItems->count(), 'route' => route('vendor-prices', $companyQuery), 'allowed' => $currentUser->hasPermission('vendor_prices.view')],
                        ['code' => 'PG', 'label' => 'Approval Pengadaan', 'desc' => 'Permintaan pengadaan menunggu keputusan Anda.', 'count' => $myProcurementApprovalItems->count(), 'route' => route('vendor-prices', array_merge($companyQuery, ['tab' => 'approval'])), 'allowed' => $currentUser->hasPermission('approvals.equipment') || $currentUser->hasPermission('approvals.director')],
                        ['code' => 'PO', 'label' => 'Siap Dibuat PO', 'desc' => 'Vendor final sudah dipilih dan siap diproses purchasing.', 'count' => $readyPoItems->count(), 'route' => route('purchase-orders', $companyQuery), 'allowed' => $currentUser->hasPermission('purchase_orders.view')],
                        ['code' => 'GR', 'label' => 'Penerimaan Gudang', 'desc' => 'PO submitted dan menunggu barang diterima.', 'count' => $receivingPo->count(), 'route' => route('receipts', $companyQuery), 'allowed' => $currentUser->hasPermission('receipts.view')],
                        ['code' => 'CN', 'label' => 'Credit Note', 'desc' => 'Barang bermasalah menunggu credit/refund atau pengajuan vendor ulang.', 'count' => $openCreditNotes->count(), 'route' => route('credit-notes', $companyQuery), 'allowed' => $currentUser->hasPermission('credit_notes.view')],
                        ['code' => 'SO', 'label' => 'Review Selisih Opname', 'desc' => 'Selisih hasil hitung fisik menunggu keputusan.', 'count' => $pendingToleranceReviews->count(), 'route' => route('stock-opname'), 'allowed' => $currentUser->hasPermission('stock_opname.view')],
                        ['code' => 'ST', 'label' => $isCampScoped ? 'Stok Site Perlu Dipenuhi' : 'Rekomendasi Pembelian', 'desc' => $isCampScoped ? 'Stok site telah mencapai batas minimum.' : 'Posisi stok jaringan telah mencapai ROP/minimum.', 'count' => $criticalCount, 'route' => $stockReminderRoute, 'allowed' => $canViewStock],
                    ])->filter(fn ($task) => $task['allowed'] && $task['count'] > 0)->values();
                    $pipeline = collect([
                        ['label' => 'OR Aktif', 'count' => $purchaseRequests->whereNotIn('status', ['draft', 'rejected', 'goods_received'])->count(), 'allowed' => $canViewRequests],
                        ['label' => 'Approval Saya', 'count' => $myApprovalItems->count(), 'allowed' => $canViewApprovals],
                        ['label' => 'Penawaran Vendor', 'count' => $vendorPendingItems->count(), 'allowed' => $canViewVendorPrices],
                        ['label' => 'Approval Pengadaan', 'count' => $myProcurementApprovalItems->count(), 'allowed' => $currentUser->hasPermission('approvals.equipment') || $currentUser->hasPermission('approvals.director')],
                        ['label' => 'Siap PO', 'count' => $readyPoItems->count(), 'allowed' => $canViewPurchaseOrders],
                        ['label' => 'PO Aktif', 'count' => $activePo->count(), 'allowed' => $canViewPurchaseOrders],
                    ])->filter(fn ($step) => $step['allowed'])->values();
                @endphp
                <div class="dashboard-stack">
                    <section class="dashboard-hero">
                        <div class="hero-copy">
                            <small>Business Operations Dashboard</small>
                            <h2>Dashboard Operasional</h2>
                            <div class="hero-actions">
                                @if ($currentUser->hasPermission('requests.view'))
                                    <a class="button" href="{{ route('requests', $companyQuery) }}">Order Request</a>
                                @endif
                                @if ($currentUser->hasPermission('approvals.view'))
                                    <a class="button ghost" href="{{ route('approvals') }}">Approval</a>
                                @endif
                                @if ($currentUser->hasPermission('purchase_orders.view'))
                                    <a class="button ghost" href="{{ route('purchase-orders', $companyQuery) }}">Purchase Order</a>
                                @endif
                                @if ($currentUser->hasPermission('reports.view'))
                                    <a class="button ghost" href="{{ route('reports') }}">Report</a>
                                @endif
                            </div>
                        </div>
                        <div class="hero-panel">
                            @if ($canViewRequests)<div><span>OR aktif</span><strong>{{ $purchaseRequests->whereNotIn('status', ['draft', 'rejected', 'goods_received'])->count() }}</strong></div>@endif
                            @if ($canViewPurchaseOrders)<div><span>PO aktif</span><strong>{{ $activePo->count() }}</strong></div>@endif
                            @if ($canViewStock)<div><span>{{ $isCampScoped ? 'Perlu dipenuhi' : 'Perlu order' }}</span><strong>{{ $criticalCount }}</strong></div>@endif
                            @if (! $canViewRequests && ! $canViewPurchaseOrders && ! $canViewStock)<div><span>Pekerjaan aktif</span><strong>{{ $priorityTasks->sum('count') }}</strong></div>@endif
                        </div>
                    </section>

                    <div class="dashboard-period-bar">
                        <div class="dashboard-period-copy">
                            <strong>Ringkasan Aktivitas</strong>
                            <span>{{ $dashboardPeriodRange }}</span>
                        </div>
                        <nav class="dashboard-period-options" aria-label="Periode dashboard">
                            @foreach ($dashboardPeriodLabels as $periodValue => $periodLabel)
                                <a class="{{ $dashboardPeriod === $periodValue ? 'active' : '' }}"
                                   href="{{ route('dashboard', array_merge($companyQuery, ['period' => $periodValue])) }}"
                                   @if ($dashboardPeriod === $periodValue) aria-current="page" @endif>{{ $periodLabel }}</a>
                            @endforeach
                        </nav>
                    </div>

                    <div class="kpi-grid">
                        @if ($canViewRequests)
                            <div class="kpi-card">
                                <div class="kpi-label"><span>OR dibuat</span><span class="kpi-icon">OR</span></div>
                                <strong>{{ $periodPurchaseRequests->count() }}</strong>
                                <small>
                                    {{ $dashboardPeriodLabel }}
                                    @if ($canViewCreditNotes)
                                        - {{ $periodCreditNotes->count() }} credit note dibuat
                                    @endif
                                </small>
                            </div>
                        @endif
                        <div class="kpi-card"><div class="kpi-label"><span>Worklist prioritas</span><span class="kpi-icon">WK</span></div><strong>{{ $priorityTasks->sum('count') }}</strong><small>Approval, vendor, PO, penerimaan, dan opname</small></div>
                        @if ($canViewPurchaseOrders)<div class="kpi-card"><div class="kpi-label"><span>PO berjalan</span><span class="kpi-icon">PO</span></div><strong>{{ $activePo->count() }}</strong><small>{{ $purchaseOrders->where('status', 'on_progress')->count() }} on progress, {{ $receivingPo->count() }} submitted/terima</small></div>@endif
                        @if ($canViewStock)<div class="kpi-card"><div class="kpi-label"><span>{{ $isCampScoped ? 'Perlu dipenuhi' : 'Perlu order' }}</span><span class="kpi-icon">ST</span></div><strong>{{ $criticalCount }}</strong><small>{{ $safeCount }} aman dari {{ $inventoryRows->count() }} barang{{ $isCampScoped ? ' di site' : ' terpantau' }}</small></div>@endif
                        @if ($canSeePrices && $canViewPurchaseOrders)
                            <div class="kpi-card"><div class="kpi-label"><span>Nilai PO aktif</span><span class="kpi-icon">Rp</span></div><strong>Rp{{ number_format($openPoValue, 0, ',', '.') }}</strong><small>{{ $dashboardPeriodLabel }} Rp{{ number_format($periodPoValue, 0, ',', '.') }}</small></div>
                        @elseif ($canViewUsage)
                            <div class="kpi-card"><div class="kpi-label"><span>Pemakaian</span><span class="kpi-icon">US</span></div><strong>{{ number_format($periodUsageQty, 0, ',', '.') }}</strong><small>Qty tercatat - {{ $dashboardPeriodLabel }}</small></div>
                        @endif
                    </div>

                    @if ($pipeline->isNotEmpty())
                    <section>
                        <div class="chart-title">
                            <h2 style="margin:0">Pipeline Proses Bisnis</h2>
                            <span class="pill">{{ $purchaseRequests->count() }} order request</span>
                        </div>
                        <div class="pipeline-grid" style="margin-top:12px">
                            @foreach ($pipeline as $step)
                                <div class="pipeline-step">
                                    <span>{{ $step['label'] }}</span>
                                    <strong>{{ $step['count'] }}</strong>
                                </div>
                            @endforeach
                        </div>
                    </section>
                    @endif

                    <div class="business-grid">
                        <section>
                            <div class="chart-title">
                                <h2 style="margin:0">Prioritas Hari Ini</h2>
                                <span class="pill {{ $priorityTasks->isEmpty() ? 'ok' : 'wait' }}">{{ $priorityTasks->isEmpty() ? 'Terkendali' : $priorityTasks->sum('count').' item' }}</span>
                            </div>
                            <div class="priority-list" style="margin-top:12px">
                                @forelse ($priorityTasks as $task)
                                    <div class="priority-card">
                                        <div class="priority-icon">{{ $task['code'] }}</div>
                                        <div>
                                            <strong>{{ $task['label'] }}</strong>
                                            <span>{{ $task['desc'] }}</span>
                                        </div>
                                        <a class="button ghost" href="{{ $task['route'] }}">{{ $task['count'] }} item</a>
                                    </div>
                                @empty
                                    <div class="empty">Tidak ada pekerjaan mendesak saat ini.</div>
                                @endforelse
                            </div>
                        </section>

                        <section>
                            <div class="chart-title">
                                <h2 style="margin:0">Insight Operasional</h2>
                                <span class="pill">Live data</span>
                            </div>
                            <div class="insight-list" style="margin-top:12px">
                                @if ($canViewStock)<div class="insight"><strong>{{ $criticalCount > 0 ? ($isCampScoped ? 'Ada stok site yang perlu dipenuhi' : 'Ada rekomendasi pembelian') : 'Kondisi stok relatif aman' }}</strong><span>{{ $criticalCount }} item mencapai ROP/minimum, {{ $coveredPoCount }} tercakup PO, dan {{ $inTransitCount }} dalam perjalanan.</span></div>@endif
                                @if ($canViewReceipts)<div class="insight"><strong>{{ $receivingPo->count() }} PO menunggu penerimaan</strong><span>Nomor faktur dicatat saat barang diterima gudang.</span></div>@endif
                                @if ($canViewDispatches)<div class="insight"><strong>{{ $dispatches->where('status', 'sent')->count() }} pengiriman belum diterima site</strong></div>@endif
                                @if ($currentUser->hasPermission('stock_opname.manage') && $pendingToleranceReviews->isNotEmpty())<div class="insight"><strong>{{ $pendingToleranceReviews->count() }} selisih opname perlu diperiksa</strong></div>@endif
                                @if (! $canViewStock && ! $canViewReceipts && ! $canViewDispatches && $pendingToleranceReviews->isEmpty())<div class="empty">Tidak ada insight untuk ditampilkan.</div>@endif
                            </div>
                        </section>
                    </div>

                    <div class="chart-grid">
                        @if ($canViewRequests)
                        <section class="chart-card">
                            <div class="chart-title">
                                <strong>Order Request per Status</strong>
                                <span class="pill">{{ $purchaseRequests->count() }} total</span>
                            </div>
                            <div class="bar-list">
                                @forelse ($requestStatusChart as $statusName => $total)
                                    <div class="bar-row">
                                        <span>{{ str_replace('_', ' ', $statusName) }}</span>
                                        <div class="bar-track"><div class="bar-fill" style="width: {{ max(6, ($total / $maxRequestStatus) * 100) }}%"></div></div>
                                        <strong>{{ $total }}</strong>
                                    </div>
                                @empty
                                    <div class="empty">Belum ada data order request.</div>
                                @endforelse
                            </div>
                        </section>
                        @endif

                        @if ($canViewStock)
                        <section class="chart-card">
                            <div class="chart-title">
                                <strong>Kesehatan Stok</strong>
                                <span class="pill {{ $criticalCount > 0 ? 'wait' : 'ok' }}">{{ $criticalCount }} perlu tindakan</span>
                            </div>
                            <div class="pie-wrap">
                                <div class="pie-chart" style="--critical-angle: {{ $criticalAngle }}deg"><strong>{{ $inventoryRows->count() }}</strong></div>
                                <div class="legend">
                                    <span><i style="background: var(--accent)"></i>Aman {{ $safeCount }}</span>
                                    <span><i style="background: var(--danger)"></i>Perlu tindakan {{ $criticalCount }}</span>
                                </div>
                            </div>
                        </section>
                        @endif

                        @if ($canViewUsage)
                        <section class="chart-card">
                            <div class="chart-title">
                                <strong>Trend Pemakaian - {{ $dashboardPeriodLabel }}</strong>
                                <span class="pill">{{ number_format($usageTrend->sum('total'), 0, ',', '.') }} qty</span>
                            </div>
                            <div class="trend-bars" style="grid-template-columns: repeat({{ max(1, $usageTrend->count()) }}, minmax(0, 1fr))">
                                @foreach ($usageTrend as $point)
                                    <div class="trend-bar">
                                        <span style="height: {{ max(8, ($point['total'] / $maxUsageTrend) * 128) }}px"></span>
                                        <small>{{ $point['label'] }}</small>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                        @endif
                    </div>

                    @if ($canViewRequests || $canViewPurchaseOrders)
                    <div class="grid-2">
                        @if ($canViewRequests)
                        <section>
                            <h2>Order Request Terbaru</h2>
                            <table class="mini-table">
                                <thead><tr><th>No</th><th>Site</th><th>Status</th>@if ($canSeePrices)<th>Total</th>@endif</tr></thead>
                                <tbody>
                                @forelse ($purchaseRequests->take(7) as $pr)
                                    <tr>
                                        <td>
                                            <a class="item-history-link" href="{{ route('purchase-requests.review', array_merge(['purchaseRequest' => $pr, 'from' => 'dashboard'], $companyQuery)) }}">{{ $pr->number }}</a>
                                        </td>
                                        <td>{{ $pr->camp?->name ?? '-' }}</td>
                                        <td><span class="pill {{ in_array($pr->status, ['rejected'], true) ? 'bad' : (in_array($pr->status, ['goods_received', 'po_issued'], true) ? 'ok' : 'wait') }}">{{ $workflowStatusLabel($pr->status) }}</span></td>
                                        @if ($canSeePrices)<td class="price">Rp{{ number_format(max($pr->final_total, $pr->estimated_total), 0, ',', '.') }}</td>@endif
                                    </tr>
                                @empty
                                    <tr><td colspan="{{ $canSeePrices ? 4 : 3 }}">Belum ada order request.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </section>
                        @endif
                        @if ($canViewPurchaseOrders)
                        <section>
                            <h2>PO Aktif</h2>
                            <table class="mini-table">
                                <thead><tr><th>No PO</th><th>Vendor</th><th>Status</th>@if ($canSeePrices)<th>Nilai</th>@endif</tr></thead>
                                <tbody>
                                @forelse ($activePo->take(7) as $po)
                                    <tr>
                                        <td>
                                            <a class="item-history-link" href="{{ route('purchase-orders.review', array_merge(['purchaseOrder' => $po, 'from' => 'dashboard'], $companyQuery)) }}">{{ $po->number }}</a>
                                        </td>
                                        <td>{{ $po->vendor?->name ?? '-' }}</td>
                                        <td><span class="pill {{ in_array($po->status, ['submitted', 'issued', 'received'], true) ? 'ok' : 'wait' }}">{{ $purchaseOrderStatusLabel($po->status) }}</span></td>
                                        @if ($canSeePrices)
                                            <td class="price">
                                                <strong>{{ \App\Support\Currency::format($po->grand_total, $po->currency_code) }}</strong>
                                                @if ($po->currency_code !== 'IDR')
                                                    <div class="muted">Setara {{ \App\Support\Currency::format($po->grandTotalIdr()) }}</div>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr><td colspan="{{ $canSeePrices ? 4 : 3 }}">Belum ada PO aktif.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </section>
                        @endif
                    </div>
                    @endif

                    @if ($canViewStock)
                    <section class="inventory-reminder">
                        <div class="chart-title">
                            <div>
                                <h2>{{ $isCampScoped ? 'Pengingat Persediaan Site' : 'Pengingat Pembelian' }}</h2>
                                <p>{{ $isCampScoped ? 'Pemantauan stok pada site yang dapat Anda akses.' : 'Berdasarkan stok gudang, seluruh camp, perjalanan, dan sisa PO aktif.' }}</p>
                            </div>
                            <a class="button ghost" href="{{ $stockReminderRoute }}">Lihat Detail Stok</a>
                        </div>
                        <div class="inventory-reminder-summary">
                            <div><span>{{ $isCampScoped ? 'Perlu dipenuhi' : 'Perlu order' }}</span><strong>{{ $criticalCount }}</strong></div>
                            <div><span>Tercakup PO</span><strong>{{ $coveredPoCount }}</strong></div>
                            <div><span>Dalam perjalanan</span><strong>{{ $inTransitCount }}</strong></div>
                            <div><span>Parameter belum lengkap</span><strong>{{ $incompleteParameterCount }}</strong></div>
                        </div>
                        <div class="dashboard-table-wrap">
                            <table class="mini-table inventory-reminder-table">
                                <thead>
                                    <tr>
                                        <th>Part Number</th>
                                        <th>Barang</th>
                                        <th>{{ $isCampScoped ? 'Stok Site' : 'Posisi Stok' }}</th>
                                        <th>ROP / Min</th>
                                        <th>EOQ</th>
                                        <th>Rekomendasi</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @forelse ($inventoryReminderRows as $row)
                                    @php
                                        $item = $row->item;
                                        $statusMeta = match ($row->status) {
                                            'needs_order' => ['Perlu Order', 'bad'],
                                            'needs_distribution' => ['Perlu Dipenuhi', 'bad'],
                                            'covered_po' => ['Tercakup PO Aktif', 'ok'],
                                            'in_transit' => ['Dalam Perjalanan', 'wait'],
                                            default => ['Aman', 'ok'],
                                        };
                                        $itemDetailUrl = $currentUser->hasPermission('stock.view')
                                            ? route('stock.items.review', $item)
                                            : ($currentUser->hasPermission('stock_opname.view')
                                                ? route('stock-opname.items.history', ['inventoryItem' => $item, 'from' => 'dashboard'])
                                                : null);
                                    @endphp
                                    <tr>
                                        <td>{{ $item->sku }}</td>
                                        <td>
                                            @if ($itemDetailUrl)
                                                <a class="item-history-link" href="{{ $itemDetailUrl }}">{{ $item->name }}</a>
                                            @else
                                                {{ $item->name }}
                                            @endif
                                        </td>
                                        <td class="stock-number"><strong>{{ number_format($row->position, 0, ',', '.') }}</strong> {{ $item->uom }}</td>
                                        <td class="stock-number">{{ number_format($row->minimum, 0, ',', '.') }} {{ $item->uom }}</td>
                                        <td class="stock-number">{{ $row->eoq !== null && $row->eoq > 0 ? number_format($row->eoq, 0, ',', '.').' '.$item->uom : '-' }}</td>
                                        <td class="stock-number">{{ $row->recommendation > 0 ? number_format($row->recommendation, 0, ',', '.').' '.$item->uom : '-' }}</td>
                                        <td>
                                            <div class="inventory-statuses">
                                                <span class="pill {{ $statusMeta[1] }}">{{ $statusMeta[0] }}</span>
                                                @if ($row->parameter_incomplete)<span class="pill wait">Parameter EOQ/ROP belum lengkap</span>@endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7">Tidak ada persediaan yang memerlukan tindakan.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>
                    @endif

                    @if ($canViewUsage)
                        <section>
                            <div class="chart-title">
                                <h2 style="margin:0">Pemakaian Tertinggi</h2>
                                <span class="pill">{{ $dashboardPeriodLabel }}</span>
                            </div>
                            <table class="mini-table">
                                <thead><tr><th>Barang</th><th>Total</th><th>Satuan</th></tr></thead>
                                <tbody>
                                @forelse ($topUsageItems as $usage)
                                    <tr>
                                        <td>{{ $usage['item']?->name ?? '-' }}</td>
                                        <td><span class="pill">{{ number_format($usage['total'], 0, ',', '.') }}</span></td>
                                        <td>{{ $usage['item']?->uom ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3">Belum ada pemakaian tercatat.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </section>
                    @endif
                </div>
            @endif
