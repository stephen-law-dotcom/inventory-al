            @if ($activeMenu === 'usage')
                @php
                    $latestUsage = $usageSummary['latest'] ?? null;
                @endphp
                @if (!($createUsage ?? false) && $workflowTab !== 'history')
                <div class="grid">
                    <div class="card stat"><div class="stat-head"><span>Pemakaian hari ini</span><span class="stat-icon">US</span></div><strong>{{ number_format($usageSummary['today_count'] ?? 0, 0, ',', '.') }}</strong><span>Transaksi tercatat</span></div>
                    <div class="card stat"><div class="stat-head"><span>Bulan ini</span><span class="stat-icon">MT</span></div><strong>{{ number_format($usageSummary['month_count'] ?? 0, 0, ',', '.') }}</strong><span>Transaksi pemakaian unit</span></div>
                    <div class="card stat"><div class="stat-head"><span>Barang bulan ini</span><span class="stat-icon">IT</span></div><strong>{{ number_format($usageSummary['month_items'] ?? 0, 0, ',', '.') }}</strong><span>Jenis barang digunakan</span></div>
                    <div class="card stat"><div class="stat-head"><span>Terakhir input</span><span class="stat-icon">LG</span></div><strong>{{ $latestUsage?->used_at?->format('d M') ?: '-' }}</strong><span>{{ $latestUsage?->unit?->code ?: 'Belum ada data' }}</span></div>
                </div>

                @if ($currentUser->hasPermission('usage.manage'))
                    <div class="actions">
                        <a class="button" href="{{ route('usage.create', array_filter(['company_id' => request()->integer('company_id') ?: null])) }}">+ Catat Pemakaian</a>
                    </div>
                @endif
                @endif
                <div class="stack">
                    @if ($currentUser->hasPermission('usage.manage') && ($createUsage ?? false))
                    <section id="usage-add-form" class="usage-create-panel">
                        <div class="row usage-form-header">
                            <div><h2>Catat Pemakaian Unit</h2><p class="muted">Pilih unit untuk menampilkan barang pada camp terkait.</p></div>
                            <a class="button ghost" href="{{ route('usage', array_filter(['company_id' => request()->integer('company_id') ?: null])) }}">Kembali</a>
                        </div>
                        <form class="usage-form" method="post" action="{{ route('usage-logs.store') }}" data-usage-form data-confirm="Catat pemakaian ini? Stok camp akan langsung berkurang sesuai qty yang diinput.">
                            @csrf
                            <div class="row">
                                <label>Tanggal <input name="used_at" type="date" value="{{ old('used_at', now()->toDateString()) }}" required></label>
                                <label>Unit
                                    <select name="unit_id" required data-usage-unit>
                                        <option value="">Pilih unit terlebih dahulu</option>
                                        @foreach ($units as $unit)
                                            <option value="{{ $unit->id }}" data-camp-id="{{ $unit->camp_id }}" @selected(old('unit_id') == $unit->id)>{{ $unit->code }} - {{ $unit->name }}{{ $unit->camp?->name ? ' - '.$unit->camp->name : '' }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>
                            <div class="empty" data-usage-unit-message>Pilih unit terlebih dahulu sebelum memilih barang dan mengisi detail pemakaian.</div>
                            <div class="three">
                                <label>Tipe
                                    <select name="usage_type" required data-usage-dependent disabled>
                                        <option value="sparepart" @selected(old('usage_type') === 'sparepart')>Sparepart</option>
                                        <option value="solar" @selected(old('usage_type') === 'solar')>Solar</option>
                                        <option value="oli" @selected(old('usage_type') === 'oli')>Oli</option>
                                        <option value="bbm" @selected(old('usage_type') === 'bbm')>BBM / Bensin</option>
                                    </select>
                                </label>
                                <label>Barang stok
                                    <select name="inventory_item_id" required data-usage-dependent disabled>
                                        <option value="">Pilih barang setelah unit dipilih</option>
                                        @foreach ($campStocks as $campStock)
                                            <option value="{{ $campStock->inventory_item_id }}" data-camp-id="{{ $campStock->camp_id }}" @selected(old('inventory_item_id') == $campStock->inventory_item_id)>{{ $campStock->inventoryItem->sku }} - {{ $campStock->inventoryItem->name }}@if ($currentUser->hasPermission('stock.view')) | stok camp {{ number_format($campStock->quantity, 2, ',', '.') }} {{ $campStock->inventoryItem->uom }}@endif</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label>Qty dipakai <input name="quantity" type="number" step="0.01" min="0.01" value="{{ old('quantity') }}" required data-usage-dependent disabled></label>
                            </div>
                            <div class="three">
                                <label>Satuan
                                    <select name="uom" required data-usage-dependent disabled>
                                        @foreach ($uoms as $uom)
                                            <option value="{{ $uom->code }}" @selected(old('uom') === $uom->code)>{{ $uom->code }} - {{ $uom->name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label>HM / KM unit <input name="meter_reading" type="number" min="0" step="0.01" value="{{ old('meter_reading') }}" placeholder="Contoh: 1250" data-usage-dependent disabled></label>
                                <label>Shift
                                    <select name="shift" data-usage-dependent disabled>
                                        <option value="">Tidak diisi</option>
                                        <option value="Day" @selected(old('shift') === 'Day')>Day</option>
                                        <option value="Night" @selected(old('shift') === 'Night')>Night</option>
                                    </select>
                                </label>
                            </div>
                            <div class="row">
                                <label>Mekanik/operator <input name="mechanic_or_operator" value="{{ old('mechanic_or_operator') }}" data-usage-dependent disabled></label>
                                <label>Lokasi kerja <input name="location" value="{{ old('location') }}" placeholder="Pit, workshop, tangki, atau area kerja" data-usage-dependent disabled></label>
                            </div>
                            <div class="row">
                                <label>No referensi <input name="reference_number" value="{{ old('reference_number') }}" placeholder="WO / form solar / bon pemakaian" data-usage-dependent disabled></label>
                                <label>Catatan <input name="notes" value="{{ old('notes') }}" placeholder="Keterangan pekerjaan atau alasan pemakaian" data-usage-dependent disabled></label>
                            </div>
                            <div class="actions">
                                <button data-usage-dependent disabled>Catat Pemakaian</button>
                            </div>
                        </form>
                    </section>
                    @endif
                    @unless ($createUsage ?? false)
                    <section>
                        <h2>{{ $workflowTab === 'history' ? 'History Pemakaian Bulanan' : 'Pemakaian Bulan Ini' }}</h2>
                        @include('inventory.components.transaction-filter')
                        <table>
                            <thead><tr><th>Tanggal</th><th>Unit / Operasi</th><th>Barang</th><th>Qty</th><th>Operator</th><th>Lokasi / Referensi</th><th>Status</th></tr></thead>
                            <tbody>
                            @forelse ($usageLogs as $log)
                                <tr>
                                    <td class="usage-date">{{ $log->used_at->format('d M Y') }}</td>
                                    <td>
                                        <div class="usage-cell">
                                            <strong>{{ $log->unit?->code ?: '-' }}</strong>
                                            <span>{{ $log->unit?->name ?: 'Unit tidak tersedia' }}</span>
                                            @if ($log->meter_reading || $log->shift)
                                                <small>{{ $log->meter_reading ? 'HM/KM '.number_format($log->meter_reading, 0, ',', '.') : '' }}{{ $log->meter_reading && $log->shift ? ' | ' : '' }}{{ $log->shift }}</small>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <div class="usage-cell">
                                            <strong>{{ $log->inventoryItem?->name ?: '-' }}</strong>
                                            <small>{{ $log->inventoryItem?->sku ?: '-' }} | {{ strtoupper($log->usage_type) }}</small>
                                        </div>
                                    </td>
                                    <td class="usage-quantity">{{ number_format($log->quantity, 2, ',', '.') }} <small>{{ $log->uom }}</small></td>
                                    <td>{{ $log->mechanic_or_operator ?: '-' }}</td>
                                    <td>
                                        <div class="usage-cell">
                                            <strong>{{ $log->location ?: '-' }}</strong>
                                            <small>{{ $log->reference_number ?: ($log->notes ?: '-') }}</small>
                                        </div>
                                    </td>
                                    <td><span class="pill ok">Tercatat</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="7">{{ $workflowTab === 'history' ? 'Belum ada history pemakaian dari bulan sebelumnya.' : 'Belum ada pemakaian pada bulan ini.' }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                        @include('inventory.components.transaction-pagination')
                    </section>
                    @endunless
                </div>
            @endif

