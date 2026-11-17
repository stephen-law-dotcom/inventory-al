@extends('layouts.inventory')

@section('content')
                @php
                    $dispatchStatus = fn ($status) => [
                        'draft' => 'Draft',
                        'sent' => 'Dalam Perjalanan', 'partial_received' => 'Diterima Sebagian',
                        'received_good' => 'Diterima Lengkap', 'received_with_issue' => 'Diterima Bermasalah',
                    ][$status] ?? $workflowStatusLabel($status);
                    $dispatchesByTab = $dispatches;
                    $dispatchTabTitle = [
                        'draft' => 'Packing List',
                        'transit' => 'Barang Dalam Perjalanan',
                        'receiving' => 'Penerimaan Site',
                        'history' => 'Riwayat Pengiriman',
                    ][$dispatchTab] ?? 'Pengiriman Barang';
                    $dispatchReviewQuery = array_filter([
                        'from' => $dispatchTab,
                        'company_id' => request()->integer('company_id') ?: null,
                    ]);
                @endphp
                @if ($dispatchTab === 'draft' && $currentUser->hasPermission('dispatches.manage') && !($createDispatch ?? false))
                    <div class="actions">
                        <a class="button" href="{{ route('dispatches.create', array_filter(['company_id' => request()->integer('company_id') ?: null])) }}">+ Buat Packing List</a>
                    </div>
                @endif
                @if ($dispatchTab === 'draft' && $currentUser->hasPermission('dispatches.manage') && ($createDispatch ?? false))
                    @php
                        $editingDispatch = $editingDispatch ?? null;
                        $initialDispatchItems = old('items', $editingDispatch?->items->map(fn ($item) => [
                            'goods_receipt_item_id' => $item->goods_receipt_item_id,
                            'quantity_sent' => $item->quantity_sent,
                            'notes' => $item->notes,
                        ])->values()->all() ?? []);
                    @endphp
                    <section id="dispatch-add-form" class="dispatch-create-panel">
                    <div class="row"><div><h2>{{ $editingDispatch ? 'Edit Packing List' : 'Buat Packing List' }}</h2><p class="muted">Packing List disimpan sebagai draft. Stok gudang baru berkurang setelah dokumen diperiksa dan dikirim ke site.</p></div><a class="button ghost" href="{{ $editingDispatch ? route('dispatches.review', ['siteDispatch' => $editingDispatch, 'from' => 'draft']) : route('dispatches', array_filter(['tab' => 'draft', 'company_id' => request()->integer('company_id') ?: null])) }}">Kembali</a></div>
                    <form method="post" action="{{ $editingDispatch ? route('dispatches.update', $editingDispatch) : route('dispatches.store') }}" data-dispatch-form data-confirm="Simpan Packing List sebagai draft? Stok gudang belum akan berkurang.">
                        @csrf
                        @if($editingDispatch) @method('PUT') @endif
                        <div class="three">
                            <label>Tujuan camp <select name="camp_id" required data-dispatch-camp>@foreach ($camps as $camp)<option value="{{ $camp->id }}" @selected(old('camp_id', $editingDispatch?->camp_id) == $camp->id)>{{ $camp->company_name }} - {{ $camp->name }}</option>@endforeach</select></label>
                            <label>Driver / ekspedisi <input name="driver_name" value="{{ old('driver_name', $editingDispatch?->driver_name) }}" placeholder="Dapat dilengkapi sebelum dikirim"></label>
                            <label>No kendaraan / resi <input name="vehicle_number" value="{{ old('vehicle_number', $editingDispatch?->vehicle_number) }}" placeholder="Dapat dilengkapi sebelum dikirim"></label>
                        </div>
                        <div class="stack" data-dispatch-rows></div>
                        <div class="actions">
                            <button>{{ $editingDispatch ? 'Simpan Perubahan' : 'Simpan Packing List' }}</button>
                        </div>
                    </form>
                    </section>
                    <template id="dispatch-row-template">
                        <div class="inline-edit dispatch-item-row">
                            <div class="dispatch-item-main">
                                <label>Sumber penerimaan
                                    <select name="items[__INDEX__][goods_receipt_item_id]" required>
                                        <option value="">Pilih barang diterima</option>
                                        @foreach ($dispatchableReceiptItems as $receiptItem)
                                            @php
                                                $poItem = $receiptItem->purchaseOrderItem;
                                                $allocatedElsewhere = $receiptItem->dispatchItems
                                                    ->when($editingDispatch, fn ($items) => $items->where('site_dispatch_id', '!=', $editingDispatch->id))
                                                    ->sum('quantity_sent');
                                                $available = (float) $receiptItem->quantity_received - (float) $allocatedElsewhere;
                                            @endphp
                                            <option
                                                value="{{ $receiptItem->id }}"
                                                data-sku="{{ $poItem->inventoryItem->sku }}"
                                                data-item-name="{{ $poItem->inventoryItem->name }}"
                                                data-available="{{ $available }}"
                                                data-uom="{{ $poItem->uom }}"
                                                data-po-number="{{ $poItem->purchaseOrder->number }}"
                                                data-or-number="{{ $poItem->requestItem->purchaseRequest->number }}"
                                            >{{ $poItem->inventoryItem->sku }} - {{ $poItem->inventoryItem->name }} | sisa {{ number_format($available, 0, ',', '.') }} {{ $poItem->uom }} | PO {{ $poItem->purchaseOrder->number }} | OR {{ $poItem->requestItem->purchaseRequest->number }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label>Qty kirim
                                    <span class="quantity-with-uom">
                                        <input name="items[__INDEX__][quantity_sent]" type="number" min="1" value="1" required>
                                        <span data-dispatch-uom>-</span>
                                    </span>
                                </label>
                            </div>
                            <div class="dispatch-item-summary" data-dispatch-summary hidden aria-live="polite">
                                <strong data-dispatch-item-name></strong>
                                <span data-dispatch-quantity-summary></span>
                                <small data-dispatch-references></small>
                            </div>
                            <div class="dispatch-item-warning" data-dispatch-warning hidden role="alert"></div>
                            <div class="dispatch-item-footer">
                                <label class="dispatch-item-remark">Remark <input name="items[__INDEX__][notes]" placeholder="Keterangan atau tujuan penggunaan"></label>
                                <div class="dispatch-item-actions">
                                    <button type="button" class="ghost" data-add-dispatch-row title="Tambah barang setelah baris ini">+ Tambah</button>
                                    <button type="button" class="ghost dispatch-remove-button" data-remove-dispatch-row title="Hapus barang" aria-label="Hapus barang dari Packing List">Hapus</button>
                                </div>
                            </div>
                        </div>
                    </template>
                @endif

                @unless ($createDispatch ?? false)
                <section>
                    <div class="panel-head"><div><h2>{{ $dispatchTabTitle }}</h2></div></div>
                    @include('inventory.components.transaction-filter')
                    <div class="table-scroll-shell">
                    <table class="logistics-table">
                        <thead><tr><th>No Dokumen</th><th>Sumber</th><th>Tanggal</th><th>Tujuan</th><th>Transportasi</th><th>Barang</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse ($dispatchesByTab as $dispatch)
                            <tr class="clickable-row" data-href="{{ route('dispatches.review', array_merge(['siteDispatch' => $dispatch], $dispatchReviewQuery)) }}">
                                <td><div class="logistics-primary">{{ $dispatch->number }}</div><div class="muted">{{ $dispatch->source_type === 'direct_vendor' ? ($dispatch->purchaseOrder?->number ?? 'PO vendor') : ($dispatch->dispatchedBy?->name ?? 'Logistik HO') }}</div></td>
                                <td><span class="logistics-source {{ $dispatch->source_type === 'direct_vendor' ? 'direct' : '' }}">{{ $dispatch->source_type === 'direct_vendor' ? 'Vendor langsung' : 'Gudang kantor' }}</span></td>
                                <td>{{ ($dispatch->sent_at ?? $dispatch->created_at)?->format('d M Y') ?? '-' }}<div class="muted">{{ $dispatch->status === 'draft' ? 'Dibuat' : 'Dikirim' }}</div></td>
                                <td><div class="logistics-primary">{{ $dispatch->camp->name }}</div><div class="muted">{{ $dispatch->camp->company_name }}</div></td>
                                <td><div class="logistics-primary">{{ $dispatch->driver_name ?: '-' }}</div><div class="muted">{{ $dispatch->vehicle_number ?: 'Resi belum diisi' }}</div></td>
                                <td><div class="logistics-primary">{{ $dispatch->items->count() }} jenis barang</div><div class="muted">{{ number_format($dispatch->items->sum('quantity_sent'), 0, ',', '.') }} total qty</div></td>
                                <td><span class="pill {{ str_contains($dispatch->status, 'issue') ? 'bad' : (str_contains($dispatch->status, 'received') ? 'ok' : 'wait') }}">{{ $dispatchStatus($dispatch->status) }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="7">Belum ada data pada tab ini.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                    </div>
                    @include('inventory.components.transaction-pagination')
                </section>
                @endunless

                @if ($dispatchTab === 'draft' && $currentUser->hasPermission('dispatches.manage') && ($createDispatch ?? false))
                    <script>
                        (() => {
                            const form = document.querySelector('[data-dispatch-form]');
                            if (!form) return;
                            const rows = form.querySelector('[data-dispatch-rows]');
                            const template = document.querySelector('#dispatch-row-template');
                            let index = 0;
                            const initialItems = @json($initialDispatchItems);
                            const quantityFormatter = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
                            const syncRemoveButtons = () => {
                                const canRemove = rows.children.length > 1;
                                rows.querySelectorAll('[data-remove-dispatch-row]').forEach(button => button.hidden = !canRemove);
                            };
                            const syncRows = () => {
                                const selectedCounts = {};
                                rows.querySelectorAll('select[name$="[goods_receipt_item_id]"]').forEach(select => {
                                    if (select.value) selectedCounts[select.value] = (selectedCounts[select.value] ?? 0) + 1;
                                });

                                rows.querySelectorAll('.dispatch-item-row').forEach(row => {
                                    const select = row.querySelector('select[name$="[goods_receipt_item_id]"]');
                                    const quantityInput = row.querySelector('input[name$="[quantity_sent]"]');
                                    const option = select.selectedOptions[0];
                                    const summary = row.querySelector('[data-dispatch-summary]');
                                    const warning = row.querySelector('[data-dispatch-warning]');
                                    const uom = option?.dataset.uom || '-';
                                    const available = Number(option?.dataset.available ?? 0);
                                    const quantity = Number(quantityInput.value || 0);
                                    const messages = [];

                                    row.querySelector('[data-dispatch-uom]').textContent = uom;
                                    quantityInput.removeAttribute('max');
                                    if (select.value) {
                                        quantityInput.max = String(available);
                                        summary.hidden = false;
                                        row.querySelector('[data-dispatch-item-name]').textContent = `${option.dataset.sku} - ${option.dataset.itemName}`;
                                        row.querySelector('[data-dispatch-quantity-summary]').textContent = `Tersedia ${quantityFormatter.format(available)} ${uom} | Akan dikirim ${quantityFormatter.format(quantity)} ${uom}`;
                                        row.querySelector('[data-dispatch-references]').textContent = `PO ${option.dataset.poNumber} | OR ${option.dataset.orNumber}`;

                                        if (quantity <= 0) messages.push('Qty kirim harus lebih dari 0.');
                                        if (quantity > available) messages.push(`Qty kirim melebihi jumlah tersedia. Maksimal ${quantityFormatter.format(available)} ${uom}.`);
                                        if (selectedCounts[select.value] > 1) messages.push('Sumber penerimaan ini sudah dipilih pada baris lain.');
                                    } else {
                                        summary.hidden = true;
                                    }

                                    quantityInput.setCustomValidity(messages[0] ?? '');
                                    warning.textContent = messages.join(' ');
                                    warning.hidden = messages.length === 0;
                                    row.classList.toggle('has-warning', messages.length > 0);
                                });
                            };
                            const addRow = (item = {}, afterRow = null) => {
                                const rowIndex = index++;
                                const wrapper = document.createElement('div');
                                wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', rowIndex).trim();
                                const row = wrapper.firstElementChild;
                                afterRow ? afterRow.insertAdjacentElement('afterend', row) : rows.appendChild(row);
                                row.querySelector(`[name="items[${rowIndex}][goods_receipt_item_id]"]`).value = item.goods_receipt_item_id ?? '';
                                row.querySelector(`[name="items[${rowIndex}][quantity_sent]"]`).value = item.quantity_sent ?? 1;
                                row.querySelector(`[name="items[${rowIndex}][notes]"]`).value = item.notes ?? '';
                                syncRemoveButtons();
                                syncRows();
                                if (afterRow) row.querySelector('select').focus();
                            };
                            rows.addEventListener('click', event => {
                                const row = event.target.closest('.dispatch-item-row');
                                if (event.target.matches('[data-add-dispatch-row]')) {
                                    addRow({}, row);
                                    return;
                                }
                                if (event.target.matches('[data-remove-dispatch-row]')) {
                                    row.remove();
                                    if (!rows.children.length) addRow();
                                    syncRemoveButtons();
                                    syncRows();
                                }
                            });
                            rows.addEventListener('change', syncRows);
                            rows.addEventListener('input', syncRows);
                            form.addEventListener('submit', syncRows);
                            initialItems.length ? initialItems.forEach(addRow) : addRow();
                        })();
                    </script>
                @endif
@endsection

