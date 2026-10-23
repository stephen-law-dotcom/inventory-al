<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Profil</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=2">
    <style>
        :root {
            --bg: #eef6f8;
            --panel: #ffffff;
            --ink: #17212b;
            --muted: #667788;
            --line: #dce8ee;
            --accent: #0b8f8a;
            --amber: #e3a72f;
            --danger: #b42318;
            --shadow: 0 16px 36px rgba(16, 42, 67, .11);
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background:
                linear-gradient(135deg, rgba(11,143,138,.11), transparent 35%),
                linear-gradient(315deg, rgba(227,167,47,.10), transparent 32%),
                var(--bg);
            color: var(--ink);
            font-family: Inter, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 14px;
            line-height: 1.45;
        }
        a { color: inherit; text-decoration: none; }
        .page { width: min(760px, calc(100% - 28px)); margin: 0 auto; padding: 24px 0; display: grid; gap: 16px; }
        .topbar, section {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: var(--shadow);
        }
        .topbar {
            padding: 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            background:
                linear-gradient(135deg, rgba(255,255,255,.96), rgba(255,255,255,.86)),
                linear-gradient(90deg, rgba(11,143,138,.13), rgba(227,167,47,.12));
        }
        h1 { margin: 0; font-size: 26px; color: #102a43; font-weight: 900; }
        p { margin: 6px 0 0; color: var(--muted); }
        section { padding: 18px; }
        form { display: grid; gap: 12px; }
        label { display: grid; gap: 6px; color: #526677; font-size: 12px; font-weight: 800; }
        input {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 11px 12px;
            background: #fff;
            color: var(--ink);
            font: inherit;
            outline: none;
        }
        input:focus { border-color: var(--accent); box-shadow: 0 0 0 4px rgba(11,143,138,.14); }
        button, .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            border: 0;
            border-radius: 8px;
            padding: 10px 14px;
            background: linear-gradient(135deg, var(--accent), #17b8ad);
            color: #fff;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 10px 18px rgba(11,143,138,.22);
        }
        .ghost { background: #edf4f7; color: #24394d; box-shadow: none; border: 1px solid var(--line); }
        .row { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end; }
        .notice { padding: 12px 14px; border-radius: 8px; border: 1px solid #f4aaa5; color: var(--danger); background: #fff6f4; font-weight: 700; }
        .signature-panel { display: grid; gap: 12px; }
        .signature-preview {
            display: grid;
            gap: 8px;
            padding: 12px;
            border: 1px dashed var(--line);
            border-radius: 7px;
            background: #fbfdfe;
        }
        .signature-preview img {
            max-width: 240px;
            max-height: 82px;
            object-fit: contain;
            mix-blend-mode: multiply;
        }
        .signature-canvas-wrap {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 7px;
            background: #fff;
            overflow: hidden;
        }
        canvas { display: block; width: 100%; height: 150px; touch-action: none; cursor: crosshair; }
        .hint { color: var(--muted); font-size: 12px; }
        .inline-check { display: flex; align-items: center; gap: 9px; font-size: 13px; }
        .inline-check input { width: auto; min-height: auto; }
        :root {
            --bg: #f4f7f9;
            --ink: #172534;
            --muted: #667687;
            --line: #dce4e9;
            --accent: #087f78;
            --shadow: 0 4px 16px rgba(28,46,61,.08);
        }
        body { background: var(--bg); }
        .page { width: min(780px, calc(100% - 32px)); padding: 20px 0 36px; gap: 14px; }
        .topbar, section { border-radius: 7px; box-shadow: var(--shadow); }
        .topbar { padding: 16px 18px; background: #fff; }
        h1 { font-size: 23px; font-weight: 800; }
        section { padding: 20px; }
        form { gap: 16px; }
        label { color: #4d6070; font-weight: 700; }
        input { min-height: 41px; border-radius: 6px; padding: 10px 11px; }
        input:focus { box-shadow: 0 0 0 3px rgba(8,127,120,.12); }
        button, .button { min-height: 39px; border-radius: 6px; background: var(--accent); box-shadow: none; font-size: 13px; }
        .ghost { background: #f3f6f8; }
        .actions { padding-top: 12px; border-top: 1px solid var(--line); }
        @media (max-width: 720px) {
            .topbar { align-items: flex-start; flex-direction: column; }
            .row { grid-template-columns: 1fr; }
            .actions { align-items: stretch; flex-direction: column; }
            button, .button { width: 100%; }
        }

        /* Profile polish */
        body { background: linear-gradient(180deg, #fbfcfd 0, var(--bg) 240px); }
        .page { width: min(860px, calc(100% - 32px)); }
        .topbar {
            align-items: flex-start;
            border-top: 4px solid var(--accent);
        }
        .topbar > div { min-width: 0; }
        .topbar h1,
        .topbar p { overflow-wrap: anywhere; }
        section {
            position: relative;
            overflow: hidden;
        }
        section::before {
            content: "";
            position: absolute;
            inset: 0 0 auto;
            height: 3px;
            background: linear-gradient(90deg, var(--accent), #e3a72f);
        }
        .signature-preview,
        .signature-canvas-wrap {
            border-color: #d7e2e8;
        }
        .signature-preview {
            background: #fbfcfd;
        }
        input {
            transition: border-color .16s ease, box-shadow .16s ease, background .16s ease;
        }
        button,
        .button {
            white-space: nowrap;
        }
        @media (max-width: 720px) {
            .page { width: min(100% - 24px, 860px); padding-top: 14px; }
            section { padding: 16px; }
        }
    </style>
</head>
<body>
<div class="page">
    <header class="topbar">
        <div>
            <h1>Edit Profil</h1>
        </div>
        <a class="button ghost" href="{{ route('dashboard') }}">Kembali</a>
    </header>

    @if ($errors->any())
        <div class="notice">{{ $errors->first() }}</div>
    @endif

    <section>
        <form method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data" data-profile-form>
            @csrf
            @method('put')
            <div class="row">
                <label>Nama <input name="name" value="{{ old('name', auth()->user()->name) }}" required></label>
                <label>Email <input name="email" type="email" value="{{ old('email', auth()->user()->email) }}" required></label>
            </div>
            <div class="row">
                <label>Password baru <input name="password" type="password" autocomplete="new-password" placeholder="Kosongkan jika tidak diganti"></label>
                <label>Konfirmasi password <input name="password_confirmation" type="password" autocomplete="new-password"></label>
            </div>
            <div class="row">
                <label>Password saat ini
                    <input name="current_password" type="password" required autocomplete="current-password" placeholder="Wajib untuk menyimpan perubahan">
                </label>
            </div>
            <div class="signature-panel">
                <label>TTD visual untuk PDF / print
                    <input name="signature_upload" type="file" accept="image/png,image/jpeg,image/webp">
                </label>
                <div class="signature-preview">
                    <strong>Preview TTD aktif</strong>
                    @if (auth()->user()->signature_image)
                        <img src="{{ auth()->user()->signature_image }}" alt="TTD {{ auth()->user()->name }}">
                        <span class="hint">Sumber: {{ auth()->user()->signature_method === 'draw' ? 'gambar langsung' : 'upload file' }}.</span>
                    @else
                        <span class="hint">Belum ada TTD visual. Dokumen tetap bisa dicetak dengan nama dan garis TTD.</span>
                    @endif
                </div>
                <label>Gambar TTD langsung
                    <span class="signature-canvas-wrap"><canvas data-signature-canvas></canvas></span>
                    <input type="hidden" name="signature_drawn" data-signature-input>
                </label>
                <div class="actions" style="padding-top:0;border-top:0">
                    <button type="button" class="ghost" data-clear-signature>Bersihkan Gambar</button>
                </div>
                <label class="inline-check">
                    <input type="checkbox" name="remove_signature" value="1">
                    Hapus TTD visual yang tersimpan
                </label>
                <p class="hint">Upload file diprioritaskan kalau upload dan gambar canvas diisi bersamaan. TTD hanya tampil sebagai bukti visual; approval tetap tercatat dari akun login.</p>
            </div>
            <div class="actions">
                <a class="button ghost" href="{{ route('dashboard') }}">Batal</a>
                <button>Simpan Profil</button>
            </div>
        </form>
    </section>
</div>
<script>
    const form = document.querySelector('[data-profile-form]');
    const canvas = document.querySelector('[data-signature-canvas]');
    const signatureInput = document.querySelector('[data-signature-input]');
    const clearButton = document.querySelector('[data-clear-signature]');
    const context = canvas?.getContext('2d');
    let drawing = false;
    let hasDrawn = false;

    function resizeCanvas() {
        if (! canvas || ! context) return;
        const rect = canvas.getBoundingClientRect();
        const ratio = window.devicePixelRatio || 1;
        canvas.width = Math.floor(rect.width * ratio);
        canvas.height = Math.floor(rect.height * ratio);
        context.setTransform(ratio, 0, 0, ratio, 0, 0);
        context.lineWidth = 2.2;
        context.lineCap = 'round';
        context.strokeStyle = '#111827';
    }

    function point(event) {
        const rect = canvas.getBoundingClientRect();
        const source = event.touches?.[0] || event;
        return { x: source.clientX - rect.left, y: source.clientY - rect.top };
    }

    function startDraw(event) {
        event.preventDefault();
        drawing = true;
        hasDrawn = true;
        const current = point(event);
        context.beginPath();
        context.moveTo(current.x, current.y);
    }

    function draw(event) {
        if (! drawing) return;
        event.preventDefault();
        const current = point(event);
        context.lineTo(current.x, current.y);
        context.stroke();
    }

    function stopDraw() {
        drawing = false;
    }

    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);
    canvas?.addEventListener('mousedown', startDraw);
    canvas?.addEventListener('mousemove', draw);
    canvas?.addEventListener('mouseup', stopDraw);
    canvas?.addEventListener('mouseleave', stopDraw);
    canvas?.addEventListener('touchstart', startDraw, { passive: false });
    canvas?.addEventListener('touchmove', draw, { passive: false });
    canvas?.addEventListener('touchend', stopDraw);

    clearButton?.addEventListener('click', () => {
        if (! canvas || ! context) return;
        context.clearRect(0, 0, canvas.width, canvas.height);
        signatureInput.value = '';
        hasDrawn = false;
    });

    form?.addEventListener('submit', () => {
        if (hasDrawn && canvas) {
            signatureInput.value = canvas.toDataURL('image/png');
        }
    });
</script>
</body>
</html>
