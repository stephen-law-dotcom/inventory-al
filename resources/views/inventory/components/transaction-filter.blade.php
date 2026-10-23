@php
    $filterStatuses = match ($activeMenu) {
        'requests', 'approvals' => [
            'draft' => 'Draft',
            'submitted' => 'Menunggu Approval',
            'logistic_checked' => 'Diperiksa Logistik',
            'waiting_manager_camp' => 'Menunggu Manager Camp',
            'ready_for_quotation' => 'Siap Harga Vendor',
            'vendor_selected' => 'Vendor Terpilih',
            'po_created' => 'PO Dibuat',
            'goods_received' => 'Selesai Diterima',
            'rejected' => 'Ditolak',
        ],
        'vendor-prices' => [
            'ready_for_quotation' => 'Siap Input Harga',
            'vendor_selected' => 'Vendor Terpilih',
            'waiting_director' => 'Menunggu Direktur',
            'po_created' => 'PO Dibuat',
            'goods_received' => 'Selesai Diterima',
            'rejected' => 'Ditolak',
        ],
        'purchase-orders' => request('tab') === 'approval'
            ? [
                'waiting_head_purchasing' => 'Menunggu Kepala Purchasing',
                'waiting_director' => 'Menunggu Direktur',
            ]
            : [
                'draft' => 'Draft',
                'on_progress' => 'On Progress',
                'submitted' => 'Submitted',
                'issued' => 'Dikirim ke Vendor',
                'partial_received' => 'Diterima Sebagian',
                'received' => 'Selesai Diterima',
                'rejected' => 'Ditolak',
                'cancelled' => 'Dibatalkan',
            ],
        'receipts' => [
            'submitted' => 'Submitted',
            'issued' => 'Dikirim ke Vendor',
            'partial_received' => 'Diterima Sebagian',
        ],
        'credit-notes' => [
            'draft' => 'Draft',
            'requested' => 'Diajukan',
            'processing' => 'Diproses',
            'credited' => 'Credited',
            'closed' => 'Selesai',
            'cancelled' => 'Dibatalkan',
        ],
        'dispatches' => [
            'draft' => 'Draft',
            'sent' => 'Dalam Perjalanan',
            'partial_received' => 'Diterima Sebagian',
            'received_good' => 'Diterima Lengkap',
            'received_with_issue' => 'Diterima Bermasalah',
            'closed' => 'Selesai',
            'cancelled' => 'Dibatalkan',
        ],
        default => [],
    };
    $searchPlaceholder = match ($activeMenu) {
        'requests', 'approvals' => 'Cari nomor OR, pemohon, site, part number...',
        'vendor-prices' => 'Cari nomor OR, barang, vendor, part number...',
        'purchase-orders' => 'Cari nomor PO/OR, vendor, barang...',
        'receipts' => 'Cari nomor PO, faktur, vendor, barang...',
        'credit-notes' => 'Cari nomor CN/PO, faktur, vendor, barang...',
        'usage' => 'Cari unit, barang, operator, lokasi, atau referensi...',
        'dispatches' => 'Cari nomor dokumen, PO/OR, site, barang, atau resi...',
        default => 'Cari data transaksi...',
    };
    $hasActiveTransactionFilter = collect($transactionFilters ?? [])->filter()->isNotEmpty();
@endphp

<form class="transaction-toolbar" method="get" action="{{ url()->current() }}">
    @if (request('tab'))
        <input type="hidden" name="tab" value="{{ request('tab') }}">
    @endif
    @if ($activeCompanyId)
        <input type="hidden" name="company_id" value="{{ $activeCompanyId }}">
    @endif
    <label class="transaction-search">
        <span>Pencarian</span>
        <input type="search" name="search" value="{{ $transactionFilters['search'] ?? '' }}" placeholder="{{ $searchPlaceholder }}">
    </label>
    @if ($filterStatuses)
        <label>
            <span>Status</span>
            <select name="status">
                <option value="">Semua status</option>
                @foreach ($filterStatuses as $statusValue => $statusLabel)
                    <option value="{{ $statusValue }}" @selected(($transactionFilters['status'] ?? '') === $statusValue)>{{ $statusLabel }}</option>
                @endforeach
            </select>
        </label>
    @endif
    <label>
        <span>Dari tanggal</span>
        <input type="date" name="date_from" value="{{ $transactionFilters['date_from'] ?? '' }}">
    </label>
    <label>
        <span>Sampai tanggal</span>
        <input type="date" name="date_to" value="{{ $transactionFilters['date_to'] ?? '' }}">
    </label>
    <div class="transaction-toolbar-actions">
        <button type="submit">Terapkan</button>
        @if ($hasActiveTransactionFilter)
            <a class="button ghost" href="{{ url()->current() }}{{ request('tab') || $activeCompanyId ? '?'.http_build_query(array_filter(['tab' => request('tab'), 'company_id' => $activeCompanyId])) : '' }}">Reset</a>
        @endif
    </div>
</form>

@if (($transactionPaginator ?? null) && $transactionPaginator->total() > 0)
    <div class="transaction-result-meta">
        <span>Menampilkan {{ $transactionPaginator->firstItem() }}-{{ $transactionPaginator->lastItem() }} dari {{ $transactionPaginator->total() }} data</span>
        @if ($hasActiveTransactionFilter)
            <span class="pill ok">Filter aktif</span>
        @endif
    </div>
@endif

