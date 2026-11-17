@extends('layouts.inventory')

@section('content')
                <section>
                    <h2>Pengingat Pembelian dari Stok Gabungan</h2>
                    <div class="network-stock-table-wrap">
                    <table class="network-stock-table">
                        <thead><tr><th>Barang</th><th>Gudang</th><th>Camp</th><th>Perjalanan</th><th>PO Aktif</th><th>Posisi Stok</th><th>EOQ</th><th>ROP / Min</th><th>Max Min-Max</th><th>Rekomendasi</th><th>Status</th></tr></thead>
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
                                <td>{{ $item->usesInventoryPlanning() ? '-' : $item->maximumStockLevel().' '.$item->uom }}</td>
                                <td>{{ $item->recommendedOrderQuantity() > 0 ? $item->recommendedOrderQuantity() . ' ' . $item->uom : '-' }}</td>
                                <td><span class="pill {{ $item->shouldReorder() ? 'bad' : 'ok' }}">{{ $item->shouldReorder() ? 'Perlu Order' : 'Aman' }}</span></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    </div>
                </section>
                <section class="warehouse-stock-section">
                    <div class="warehouse-stock-heading">
                        <h2>Stok Gudang Kantor</h2>
                        <span>{{ $warehouseItems->count() }} barang tersedia</span>
                    </div>
                    <div class="warehouse-stock-table-wrap">
                        <table class="warehouse-stock-table">
                            <colgroup>
                                <col class="warehouse-stock-part">
                                <col class="warehouse-stock-item">
                                <col class="warehouse-stock-category">
                                <col class="warehouse-stock-balance-column">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Part Number</th>
                                    <th>Barang</th>
                                    <th>Kategori</th>
                                    <th class="is-numeric">Saldo Tersedia</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse ($warehouseItems as $warehouseItem)
                                @php
                                    $warehouseQuantity = $warehouseItem->warehouseStock();
                                    $warehouseQuantityDecimals = floor($warehouseQuantity) === $warehouseQuantity ? 0 : 2;
                                @endphp
                                <tr class="clickable-row" data-href="{{ route('stock.items.review', $warehouseItem) }}" tabindex="0" role="link" aria-label="Lihat kartu stok gudang {{ $warehouseItem->name }}">
                                    <td><span class="warehouse-stock-sku">{{ $warehouseItem->sku }}</span></td>
                                    <td><strong class="warehouse-stock-item-name">{{ $warehouseItem->name }}</strong></td>
                                    <td>{{ $warehouseItem->itemCategory?->name ?? $warehouseItem->category }}</td>
                                    <td class="warehouse-stock-quantity">{{ number_format($warehouseQuantity, $warehouseQuantityDecimals, ',', '.') }} <span>{{ $warehouseItem->uom }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="warehouse-stock-empty">Belum ada stok tersedia di gudang kantor.</td></tr>
                            @endforelse
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
@endsection

