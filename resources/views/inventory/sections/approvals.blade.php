            @if ($activeMenu === 'approvals')
                @php
                    $approvalRows = $purchaseRequests->map(function ($pr) use ($currentUser) {
                        $readyCount = $pr->items->filter(fn ($requestItem) =>
                            ($currentUser->canApproveLogistic() && $requestItem->status === 'submitted' && $requestItem->logistic_approval_status === 'pending')
                            || ($currentUser->canApproveChiefMechanic() && $requestItem->status === 'logistic_checked' && $requestItem->chief_mechanic_approval_status === 'pending')
                            || ($currentUser->canApproveManagerCamp() && $requestItem->status === 'waiting_manager_camp' && $requestItem->manager_camp_approval_status === 'pending')
                        )->count();

                        return [
                            'pr' => $pr,
                            'ready_count' => $readyCount,
                            'rejected_count' => $pr->items->where('status', 'rejected')->count(),
                            'done_count' => $pr->items->whereIn('status', ['ready_for_quotation', 'vendor_selected', 'po_created', 'po_issued', 'goods_received'])->count(),
                        ];
                    })->filter(fn ($row) => $row['ready_count'] > 0)->values();
                    $procurementApprovalItems = $purchaseRequests->flatMap->items->filter(fn ($item) =>
                        ($currentUser->canApproveEquipment() && $item->procurement_approval_status === 'pending_equipment')
                        || ($currentUser->canApproveDirector() && $item->procurement_approval_status === 'pending_director')
                    );
                    $poApprovalRows = $purchaseOrders->filter(fn ($po) =>
                        $po->status === 'draft'
                        && $po->proposed_by
                        && (
                            ($currentUser->hasPermission('purchase_orders.check') && ! $po->checked_by)
                            || ($currentUser->hasPermission('purchase_orders.ppic_review') && $po->checked_by && ! $po->ppic_reviewed_by)
                            || ($currentUser->hasPermission('purchase_orders.approve')
                                && strtolower((string) $currentUser->email) === strtolower((string) config('procurement.main_director_email'))
                                && $po->requires_main_director_approval
                                && $po->ppic_reviewed_by
                                && ! $po->approved_by)
                        )
                    );
                    $approvalWorkCount = $approvalRows->sum('ready_count');
                    $procurementWorkCount = $procurementApprovalItems->count();
                    $poWorkCount = $poApprovalRows->count();
                    $toleranceWorkCount = ($pendingToleranceReviews ?? collect())->count() + ($readyStockOpnames ?? collect())->count();
                    $totalApprovalWork = $approvalWorkCount + $procurementWorkCount + $poWorkCount + $toleranceWorkCount;
                @endphp

                <section>
                    <div class="row">
                        <div>
                            <h2 style="margin-bottom:4px">Approval Saya</h2>
                        </div>
                        <div class="actions">
                            <span class="pill {{ $totalApprovalWork > 0 ? 'wait' : 'ok' }}">{{ $totalApprovalWork }} tugas menunggu</span>
                        </div>
                    </div>
                    <div class="approval-worklist" style="margin-top:14px">
                        <article class="approval-card {{ $approvalWorkCount > 0 ? 'is-wait' : '' }}">
                            <div class="approval-card-head">
                                <strong>Order Request</strong>
                                <span class="count">{{ $approvalWorkCount }}</span>
                            </div>
                            <div class="approval-meta">
                                <span>Logistik, Chief Mechanic, Manager Camp</span>
                                <span>{{ $approvalRows->count() }} dokumen terkait</span>
                            </div>
                            <a class="button ghost" href="{{ route('requests', array_merge($companyQuery, ['tab' => 'approval'])) }}">Buka Approval OR</a>
                        </article>
                        <article class="approval-card {{ $procurementWorkCount > 0 ? 'is-wait' : '' }}">
                            <div class="approval-card-head">
                                <strong>Pengadaan</strong>
                                <span class="count">{{ $procurementWorkCount }}</span>
                            </div>
                            <div class="approval-meta">
                                <span>Peralatan dan Direktur</span>
                                <span>Item dengan vendor final</span>
                            </div>
                            <a class="button ghost" href="{{ route('vendor-prices', array_merge($companyQuery, ['tab' => 'approval'])) }}">Buka Approval Pengadaan</a>
                        </article>
                        <article class="approval-card {{ $poWorkCount > 0 ? 'is-wait' : '' }}">
                            <div class="approval-card-head">
                                <strong>Purchase Order</strong>
                                <span class="count">{{ $poWorkCount }}</span>
                            </div>
                            <div class="approval-meta">
                                <span>Kepala Purchasing, PPIC, dan Direktur Utama</span>
                                <span>PO draft menunggu ttd</span>
                            </div>
                            <a class="button ghost" href="{{ route('purchase-orders', array_merge($companyQuery, ['tab' => 'approval'])) }}">Buka Approval PO</a>
                        </article>
                        <article class="approval-card {{ $toleranceWorkCount > 0 ? 'is-danger' : '' }}">
                            <div class="approval-card-head">
                                <strong>Toleransi Opname</strong>
                                <span class="count">{{ $toleranceWorkCount }}</span>
                            </div>
                            <div class="approval-meta">
                                <span>Selisih hasil hitung fisik</span>
                                <span>Perlu keputusan diterima atau hitung ulang</span>
                            </div>
                            <a class="button ghost" href="#stock-opname-review">Buka Pemeriksaan</a>
                        </article>
                    </div>
                </section>

                <div id="stock-opname-review"></div>
                @if (($pendingToleranceReviews ?? collect())->isNotEmpty())
                    <section>
                        <h2>Pemeriksaan Selisih Stock Opname</h2>
                        <div class="stack">
                            @foreach ($pendingToleranceReviews as $opnameItem)
                                <article>
                                    <div class="row">
                                        <div>
                                            <h3>{{ $opnameItem->inventoryItem->sku }} - {{ $opnameItem->inventoryItem->name }}</h3>
                                            <p>{{ $opnameItem->stockOpname->number }} - {{ $opnameItem->stockOpname->opname_date->format('d M Y') }} - {{ $opnameItem->stockOpname->location ?: 'Gudang' }}</p>
                                        </div>
                                        <div class="actions">
                                            <span class="pill wait">Menunggu pemeriksaan</span>
                                            <span class="pill">Selisih {{ $opnameItem->difference > 0 ? '+' : '' }}{{ $opnameItem->difference }} {{ $opnameItem->inventoryItem->uom }}</span>
                                            <span class="pill ok">Referensi toleransi {{ $opnameItem->tolerance_quantity }} {{ $opnameItem->inventoryItem->uom }}</span>
                                        </div>
                                    </div>
                                    <table style="margin-top:12px">
                                        <thead><tr><th>Stok Sistem</th><th>Stok Fisik</th><th>Toleransi</th><th>Catatan Opname</th><th>Keputusan</th></tr></thead>
                                        <tbody>
                                        <tr>
                                            <td>{{ $opnameItem->system_stock }} {{ $opnameItem->inventoryItem->uom }}</td>
                                            <td>{{ $opnameItem->physical_stock }} {{ $opnameItem->inventoryItem->uom }}</td>
                                            <td>{{ $opnameItem->shrinkage_tolerance_percent }}% / {{ $opnameItem->tolerance_quantity }} {{ $opnameItem->inventoryItem->uom }}</td>
                                            <td>{{ $opnameItem->notes ?: '-' }}</td>
                                            <td>
                                                <div class="actions">
                                                    <form method="post" action="{{ route('stock-opname-items.accept-tolerance', $opnameItem) }}" data-confirm="Terima selisih {{ $opnameItem->inventoryItem->name }}? Stok belum berubah sampai posting final.">
                                                        @csrf
                                                        <input name="tolerance_review_notes" placeholder="Catatan pemeriksaan">
                                                        <button>Terima Selisih</button>
                                                    </form>
                                                    <form method="post" action="{{ route('stock-opname-items.reject-tolerance', $opnameItem) }}" data-confirm="Tolak selisih dan kembalikan dokumen untuk hitung ulang?">
                                                        @csrf
                                                        <input name="tolerance_review_notes" placeholder="Alasan tolak" required>
                                                        <button class="ghost">Tolak & Hitung Ulang</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if (($readyStockOpnames ?? collect())->isNotEmpty())
                    <section>
                        <h2>Stock Opname Siap Posting</h2>
                        <div class="stack">
                            @foreach ($readyStockOpnames as $opname)
                                <article>
                                    <div class="row">
                                        <div>
                                            <h3>{{ $opname->number }} - {{ $opname->location ?: 'Gudang Kantor' }}</h3>
                                            <p>{{ $opname->opname_date->format('d M Y') }} - {{ $opname->items->count() }} barang - Petugas {{ $opname->officers->pluck('name')->implode(', ') }}</p>
                                        </div>
                                        <form method="post" action="{{ route('stock-opnames.post', $opname) }}" data-confirm="Posting final dan sesuaikan stok dengan hasil hitung fisik?">
                                            @csrf
                                            <button>Posting Final</button>
                                        </form>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endif

                <section>
                    <h2>Worklist Order Request</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Order Request</th>
                                <th>Site / Perusahaan</th>
                                <th>Pemohon</th>
                                <th>Item</th>
                                <th>Tahap Approval</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse ($approvalRows as $row)
                            @php
                                $pr = $row['pr'];
                            @endphp
                            <tr class="clickable-row" data-href="{{ route('approvals.review', array_filter([
                                'purchaseRequest' => $pr,
                                'from' => 'approvals',
                                'company_id' => request()->integer('company_id') ?: null,
                            ])) }}" title="Klik untuk review approval {{ $pr->number }}">
                                <td>
                                    <strong>{{ $pr->number }}</strong>
                                    <div class="muted">{{ $pr->requested_at->format('d M Y') }}</div>
                                    <div class="muted">{{ $pr->purpose ?: '-' }}</div>
                                </td>
                                <td>
                                    {{ $pr->camp->name }}
                                    <div class="muted">{{ $pr->camp->company_name }}</div>
                                </td>
                                <td>
                                    {{ $pr->requested_by }}
                                    <div class="muted">{{ $pr->unit?->code ?: 'Multi unit' }}</div>
                                </td>
                                <td>
                                    <span class="pill">{{ $pr->items->count() }} item</span>
                                    @if ($row['ready_count'] > 0)
                                        <span class="pill wait">{{ $row['ready_count'] }} siap</span>
                                    @endif
                                    @if ($row['rejected_count'] > 0)
                                        <span class="pill bad">{{ $row['rejected_count'] }} reject</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="actions">
                                        <span class="pill {{ $pr->logistic_approval_status === 'approved' ? 'ok' : 'wait' }}">Logistik {{ $pr->logistic_approval_status }}</span>
                                        <span class="pill {{ $pr->chief_mechanic_approval_status === 'approved' ? 'ok' : 'wait' }}">Chief Mechanic {{ $pr->chief_mechanic_approval_status }}</span>
                                        <span class="pill {{ $pr->manager_camp_approval_status === 'approved' ? 'ok' : 'wait' }}">Manager Camp {{ $pr->manager_camp_approval_status }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="pill {{ $row['ready_count'] > 0 ? 'wait' : ($pr->status === 'rejected' ? 'bad' : 'ok') }}">{{ $workflowStatusLabel($pr->status) }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6">Tidak ada approval order request yang menunggu Anda.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </section>

                <div class="grid-2">
                    <section>
                        <h2>Worklist Pengadaan</h2>
                        <div class="approval-list">
                            @forelse ($procurementApprovalItems as $requestItem)
                                <a class="approval-list-item" href="{{ route('vendor-prices.review', array_filter([
                                    'purchaseRequestItem' => $requestItem,
                                    'from' => 'approvals',
                                    'company_id' => request()->integer('company_id') ?: null,
                                ])) }}">
                                    <span>
                                        <strong>{{ $requestItem->purchaseRequest->number }} - {{ $requestItem->inventoryItem->name }}</strong>
                                        <span class="muted">{{ $requestItem->purchaseRequest->camp->name }} - {{ $workflowStatusLabel($requestItem->procurement_approval_status) }}</span>
                                    </span>
                                    <span class="pill wait">Review</span>
                                </a>
                            @empty
                                <div class="empty">Tidak ada approval pengadaan yang menunggu Anda.</div>
                            @endforelse
                        </div>
                    </section>

                    <section>
                        <h2>Worklist Purchase Order</h2>
                        <div class="approval-list">
                            @forelse ($poApprovalRows as $purchaseOrder)
                                <a class="approval-list-item" href="{{ route('purchase-orders.review', array_filter([
                                    'purchaseOrder' => $purchaseOrder,
                                    'from' => 'approvals',
                                    'company_id' => request()->integer('company_id') ?: null,
                                ])) }}">
                                    <span>
                                        <strong>{{ $purchaseOrder->number }} - {{ $purchaseOrder->vendor->name }}</strong>
                                        <span class="muted">{{ $purchaseOrder->purchaseRequest->number }} - {{ $purchaseOrder->purchaseRequest->camp->name }}</span>
                                    </span>
                                    <span class="pill wait">{{ ! $purchaseOrder->checked_by ? 'Periksa' : 'Setujui' }}</span>
                                </a>
                            @empty
                                <div class="empty">Tidak ada approval PO yang menunggu Anda.</div>
                            @endforelse
                        </div>
                    </section>
                </div>
            @endif

