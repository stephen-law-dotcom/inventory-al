            @if ($activeMenu === 'receipts')
                <section>
                    <h2>{{ $workflowTab === 'history' ? 'History Penerimaan Gudang' : 'Penerimaan Berdasarkan Purchase Order' }}</h2>
                    @include('inventory.components.transaction-filter')
                    <div class="stack receipt-list">
                        @forelse ($purchaseOrders as $purchaseOrder)
                            <article class="receipt-card">
                                <div class="receipt-card-main">
                                    <div>
                                        <h3>{{ $purchaseOrder->number }} - {{ $purchaseOrder->vendor->name }} <span class="pill">{{ $purchaseOrderStatusLabel($purchaseOrder->status) }}</span></h3>
                                        <p>PR {{ $purchaseOrder->purchaseRequest->number }} | {{ $purchaseOrder->purchaseRequest->camp->name }}</p>
                                    </div>
                                    @if ($workflowTab !== 'history' && $currentUser->hasPermission('receipts.manage'))
                                        <div class="actions">
                                            <a class="button" href="{{ route('goods-receipts.create', array_filter([
                                                'purchaseOrder' => $purchaseOrder,
                                                'company_id' => request()->integer('company_id') ?: null,
                                            ])) }}">+ Catat Penerimaan</a>
                                        </div>
                                    @endif
                                </div>
                                @if ($workflowTab === 'history')
                                    <div class="receipt-records">
                                        @forelse ($purchaseOrder->goodsReceipts as $goodsReceipt)
                                            <a class="receipt-record" href="{{ route('goods-receipts.review', array_filter([
                                                'goodsReceipt' => $goodsReceipt,
                                                'company_id' => request()->integer('company_id') ?: null,
                                            ])) }}" title="Lihat detail penerimaan {{ $goodsReceipt->number }}">
                                                <div><span>No. penerimaan</span><strong>{{ $goodsReceipt->number }}</strong></div>
                                                <div><span>Faktur vendor</span><strong>{{ $goodsReceipt->invoice_number ?: '-' }}</strong></div>
                                                <div><span>Tanggal diterima</span><strong>{{ $goodsReceipt->received_at?->format('d M Y') ?: '-' }}</strong></div>
                                                <div><span>Diterima oleh</span><strong>{{ $goodsReceipt->received_by ?: '-' }}</strong></div>
                                                <div class="receipt-record-action"><span>Aksi</span><strong>Lihat Detail</strong></div>
                                            </a>
                                        @empty
                                            <div class="muted">Detail penerimaan belum tersedia.</div>
                                        @endforelse
                                    </div>
                                @endif
                            </article>
                        @empty
                            <div class="empty">{{ $workflowTab === 'history' ? 'Belum ada history penerimaan.' : 'Belum ada PO diterbitkan yang menunggu penerimaan.' }}</div>
                        @endforelse
                    </div>
                    @include('inventory.components.transaction-pagination')
                </section>
            @endif

