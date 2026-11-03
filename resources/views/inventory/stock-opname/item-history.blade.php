<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>History Penggunaan {{ $item->sku }} - Sistem Inventory AL</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=2">
    <style>
        :root {
            --bg: #f3f7f8;
            --panel: #ffffff;
            --ink: #102a43;
            --muted: #627789;
            --line: #d9e5ea;
            --teal: #087f78;
            --teal-dark: #066a65;
            --amber: #e7a629;
            --green: #16794b;
            --red: #b42318;
            --blue: #3d5cc7;
            --shadow: 0 12px 30px rgba(22, 48, 65, .09);
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background: linear-gradient(135deg, #f7fbfc 0%, var(--bg) 100%);
            color: var(--ink);
            font-family: Inter, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 14px;
            line-height: 1.5;
        }
        a { color: inherit; text-decoration: none; }
        .page { width: min(1220px, calc(100% - 32px)); margin: 0 auto; padding: 24px 0 42px; }
        .topbar, .panel, .stat {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: var(--shadow);
        }
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 20px 22px;
            border-top: 4px solid var(--teal);
        }
        .eyebrow { margin: 0 0 4px; color: var(--teal); font-size: 12px; font-weight: 900; text-transform: uppercase; }
        h1 { margin: 0; font-size: 27px; line-height: 1.2; letter-spacing: 0; }
        h2 { margin: 0; font-size: 17px; letter-spacing: 0; }
        h3 { margin: 0; font-size: 15px; }
        p { margin: 5px 0 0; color: var(--muted); }
        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            border: 1px solid var(--line);
            border-radius: 6px;
            padding: 9px 14px;
            background: #f4f7f9;
            color: var(--ink);
            font-weight: 800;
        }
        .button:hover { background: #e9f0f3; }
        .stat-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-top: 16px; }
        .stat { padding: 16px; border-top: 4px solid var(--teal); }
        .stat:nth-child(2) { border-top-color: var(--amber); }
        .stat:nth-child(3) { border-top-color: var(--blue); }
        .stat:nth-child(4) { border-top-color: var(--red); }
        .stat span { display: block; color: var(--muted); font-size: 12px; font-weight: 800; }
        .stat strong { display: block; margin-top: 8px; font-size: 24px; font-weight: 900; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-top: 16px; }
        .panel { padding: 18px; overflow: hidden; }
        .panel-head { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; margin-bottom: 12px; }
        .muted { color: var(--muted); }
        .pill {
            display: inline-flex;
            align-items: center;
            min-height: 24px;
            padding: 4px 9px;
            border-radius: 999px;
            background: #e8efef;
            color: #244047;
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }
        .ok { background: #dcf7ee; color: var(--green); }
        .wait { background: #fff1d6; color: #8a5a00; }
        .bad { background: #fee7e2; color: var(--red); }
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border: 1px solid var(--line);
            border-radius: 8px;
            overflow: hidden;
            background: #fff;
            font-size: 13px;
        }
        th, td { border-bottom: 1px solid var(--line); padding: 10px; text-align: left; vertical-align: top; }
        tr:last-child td { border-bottom: 0; }
        th { background: #eef7f8; color: #4d6273; font-size: 12px; font-weight: 900; }
        tbody tr:hover { background: #f7fbfc; }
        .empty {
            padding: 18px;
            border: 1px dashed var(--line);
            border-radius: 8px;
            color: var(--muted);
            background: #fbfdfe;
            text-align: center;
            font-weight: 800;
        }
        .full { grid-column: 1 / -1; }
        .usage-summary { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-bottom: 14px; }
        .usage-box { padding: 12px; border: 1px solid var(--line); border-radius: 8px; background: #fbfdfe; }
        .usage-box span { display: block; color: var(--muted); font-size: 12px; font-weight: 800; }
        .usage-box strong { display: block; margin-top: 5px; font-size: 18px; font-weight: 900; }
        @media (max-width: 900px) {
            .topbar { flex-direction: column; align-items: flex-start; }
            .stat-grid, .grid, .usage-summary { grid-template-columns: 1fr; }
            table { display: block; overflow-x: auto; }
            th, td { min-width: 130px; }
        }

        /* History polish */
        body { background: linear-gradient(180deg, #fbfcfd 0, var(--bg) 250px); }
        .page { display: grid; gap: 16px; }
        .topbar,
        .panel,
        .stat,
        .usage-box {
            border-color: #d7e2e8;
        }
        .topbar > div { min-width: 0; }
        .topbar h1,
        .topbar p { overflow-wrap: anywhere; }
        .panel {
            position: relative;
            overflow: visible;
        }
        .panel::before {
            content: "";
            position: absolute;
            inset: 0 0 auto;
            height: 3px;
            background: linear-gradient(90deg, var(--teal), var(--amber));
            border-radius: 8px 8px 0 0;
        }
        .stat,
        .usage-box {
            background: #fbfcfd;
        }
        .usage-box:hover {
            border-color: #c7d2da;
            background: #fff;
        }
        tbody tr:hover {
            background: #f2fbfb;
        }
        .button {
            white-space: nowrap;
        }
        @media (max-width: 900px) {
            .page { width: min(100% - 24px, 1180px); padding-top: 14px; }
            .panel { padding: 16px; }
        }
    </style>
</head>
<body>
    <main class="page">
        <header class="topbar">
            <div>
                <p class="eyebrow">History Penggunaan Barang</p>
                <h1>{{ $item->sku }} - {{ $item->name }}</h1>
                <p>{{ $item->category }} - {{ $item->uom }}</p>
            </div>
            <a class="button" href="{{ request()->query('from') === 'dashboard' ? route('dashboard') : route('stock-opname') }}">Kembali</a>
        </header>

        <section class="stat-grid">
            <div class="stat">
                <span>Stok saat ini</span>
                <strong>{{ $item->stock_on_hand }} {{ $item->uom }}</strong>
            </div>
            <div class="stat">
                <span>Total pemakaian tercatat</span>
                <strong>{{ $usageLogs->sum('quantity') }} {{ $item->uom }}</strong>
            </div>
            <div class="stat">
                <span>Jumlah transaksi stok</span>
                <strong>{{ $stockMovements->count() + $campStockMovements->count() }}</strong>
            </div>
            <div class="stat">
                <span>Pemakaian terakhir</span>
                <strong>{{ $usageLogs->first()?->used_at?->format('d M Y') ?? '-' }}</strong>
            </div>
        </section>

        <section class="grid">
            <article class="panel full">
                <div class="panel-head">
                    <div>
                        <h2>Ringkasan Pemakaian per Unit</h2>
                    </div>
                </div>
                @if ($usageLogs->isNotEmpty())
                    <div class="usage-summary">
                        @foreach ($usageLogs->groupBy('unit_id') as $unitLogs)
                            @php $latestUsage = $unitLogs->sortByDesc('used_at')->first(); @endphp
                            <div class="usage-box">
                                <span>{{ $latestUsage?->unit?->code ?? 'Tanpa unit' }}</span>
                                <strong>{{ $unitLogs->sum('quantity') }} {{ $item->uom }}</strong>
                                <div class="muted">{{ $latestUsage?->unit?->camp?->name ?? 'Camp belum terisi' }} - terakhir {{ $latestUsage?->used_at?->format('d M Y') ?? '-' }}</div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="empty">Belum ada histori pemakaian untuk barang ini.</div>
                @endif
            </article>

            <article class="panel full">
                <div class="panel-head">
                    <div>
                        <h2>Histori Pemakaian</h2>
                    </div>
                </div>
                @if ($usageLogs->isNotEmpty())
                    <table>
                        <thead><tr><th>Tanggal</th><th>Unit</th><th>Tipe</th><th>Qty</th><th>Operator</th><th>Lokasi</th><th>Shift/HM</th><th>Ref</th></tr></thead>
                        <tbody>
                        @foreach ($usageLogs as $usageLog)
                            <tr>
                                <td>{{ $usageLog->used_at?->format('d M Y') }}</td>
                                <td>{{ $usageLog->unit?->code ?? '-' }}<br><span class="muted">{{ $usageLog->unit?->camp?->name }}</span></td>
                                <td>{{ strtoupper($usageLog->usage_type) }}</td>
                                <td>{{ $usageLog->quantity }} {{ $usageLog->uom }}</td>
                                <td>{{ $usageLog->mechanic_or_operator ?: '-' }}</td>
                                <td>{{ $usageLog->location ?: '-' }}</td>
                                <td>{{ $usageLog->shift ?: '-' }}{{ $usageLog->meter_reading ? ' / '.$usageLog->meter_reading : '' }}</td>
                                <td>{{ $usageLog->reference_number ?: '-' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="empty">Belum ada histori pemakaian untuk barang ini.</div>
                @endif
            </article>

            <article class="panel full">
                <div class="panel-head">
                    <div>
                        <h2>Kartu Stok Pendukung</h2>
                    </div>
                </div>
                @if ($stockMovements->isNotEmpty())
                    <table>
                        <thead><tr><th>Waktu</th><th>Tipe</th><th>Qty</th><th>Sebelum</th><th>Sesudah</th><th>User</th></tr></thead>
                        <tbody>
                        @foreach ($stockMovements as $movement)
                            <tr>
                                <td>{{ $movement->created_at?->format('d M Y H:i') }}</td>
                                <td>{{ str_replace('_', ' ', $movement->type) }}</td>
                                <td>{{ $movement->quantity > 0 ? '+' : '' }}{{ $movement->quantity }}</td>
                                <td>{{ $movement->stock_before }}</td>
                                <td>{{ $movement->stock_after }}</td>
                                <td>{{ $movement->user?->name ?? '-' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="empty">Belum ada kartu stok untuk barang ini.</div>
                @endif
            </article>

            <article class="panel full">
                <div class="panel-head">
                    <div><h2>Mutasi Stok Camp</h2></div>
                </div>
                @if ($campStockMovements->isNotEmpty())
                    <table>
                        <thead><tr><th>Waktu</th><th>Camp</th><th>Tipe</th><th>Qty</th><th>Sebelum</th><th>Sesudah</th><th>User</th></tr></thead>
                        <tbody>
                        @foreach ($campStockMovements as $movement)
                            <tr>
                                <td>{{ $movement->created_at?->format('d M Y H:i') }}</td>
                                <td>{{ $movement->camp?->name ?? '-' }}</td>
                                <td>{{ str_replace('_', ' ', $movement->type) }}</td>
                                <td>{{ $movement->quantity > 0 ? '+' : '' }}{{ $movement->quantity }}</td>
                                <td>{{ $movement->stock_before }}</td>
                                <td>{{ $movement->stock_after }}</td>
                                <td>{{ $movement->user?->name ?? '-' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="empty">Belum ada mutasi stock opname di camp.</div>
                @endif
            </article>
        </section>
    </main>
</body>
</html>
