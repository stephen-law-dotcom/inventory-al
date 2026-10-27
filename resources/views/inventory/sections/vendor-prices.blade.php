            @if ($activeMenu === 'vendor-prices')
                @php
                    $vendorActiveStatuses = ['ready_for_quotation', 'vendor_selected', 'waiting_director'];
                    $vendorHistoryStatuses = ['po_created', 'po_issued', 'partial_received', 'goods_received', 'rejected'];
                    $vendorPriceRequests = $purchaseRequests
                        ->map(function ($pr) use ($workflowTab, $vendorActiveStatuses, $vendorHistoryStatuses) {
                            $isHistoryItem = fn ($item) => in_array($item->status, $vendorHistoryStatuses, true)
                                || in_array($item->procurement_approval_status, ['approved', 'rejected'], true)
                                || $item->purchaseOrderItems->isNotEmpty();
                            $isApprovalItem = fn ($item) => ! $isHistoryItem($item)
                                && in_array($item->procurement_approval_status, ['pending_equipment', 'pending_director'], true);
                            $filteredItems = match ($workflowTab) {
                                'history' => $pr->items->filter($isHistoryItem),
                                'approval' => $pr->items->filter($isApprovalItem),
                                default => $pr->items->filter(fn ($item) => in_array($item->status, $vendorActiveStatuses, true)
                                    && ! $isHistoryItem($item)
                                    && ! $isApprovalItem($item)
                                    && in_array($item->procurement_approval_status, ['draft', 'not_required'], true)),
                            };

                            return ['pr' => $pr, 'items' => $filteredItems];
                        })
                        ->filter(fn ($row) => $row['items']->isNotEmpty())
                        ->values();
                @endphp
                @php
                    $vendorReviewQuery = array_filter([
                        'from' => $workflowTab,
                        'company_id' => request()->integer('company_id') ?: null,
                    ]);
                @endphp
                <section>
                    <h2>{{ $workflowTab === 'history' ? 'History Harga Vendor' : ($workflowTab === 'approval' ? 'Approval Pengadaan' : 'Harga Vendor') }}</h2>
                    @include('inventory.components.transaction-filter')
                    <div class="stack">
                        @forelse ($vendorPriceRequests as $vendorRow)
                            @php
                                $pr = $vendorRow['pr'];
                                $vendorItems = $vendorRow['items'];
                                $visibleFinalTotal = $vendorItems->sum('final_total');
                                $rejectedItemCount = $vendorItems->where('procurement_approval_status', 'rejected')->count();
                                $cardStatus = match ($workflowTab) {
                                    'history' => $rejectedItemCount === $vendorItems->count()
                                        ? $rejectedItemCount.' item ditolak'
                                        : $vendorItems->count().' item selesai',
                                    'approval' => $vendorItems->count().' item menunggu approval',
                                    default => $vendorItems->count().' item perlu diproses',
                                };
                                $cardStatusClass = $workflowTab === 'history' && $rejectedItemCount === $vendorItems->count() ? 'bad' : ($workflowTab === 'history' ? 'ok' : 'wait');
                            @endphp
                                <article class="vendor-price-card">
                                    <div class="row">
                                        <div>
                                            <h3>{{ $pr->number }} - {{ $pr->camp->name }}</h3>
                                            <p>{{ $pr->requested_at->format('d M Y') }} oleh {{ $pr->requested_by }}{{ $pr->purpose ? ' - '.$pr->purpose : '' }}</p>
                                        </div>
                                        <div class="actions">
                                            <span class="pill {{ $cardStatusClass }}">{{ $cardStatus }}</span>
                                            @if ($canSeePrices && $visibleFinalTotal > 0)
                                                <span class="pill ok">Final Rp{{ number_format($visibleFinalTotal, 0, ',', '.') }}</span>
                                            @endif
                                        </div>
                                    </div>

                                    <table class="vendor-price-table">
                                        <thead><tr><th>Unit</th><th>Barang</th><th>Qty</th><th>Pembanding</th><th>{{ $workflowTab === 'approval' ? 'Approval Pengadaan' : 'Rekomendasi' }}</th><th>Aksi</th></tr></thead>
                                        <tbody>
                                        @foreach ($vendorItems as $requestItem)
                                            @php
                                                $sourceCreditNote = $requestItem->sourceCreditNoteItem?->creditNote;
                                                $selectedQuotations = $requestItem->selectedQuotations;
                                                $recommendedQuotation = $requestItem->quotations
                                                    ->where('available_quantity', '>=', $requestItem->quantity)
                                                    ->sortBy([['unit_price', 'asc'], ['available_quantity', 'desc'], ['lead_time_days', 'asc']])
                                                    ->first();
                                                $quotationCount = $requestItem->quotations->count();
                                            @endphp
                                            <tr class="clickable-row" data-href="{{ route('vendor-prices.review', array_merge(['purchaseRequestItem' => $requestItem], $vendorReviewQuery)) }}" title="Klik untuk membuka detail harga vendor {{ $requestItem->inventoryItem->name }}">
                                                <td>{{ $requestItem->unit?->code }} - {{ $requestItem->unit?->name }}</td>
                                                <td>
                                                    {{ $requestItem->inventoryItem->name }}
                                                    @if ($sourceCreditNote)
                                                        <div style="margin-top:5px">
                                                            <span class="pill">Pengajuan Ulang</span>
                                                            @if ($currentUser->hasPermission('credit_notes.view'))
                                                                <a class="muted" href="{{ route('credit-notes.review', $sourceCreditNote) }}" onclick="event.stopPropagation()">CN {{ $sourceCreditNote->number }}</a>
                                                            @else
                                                                <span class="muted">CN {{ $sourceCreditNote->number }}</span>
                                                            @endif
                                                        </div>
                                                    @endif
                                                    <span class="muted">SKU {{ $requestItem->inventoryItem->sku }}{{ $requestItem->specification ? ' - '.$requestItem->specification : '' }}</span>
                                                </td>
                                                <td>{{ $requestItem->quantity }} {{ $requestItem->uom ?? $requestItem->inventoryItem->uom }}</td>
                                                <td>
                                                    <span class="pill {{ $quotationCount > 0 ? 'ok' : 'wait' }}">{{ $quotationCount }} penawaran</span>
                                                </td>
                                                <td>
                                                    @if ($selectedQuotations->isNotEmpty())
                                                        @foreach ($selectedQuotations as $selectedQuotation)
                                                            <div>
                                                                <span class="pill ok">{{ $selectedQuotation->vendor->name }} - {{ $selectedQuotation->selected_quantity }} {{ $requestItem->uom ?? $requestItem->inventoryItem->uom }}</span>
                                                                <div class="muted">{{ $selectedQuotation->brand_name ?: 'Merek belum diisi' }} - stok {{ $selectedQuotation->available_quantity }}</div>
                                                                @if ($canSeePrices)
                                                                    <div class="price">Rp{{ number_format($selectedQuotation->unit_price * $selectedQuotation->selected_quantity, 0, ',', '.') }}</div>
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                    @elseif ($recommendedQuotation)
                                                        <div>
                                                            <span class="pill wait">Rekomendasi {{ $recommendedQuotation->vendor->name }}</span>
                                                            <div class="muted">Belum dipilih final</div>
                                                        </div>
                                                    @else
                                                        <span class="pill wait">Belum ada rekomendasi</span>
                                                    @endif
                                                    @if ($requestItem->status === 'waiting_director')
                                                        <div><span class="pill wait">Menunggu direktur</span></div>
                                                    @endif
                                                    @if ($requestItem->procurement_approval_status === 'rejected')
                                                        <div><span class="pill bad">Ditolak</span></div>
                                                        @if ($requestItem->procurement_rejection_note)
                                                            <div class="muted">{{ $requestItem->procurement_rejection_note }}</div>
                                                        @endif
                                                    @endif
                                                    @if ($workflowTab === 'approval')
                                                        <div class="actions" style="margin-top:6px">
                                                            <span class="pill {{ $requestItem->procurement_approval_status === 'pending_equipment' ? 'wait' : 'ok' }}">Peralatan {{ $requestItem->procurement_equipment_checked_at ? 'Disetujui' : 'Menunggu' }}</span>
                                                            <span class="pill {{ $requestItem->procurement_approval_status === 'pending_director' ? 'wait' : ($requestItem->procurement_approval_status === 'approved' ? 'ok' : '') }}">Direktur {{ $requestItem->procurement_director_approved_at ? 'Disetujui' : 'Menunggu' }}</span>
                                                        </div>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($workflowTab === 'approval')
                                                        <a class="button" href="{{ route('vendor-prices.review', array_merge(['purchaseRequestItem' => $requestItem], $vendorReviewQuery)) }}">Review</a>
                                                    @elseif ($workflowTab === 'active' && $currentUser->hasPermission('vendor_prices.manage'))
                                                        <a class="button" href="{{ route('vendor-prices.review', array_merge(['purchaseRequestItem' => $requestItem], $vendorReviewQuery)) }}">Input</a>
                                                    @elseif ($workflowTab === 'history')
                                                        <a class="button ghost" href="{{ route('vendor-prices.review', array_merge(['purchaseRequestItem' => $requestItem], $vendorReviewQuery)) }}">Detail</a>
                                                    @else
                                                        <a class="button ghost" href="{{ route('vendor-prices.review', array_merge(['purchaseRequestItem' => $requestItem], $vendorReviewQuery)) }}">Detail</a>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </article>
                        @empty
                            <div class="empty">{{ $workflowTab === 'history' ? 'Belum ada history harga vendor.' : ($workflowTab === 'approval' ? 'Belum ada approval pengadaan.' : 'Belum ada detail barang yang siap input harga vendor.') }}</div>
                        @endforelse
                    </div>
                    @include('inventory.components.transaction-pagination')
                </section>
            @endif

