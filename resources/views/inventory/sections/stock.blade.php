            @if ($activeMenu === 'stock')
                <section>
                    <h2>Pengingat Pembelian dari Stok Gabungan</h2>
                    <div class="network-stock-table-wrap">
                    <table class="network-stock-table">
                        <thead><tr><th>Barang</th><th>Gudang</th><th>Camp</th><th>Perjalanan</th><th>PO Aktif</th><th>Posisi Stok</th><th>EOQ</th><th>ROP / Min</th><th>Max</th><th>Rekomendasi</th><th>Status</th></tr></thead>
                        <tbody>
                        @foreach ($items as $item)
                            <tr class="clickable-row" data-href="{{ route('stock.items.review', $item) }}" tabindex="0" role="link" aria-label="Lihat kartu stok {{ $item->name }}">
                                <td><div class="stock-balance"><strong>{{ $item->name }}</strong><span>{{ $item->sku }}</span></div></td>
                                <td class="stock-number">{{ number_format($item->warehouseStock(), 0, ',', '.') }} <small>{{ $item->uom }}</small></td>
                                <td class="stock-number">{{ number_format($item->campStock(), 0, ',', '.') }} <small>{{ $item->uom }}</small></td>
                                <td class="stock-number">{{ number_format($item->inTransitStock(), 0, ',', '.') }} <small>{{ $item->uom }}</small></td>
                                <td class="stock-number">{{ number_format($item->outstandingPurchaseOrderQuantity(), 0, ',', '.') }} <small>{{ $item->uom }}</small></td>
                                <td class="stock-number stock-position"><strong>{{ number_format($item->inventoryPosition(), 0, ',', '.') }}</strong> <small>{{ $item->uom }}</small></td>
                                <td>{{ $item->usesInventoryPlanning() ? number_format($item->eoq(), 0, ',', '.') . ' ' . $item->uom : '-' }}</td>
                                <td>{{ $item->minimumStockLevel() }} {{ $item->uom }}</td>
                                <td>{{ $item->maximumStockLevel() }} {{ $item->uom }}</td>
                                <td>{{ $item->recommendedOrderQuantity() > 0 ? $item->recommendedOrderQuantity() . ' ' . $item->uom : '-' }}</td>
                                <td><span class="pill {{ $item->shouldReorder() ? 'bad' : 'ok' }}">{{ $item->shouldReorder() ? 'Perlu Order' : 'Aman' }}</span></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    </div>
                </section>
                <section class="camp-stock-section">
                    <div class="camp-stock-heading">
                        <h2>Stok per Camp</h2>
                        <span>{{ $campStocks->count() }} barang tercatat</span>
                    </div>
                    <div class="camp-stock-table-wrap">
                        <table class="camp-stock-table">
                            <colgroup>
                                <col class="camp-stock-company">
                                <col class="camp-stock-location">
                                <col class="camp-stock-part">
                                <col class="camp-stock-item">
                                <col class="camp-stock-balance-column">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Perusahaan</th>
                                    <th>Camp</th>
                                    <th>Part Number</th>
                                    <th>Barang</th>
                                    <th class="is-numeric">Saldo Tersedia</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse ($campStocks as $campStock)
                                @php
                                    $campQuantity = (float) $campStock->quantity;
                                    $campQuantityDecimals = floor($campQuantity) === $campQuantity ? 0 : 2;
                                @endphp
                                <tr>
                                    <td>{{ $campStock->camp->company_name }}</td>
                                    <td><strong class="camp-stock-location-name">{{ $campStock->camp->name }}</strong></td>
                                    <td><span class="camp-stock-sku">{{ $campStock->inventoryItem->sku }}</span></td>
                                    <td><strong class="camp-stock-item-name">{{ $campStock->inventoryItem->name }}</strong></td>
                                    <td class="camp-stock-quantity">{{ number_format($campQuantity, $campQuantityDecimals, ',', '.') }} <span>{{ $campStock->inventoryItem->uom }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="camp-stock-empty">Belum ada stok yang diterima di camp.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

