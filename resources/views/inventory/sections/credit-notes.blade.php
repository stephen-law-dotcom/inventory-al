            @if ($activeMenu === 'credit-notes')
                @php
                    $openCreditNotes = $creditNotes->whereNotIn('status', ['closed']);
                    $problemQty = $creditNotes->flatMap->items->sum('quantity_problem');
                    $retryItemCount = $creditNotes->flatMap->items->whereNotNull('retry_request_item_id')->count();
                    $creditNoteSourceLabel = fn ($source) => [
                        'goods_receipt' => 'Penerimaan Gudang',
                        'site_request' => 'Request Camp',
                        'manual_return' => 'Manual Return',
                    ][$source] ?? str_replace('_', ' ', (string) $source);
                    $creditNoteFormType = $canSeePrices && old('camp_id') ? 'manual' : 'site';
                    $creditNoteReviewQuery = array_filter([
                        'from' => $workflowTab,
                        'company_id' => request()->integer('company_id') ?: null,
                    ]);
                @endphp
                @if (!($createCreditNote ?? false) && $workflowTab !== 'history')
                <div class="credit-note-summary">
                    <div><span>Credit note aktif</span><strong>{{ $openCreditNotes->count() }}</strong><small>{{ $creditNotes->where('status', 'draft')->count() }} masih draft</small></div>
                    <div><span>Qty bermasalah</span><strong>{{ number_format($problemQty, 0, ',', '.') }}</strong><small>Total barang perlu tindak lanjut</small></div>
                    <div><span>Pengajuan ulang</span><strong>{{ number_format($retryItemCount, 0, ',', '.') }}</strong><small>Item sudah dibuatkan proses vendor ulang</small></div>
                </div>
                @endif

                @if ($currentUser->hasPermission('credit_notes.manage') && $workflowTab !== 'history')
                    <section class="credit-note-create-panel">
                        <div class="row">
                            <div>
                                <h2 style="margin-bottom:4px">{{ ($createCreditNote ?? false) ? 'Buat Pengajuan Credit Note' : 'Credit Note Baru' }}</h2>
                                @if ($createCreditNote ?? false)
                                    <p class="muted">Pilih sumber pengajuan, lalu lengkapi data yang diperlukan.</p>
                                @endif
                            </div>
                            <div class="actions">
                                @if ($createCreditNote ?? false)
                                    <a class="button ghost" href="{{ route('credit-notes', array_filter(['company_id' => request()->integer('company_id') ?: null])) }}">Kembali</a>
                                @else
                                    <a class="button" href="{{ route('credit-notes.create', array_filter(['company_id' => request()->integer('company_id') ?: null])) }}">+ Buat Pengajuan</a>
                                @endif
                            </div>
                        </div>

                        @if ($createCreditNote ?? false)
                            <div class="credit-note-type-picker" role="tablist" aria-label="Jenis pengajuan Credit Note">
                                <button type="button" class="credit-note-type {{ $creditNoteFormType === 'site' ? 'active' : '' }}" data-credit-note-type="site" role="tab" aria-selected="{{ $creditNoteFormType === 'site' ? 'true' : 'false' }}">
                                    <strong>Masalah Penerimaan Camp</strong>
                                    <span>Barang yang diterima camp rusak atau tidak sesuai.</span>
                                </button>
                                @if ($canSeePrices)
                                    <button type="button" class="credit-note-type {{ $creditNoteFormType === 'manual' ? 'active' : '' }}" data-credit-note-type="manual" role="tab" aria-selected="{{ $creditNoteFormType === 'manual' ? 'true' : 'false' }}">
                                        <strong>Pengembalian Manual</strong>
                                        <span>Barang tidak dipakai, usang, atau dikreditkan dengan nilai manual.</span>
                                    </button>
                                @endif
                            </div>
                        @endif

                        <form id="credit-note-site-form" class="inline-edit credit-note-form" @if(!($createCreditNote ?? false) || $creditNoteFormType !== 'site') hidden @endif method="post" action="{{ route('credit-notes.site-requests.store') }}">
                            @csrf
                            <h3>Credit Note dari Penerimaan Camp</h3>
                            <div class="two">
                                <label>Barang bermasalah di camp
                                    <select name="site_receipt_item_id" required>
                                        <option value="">Pilih item penerimaan camp</option>
                                        @foreach ($siteReceiptIssueItems as $siteReceiptItem)
                                            <option value="{{ $siteReceiptItem->id }}" @selected(old('site_receipt_item_id') == $siteReceiptItem->id)>{{ $siteReceiptItem->siteReceipt->number }} - {{ $siteReceiptItem->siteReceipt->siteDispatch->camp->name }} - {{ $siteReceiptItem->dispatchItem->inventoryItem->name }} x {{ $siteReceiptItem->quantity_received }} ({{ $siteReceiptItem->condition }})</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label>Vendor tujuan klaim <select name="vendor_id" required><option value="">Pilih vendor</option>@foreach ($vendors as $vendor)<option value="{{ $vendor->id }}" @selected(old('vendor_id') == $vendor->id)>{{ $vendor->name }}</option>@endforeach</select></label>
                                <label>Diajukan oleh <input name="requested_by_name" value="{{ $currentUser->name }}" readonly required></label>
                                <label>Alasan <input name="notes" value="{{ old('notes') }}" required placeholder="Contoh: camp menolak karena part number tidak sesuai"></label>
                            </div>
                            <button>Buat Request Credit Note Camp</button>
                        </form>

                        @if ($canSeePrices)
                        <form id="credit-note-manual-form" class="inline-edit credit-note-form" @if($creditNoteFormType !== 'manual') hidden @endif method="post" action="{{ route('credit-notes.manual-requests.store') }}">
                            @csrf
                            <h3>Credit Note Manual / Barang Tidak Dipakai</h3>
                            <div class="three">
                                <label>Camp / perusahaan <select name="camp_id" required>@foreach ($camps as $camp)<option value="{{ $camp->id }}" @selected(old('camp_id') == $camp->id)>{{ $camp->company_name }} - {{ $camp->name }}</option>@endforeach</select></label>
                                <label>Vendor <select name="vendor_id" required>@foreach ($vendors as $vendor)<option value="{{ $vendor->id }}" @selected(old('vendor_id') == $vendor->id)>{{ $vendor->name }}</option>@endforeach</select></label>
                                <label>Barang <select name="inventory_item_id" required>@foreach ($items as $item)<option value="{{ $item->id }}" @selected(old('inventory_item_id') == $item->id)>{{ $item->sku }} - {{ $item->name }} (stok {{ $item->stock_on_hand }} {{ $item->uom }})</option>@endforeach</select></label>
                                <label>Qty <input type="number" name="quantity_problem" min="1" value="{{ old('quantity_problem', 1) }}" required></label>
                                <label>Harga credit manual <input type="number" name="unit_price" min="0" value="{{ old('unit_price', 0) }}" required></label>
                                <label>Jenis <select name="issue_type" required><option value="unused" @selected(old('issue_type') === 'unused')>Tidak dipakai</option><option value="obsolete" @selected(old('issue_type') === 'obsolete')>Obsolete</option><option value="overstock" @selected(old('issue_type') === 'overstock')>Stok berlebih</option><option value="wrong_purchase" @selected(old('issue_type') === 'wrong_purchase')>Salah beli</option><option value="used_return" @selected(old('issue_type') === 'used_return')>Bekas pakai / nilai turun</option><option value="other" @selected(old('issue_type') === 'other')>Lainnya</option></select></label>
                                <label>Diajukan oleh <input name="requested_by_name" value="{{ $currentUser->name }}" readonly required></label>
                                <label class="full">Alasan <input name="notes" value="{{ old('notes') }}" required placeholder="Contoh: barang tidak digunakan dan vendor menyetujui credit dengan harga lebih rendah"></label>
                            </div>
                            <button>Buat Request Credit Note Manual</button>
                        </form>
                        @endif
                    </section>
                @endif

                @unless ($createCreditNote ?? false)
                <section>
                    <h2>{{ $workflowTab === 'history' ? 'History Credit Note' : 'Daftar Credit Note / Pengembalian' }}</h2>
                    @include('inventory.components.transaction-filter')
                    <div class="stack">
                        @forelse ($creditNotes as $creditNote)
                            @php
                                $totalProblem = $creditNote->items->sum('quantity_problem');
                                $retryCreated = $creditNote->items->whereNotNull('retry_request_item_id')->count();
                                $canCreateRetry = $creditNote->source_type !== 'manual_return' && $currentUser->hasPermission('credit_notes.manage') && in_array($creditNote->status, ['credited', 'closed'], true) && $retryCreated < $creditNote->items->count();
                            @endphp
                            <article class="credit-note-card" data-href="{{ route('credit-notes.review', array_merge(['creditNote' => $creditNote], $creditNoteReviewQuery)) }}" tabindex="0" role="link">
                                <div class="credit-note-card-header">
                                    <div class="credit-note-card-heading">
                                        <h3>{{ $creditNote->number }} - {{ $creditNote->vendor?->name ?: 'Vendor belum dipilih' }}</h3>
                                        <p>
                                            {{ $creditNoteSourceLabel($creditNote->source_type) }}
                                            @if ($creditNote->purchaseOrder)
                                                - PO {{ $creditNote->purchaseOrder->number }}
                                            @endif
                                            @if ($creditNote->invoice_number)
                                                - Faktur {{ $creditNote->invoice_number }}
                                            @endif
                                            - {{ $creditNote->purchaseOrder?->purchaseRequest?->camp?->name ?? $creditNote->siteReceipt?->siteDispatch?->camp?->name ?? $creditNote->camp?->name ?? '-' }}
                                        </p>
                                    </div>
                                    <div class="actions">
                                        <span class="pill {{ in_array($creditNote->status, ['credited', 'closed'], true) ? 'ok' : 'wait' }}">{{ $creditNoteStatusLabel($creditNote->status) }}</span>
                                        <span class="pill">{{ $retryCreated }}/{{ $creditNote->items->count() }} item ulang</span>
                                        <a class="button ghost" href="{{ route('credit-notes.review', array_merge(['creditNote' => $creditNote], $creditNoteReviewQuery)) }}">Detail</a>
                                        @if ($canCreateRetry)
                                            <form method="post" action="{{ route('credit-notes.retry.store', $creditNote) }}" onsubmit="return confirm('Buat pengajuan vendor ulang dari {{ $creditNote->number }}? Item akan masuk lagi ke Harga Vendor.')">
                                                @csrf
                                                <button type="submit">Buat Pengajuan Ulang</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>

                                <div class="credit-note-table-wrap">
                                    <table class="credit-note-table">
                                        <thead><tr><th>Barang</th><th>Masalah</th><th>Qty Problem</th><th>Pengajuan Ulang</th><th>Catatan</th></tr></thead>
                                        <tbody>
                                        @foreach ($creditNote->items as $creditNoteItem)
                                            @php
                                                $retryItem = $creditNoteItem->retryRequestItem;
                                            @endphp
                                            <tr>
                                                <td>
                                                    <strong class="credit-note-cell-primary">{{ $creditNoteItem->inventoryItem->name }}</strong>
                                                    <span class="credit-note-cell-secondary">SKU {{ $creditNoteItem->inventoryItem->sku }}</span>
                                                </td>
                                                <td>
                                                    <strong class="credit-note-cell-primary">{{ $workflowStatusLabel($creditNoteItem->issue_type) }}</strong>
                                                    <span class="credit-note-cell-secondary">Tindak lanjut: {{ $workflowStatusLabel($creditNoteItem->resolution) }}</span>
                                                </td>
                                                <td class="credit-note-qty">{{ number_format($creditNoteItem->quantity_problem, 0, ',', '.') }} {{ $creditNoteItem->purchaseOrderItem?->uom ?? $creditNoteItem->inventoryItem?->uom }}</td>
                                                <td>
                                                    @if ($retryItem)
                                                        <span class="pill ok">{{ $workflowStatusLabel($retryItem->status) }}</span>
                                                        <span class="credit-note-cell-secondary">Item ulang #{{ $retryItem->id }}</span>
                                                    @else
                                                        <span class="pill wait">Belum dibuat</span>
                                                    @endif
                                                </td>
                                                <td class="credit-note-notes">{{ $creditNoteItem->notes ?: '-' }}</td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>

                            </article>
                        @empty
                            <div class="empty">{{ $workflowTab === 'history' ? 'Belum ada history Credit Note.' : 'Belum ada Credit Note aktif.' }}</div>
                        @endforelse
                    </div>
                    @include('inventory.components.transaction-pagination')
                </section>
                @endunless

                @if ($createCreditNote ?? false)
                    <script>
                        document.querySelectorAll('[data-credit-note-type]').forEach((button) => {
                            button.addEventListener('click', () => {
                                const selectedType = button.dataset.creditNoteType;
                                document.querySelectorAll('[data-credit-note-type]').forEach((item) => {
                                    const active = item === button;
                                    item.classList.toggle('active', active);
                                    item.setAttribute('aria-selected', active ? 'true' : 'false');
                                });
                                document.getElementById('credit-note-site-form').hidden = selectedType !== 'site';
                                const manualForm = document.getElementById('credit-note-manual-form');
                                if (manualForm) manualForm.hidden = selectedType !== 'manual';
                            });
                        });
                    </script>
                @endif
            @endif

