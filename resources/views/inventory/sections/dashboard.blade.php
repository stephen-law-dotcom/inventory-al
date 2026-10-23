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
                    $isCampScoped = ! $currentUser->isAdmin() && count($currentUser->scopedCampIds()) > 0;
                    $requestStatusChart = $purchaseRequests->groupBy('status')->map->count();
                    $maxRequestStatus = max(1, $requestStatusChart->max() ?? 1);
                    $dashboardStocks = $isCampScoped
                        ? $campStocks->groupBy('inventory_item_id')->map(function ($stocks) {
                            $item = $stocks->first()->inventoryItem;

                            return (object) ['item' => $item, 'quantity' => (float) $stocks->sum('quantity')];
                        })->values()
                        : $items->map(fn ($item) => (object) ['item' => $item, 'quantity' => (float) $item->stock_on_hand]);
                    $criticalStocks = $dashboardStocks->filter(fn ($stock) => $stock->quantity <= $stock->item->minimumStockLevel());
                    $criticalCount = $criticalStocks->count();
                    $safeCount = max(0, $dashboardStocks->count() - $criticalCount);
                    $criticalAngle = $dashboardStocks->count() > 0 ? round(($criticalCount / $dashboardStocks->count()) * 360) : 0;
                    $usageTrend = collect(range(6, 0))->map(function ($daysAgo) use ($usageLogs) {
                        $date = now()->subDays($daysAgo);

                        return [
                            'label' => $date->format('d M'),
                            'total' => $usageLogs->filter(fn ($log) => $log->used_at->isSameDay($date))->sum('quantity'),
                        ];
                    });
                    $maxUsageTrend = max(1, $usageTrend->max('total') ?? 1);
                    $approvalPendingItems = $purchaseRequests->flatMap->items->filter(fn ($item) => in_array($item->status, ['submitted', 'logistic_checked', 'waiting_manager_camp'], true));
                    $myApprovalItems = $approvalPendingItems->filter(fn ($item) =>
                        ($currentUser->hasPermission('approvals.logistic') && $item->logistic_approval_status === 'pending')
                        || ($currentUser->hasPermission('approvals.chief_mechanic') && $item->chief_mechanic_approval_status === 'pending')
                        || ($currentUser->hasPermission('approvals.manager_camp') && $item->manager_camp_approval_status === 'pending')
                        || ($currentUser->hasPermission('approvals.director') && $item->status === 'waiting_director' && $item->director_approval_status === 'pending')
                    );
                    $vendorPendingItems = $purchaseRequests->flatMap->items->filter(fn ($item) =>
                        $item->purchaseOrderItems->isEmpty()
                        && (
                            in_array($item->status, ['ready_for_quotation', 'waiting_director'], true)
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
                    $openPoValue = $activePo->sum('grand_total');
                    $monthPoValue = $purchaseOrders->filter(fn ($po) => $po->ordered_at && \Illuminate\Support\Carbon::parse($po->ordered_at)->isSameMonth(now()))->sum('grand_total');
                    $monthUsageQty = $usageLogs->filter(fn ($log) => $log->used_at->isSameMonth(now()))->sum('quantity');
                    $criticalItems = $criticalStocks->sortBy('quantity')->take(8);
                    $topUsageItems = $usageLogs
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
                            @if ($canViewStock)<div><span>Stok kritis</span><strong>{{ $criticalCount }}</strong></div>@endif
                            @if (! $canViewRequests && ! $canViewPurchaseOrders && ! $canViewStock)<div><span>Pekerjaan aktif</span><strong>{{ $priorityTasks->sum('count') }}</strong></div>@endif
                        </div>
                    </section>

                    <div class="kpi-grid">
                        @if ($canViewRequests)<div class="kpi-card"><div class="kpi-label"><span>Total OR</span><span class="kpi-icon">OR</span></div><strong>{{ $purchaseRequests->count() }}</strong><small>{{ $purchaseRequests->where('status', 'draft')->count() }} draft, {{ $purchaseRequests->whereNotIn('status', ['draft', 'rejected'])->count() }} berjalan</small></div>@endif
                        <div class="kpi-card"><div class="kpi-label"><span>Worklist prioritas</span><span class="kpi-icon">WK</span></div><strong>{{ $priorityTasks->sum('count') }}</strong><small>Approval, vendor, PO, penerimaan, dan opname</small></div>
                        @if ($canViewPurchaseOrders)<div class="kpi-card"><div class="kpi-label"><span>PO berjalan</span><span class="kpi-icon">PO</span></div><strong>{{ $activePo->count() }}</strong><small>{{ $purchaseOrders->where('status', 'on_progress')->count() }} on progress, {{ $receivingPo->count() }} submitted/terima</small></div>@endif
                        @if ($canViewStock)<div class="kpi-card"><div class="kpi-label"><span>Stok kritis</span><span class="kpi-icon">ST</span></div><strong>{{ $criticalCount }}</strong><small>{{ $safeCount }} item aman dari {{ $dashboardStocks->count() }} barang{{ $isCampScoped ? ' di site' : '' }}</small></div>@endif
                        @if ($canSeePrices && $canViewPurchaseOrders)
                            <div class="kpi-card"><div class="kpi-label"><span>Nilai PO aktif</span><span class="kpi-icon">Rp</span></div><strong>Rp{{ number_format($openPoValue, 0, ',', '.') }}</strong><small>Bulan ini Rp{{ number_format($monthPoValue, 0, ',', '.') }}</small></div>
                        @elseif ($canViewUsage)
                            <div class="kpi-card"><div class="kpi-label"><span>Pemakaian bulan ini</span><span class="kpi-icon">US</span></div><strong>{{ number_format($monthUsageQty, 0, ',', '.') }}</strong><small>Qty pemakaian tercatat bulan ini</small></div>
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
                                @if ($canViewStock)<div class="insight"><strong>{{ $criticalCount > 0 ? 'Ada stok yang perlu segera dicek' : 'Kondisi stok relatif aman' }}</strong><span>{{ $criticalCount }} item berada di titik reorder/minimum.</span></div>@endif
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
                                <span class="pill {{ $criticalCount > 0 ? 'wait' : 'ok' }}">{{ $criticalCount }} kritis</span>
                            </div>
                            <div class="pie-wrap">
                                <div class="pie-chart" style="--critical-angle: {{ $criticalAngle }}deg"><strong>{{ $dashboardStocks->count() }}</strong></div>
                                <div class="legend">
                                    <span><i style="background: var(--accent)"></i>Aman {{ $safeCount }}</span>
                                    <span><i style="background: var(--danger)"></i>Kritis {{ $criticalCount }}</span>
                                </div>
                            </div>
                        </section>
                        @endif

                        @if ($canViewUsage)
                        <section class="chart-card">
                            <div class="chart-title">
                                <strong>Trend Pemakaian 7 Hari</strong>
                                <span class="pill">{{ number_format($usageTrend->sum('total'), 0, ',', '.') }} qty</span>
                            </div>
                            <div class="trend-bars">
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
                                        @if ($canSeePrices)<td class="price">Rp{{ number_format($po->grand_total, 0, ',', '.') }}</td>@endif
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

                    @if ($canViewStock || $canViewUsage)
                    <div class="grid-2">
                        @if ($canViewStock)
                        <section>
                            <h2>Stok Kritis</h2>
                            <table class="mini-table">
                                <thead><tr><th>Part Number</th><th>Barang</th><th>Stok</th><th>ROP/Min</th></tr></thead>
                                <tbody>
                                @forelse ($criticalItems as $stock)
                                    @php($item = $stock->item)
                                    <tr>
                                        <td>{{ $item->sku }}</td>
                                        <td>
                                            @if ($currentUser->hasPermission('stock_opname.view'))
                                                <a class="item-history-link" href="{{ route('stock-opname.items.history', ['inventoryItem' => $item, 'from' => 'dashboard']) }}">{{ $item->name }}</a>
                                            @else
                                                {{ $item->name }}
                                            @endif
                                        </td>
                                        <td><span class="pill bad">{{ number_format($stock->quantity, 0, ',', '.') }} {{ $item->uom }}</span></td>
                                        <td>{{ $item->minimumStockLevel() }} {{ $item->uom }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4">Tidak ada stok kritis.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </section>
                        @endif
                        @if ($canViewUsage)
                        <section>
                            <h2>Pemakaian Tertinggi</h2>
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
                </div>
            @endif
