@if (($transactionPaginator ?? null) && $transactionPaginator->hasPages())
    <nav class="transaction-pagination" aria-label="Navigasi halaman data">
        @if ($transactionPaginator->onFirstPage())
            <span class="is-disabled">Sebelumnya</span>
        @else
            <a href="{{ $transactionPaginator->previousPageUrl() }}">Sebelumnya</a>
        @endif
        <strong>Halaman {{ $transactionPaginator->currentPage() }} dari {{ $transactionPaginator->lastPage() }}</strong>
        @if ($transactionPaginator->hasMorePages())
            <a href="{{ $transactionPaginator->nextPageUrl() }}">Berikutnya</a>
        @else
            <span class="is-disabled">Berikutnya</span>
        @endif
    </nav>
@endif
