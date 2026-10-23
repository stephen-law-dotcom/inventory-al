@if ($activeMenu === 'maintenance')
    @php
        $formatBytes = function (int $bytes): string {
            if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2, ',', '.').' GB';
            if ($bytes >= 1048576) return number_format($bytes / 1048576, 2, ',', '.').' MB';
            return number_format(max(0, $bytes) / 1024, 2, ',', '.').' KB';
        };
    @endphp

    <section>
        <h2>Backup Sistem</h2>
        <div class="row" style="margin-top:14px">
            <div><strong>Database dan file lampiran</strong><p>Paket ZIP tersimpan di area private aplikasi.</p></div>
            <form method="post" action="{{ route('system-backups.store') }}" data-confirm="Buat backup database dan seluruh file lampiran sekarang?">
                @csrf
                <button @disabled(! $backupToolsAvailable)>Buat Backup</button>
            </form>
        </div>
        @unless ($backupToolsAvailable)
            <div class="notice" style="margin-top:12px">Tool MySQL atau ekstensi ZIP belum tersedia pada server.</div>
        @endunless
    </section>

    @if ($currentUser->isAdmin())
        <section>
            <h2>Import dan Restore</h2>
            <form method="post" action="{{ route('system-backups.import') }}" enctype="multipart/form-data" data-confirm="Restore akan mengganti database dan file aplikasi dengan isi backup. Lanjutkan?" style="margin-top:14px">
                @csrf
                <div class="three">
                    <label>Paket backup<input type="file" name="backup_file" accept=".zip,application/zip" required></label>
                    <label>Password akun<input type="password" name="password" autocomplete="current-password" required></label>
                    <label>Konfirmasi<input name="confirmation" placeholder="Ketik RESTORE" required></label>
                </div>
                <div class="actions"><button>Import dan Restore</button></div>
            </form>
        </section>
    @endif

    <section>
        <h2>Riwayat Backup</h2>
        <div class="stack" style="margin-top:14px">
            @forelse ($systemBackups as $backup)
                <article>
                    <div class="row">
                        <div>
                            <h3>{{ $backup->filename }}</h3>
                            <p>{{ $backup->created_at->format('d M Y H:i') }} oleh {{ $backup->createdBy?->name ?: 'Sistem' }} - {{ $formatBytes((int) $backup->size) }}</p>
                            <p>SHA-256 {{ substr($backup->checksum, 0, 16) }}... - {{ str_replace('_', ' ', $backup->source) }}</p>
                        </div>
                        <div class="actions">
                            <span class="pill {{ $backup->status === 'ready' ? 'ok' : ($backup->status === 'restored' ? 'wait' : 'bad') }}">{{ $backup->status }}</span>
                            <a class="button ghost" href="{{ route('system-backups.download', $backup) }}">Download</a>
                        </div>
                    </div>
                    @if ($backup->restored_at)
                        <p>Direstore {{ $backup->restored_at->format('d M Y H:i') }} oleh {{ $backup->restoredBy?->name ?: 'Administrator' }}</p>
                    @endif
                    <div class="actions" style="margin-top:12px">
                        @if ($currentUser->isAdmin())
                            <details>
                                <summary class="button ghost">Restore</summary>
                                <form method="post" action="{{ route('system-backups.restore', $backup) }}" data-confirm="Restore akan mengganti seluruh database dan file lampiran. Lanjutkan?" style="margin-top:10px; min-width:min(520px, 80vw)">
                                    @csrf
                                    <div class="two">
                                        <label>Password akun<input type="password" name="password" autocomplete="current-password" required></label>
                                        <label>Konfirmasi<input name="confirmation" placeholder="Ketik RESTORE" required></label>
                                    </div>
                                    <div class="actions"><button>Jalankan Restore</button></div>
                                </form>
                            </details>
                        @endif
                        <form method="post" action="{{ route('system-backups.destroy', $backup) }}" data-confirm="Hapus file backup {{ $backup->filename }}?">
                            @csrf @method('delete')
                            <button class="ghost">Hapus</button>
                        </form>
                    </div>
                </article>
            @empty
                <div class="empty">Belum ada backup sistem.</div>
            @endforelse
        </div>
    </section>
@endif
