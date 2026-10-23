@if ($activeMenu === 'reports')
    @php
        $report = $reportDataset;
        $filterFields = $report['filter_fields'];
        $filters = $report['filters'];
        $exportQuery = request()->except('page');
        $activeFilters = collect([
            $filters['from_date'] || $filters['to_date'] ? 'Periode: '.($filters['from_date'] ?: 'awal').' s.d. '.($filters['to_date'] ?: 'sekarang') : null,
            $filters['company'] ? 'Perusahaan: '.$filters['company'] : null,
            $filters['camp_id'] ? 'Site: '.($camps->firstWhere('id', (int) $filters['camp_id'])?->name ?: $filters['camp_id']) : null,
            $filters['unit_id'] ? 'Unit: '.($units->firstWhere('id', (int) $filters['unit_id'])?->code ?: $filters['unit_id']) : null,
            $filters['item_id'] ? 'Barang: '.($items->firstWhere('id', (int) $filters['item_id'])?->sku ?: $filters['item_id']) : null,
            $filters['vendor_id'] ? 'Vendor: '.($vendors->firstWhere('id', (int) $filters['vendor_id'])?->name ?: $filters['vendor_id']) : null,
            $filters['status'] ? 'Status: '.($report['status_options'][$filters['status']] ?? $filters['status']) : null,
        ])->filter();
        $companies = $camps->pluck('company_name')->filter()->unique()->sort()->values();
        $reportPath = str_replace('_', '-', $report['type']);
    @endphp

    <section class="filter-panel report-toolbar">
        <div class="panel-head">
            <div class="report-filter-heading">
                <span class="section-kicker">REPORT OPERASIONAL</span>
                <h2>{{ $report['title'] }}</h2>
                <p>{{ $report['description'] }}</p>
            </div>
            <div class="report-export-actions">
                <a class="button ghost" href="{{ route('reports.export', ['reportType' => $reportPath, 'format' => 'csv'] + $exportQuery) }}">Export CSV</a>
                <a class="button ghost" href="{{ route('reports.export', ['reportType' => $reportPath, 'format' => 'xlsx'] + $exportQuery) }}">Export Excel</a>
                <a class="button ghost" href="{{ route('reports.export', ['reportType' => $reportPath, 'format' => 'pdf'] + $exportQuery) }}">Export PDF</a>
            </div>
        </div>

        <form method="get" action="{{ $reportNav[$report['type']][1] ?? route('reports') }}">
            <div class="filter-grid">
                @if (in_array('date', $filterFields, true))
                    <label>Dari tanggal<input name="from_date" type="date" value="{{ $filters['from_date'] }}"></label>
                    <label>Sampai tanggal<input name="to_date" type="date" value="{{ $filters['to_date'] }}"></label>
                @endif
                @if (in_array('company', $filterFields, true))
                    <label>Perusahaan<select name="company"><option value="">Semua perusahaan</option>@foreach ($companies as $companyName)<option value="{{ $companyName }}" @selected($filters['company'] === $companyName)>{{ $companyName }}</option>@endforeach</select></label>
                @endif
                @if (in_array('camp', $filterFields, true))
                    <label>Site<select name="camp_id"><option value="">Semua site</option>@foreach ($camps as $camp)<option value="{{ $camp->id }}" @selected((int) $filters['camp_id'] === $camp->id)>{{ $camp->company_name }} - {{ $camp->name }}</option>@endforeach</select></label>
                @endif
                @if (in_array('unit', $filterFields, true))
                    <label>Unit<select name="unit_id"><option value="">Semua unit</option>@foreach ($units as $unit)<option value="{{ $unit->id }}" @selected((int) $filters['unit_id'] === $unit->id)>{{ $unit->code }} - {{ $unit->name }}</option>@endforeach</select></label>
                @endif
                @if (in_array('item', $filterFields, true))
                    <label>Barang<select name="item_id"><option value="">Semua barang</option>@foreach ($items as $item)<option value="{{ $item->id }}" @selected((int) $filters['item_id'] === $item->id)>{{ $item->sku }} - {{ $item->name }}</option>@endforeach</select></label>
                @endif
                @if (in_array('vendor', $filterFields, true))
                    <label>Vendor<select name="vendor_id"><option value="">Semua vendor</option>@foreach ($vendors as $vendor)<option value="{{ $vendor->id }}" @selected((int) $filters['vendor_id'] === $vendor->id)>{{ $vendor->name }}</option>@endforeach</select></label>
                @endif
                @if (in_array('status', $filterFields, true))
                    <label>{{ $report['type'] === 'activity_logs' ? 'Event' : ($report['type'] === 'stock_movements' ? 'Tipe mutasi' : 'Status') }}
                        <select name="status"><option value="">Semua status</option>@foreach ($report['status_options'] as $statusValue => $statusLabel)<option value="{{ $statusValue }}" @selected($filters['status'] === $statusValue)>{{ $statusLabel }}</option>@endforeach</select>
                    </label>
                @endif
            </div>
            <div class="actions filter-actions">
                <a class="button ghost" href="{{ $reportNav[$report['type']][1] ?? route('reports') }}">Reset</a>
                <button>Terapkan Filter</button>
            </div>
        </form>
        @if ($activeFilters->isNotEmpty())
            <div class="filter-chips">@foreach ($activeFilters as $activeFilter)<span class="filter-chip">{{ $activeFilter }}</span>@endforeach</div>
        @endif
    </section>

    <div class="report-grid">
        @foreach ($report['kpis'] as $kpi)
            <div class="card stat"><span>{{ $kpi['label'] }}</span><strong>{{ $kpi['value'] }}</strong><span>{{ $kpi['note'] }}</span></div>
        @endforeach
    </div>

    <section class="report-table-section">
        <div class="report-table-head">
            <div><h2>Detail Data</h2><p>Menampilkan {{ $report['rows']->firstItem() ?? 0 }}-{{ $report['rows']->lastItem() ?? 0 }} dari {{ $report['total'] }} baris</p></div>
            <span class="filter-chip">25 baris per halaman</span>
        </div>
        <div class="table-wrap report-table-wrap">
            <table class="report-table">
                <thead><tr>@foreach ($report['columns'] as $column)<th class="{{ ($column['align'] ?? '') === 'right' ? 'is-numeric' : '' }}">{{ $column['label'] }}</th>@endforeach</tr></thead>
                <tbody>
                @forelse ($report['rows'] as $row)
                    <tr @if (! empty($row['_url'])) class="clickable-row" data-href="{{ $row['_url'] }}" @endif>
                        @foreach ($report['columns'] as $column)
                            <td class="{{ ($column['align'] ?? '') === 'right' ? 'is-numeric' : '' }}">
                                @if (($column['type'] ?? null) === 'status')
                                    <span class="pill {{ $row['_status_tone'] ?? '' }}">{{ $row[$column['key']] ?? '-' }}</span>
                                @elseif (($column['type'] ?? null) === 'multiline')
                                    <span class="report-multiline">{{ $row[$column['key']] ?? '-' }}</span>
                                @else
                                    {{ $row[$column['key']] ?? '-' }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($report['columns']) }}"><div class="empty">Tidak ada data sesuai filter.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($report['rows']->hasPages())
            <div class="transaction-pagination">
                @if ($report['rows']->onFirstPage())<span class="is-disabled">Sebelumnya</span>@else<a href="{{ $report['rows']->previousPageUrl() }}">Sebelumnya</a>@endif
                <strong>Halaman {{ $report['rows']->currentPage() }} dari {{ $report['rows']->lastPage() }}</strong>
                @if ($report['rows']->hasMorePages())<a href="{{ $report['rows']->nextPageUrl() }}">Berikutnya</a>@else<span class="is-disabled">Berikutnya</span>@endif
            </div>
        @endif
    </section>
@endif
