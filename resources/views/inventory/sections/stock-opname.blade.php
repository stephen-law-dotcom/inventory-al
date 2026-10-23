            @if ($activeMenu === 'stock-opname')
                @php
                    $opnameStatusLabels = [
                        'draft' => 'Draft',
                        'pending_review' => 'Menunggu Pemeriksaan',
                        'revision_required' => 'Perlu Hitung Ulang',
                        'ready_to_post' => 'Siap Posting',
                        'posted' => 'Selesai',
                    ];
                    $canFinalizeOpname = $currentUser->canPostStockOpname();
                @endphp
                @if ($currentUser->hasPermission('stock_opname.manage'))
                    <div class="actions">
                        <a class="button" href="{{ route('stock-opname.create', array_filter(['company_id' => $activeCompanyId])) }}">+ Buat Stock Opname</a>
                    </div>
                @endif

                <section>
                    <h2>Riwayat Stock Opname</h2>
                    <div class="stack">
                        @forelse ($stockOpnames as $opname)
                            <article>
                                <div class="row">
                                    <div>
                                        <h3>{{ $opname->number }} - {{ $opname->opname_date->format('d M Y') }}</h3>
                                        <p>{{ $opname->camp?->name ?: ($opname->location ?: 'Gudang Kantor') }} oleh {{ $opname->officers->pluck('name')->implode(', ') ?: $opname->counted_by }}{{ $opname->notes ? ' - '.$opname->notes : '' }}</p>
                                    </div>
                                    <div class="actions">
                                        <span class="pill {{ $opname->status === 'posted' ? 'ok' : ($opname->status === 'revision_required' ? 'bad' : 'wait') }}">{{ $opnameStatusLabels[$opname->status] ?? $opname->status }}</span>
                                        @if ($opname->posted_at)
                                            <span class="muted">Posted {{ $opname->posted_at->format('d M Y H:i') }}</span>
                                        @endif
                                        @if ($opname->isEditable() && $currentUser->hasPermission('stock_opname.manage'))
                                            <a class="button ghost" href="{{ route('stock-opnames.edit', $opname) }}">Edit / Hitung Ulang</a>
                                        @endif
                                        @if ($opname->status === 'draft' && $currentUser->hasPermission('stock_opname.manage'))
                                            <form method="post" action="{{ route('stock-opnames.submit', $opname) }}" data-confirm="Ajukan stock opname ini untuk pemeriksaan?">
                                                @csrf
                                                <button>Ajukan</button>
                                            </form>
                                        @endif
                                        @if ($opname->status === 'ready_to_post' && $canFinalizeOpname)
                                            <form method="post" action="{{ route('stock-opnames.post', $opname) }}" data-confirm="Posting final dan sesuaikan stok dengan hasil hitung fisik?">
                                                @csrf
                                                <button>Posting Final</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                                <table style="margin-top:12px">
                                    <thead><tr><th>Barang</th><th>Stok Sistem</th><th>Stok Fisik</th><th>Selisih</th><th>Status</th><th>Catatan</th></tr></thead>
                                    <tbody>
                                    @foreach ($opname->items as $opnameItem)
                                        <tr>
                                            <td>
                                                <a class="item-history-link" href="{{ route('stock-opname.items.history', ['inventoryItem' => $opnameItem->inventoryItem, 'from' => 'stock-opname']) }}">
                                                    {{ $opnameItem->inventoryItem->sku }} - {{ $opnameItem->inventoryItem->name }}
                                                    <span>Buka history penggunaan</span>
                                                </a>
                                            </td>
                                            <td>{{ number_format((float) $opnameItem->system_stock, 2, ',', '.') }} {{ $opnameItem->inventoryItem->uom }}</td>
                                            <td>{{ number_format((float) $opnameItem->physical_stock, 2, ',', '.') }} {{ $opnameItem->inventoryItem->uom }}</td>
                                            <td>
                                                <span class="pill {{ $opnameItem->variance_status === 'out_of_tolerance' ? 'bad' : ($opnameItem->variance_status === 'within_tolerance' ? 'wait' : 'ok') }}">
                                                    {{ $opnameItem->difference > 0 ? '+' : '' }}{{ number_format((float) $opnameItem->difference, 2, ',', '.') }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="pill {{ $opnameItem->variance_status === 'out_of_tolerance' || $opnameItem->tolerance_review_status === 'rejected' ? 'bad' : ($opnameItem->tolerance_review_status === 'pending' ? 'wait' : 'ok') }}">
                                                    {{ $opnameItem->variance_status === 'matched' ? 'Sesuai' : ($opnameItem->tolerance_review_status === 'pending' ? 'Menunggu pemeriksaan' : ($opnameItem->tolerance_review_status === 'accepted' ? 'Selisih diterima' : ($opnameItem->tolerance_review_status === 'rejected' ? 'Hitung ulang' : 'Belum diajukan'))) }}
                                                </span>
                                                @if ($opnameItem->tolerance_quantity > 0)
                                                    <div class="muted">Referensi toleransi {{ number_format((float) $opnameItem->tolerance_quantity, 2, ',', '.') }} {{ $opnameItem->inventoryItem->uom }} ({{ $opnameItem->shrinkage_tolerance_percent }}%)</div>
                                                @endif
                                                @if ($opnameItem->tolerance_review_notes)
                                                    <div class="muted">Review: {{ $opnameItem->tolerance_review_notes }}</div>
                                                @endif
                                            </td>
                                            <td>{{ $opnameItem->notes }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </article>
                        @empty
                            <div class="empty">Belum ada stock opname.</div>
                        @endforelse
                    </div>
                </section>
            @endif

