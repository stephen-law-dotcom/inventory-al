<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }}</title>
    <style>
        @page { margin: 24px; }
        body { font-family: DejaVu Sans, sans-serif; color: #142b3d; font-size: 9px; }
        h1 { margin: 0 0 4px; font-size: 17px; }
        p { margin: 0 0 12px; color: #526677; }
        .meta { margin-bottom: 12px; padding: 7px; background: #eef5f6; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #b8c8d1; padding: 5px; vertical-align: top; overflow-wrap: anywhere; }
        th { background: #eaf2f4; text-align: left; font-size: 8px; }
        .right { text-align: right; }
        .multiline { white-space: pre-line; line-height: 1.4; }
        footer { margin-top: 10px; color: #6d7f8d; }
    </style>
</head>
<body>
    <h1>{{ $report['title'] }}</h1>
    <p>{{ $report['description'] }}</p>
    <div class="meta">Dicetak {{ now()->format('d M Y H:i') }} | Total {{ $report['total'] }} baris</div>
    <table>
        <thead><tr>@foreach ($report['columns'] as $column)<th class="{{ ($column['align'] ?? '') === 'right' ? 'right' : '' }}">{{ $column['label'] }}</th>@endforeach</tr></thead>
        <tbody>
        @forelse ($report['rows'] as $row)
            <tr>@foreach ($report['columns'] as $column)<td class="{{ ($column['align'] ?? '') === 'right' ? 'right' : '' }} {{ ($column['type'] ?? '') === 'multiline' ? 'multiline' : '' }}">{{ $row[$column['key']] ?? '-' }}</td>@endforeach</tr>
        @empty
            <tr><td colspan="{{ count($report['columns']) }}">Tidak ada data sesuai filter.</td></tr>
        @endforelse
        </tbody>
    </table>
    <footer>Inventory AL - {{ $report['title'] }}</footer>
</body>
</html>
