            @if ($activeMenu === 'dispatches')
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
                            'unit_id' => $item->unit_id,
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
                            <button type="button" class="ghost" data-add-dispatch-row>+ Tambah Barang</button>
                            <button>{{ $editingDispatch ? 'Simpan Perubahan' : 'Simpan Packing List' }}</button>
                        </div>
                    </form>
                    </section>
                    <template id="dispatch-row-template">
                        <div class="inline-edit dispatch-item-row">
                            <div class="three">
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
                                            <option value="{{ $receiptItem->id }}">{{ $poItem->inventoryItem->sku }} - {{ $poItem->inventoryItem->name }} | sisa {{ number_format($available, 0, ',', '.') }} {{ $poItem->uom }} | PO {{ $poItem->purchaseOrder->number }} | OR {{ $poItem->requestItem->purchaseRequest->number }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label>Unit tujuan
                                    <select name="items[__INDEX__][unit_id]" data-unit-select>
                                        <option value="">Stok umum camp</option>
                                        @foreach ($units as $unit)<option value="{{ $unit->id }}" data-camp-id="{{ $unit->camp_id }}">{{ $unit->code }} - {{ $unit->name }}</option>@endforeach
                                    </select>
                                </label>
                                <label>Qty kirim <input name="items[__INDEX__][quantity_sent]" type="number" min="1" value="1" required></label>
                            </div>
                            <div class="row">
                                <label>Remark <input name="items[__INDEX__][notes]" placeholder="Keterangan atau tujuan penggunaan"></label>
                                <button type="button" class="ghost" data-remove-dispatch-row>Hapus Baris</button>
                            </div>
                        </div>
                    </template>
                @endif

                @unless ($createDispatch ?? false)
                <section>
                    <div class="panel-head"><div><h2>{{ $dispatchTabTitle }}</h2></div></div>
                    @include('inventory.components.transaction-filter')
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
                            const camp = form.querySelector('[data-dispatch-camp]');
                            let index = 0;
                            const syncUnits = () => rows.querySelectorAll('[data-unit-select]').forEach(select => {
                                Array.from(select.options).forEach(option => option.hidden = option.dataset.campId && option.dataset.campId !== camp.value);
                                if (select.selectedOptions[0]?.hidden) select.value = '';
                            });
                            const initialItems = @json($initialDispatchItems);
                            const addRow = (item = {}) => {
                                const rowIndex = index++;
                                rows.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', rowIndex));
                                const row = rows.lastElementChild;
                                row.querySelector(`[name="items[${rowIndex}][goods_receipt_item_id]"]`).value = item.goods_receipt_item_id ?? '';
                                row.querySelector(`[name="items[${rowIndex}][unit_id]"]`).value = item.unit_id ?? '';
                                row.querySelector(`[name="items[${rowIndex}][quantity_sent]"]`).value = item.quantity_sent ?? 1;
                                row.querySelector(`[name="items[${rowIndex}][notes]"]`).value = item.notes ?? '';
                                syncUnits();
                            };
                            form.querySelector('[data-add-dispatch-row]').addEventListener('click', addRow);
                            rows.addEventListener('click', event => {
                                if (!event.target.matches('[data-remove-dispatch-row]')) return;
                                event.target.closest('.dispatch-item-row').remove();
                                if (!rows.children.length) addRow();
                            });
                            camp.addEventListener('change', syncUnits);
                            initialItems.length ? initialItems.forEach(addRow) : addRow();
                        })();
                    </script>
                @endif
            @endif

