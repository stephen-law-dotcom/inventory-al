            @if ($activeMenu === 'purchase-orders')
                @php
                    $purchaseOrderHistoryStatuses = ['received', 'rejected'];
                    $purchaseOrderRows = match ($workflowTab) {
                        'history' => $purchaseOrders->whereIn('status', $purchaseOrderHistoryStatuses),
                        'approval' => $purchaseOrders->filter(fn ($po) => $po->status === 'draft' && $po->proposed_by && (! $po->checked_by || ! $po->approved_by)),
                        default => $purchaseOrders->whereNotIn('status', $purchaseOrderHistoryStatuses),
                    };
                    $purchaseOrderReviewQuery = array_filter([
                        'from' => $workflowTab,
                        'company_id' => request()->integer('company_id') ?: null,
                    ]);
                @endphp
                @if ($workflowTab === 'active' && $currentUser->hasPermission('purchase_orders.manage'))
                    <section>
                        <div class="row">
                            <div>
                                <h2 style="margin-bottom:4px">Pembuatan Purchase Order</h2>
                            </div>
                            <div class="actions">
                                <span class="pill ok">{{ $purchaseRequests->sum(fn ($pr) => $pr->items->where('status', 'vendor_selected')->filter(fn ($item) => ! $item->purchaseOrderItem)->count()) }} item siap PO</span>
                                <a class="button" href="{{ route('purchase-orders.create', $companyQuery) }}">+ Tambah PO</a>
                            </div>
                        </div>
                    </section>
                @endif

                <section>
                    <h2>{{ $workflowTab === 'history' ? 'History Purchase Order' : ($workflowTab === 'approval' ? 'Approval Purchase Order' : 'Purchase Order') }}</h2>
                    @include('inventory.components.transaction-filter')
                    <table class="purchase-order-table">
                        <thead>
                        <tr>
                            <th>Purchase Order</th>
                            <th>Vendor</th>
                            <th>Tujuan</th>
                            <th>Barang</th>
                            @if ($canSeePrices)<th>Nilai</th>@endif
                            <th>Status</th>
                            <th>Approval</th>
                            <th>Aksi</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($purchaseOrderRows as $purchaseOrder)
                            @php
                                $reviewUrl = route('purchase-orders.review', array_merge(['purchaseOrder' => $purchaseOrder], $purchaseOrderReviewQuery));
                                $firstItems = $purchaseOrder->items->take(2);
                                $remainingItems = max(0, $purchaseOrder->items->count() - $firstItems->count());
                                $purchaseOrderDisplayStatus = match (true) {
                                    $purchaseOrder->status !== 'draft' => $purchaseOrderStatusLabel($purchaseOrder->status),
                                    ! $purchaseOrder->proposed_by && (bool) $purchaseOrder->rejected_at => 'Dikembalikan untuk revisi',
                                    ! $purchaseOrder->proposed_by => 'Draft',
                                    ! $purchaseOrder->checked_by => 'Menunggu Kepala Purchasing',
                                    ! $purchaseOrder->approved_by => 'Menunggu Direktur',
                                    default => 'Disetujui Direktur',
                                };
                            @endphp
                            <tr class="clickable-row" data-href="{{ $reviewUrl }}" title="Klik untuk membuka detail {{ $purchaseOrder->number }}">
                                <td>
                                    <strong>{{ $purchaseOrder->number }}</strong>
                                    <div class="muted">OR {{ $purchaseOrder->purchaseRequest->number }}</div>
                                    <div class="muted">{{ $purchaseOrder->ordered_at?->format('d M Y') ?: '-' }} · Estimasi {{ $purchaseOrder->expected_at?->format('d M Y') ?: '-' }}</div>
                                </td>
                                <td>
                                    <strong>{{ $purchaseOrder->vendor->name }}</strong>
                                    <div class="muted">{{ $purchaseOrder->delivery_destination === 'direct_site' ? 'Langsung ke site' : 'Melalui gudang kantor' }}</div>
                                </td>
                                <td>
                                    <strong>{{ $purchaseOrder->purchaseRequest->camp->name }}</strong>
                                    <div class="muted">{{ $purchaseOrder->purchaseRequest->camp->company_name }}</div>
                                </td>
                                <td>
                                    @foreach ($firstItems as $purchaseOrderItem)
                                        <div class="po-item-summary">
                                            <strong>{{ $purchaseOrderItem->inventoryItem?->sku }} · {{ $purchaseOrderItem->description }}</strong>
                                            <span>{{ number_format((float) $purchaseOrderItem->quantity_ordered, 0, ',', '.') }} {{ $purchaseOrderItem->uom }}</span>
                                        </div>
                                    @endforeach
                                    @if ($remainingItems > 0)<span class="muted">+{{ $remainingItems }} barang lainnya</span>@endif
                                </td>
                                @if ($canSeePrices)
                                    <td>
                                        <strong>{{ \App\Support\Currency::format($purchaseOrder->grand_total, $purchaseOrder->currency_code) }}</strong>
                                        @if ($purchaseOrder->currency_code !== 'IDR')
                                            <div class="muted">Setara {{ \App\Support\Currency::format($purchaseOrder->grandTotalIdr()) }}</div>
                                        @endif
                                    </td>
                                @endif
                                <td>
                                    <span class="pill {{ $purchaseOrder->status === 'rejected' ? 'bad' : (in_array($purchaseOrder->status, ['received', 'submitted', 'issued'], true) || $purchaseOrder->approved_by ? 'ok' : 'wait') }}">{{ $purchaseOrderDisplayStatus }}</span>
                                    @if ($purchaseOrder->status === 'draft' && ! $purchaseOrder->proposed_by)
                                        <div class="muted">{{ $purchaseOrder->rejected_at ? $purchaseOrder->rejection_note : 'Belum diajukan approval' }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="po-approval-list">
                                        <span class="approval-state {{ $purchaseOrder->proposed_by ? 'done' : 'waiting' }}">Admin Purchasing · {{ $purchaseOrder->proposed_by ? 'Diajukan' : 'Belum' }}</span>
                                        <span class="approval-state {{ $purchaseOrder->checked_by ? 'done' : 'waiting' }}">Kepala Purchasing · {{ $purchaseOrder->checked_by ? 'Diperiksa' : 'Menunggu' }}</span>
                                        <span class="approval-state {{ $purchaseOrder->approved_by ? 'done' : 'waiting' }}">Direktur · {{ $purchaseOrder->approved_by ? 'Disetujui' : 'Menunggu' }}</span>
                                    </div>
                                </td>
                                <td>
                                    <details class="action-menu">
                                        <summary>Aksi</summary>
                                        <div class="menu">
                                            <a class="button" href="{{ $reviewUrl }}">{{ $workflowTab === 'approval' ? 'Review Approval' : 'Detail' }}</a>
                                            @if ($purchaseOrder->status === 'draft' && ! $purchaseOrder->proposed_by && $currentUser->hasPermission('purchase_orders.manage'))
                                                <a class="button ghost" href="{{ route('purchase-orders.edit', array_merge(['purchaseOrder' => $purchaseOrder], $purchaseOrderReviewQuery)) }}">Edit Draft</a>
                                            @endif
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $canSeePrices ? 8 : 7 }}">{{ $workflowTab === 'history' ? 'Belum ada history purchase order.' : ($workflowTab === 'approval' ? 'Belum ada purchase order yang menunggu approval.' : 'Belum ada purchase order aktif.') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                    @include('inventory.components.transaction-pagination')
                </section>
            @endif

