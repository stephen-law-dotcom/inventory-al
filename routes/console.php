<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Services\InventoryIntegrityService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('inventory:check-integrity {--fix : Terapkan hanya perbaikan yang ditandai aman} {--json : Tampilkan hasil sebagai JSON}', function (InventoryIntegrityService $service) {
    $result = $service->inspect((bool) $this->option('fix'));

    if ($this->option('json')) {
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return 0;
    }

    $this->info('Pemeriksaan integritas inventory: '.strtoupper($result['mode']));
    $this->table(
        ['Level', 'Dokumen', 'Nomor', 'Masalah', 'Saat ini', 'Seharusnya', 'Tindakan'],
        collect($result['findings'])->map(fn ($finding) => [
            strtoupper($finding['severity']),
            $finding['document_type'],
            $finding['document_number'],
            $finding['message'],
            $finding['current'] ?? '-',
            $finding['expected'] ?? '-',
            $finding['fixed'] ? 'Diperbaiki' : ($finding['auto_fixable'] ? 'Bisa otomatis' : 'Manual'),
        ])->all()
    );
    $this->newLine();
    $this->line("Total: {$result['total']} | Bisa otomatis: {$result['automatic']} | Manual: {$result['manual']} | Diperbaiki: {$result['fixed']}");

    if (! $this->option('fix') && $result['automatic'] > 0) {
        $this->comment('Preview saja. Jalankan kembali dengan --fix untuk menerapkan perbaikan aman.');
    }

    return 0;
})->purpose('Periksa dan rekonsiliasi status dokumen serta stock reserved');
