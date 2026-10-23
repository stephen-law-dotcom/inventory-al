<div class="master-toolbar">
    <div class="master-primary-action">
        @if ($canImport)
            <a class="button" href="{{ $createUrl }}">+ {{ $createLabel }}</a>
        @endif
    </div>
    <div class="master-transfer-actions">
        @if ($canImport)
            <form class="master-import-form" method="post" action="{{ route('master.import', $type) }}" enctype="multipart/form-data" data-confirm="Import akan menambah data baru dan memperbarui data dengan kode unik yang sama. Lanjutkan?">
                @csrf
                <label class="button ghost" title="Import file XLSX, XLS, atau CSV">
                    Import Excel
                    <input type="file" name="import_file" accept=".xlsx,.xls,.csv" required onchange="this.form.requestSubmit()">
                </label>
            </form>
        @endif
        <a class="button ghost" href="{{ route('master.export', $type) }}" title="Export data sekaligus template import">Export Excel</a>
    </div>
</div>
