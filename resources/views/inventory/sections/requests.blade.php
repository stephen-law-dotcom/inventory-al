            @if ($activeMenu === 'requests')
                @php
                    $requestHistoryStatuses = ['goods_received', 'rejected'];
                    $requestApprovalStatuses = ['submitted', 'logistic_checked', 'waiting_manager_camp'];
                    $requestRows = match ($workflowTab) {
                        'history' => $purchaseRequests->whereIn('status', $requestHistoryStatuses),
                        'approval' => $purchaseRequests->filter(fn ($pr) => $pr->items->contains(fn ($item) => in_array($item->status, $requestApprovalStatuses, true))),
                        default => $purchaseRequests->whereNotIn('status', array_merge($requestHistoryStatuses, $requestApprovalStatuses)),
                    };
                    $requestReviewQuery = array_filter([
                        'from' => $workflowTab,
                        'company_id' => request()->integer('company_id') ?: null,
                    ]);
                @endphp
                @if ($workflowTab === 'active' && $currentUser->hasPermission('requests.manage'))
                    <div class="actions">
                        <a class="button" href="{{ route('purchase-requests.create', $companyQuery) }}">+ Tambah Order Request</a>
                    </div>
                @endif

                <section>
                    <h2>{{ $workflowTab === 'history' ? 'History Order Request' : ($workflowTab === 'approval' ? 'Approval Order Request' : 'Order Request') }}</h2>
                    @if ($workflowTab === 'approval')
                    @endif
                    @include('inventory.components.transaction-filter')
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Tanggal</th>
                                <th>Site Pengaju</th>
                                <th>Pemohon</th>
                                <th>Detail</th>
                                <th>Status</th>
                                @if ($workflowTab === 'approval')
                                    <th>Tahap Approval</th>
                                @endif
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse ($requestRows as $pr)
                            <tr class="clickable-row" data-href="{{ $workflowTab === 'approval'
                                ? route('approvals.review', array_merge(['purchaseRequest' => $pr], $requestReviewQuery))
                                : route('purchase-requests.review', array_merge(['purchaseRequest' => $pr], $requestReviewQuery)) }}" title="Klik untuk melihat detail order request">
                                <td>{{ $pr->number }}</td>
                                <td>{{ $pr->requested_at->format('d M Y') }}</td>
                                <td>{{ $pr->camp->name }}</td>
                                <td>{{ $pr->requested_by }}</td>
                                <td>
                                    @foreach($pr->items as $requestItem)
                                        <div>{{ $requestItem->unit?->code }} - {{ $requestItem->inventoryItem->name }} x {{ $requestItem->quantity }} {{ $requestItem->uom ?? $requestItem->inventoryItem->uom }}<br><span class="muted">{{ $requestItem->specification }}</span></div>
                                    @endforeach
                                </td>
                                <td><span class="pill {{ $pr->status === 'draft' ? 'wait' : 'ok' }}">{{ $workflowStatusLabel($pr->status) }}</span></td>
                                @if ($workflowTab === 'approval')
                                    <td>
                                        <div class="actions">
                                            <span class="pill {{ $pr->logistic_approval_status === 'approved' ? 'ok' : 'wait' }}">Logistik {{ $pr->logistic_approval_status }}</span>
                                            <span class="pill {{ $pr->chief_mechanic_approval_status === 'approved' ? 'ok' : 'wait' }}">Chief Mechanic {{ $pr->chief_mechanic_approval_status }}</span>
                                            <span class="pill {{ $pr->manager_camp_approval_status === 'approved' ? 'ok' : 'wait' }}">Manager Camp {{ $pr->manager_camp_approval_status }}</span>
                                        </div>
                                    </td>
                                @endif
                                <td>
                                    @if ($workflowTab === 'approval' || ($pr->status === 'draft' && $currentUser->hasPermission('requests.manage')))
                                        <details class="action-menu">
                                            <summary>Aksi</summary>
                                            <div class="menu">
                                                @if ($workflowTab === 'approval')
                                                    <a class="button" href="{{ route('approvals.review', array_merge(['purchaseRequest' => $pr], $requestReviewQuery)) }}">Review</a>
                                                @else
                                                    <a class="button ghost" href="{{ route('purchase-requests.edit', $pr) }}">Edit</a>
                                                    <form method="post" action="{{ route('purchase-requests.post', $pr) }}" data-confirm="Posting Order Request {{ $pr->number }} sekarang? Dokumen akan masuk ke proses approval dan tidak lagi berstatus draft.">
                                                        @csrf
                                                        <button type="submit">Posting</button>
                                                    </form>
                                                    <form method="post" action="{{ route('purchase-requests.destroy', $pr) }}" data-confirm="Hapus draft order request {{ $pr->number }}?">
                                                        @csrf
                                                        @method('delete')
                                                        <button type="submit" class="ghost danger-action">Hapus</button>
                                                    </form>
                                                @endif
                                            </div>
                                        </details>
                                    @else
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $workflowTab === 'approval' ? 8 : 7 }}">{{ $workflowTab === 'history' ? 'Belum ada history order request.' : ($workflowTab === 'approval' ? 'Belum ada approval order request.' : 'Belum ada order request aktif.') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                    @include('inventory.components.transaction-pagination')
                </section>
            @endif

