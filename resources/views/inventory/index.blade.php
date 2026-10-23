@php
    $purchaseOrderStatusLabel = fn ($status) => [
        'draft' => 'Draft',
        'on_progress' => 'On Progress',
        'submitted' => 'Submitted',
        'issued' => 'Submitted',
        'partial_received' => 'Partial Received',
        'received' => 'Received',
        'rejected' => 'Ditolak',
    ][$status] ?? str_replace('_', ' ', (string) $status);
    $creditNoteStatusLabel = fn ($status) => [
        'draft' => 'Draft',
        'sent_to_vendor' => 'Dikirim ke Vendor',
        'credited' => 'Credited / Dana Kembali',
        'closed' => 'Selesai',
    ][$status] ?? str_replace('_', ' ', (string) $status);
    $workflowStatusLabel = fn ($status) => [
        'draft' => 'Draft',
        'submitted' => 'Menunggu Logistik',
        'logistic_checked' => 'Menunggu Chief Mechanic',
        'waiting_manager_camp' => 'Menunggu Manager Camp',
        'ready_for_quotation' => 'Siap Input Harga',
        'vendor_selected' => 'Vendor Dipilih',
        'ready_for_po' => 'Siap Dibuat PO',
        'po_created' => 'PO Dibuat',
        'po_issued' => 'PO Submitted',
        'partial_received' => 'Diterima Sebagian',
        'goods_received' => 'Selesai Diterima',
        'waiting_director' => 'Menunggu Direktur',
        'rejected' => 'Ditolak',
        'pending' => 'Menunggu',
        'waiting' => 'Menunggu',
        'approved' => 'Disetujui',
        'not_required' => 'Tidak Perlu',
        'pending_equipment' => 'Menunggu Peralatan',
        'pending_director' => 'Menunggu Direktur',
    ][$status] ?? ucwords(str_replace('_', ' ', (string) $status));
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sistem Inventory AL</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=2">
    <style>
        :root {
            --bg: #eef6f8;
            --bg-soft: #f8fbfc;
            --sidebar: #102a43;
            --sidebar-deep: #071827;
            --sidebar-soft: #1d4e5f;
            --panel: #ffffff;
            --panel-tint: #f9fcfd;
            --ink: #17212b;
            --muted: #667788;
            --line: #dce8ee;
            --line-strong: #bfd1dc;
            --accent: #0b8f8a;
            --accent-dark: #066d70;
            --accent-soft: #ddf7f4;
            --accent-glow: rgba(11,143,138,.18);
            --coral: #ef6f5e;
            --amber: #e3a72f;
            --indigo: #4f68c6;
            --warn: #9a6400;
            --danger: #b42318;
            --good: #087f5b;
            --shadow: 0 16px 36px rgba(16, 42, 67, .11);
            --shadow-soft: 0 8px 22px rgba(16, 42, 67, .08);
            --shadow-strong: 0 24px 54px rgba(16, 42, 67, .16);
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: var(--bg);
            color: var(--ink);
            font-family: Inter, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 14px;
            line-height: 1.45;
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }
        a { color: inherit; text-decoration: none; }
        .app { min-height: 100vh; display: grid; grid-template-columns: 276px 1fr; }
        .sidebar {
            position: sticky;
            top: 0;
            z-index: 3500;
            height: 100vh;
            background:
                linear-gradient(180deg, rgba(16,42,67,.98) 0%, rgba(7,24,39,.98) 100%),
                linear-gradient(135deg, rgba(227,167,47,.20), transparent 35%);
            color: #fff;
            padding: 22px 16px;
            display: flex;
            flex-direction: column;
            gap: 18px;
            overflow-y: auto;
            overflow-x: hidden;
            box-shadow: 12px 0 32px rgba(12, 35, 31, .18);
            scrollbar-color: rgba(255,255,255,.28) transparent;
            scrollbar-width: thin;
        }
        .brand { padding: 12px 12px 18px; border-bottom: 1px solid rgba(255,255,255,.14); display: grid; grid-template-columns: 44px 1fr; gap: 12px; align-items: center; }
        .brand-mark { width: 44px; height: 44px; border-radius: 8px; display: grid; place-items: center; background: linear-gradient(135deg, #f8fbfc, #dff7f4); color: var(--accent-dark); box-shadow: 0 12px 22px rgba(0,0,0,.18); border: 1px solid rgba(255,255,255,.35); }
        .brand-mark svg { width: 27px; height: 27px; stroke: currentColor; stroke-width: 2; fill: none; stroke-linecap: round; stroke-linejoin: round; }
        .brand strong { display: block; font-size: 21px; letter-spacing: 0; font-weight: 900; }
        .brand span { display: block; margin-top: 6px; color: #c8ddea; font-size: 12px; line-height: 1.45; }
        .nav { display: grid; gap: 6px; }
        .nav a {
            display: flex;
            align-items: center;
            gap: 10px;
            min-height: 44px;
            padding: 10px 12px;
            border-radius: 8px;
            color: #d9e8ef;
            font-size: 14px;
            font-weight: 700;
            border: 1px solid transparent;
            transition: background .18s ease, border-color .18s ease, transform .18s ease;
        }
        .nav a.active, .nav a:hover { background: rgba(255,255,255,.12); border-color: rgba(255,255,255,.16); color: #fff; transform: translateX(2px); }
        .nav .ico {
            width: 28px;
            height: 28px;
            display: inline-grid;
            place-items: center;
            border-radius: 8px;
            background: rgba(255,255,255,.1);
            font-weight: 800;
            font-size: 11px;
            color: #85efe7;
        }
        .nav .ico svg { width: 17px; height: 17px; stroke: currentColor; stroke-width: 2; fill: none; stroke-linecap: round; stroke-linejoin: round; }
        .nav a.active .ico { background: #f2b84b; color: #071827; }
        .subnav { display: grid; gap: 4px; margin: -1px 0 8px 40px; }
        .subnav a { min-height: 34px; padding: 8px 10px; border-radius: 8px; font-size: 13px; color: #c8ddea; font-weight: 700; }
        .subnav a.active, .subnav a:hover { background: rgba(255,255,255,.13); color: #fff; }
        .subnav-label { margin: 9px 10px 2px; color: #7fa6b8; font-size: 10px; font-weight: 900; text-transform: uppercase; }
        .sidebar-bottom {
            margin-top: auto;
            padding: 10px 14px 0;
            color: rgba(200,221,234,.70);
            font-size: 12px;
            line-height: 1.45;
        }
        .sidebar-bottom strong { display: block; color: rgba(255,255,255,.86); font-size: 13px; margin-bottom: 5px; }
        .sidebar-bottom span { display: block; }
        .logout { width: 100%; border: 1px solid rgba(239,111,94,.34); background: linear-gradient(135deg, #d9483b, #ef6f5e); color: #fff; box-shadow: 0 10px 18px rgba(239,111,94,.18); }
        .logout:hover { background: linear-gradient(135deg, #bf352a, #e25748); box-shadow: 0 12px 22px rgba(239,111,94,.22); }
        .content { min-width: 0; }
        .topbar {
            position: sticky;
            top: 0;
            z-index: 2500;
            min-height: 96px;
            background:
                linear-gradient(135deg, rgba(255,255,255,.95), rgba(255,255,255,.84)),
                linear-gradient(90deg, rgba(11,143,138,.15), rgba(227,167,47,.14));
            border-bottom: 1px solid var(--line);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 16px 28px;
            backdrop-filter: blur(8px);
        }
        .topbar h1 { margin: 0; font-size: 29px; letter-spacing: 0; color: #102a43; font-weight: 900; }
        .topbar p { margin: 5px 0 0; color: var(--muted); font-size: 13px; }
        .topbar-tools { display: flex; align-items: center; justify-content: flex-end; gap: 10px; flex-wrap: wrap; }
        .account-menu { position: relative; }
        .notification-menu { position: relative; }
        .notification-menu summary {
            list-style: none;
            min-height: 34px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 12px;
            border-radius: 999px;
            background: #fff;
            border: 1px solid var(--line);
            box-shadow: var(--shadow-soft);
            cursor: pointer;
            color: #102a43;
            font-weight: 900;
            font-size: 12px;
        }
        .notification-menu summary::-webkit-details-marker { display: none; }
        .notification-menu[open] summary { border-color: var(--accent); box-shadow: var(--shadow); }
        .notification-badge { min-width: 20px; height: 20px; padding: 0 6px; border-radius: 999px; display: inline-grid; place-items: center; background: var(--coral); color: #fff; font-size: 11px; }
        .notification-dropdown {
            position: absolute;
            right: 0;
            z-index: 4100;
            width: min(380px, calc(100vw - 32px));
            max-height: min(520px, calc(100vh - 110px));
            margin-top: 8px;
            padding: 6px;
            border-radius: 8px;
            background: #fff;
            border: 1px solid var(--line);
            box-shadow: var(--shadow-strong);
            overflow-y: auto;
            overscroll-behavior: contain;
        }
        .notification-dropdown form { margin: 0; }
        .notification-dropdown .notification-item {
            display: grid;
            grid-template-columns: 8px minmax(0, 1fr);
            align-items: start;
            gap: 10px;
            width: 100%;
            min-height: 0;
            padding: 11px 10px;
            border: 0;
            border-bottom: 1px solid var(--line);
            border-radius: 4px;
            background: #fff;
            color: var(--ink);
            box-shadow: none;
            text-align: left;
            white-space: normal;
            cursor: pointer;
        }
        .notification-dropdown form:last-child .notification-item,
        .notification-dropdown > .notification-item:last-child { border-bottom: 0; }
        .notification-dropdown .notification-item:hover { background: #f5f9fa; box-shadow: none; transform: none; }
        .notification-dropdown .notification-item.unread { background: #f2fbf9; }
        .notification-dot { width: 7px; height: 7px; margin-top: 5px; border-radius: 50%; background: #cbd6dc; }
        .notification-item.unread .notification-dot { background: var(--accent); box-shadow: 0 0 0 3px rgba(11,143,138,.12); }
        .notification-copy { display: grid; min-width: 0; gap: 3px; }
        .notification-dropdown .notification-copy strong { color: #102a43; font-size: 13px; line-height: 1.3; }
        .notification-body {
            display: -webkit-box;
            overflow: hidden;
            color: var(--muted);
            font-size: 12px;
            font-weight: 500;
            line-height: 1.4;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
        }
        .notification-time { color: #78909f; font-size: 11px; font-weight: 700; line-height: 1.3; }
        .notification-empty { cursor: default; }
        .account-menu summary {
            list-style: none;
            display: flex;
            align-items: center;
            gap: 10px;
            min-height: 42px;
            padding: 7px 10px;
            border-radius: 8px;
            background: #fff;
            border: 1px solid var(--line);
            box-shadow: var(--shadow-soft);
            cursor: pointer;
        }
        .account-menu summary::-webkit-details-marker { display: none; }
        .account-menu[open] summary { border-color: var(--accent); box-shadow: var(--shadow); }
        .account-dropdown {
            position: absolute;
            right: 0;
            z-index: 4000;
            min-width: 245px;
            margin-top: 8px;
            padding: 10px;
            border-radius: 8px;
            background: #fff;
            border: 1px solid var(--line);
            box-shadow: var(--shadow-strong);
            display: grid;
            gap: 9px;
            isolation: isolate;
        }
        .account-avatar { width: 30px; height: 30px; border-radius: 8px; display: grid; place-items: center; background: linear-gradient(135deg, var(--accent), var(--amber)); color: #fff; font-size: 12px; font-weight: 900; }
        .account-meta { display: grid; gap: 1px; min-width: 0; }
        .account-meta strong { color: #102a43; font-size: 13px; font-weight: 900; max-width: 170px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .account-meta span { color: var(--muted); font-size: 11px; max-width: 170px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .account-caret { color: var(--muted); font-size: 12px; font-weight: 900; }
        .account-dropdown .role-badge { width: fit-content; }
        .account-action { width: 100%; justify-content: center; box-shadow: none; }
        .role-badge { min-height: 30px; padding: 6px 10px; border-radius: 999px; background: #fff8e8; color: #8a5a00; border: 1px solid #f2d28a; font-size: 12px; font-weight: 900; box-shadow: var(--shadow-soft); }
        .date-badge { min-height: 34px; padding: 7px 12px; background: #fff; border: 1px solid var(--line); box-shadow: var(--shadow-soft); }
        main { position: relative; z-index: 1; padding: 24px; display: grid; gap: 20px; }
        section, article, .card {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 18px;
            box-shadow: var(--shadow-soft);
        }
        section { background: linear-gradient(180deg, #fff, var(--panel-tint)); position: relative; }
        section::before { content: ""; position: absolute; inset: 0 auto 0 0; width: 4px; border-radius: 8px 0 0 8px; background: linear-gradient(180deg, var(--accent), var(--amber)); }
        article { box-shadow: none; background: #fff; transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease; }
        article:hover { transform: translateY(-1px); box-shadow: var(--shadow-soft); border-color: var(--line-strong); }
        h2 { margin: 0 0 16px; font-size: 18px; color: #102a43; display: flex; align-items: center; gap: 10px; font-weight: 900; }
        h2::before { content: ""; width: 10px; height: 10px; border-radius: 999px; background: var(--coral); box-shadow: 0 0 0 5px rgba(239,111,94,.12); }
        h3 { margin: 0 0 10px; font-size: 15px; color: #23384d; font-weight: 900; }
        p { margin: 0; color: var(--muted); line-height: 1.45; }
        form { display: grid; gap: 10px; }
        label { display: grid; gap: 6px; color: #526677; font-size: 12px; font-weight: 800; }
        input, select, textarea {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 10px 11px;
            background: #fff;
            color: var(--ink);
            font: inherit;
            outline: none;
            transition: border-color .18s ease, box-shadow .18s ease, background .18s ease;
        }
        input:focus, select:focus, textarea:focus { border-color: var(--accent); box-shadow: 0 0 0 4px rgba(11,143,138,.14); }
        textarea { min-height: 74px; resize: vertical; }
        button, .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 8px;
            padding: 10px 13px;
            background: linear-gradient(135deg, var(--accent), #17b8ad);
            color: #fff;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 10px 18px rgba(11,143,138,.22);
            transition: transform .16s ease, background .16s ease, box-shadow .16s ease;
        }
        button:hover, .button:hover { background: linear-gradient(135deg, var(--accent-dark), var(--accent)); transform: translateY(-1px); box-shadow: 0 14px 24px rgba(11,143,138,.25); }
        .ghost { background: #edf4f7; color: #24394d; box-shadow: none; border: 1px solid var(--line); }
        .ghost:hover { background: #e3edf2; box-shadow: none; }
        .grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; align-items: start; }
        .report-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; align-items: start; }
        .filter-panel { display: grid; gap: 12px; }
        .filter-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; align-items: end; }
        .grid-2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; align-items: start; }
        .row { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
        .three { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
        .detail-row { display: grid; grid-template-columns: 1.2fr 1.3fr .45fr .7fr 1.5fr auto; gap: 10px; align-items: end; }
        .detail-heading { margin-top: 4px; color: var(--accent-dark); font-size: 13px; font-weight: 800; }
        .full { grid-column: 1 / -1; }
        .stat { display: grid; gap: 8px; min-height: 124px; border-top: 4px solid var(--accent); background: linear-gradient(145deg, #fff, #f6fbfc); overflow: hidden; }
        .stat:hover { transform: translateY(-2px); box-shadow: var(--shadow); }
        .stat:nth-child(2) { border-top-color: var(--amber); }
        .stat:nth-child(3) { border-top-color: var(--indigo); }
        .stat:nth-child(4) { border-top-color: var(--coral); }
        .stat-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
        .stat-icon { width: 34px; height: 34px; border-radius: 8px; display: grid; place-items: center; background: var(--accent-soft); color: var(--accent-dark); font-size: 12px; font-weight: 900; }
        .stat:nth-child(2) .stat-icon { background: #fff3d8; color: #9a6400; }
        .stat:nth-child(3) .stat-icon { background: #e8edff; color: var(--indigo); }
        .stat:nth-child(4) .stat-icon { background: #fee7e2; color: var(--danger); }
        .stat strong { font-size: 32px; color: #102a43; font-weight: 900; }
        .stat span { color: var(--muted); font-size: 13px; }
        .approval-worklist { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
        .approval-card {
            border: 1px solid var(--line);
            border-top: 4px solid var(--accent);
            border-radius: 8px;
            padding: 16px;
            background: #fff;
            box-shadow: var(--shadow-soft);
            display: grid;
            gap: 12px;
        }
        .approval-card.is-wait { border-top-color: var(--amber); }
        .approval-card.is-danger { border-top-color: var(--coral); }
        .approval-card-head { display: flex; justify-content: space-between; gap: 10px; align-items: flex-start; }
        .approval-card strong { color: #102a43; font-size: 16px; }
        .approval-card .count { font-size: 30px; line-height: 1; font-weight: 950; color: #102a43; }
        .approval-meta { display: grid; gap: 5px; color: var(--muted); font-size: 12px; }
        .approval-list { display: grid; gap: 10px; }
        .approval-list-item {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 10px;
            align-items: center;
            padding: 12px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #f8fbfc;
        }
        .approval-list-item:hover { border-color: var(--accent); background: #f1fbf9; }
        .chart-grid { display: grid; grid-template-columns: 1.25fr .85fr 1fr; gap: 16px; align-items: stretch; }
        .chart-card { min-height: 250px; display: grid; gap: 14px; align-content: start; }
        .chart-title { display: flex; justify-content: space-between; gap: 10px; align-items: center; }
        .chart-title strong { color: #102a43; font-size: 15px; }
        .bar-list { display: grid; gap: 11px; }
        .bar-row { display: grid; grid-template-columns: 110px 1fr 34px; gap: 10px; align-items: center; font-size: 12px; color: var(--muted); }
        .bar-track { height: 11px; border-radius: 999px; background: #eaf2f5; overflow: hidden; }
        .bar-fill { height: 100%; border-radius: inherit; background: linear-gradient(90deg, var(--accent), var(--indigo)); }
        .pie-wrap { display: grid; place-items: center; gap: 12px; }
        .pie-chart { width: 150px; height: 150px; border-radius: 999px; display: grid; place-items: center; background: conic-gradient(var(--danger) 0 var(--critical-angle), var(--accent) var(--critical-angle) 360deg); box-shadow: inset 0 0 0 16px #f8fbfc, var(--shadow-soft); }
        .pie-chart strong { width: 92px; height: 92px; border-radius: 999px; display: grid; place-items: center; background: #fff; color: #102a43; box-shadow: var(--shadow-soft); }
        .legend { display: flex; gap: 10px; flex-wrap: wrap; justify-content: center; color: var(--muted); font-size: 12px; }
        .legend i { width: 9px; height: 9px; border-radius: 999px; display: inline-block; margin-right: 5px; }
        .trend-bars { min-height: 150px; display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 8px; align-items: end; padding-top: 12px; border-bottom: 1px solid var(--line); }
        .trend-bar { display: grid; gap: 6px; justify-items: center; color: var(--muted); font-size: 11px; }
        .trend-bar span:first-child { width: 100%; min-height: 8px; border-radius: 8px 8px 0 0; background: linear-gradient(180deg, var(--amber), var(--accent)); }
        .trend-note { color: var(--muted); font-size: 12px; }
        .dashboard-stack { display: grid; gap: 18px; }
        .dashboard-hero {
            display: grid;
            grid-template-columns: minmax(0, 1.45fr) minmax(280px, .8fr);
            gap: 16px;
            align-items: stretch;
            padding: 0;
            overflow: hidden;
            background:
                radial-gradient(circle at 78% 16%, rgba(227,167,47,.20), transparent 28%),
                linear-gradient(135deg, #102a43 0%, #0b5559 62%, #0b8f8a 100%);
            color: #fff;
            border: 0;
        }
        .dashboard-hero::before { display: none; }
        .hero-copy { padding: 24px; display: grid; gap: 14px; align-content: center; }
        .hero-copy small { color: #9ff2ec; font-weight: 900; letter-spacing: .04em; text-transform: uppercase; }
        .hero-copy h2 { margin: 0; color: #fff; font-size: 28px; line-height: 1.16; }
        .hero-copy h2::before { display: none; }
        .hero-copy p { color: #d4e7ed; max-width: 720px; }
        .hero-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 4px; }
        .hero-panel {
            padding: 20px;
            display: grid;
            gap: 12px;
            align-content: center;
            background: rgba(255,255,255,.10);
            border-left: 1px solid rgba(255,255,255,.16);
            backdrop-filter: blur(8px);
        }
        .hero-panel div {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,.14);
            color: #d7eef1;
            font-size: 13px;
        }
        .hero-panel div:last-child { border-bottom: 0; }
        .hero-panel strong { color: #fff; font-size: 18px; }
        .kpi-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 14px; }
        .kpi-card {
            min-height: 126px;
            display: grid;
            gap: 8px;
            padding: 16px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: linear-gradient(145deg, #fff, #f6fbfc);
            box-shadow: var(--shadow-soft);
            border-top: 4px solid var(--accent);
        }
        .kpi-card:nth-child(2) { border-top-color: var(--amber); }
        .kpi-card:nth-child(3) { border-top-color: var(--indigo); }
        .kpi-card:nth-child(4) { border-top-color: var(--coral); }
        .kpi-card:nth-child(5) { border-top-color: var(--good); }
        .kpi-label { display: flex; align-items: center; justify-content: space-between; gap: 8px; color: var(--muted); font-size: 12px; font-weight: 900; }
        .kpi-icon { width: 30px; height: 30px; border-radius: 8px; display: grid; place-items: center; background: var(--accent-soft); color: var(--accent-dark); font-size: 11px; font-weight: 900; }
        .kpi-card strong { color: #102a43; font-size: 27px; line-height: 1.1; font-weight: 900; }
        .kpi-card small { color: var(--muted); font-size: 12px; font-weight: 700; }
        .pipeline-grid { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 10px; }
        .pipeline-step {
            position: relative;
            min-height: 92px;
            padding: 13px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            box-shadow: var(--shadow-soft);
            overflow: hidden;
        }
        .pipeline-step::before { content: ""; position: absolute; inset: 0 0 auto; height: 4px; background: var(--accent); }
        .pipeline-step:nth-child(2)::before { background: var(--amber); }
        .pipeline-step:nth-child(3)::before { background: var(--indigo); }
        .pipeline-step:nth-child(4)::before { background: var(--coral); }
        .pipeline-step:nth-child(5)::before { background: var(--good); }
        .pipeline-step:nth-child(6)::before { background: #7b61ff; }
        .pipeline-step span { display: block; color: var(--muted); font-size: 12px; font-weight: 800; }
        .pipeline-step strong { display: block; margin-top: 8px; color: #102a43; font-size: 26px; font-weight: 900; }
        .business-grid { display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(0, .85fr); gap: 16px; align-items: start; }
        .priority-list { display: grid; gap: 10px; }
        .priority-card {
            display: grid;
            grid-template-columns: 42px minmax(0, 1fr) auto;
            gap: 12px;
            align-items: center;
            padding: 12px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
        }
        .priority-icon { width: 42px; height: 42px; border-radius: 8px; display: grid; place-items: center; background: var(--accent-soft); color: var(--accent-dark); font-weight: 900; }
        .priority-card strong { display: block; color: #102a43; font-size: 14px; }
        .priority-card span { color: var(--muted); font-size: 12px; }
        .insight-list { display: grid; gap: 10px; }
        .insight {
            padding: 12px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            display: grid;
            gap: 5px;
        }
        .insight strong { color: #102a43; }
        .insight span { color: var(--muted); font-size: 12px; }
        .mini-table th,
        .mini-table td { padding: 9px; font-size: 12px; }
        .activity-list { display: grid; gap: 10px; }
        .activity-item { display: grid; grid-template-columns: 10px minmax(0, 1fr); gap: 10px; padding: 10px 0; border-bottom: 1px solid var(--line); }
        .activity-item:last-child { border-bottom: 0; }
        .activity-dot { width: 10px; height: 10px; margin-top: 5px; border-radius: 999px; background: var(--accent); box-shadow: 0 0 0 4px rgba(11,143,138,.12); }
        .activity-item strong { display: block; color: #102a43; font-size: 13px; }
        .activity-item span { display: block; color: var(--muted); font-size: 12px; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; border: 1px solid var(--line); border-radius: 8px; background: #fff; box-shadow: inset 0 1px 0 rgba(255,255,255,.8); }
        .table-toolbar { display: flex; justify-content: flex-end; margin: 0 0 10px; }
        .table-search { max-width: 280px; min-height: 38px; }
        th, td { border-bottom: 1px solid var(--line); padding: 11px 10px; text-align: left; vertical-align: top; }
        tr:last-child td { border-bottom: 0; }
        tbody tr:hover { background: #f2fbfb; }
        tr.clickable-row { cursor: pointer; }
        tr.clickable-row:hover { background: #e8f7f5; }
        tbody tr:has(.action-menu[open]) { position: relative; z-index: 40; }
        td:has(.action-menu[open]) { position: relative; z-index: 4500; }
        th { color: #4d6273; font-size: 12px; font-weight: 900; background: linear-gradient(180deg, #eef7f8, #e6f0f4); }
        .pill { display: inline-flex; align-items: center; min-height: 24px; padding: 4px 9px; border-radius: 999px; background: #e8efef; color: #244047; font-size: 12px; font-weight: 800; }
        .ok { background: #dcf7ee; color: var(--good); }
        .wait { background: #fff1d6; color: var(--warn); }
        .bad { background: #fee7e2; color: var(--danger); }
        .stack { display: grid; gap: 12px; }
        .actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        .master-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            min-width: 0;
        }
        .master-primary-action,
        .master-transfer-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .master-transfer-actions { justify-content: flex-end; }
        .master-import-form { margin: 0; }
        .master-import-form label { cursor: pointer; }
        .master-import-form input[type="file"] { display: none; }
        main > .actions {
            justify-content: flex-end;
            padding: 14px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background:
                linear-gradient(135deg, rgba(255,255,255,.93), rgba(255,255,255,.76)),
                linear-gradient(90deg, rgba(11,143,138,.11), rgba(227,167,47,.11));
            box-shadow: var(--shadow-soft);
        }
        .action-menu { position: relative; display: inline-block; z-index: 1500; }
        .action-menu summary {
            list-style: none;
            display: inline-flex;
            align-items: center;
            min-height: 36px;
            padding: 9px 12px;
            border-radius: 8px;
            background: var(--accent);
            color: #fff;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 8px 16px rgba(15,143,115,.16);
        }
        .action-menu summary::-webkit-details-marker { display: none; }
        .action-menu[open] summary { background: var(--accent-dark); }
        .action-menu .menu {
            position: absolute;
            right: 0;
            z-index: 5000;
            min-width: 150px;
            margin-top: 6px;
            padding: 6px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            box-shadow: var(--shadow);
            display: grid;
            gap: 6px;
        }
        .action-menu .menu button { width: 100%; box-shadow: none; }
        .action-menu .menu form { width: 100%; margin: 0; }
        .action-menu .menu .danger-action {
            border-color: #f0b8b4;
            background: #fff4f2;
            color: var(--danger);
        }
        .action-menu .menu .danger-action:hover {
            background: #fee7e2;
        }
        .action-menu .menu form { width: 100%; margin: 0; }
        .action-menu .menu .danger-action {
            border-color: #f0b8b4;
            background: #fff4f2;
            color: var(--danger);
        }
        .action-menu .menu .danger-action:hover {
            background: #fee7e2;
        }
        .inline-edit { margin-top: 10px; padding: 14px; border: 1px solid var(--line-strong); border-radius: 8px; background: linear-gradient(180deg, #ffffff, #f4fbf8); box-shadow: var(--shadow-soft); }
        .inline-edit[hidden] { display: none; }
        .notice { position: sticky; top: 12px; z-index: 1000; padding: 12px 14px; border-radius: 8px; border: 1px solid var(--line); background: #fff; box-shadow: var(--shadow); font-weight: 700; animation: notify-in .22s ease both; }
        .success { border-color: #9bd8c2; color: var(--good); background: #f0fbf6; }
        .error { border-color: #f4aaa5; color: var(--danger); background: #fff6f4; }
        @keyframes notify-in { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
        .muted { color: var(--muted); }
        .stock-balance { display: flex; flex-direction: column; gap: 2px; min-width: 112px; }
        .stock-balance strong { color: var(--ink); font-variant-numeric: tabular-nums; }
        .stock-balance span { color: var(--muted); font-size: 11px; font-weight: 700; }
        .stock-in-transit { display: inline-flex; align-items: center; width: fit-content; margin-top: 7px; padding: 3px 8px; border: 1px solid #f3d28a; border-radius: 999px; background: #fff7df; color: #875800; font-size: 11px; font-weight: 800; line-height: 1.3; }
        .stock-number { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .stock-number small { color: var(--muted); font-size: 11px; font-weight: 700; }
        .stock-position { background: #edf8f7; color: var(--accent-dark); }
        .network-stock-table-wrap { overflow-x: auto; border: 1px solid var(--line); border-radius: 6px; }
        .network-stock-table { min-width: 1180px; margin: 0; border: 0; border-radius: 0; }
        .network-stock-table th,
        .network-stock-table td { vertical-align: middle; }
        .camp-stock-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
        .camp-stock-heading h2 { margin-bottom: 0; }
        .camp-stock-heading > span { color: var(--muted); font-size: 12px; font-weight: 700; }
        .camp-stock-table-wrap { overflow-x: auto; border: 1px solid var(--line); border-radius: 6px; }
        .camp-stock-table { min-width: 820px; margin: 0; border: 0; border-radius: 0; table-layout: fixed; }
        .camp-stock-table .camp-stock-company { width: 22%; }
        .camp-stock-table .camp-stock-location { width: 14%; }
        .camp-stock-table .camp-stock-part { width: 16%; }
        .camp-stock-table .camp-stock-item { width: 30%; }
        .camp-stock-table .camp-stock-balance-column { width: 18%; }
        .camp-stock-table th,
        .camp-stock-table td { vertical-align: middle; }
        .camp-stock-table .is-numeric,
        .camp-stock-quantity { text-align: right; }
        .camp-stock-location-name,
        .camp-stock-item-name { color: var(--ink); font-weight: 800; }
        .camp-stock-sku { color: #40576a; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .camp-stock-quantity { color: var(--ink); font-weight: 900; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .camp-stock-quantity span { color: var(--muted); font-size: 11px; font-weight: 750; }
        .camp-stock-empty { padding: 24px !important; color: var(--muted); text-align: center; }
        .usage-cell { display: grid; gap: 2px; min-width: 130px; }
        .usage-cell strong { color: var(--ink); font-weight: 800; }
        .usage-cell span, .usage-cell small { color: var(--muted); font-size: 11px; line-height: 1.35; }
        .usage-quantity { color: var(--ink); font-weight: 900; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .usage-quantity small { color: var(--muted); font-size: 11px; font-weight: 800; }
        .usage-date { color: var(--ink); font-variant-numeric: tabular-nums; white-space: nowrap; }
        .usage-create-panel { display: grid; gap: 18px; border-top: 4px solid var(--accent); }
        .usage-form-header { align-items: flex-start; padding-bottom: 14px; border-bottom: 1px solid var(--line); }
        .usage-form-header h2 { margin-bottom: 4px; }
        .usage-form { display: grid; gap: 14px; }
        .usage-form > .row,
        .usage-form > .three { padding: 14px; border: 1px solid var(--line); border-radius: 8px; background: var(--bg-soft); }
        .usage-form > .empty { margin: -4px 0 0; padding: 12px 14px; text-align: left; }
        .usage-form > .actions { justify-content: flex-end; padding-top: 4px; }
        .receipt-list { gap: 10px; }
        .receipt-card {
            display: block;
            min-height: 0;
            padding: 16px 18px;
            border: 1px solid var(--line);
            border-left: 4px solid var(--accent);
            border-radius: 8px;
            background: #fff;
            box-shadow: none;
        }
        .receipt-card-main { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: center; gap: 16px; }
        .receipt-card h3 { margin: 0; color: var(--ink); font-size: 16px; line-height: 1.35; }
        .receipt-card h3 .pill { margin-left: 6px; vertical-align: middle; }
        .receipt-card p { margin: 5px 0 0; color: var(--muted); }
        .receipt-card .actions { margin: 0 !important; }
        .receipt-card .button { white-space: nowrap; }
        .receipt-card .inline-edit { display: none !important; }
        .receipt-records { display: grid; gap: 8px; margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--line); }
        .receipt-record { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)) auto; align-items:center; gap: 10px; padding: 11px 12px; border: 1px solid var(--line); border-radius: 7px; background: var(--bg-soft); transition:border-color .16s ease, background .16s ease, box-shadow .16s ease; }
        .receipt-record:hover { border-color:var(--accent); background:#fff; box-shadow:0 5px 14px rgba(16,42,67,.08); }
        .receipt-record span, .receipt-record strong { display: block; }
        .receipt-record span { margin-bottom: 3px; color: var(--muted); font-size: 11px; font-weight: 800; }
        .receipt-record strong { color: var(--ink); overflow-wrap: anywhere; }
        .receipt-record-action { text-align:right; }
        .receipt-record-action strong { color:var(--accent-dark); white-space:nowrap; }
        .price { font-variant-numeric: tabular-nums; white-space: nowrap; }
        .empty { padding: 22px; border: 1px dashed var(--line-strong); border-radius: 8px; color: var(--muted); background: linear-gradient(135deg, #f8fcfa, #fff); text-align: center; font-weight: 700; }
        .item-history-link { display: inline-flex; flex-direction: column; gap: 4px; color: #102a43; font-weight: 900; }
        .item-history-link span { width: fit-content; padding: 3px 8px; border-radius: 999px; background: #eef7f8; color: var(--accent-dark); font-size: 11px; font-weight: 900; }
        .mobile-nav-toggle, .sidebar-backdrop { display: none; }
        /* Operational UI refinement */
        :root {
            --bg: #f4f7f9;
            --bg-soft: #f8fafb;
            --sidebar: #132536;
            --sidebar-deep: #0d1c29;
            --panel-tint: #ffffff;
            --ink: #172534;
            --muted: #667687;
            --line: #dce4e9;
            --line-strong: #c7d2da;
            --accent: #087f78;
            --accent-dark: #06645f;
            --accent-soft: #e1f3f1;
            --shadow: 0 8px 24px rgba(28, 46, 61, .09);
            --shadow-soft: 0 2px 8px rgba(28, 46, 61, .06);
            --shadow-strong: 0 16px 36px rgba(28, 46, 61, .15);
        }
        body { background: var(--bg); }
        .app { grid-template-columns: 256px minmax(0, 1fr); }
        .sidebar {
            padding: 18px 14px;
            gap: 14px;
            background: var(--sidebar);
            box-shadow: 4px 0 16px rgba(13, 28, 41, .12);
        }
        .brand { padding: 8px 8px 16px; grid-template-columns: 38px 1fr; gap: 10px; }
        .brand-mark { width: 38px; height: 38px; box-shadow: none; background: #fff; border: 0; }
        .brand-mark svg { width: 23px; height: 23px; }
        .brand strong { font-size: 17px; }
        .brand span { margin-top: 2px; font-size: 11px; }
        .nav { gap: 3px; }
        .nav a {
            min-height: 40px;
            padding: 7px 9px;
            border-radius: 6px;
            color: #c9d5de;
            font-size: 13px;
            font-weight: 650;
            transition: background .15s ease, color .15s ease;
        }
        .nav a:hover { transform: none; background: rgba(255,255,255,.07); border-color: transparent; }
        .nav a.active { transform: none; background: #1e3b4c; border-color: rgba(255,255,255,.08); color: #fff; }
        .nav .ico { width: 27px; height: 27px; border-radius: 6px; background: rgba(255,255,255,.06); }
        .nav a.active .ico { background: #2ec4b6; color: #102a35; }
        .subnav { margin: 1px 0 8px 35px; padding-left: 9px; border-left: 1px solid rgba(255,255,255,.12); }
        .subnav a { min-height: 31px; padding: 6px 9px; font-size: 12px; border-radius: 5px; }
        .subnav a.active { background: rgba(46,196,182,.14); color: #8ce7df; }
        .sidebar-bottom { padding: 10px 9px 0; border-top: 1px solid rgba(255,255,255,.08); }
        .topbar {
            min-height: 82px;
            padding: 14px 24px;
            background: rgba(255,255,255,.96);
            box-shadow: 0 1px 0 var(--line);
            backdrop-filter: blur(10px);
        }
        .topbar h1 { font-size: 25px; font-weight: 800; }
        .topbar p { margin-top: 3px; }
        .account-menu summary, .date-badge { box-shadow: none; }
        .account-menu summary { min-height: 44px; }
        .account-avatar { background: var(--accent); }
        main { max-width: 1680px; width: 100%; margin: 0 auto; padding: 22px 24px 34px; gap: 16px; }
        section, article, .card {
            border-radius: 7px;
            padding: 16px;
            box-shadow: var(--shadow-soft);
        }
        section { background: #fff; }
        section::before { display: none; }
        article:hover { transform: none; box-shadow: var(--shadow-soft); }
        h2 { margin-bottom: 14px; font-size: 16px; font-weight: 800; }
        h2::before { width: 4px; height: 18px; border-radius: 2px; background: var(--accent); box-shadow: none; }
        input, select, textarea { min-height: 40px; border-radius: 6px; padding: 9px 10px; }
        input:focus, select:focus, textarea:focus { box-shadow: 0 0 0 3px rgba(8,127,120,.12); }
        button, .button {
            min-height: 38px;
            border-radius: 6px;
            padding: 9px 12px;
            background: var(--accent);
            box-shadow: none;
            font-size: 13px;
        }
        button:hover, .button:hover { transform: none; background: var(--accent-dark); box-shadow: none; }
        .ghost { background: #f4f7f9; }
        .grid, .report-grid, .chart-grid, .grid-2, .approval-worklist { gap: 12px; }
        .stat {
            min-height: 112px;
            border-top: 0;
            border-left: 4px solid var(--accent);
            background: #fff;
        }
        .stat:nth-child(2) { border-left-color: var(--amber); }
        .stat:nth-child(3) { border-left-color: var(--indigo); }
        .stat:nth-child(4) { border-left-color: var(--coral); }
        .stat:hover { transform: none; box-shadow: var(--shadow-soft); }
        .stat strong { font-size: 27px; }
        .chart-card { min-height: 230px; }
        .dashboard-hero {
            border-radius: 8px;
            box-shadow: var(--shadow);
        }
        .kpi-card,
        .pipeline-step,
        .priority-card,
        .insight {
            border-radius: 7px;
            box-shadow: var(--shadow-soft);
        }
        .kpi-card:hover,
        .pipeline-step:hover,
        .priority-card:hover {
            border-color: var(--line-strong);
        }
        .priority-card .button {
            white-space: nowrap;
        }
        table { border-radius: 6px; font-size: 12.5px; box-shadow: none; }
        th, td { padding: 10px 11px; }
        th {
            position: sticky;
            top: 81px;
            z-index: 5;
            background: #f0f4f6;
            color: #45596a;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .02em;
        }
        tbody tr:nth-child(even) { background: #fbfcfd; }
        tbody tr:hover { background: #edf8f7; }
        .pill { min-height: 22px; padding: 3px 8px; border-radius: 999px; font-size: 11px; }
        main > .actions {
            padding: 0;
            border: 0;
            background: transparent;
            box-shadow: none;
        }
        .action-menu summary { min-height: 32px; padding: 7px 10px; border-radius: 6px; box-shadow: none; }
        .action-menu .menu { border-radius: 6px; box-shadow: var(--shadow-strong); }
        .filter-panel { gap: 10px; border-top: 3px solid var(--accent); }
        .filter-panel .panel-head {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        .filter-panel h2 { margin-bottom: 4px; }
        .filter-panel p { margin: 0; color: var(--muted); }
        .report-filter-heading { max-width: 720px; }
        .section-kicker { display: block; margin-bottom: 5px; color: var(--accent-dark); font-size: 10px; font-weight: 900; letter-spacing: 0; }
        .filter-chips { display: flex; flex-wrap: wrap; gap: 6px; justify-content: flex-end; }
        .filter-chip { display: inline-flex; min-height: 26px; align-items: center; padding: 4px 9px; border-radius: 999px; background: var(--accent-soft); color: var(--accent-dark); font-size: 11px; font-weight: 800; }
        .report-export-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 7px; }
        .report-export-actions .button { min-height: 38px; padding: 8px 11px; }
        .report-table-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
        .report-table-head h2 { margin-bottom: 3px; }
        .report-table-wrap { max-height: calc(100vh - 250px); overflow: auto; border: 1px solid var(--line); border-radius: 7px; }
        .report-table { margin: 0; min-width: 920px; }
        .report-table thead th { position: sticky; top: 0; z-index: 2; background: #eaf2f4; box-shadow: 0 1px 0 var(--line-strong); }
        .report-table tbody tr:nth-child(even) { background: #fbfdfe; }
        .report-table tbody tr:hover { background: #f0faf9; }
        .report-table th, .report-table td { vertical-align: top; }
        .report-table .is-numeric { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .report-multiline { display: block; white-space: pre-line; line-height: 1.55; }
        .filter-actions { justify-content: flex-end; margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--line); }
        .transaction-toolbar { display: grid; grid-template-columns: minmax(260px, 1.6fr) repeat(3, minmax(150px, .7fr)) auto; gap: 10px; align-items: end; margin: 0 0 14px; padding: 14px; border: 1px solid var(--line); border-radius: 7px; background: #f7fafb; }
        .transaction-toolbar label span { color: #4d6070; font-size: 11px; font-weight: 850; }
        .transaction-toolbar-actions { display: flex; gap: 7px; align-items: center; }
        .transaction-toolbar-actions > * { min-height: 41px; }
        .transaction-result-meta { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin: -4px 0 12px; color: var(--muted); font-size: 12px; }
        .transaction-pagination { display: flex; align-items: center; justify-content: flex-end; gap: 8px; margin-top: 14px; }
        .transaction-pagination a, .transaction-pagination span, .transaction-pagination strong { display: inline-flex; min-height: 34px; align-items: center; padding: 7px 11px; border: 1px solid var(--line); border-radius: 6px; background: #fff; color: #334e68; font-size: 12px; }
        .transaction-pagination a { color: var(--accent-dark); font-weight: 850; }
        .transaction-pagination .is-disabled { color: #9aa8b3; background: #f4f7f8; }
        .company-tabs-wrap {
            padding: 0;
            overflow: visible;
            border: 0;
            background: transparent;
            box-shadow: none;
        }
        .company-tabs {
            display: flex;
            align-items: stretch;
            gap: 0;
            overflow-x: auto;
            padding: 0 0 2px;
        }
        .company-tab {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            min-height: 46px;
            padding: 11px 18px;
            border: 1px solid var(--line);
            border-right: 0;
            background: rgba(255,255,255,.84);
            color: #334e68;
            font-weight: 850;
            white-space: nowrap;
        }
        .company-tab:first-child { border-radius: 8px 0 0 8px; }
        .company-tab:last-child { border-right: 1px solid var(--line); border-radius: 0 8px 8px 0; }
        .company-tab.active {
            position: relative;
            z-index: 1;
            border-color: rgba(8,127,120,.45);
            background: #fff;
            color: var(--accent-dark);
            box-shadow: 0 10px 24px rgba(16,42,67,.08);
        }
        .company-tab-count {
            display: inline-flex;
            min-width: 27px;
            height: 27px;
            align-items: center;
            justify-content: center;
            padding: 0 8px;
            border-radius: 999px;
            background: #22bfd0;
            color: #fff;
            font-size: 12px;
            font-weight: 900;
        }
        .company-tab.active .company-tab-count { background: #ef426f; }
        .notice { top: 92px; box-shadow: var(--shadow); }
        .empty { background: #fafcfd; }
        @media (max-width: 1080px) {
            .app { grid-template-columns: 1fr; }
            .sidebar {
                position: fixed;
                inset: 0 auto 0 0;
                width: min(290px, 86vw);
                height: 100vh;
                padding: 14px;
                transform: translateX(-105%);
                transition: transform .2s ease;
                box-shadow: 12px 0 32px rgba(13,28,41,.22);
            }
            body.nav-open { overflow: hidden; }
            body.nav-open .sidebar { transform: translateX(0); }
            .sidebar-backdrop {
                position: fixed;
                inset: 0;
                z-index: 3400;
                border: 0;
                background: rgba(13,28,41,.48);
                padding: 0;
                border-radius: 0;
                box-shadow: none;
            }
            body.nav-open .sidebar-backdrop { display: block; }
            .mobile-nav-toggle {
                display: inline-grid;
                place-items: center;
                width: 40px;
                height: 40px;
                padding: 0;
                background: #f3f6f8;
                color: #24394d;
                border: 1px solid var(--line);
            }
            .mobile-nav-toggle svg { width: 20px; height: 20px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; }
            .topbar-title { flex: 1; }
            .sidebar-bottom { margin-top: 6px; }
            .nav { grid-template-columns: 1fr; }
            .transaction-toolbar { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .transaction-search { grid-column: 1 / -1; }
            .grid, .report-grid, .approval-worklist { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .dashboard-hero,
            .business-grid {
                grid-template-columns: 1fr;
            }
            .hero-panel {
                border-left: 0;
                border-top: 1px solid rgba(255,255,255,.16);
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
            .kpi-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .pipeline-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
            .subnav { margin-left: 35px; }
            th { top: 0; }
        }
        @media (max-width: 720px) {
            .topbar { align-items: flex-start; flex-direction: column; padding: 16px; }
            .credit-note-summary { grid-template-columns: 1fr; }
            .credit-note-type-picker { grid-template-columns: 1fr; }
            .credit-note-form { padding: 14px; }
            .credit-note-card-header { grid-template-columns: 1fr; }
            .topbar-tools { justify-content: flex-start; width: 100%; }
            .account-menu, .account-menu summary, .notification-menu, .notification-menu summary { width: 100%; }
            .account-dropdown { position: static; width: 100%; }
            .notification-dropdown {
                position: fixed;
                top: 74px;
                right: 12px;
                left: 12px;
                width: auto;
                max-height: calc(100dvh - 88px);
            }
            .account-meta strong, .account-meta span { max-width: none; }
            main { padding: 14px; }
            .nav, .grid, .report-grid, .filter-grid, .chart-grid, .grid-2, .row, .three, .detail-row, .kpi-grid, .pipeline-grid, .hero-panel, .approval-worklist { grid-template-columns: 1fr; }
            .hero-copy { padding: 18px; }
            .hero-copy h2 { font-size: 22px; }
            .hero-panel { padding: 16px 18px; }
            .priority-card { grid-template-columns: 36px minmax(0, 1fr); }
            .priority-card .button { grid-column: 1 / -1; width: 100%; }
            table { display: block; overflow-x: auto; }
            th, td { min-width: 130px; }
            .brand { grid-template-columns: 38px 1fr; }
            .brand-mark { width: 38px; height: 38px; }
            .topbar h1 { font-size: 23px; }
            .transaction-toolbar { grid-template-columns: 1fr; }
            .transaction-search { grid-column: auto; }
            .transaction-toolbar-actions { width: 100%; }
            .transaction-toolbar-actions > * { flex: 1; }
            .transaction-result-meta, .transaction-pagination { align-items: stretch; flex-direction: column; }
            .transaction-pagination > * { justify-content: center; }
        }
        @media (max-width: 520px) {
            .sidebar { padding: 16px 12px; }
            .nav { grid-template-columns: 1fr; }
            .actions { align-items: stretch; flex-direction: column; }
            .actions button, .actions form { width: 100%; }
            button, .button { width: 100%; }
        }

        /* Final polish: tighter visual hierarchy without changing workflow */
        :root {
            --radius: 8px;
            --radius-sm: 6px;
            --surface-border: #d7e2e8;
        }
        .content {
            background:
                linear-gradient(180deg, rgba(255,255,255,.78), rgba(244,247,249,.96) 220px),
                var(--bg);
        }
        .topbar {
            border-bottom-color: var(--surface-border);
        }
        .topbar-title {
            min-width: 0;
        }
        .topbar-title h1,
        .topbar-title p {
            overflow-wrap: anywhere;
        }
        main {
            align-content: start;
        }
        section,
        article,
        .card {
            border-color: var(--surface-border);
        }
        section,
        .card,
        .chart-card,
        .filter-panel {
            overflow: visible;
        }
        .card.stat,
        .chart-card,
        section {
            transition: border-color .16s ease, box-shadow .16s ease;
        }
        .card.stat:hover,
        .chart-card:hover,
        section:hover {
            border-color: #c9d8df;
        }
        .stack > section,
        .grid-2 > section,
        .chart-card,
        .filter-panel {
            min-width: 0;
        }
        table {
            overflow: visible;
        }
        th {
            position: static !important;
            top: auto !important;
            z-index: auto !important;
        }
        th:first-child,
        td:first-child {
            padding-left: 14px;
        }
        th:last-child,
        td:last-child {
            padding-right: 14px;
        }
        td {
            line-height: 1.5;
        }
        td .muted,
        .spec,
        .item-note {
            display: inline-block;
            margin-top: 3px;
        }
        .logistics-table { table-layout: fixed; }
        .logistics-table th:nth-child(1) { width: 18%; }
        .logistics-table th:nth-child(2) { width: 12%; }
        .logistics-table th:nth-child(3) { width: 10%; }
        .logistics-table th:nth-child(4) { width: 20%; }
        .logistics-table th:nth-child(5) { width: 15%; }
        .logistics-table th:nth-child(6) { width: 13%; }
        .logistics-table th:nth-child(7) { width: 12%; }
        .logistics-table td { overflow-wrap: anywhere; vertical-align: middle; }
        .logistics-table td .muted { display: block; margin-top: 2px; font-size: 11px; line-height: 1.35; }
        .logistics-primary { color: #102a43; font-weight: 900; }
        .logistics-source { display: inline-flex; align-items: center; min-height: 24px; padding: 3px 8px; border-radius: 999px; background: #edf4f7; color: #345064; font-size: 11px; font-weight: 800; }
        .logistics-source.direct { background: #fff1d6; color: #8a5a00; }
        .vendor-price-card > .row { grid-template-columns: minmax(0, 1fr) auto; align-items: start; }
        .vendor-price-card > .row > .actions { justify-content: flex-end; }
        .vendor-price-table { table-layout: fixed; }
        .vendor-price-table th:nth-child(1) { width: 18%; }
        .vendor-price-table th:nth-child(2) { width: 27%; }
        .vendor-price-table th:nth-child(3) { width: 8%; }
        .vendor-price-table th:nth-child(4) { width: 12%; }
        .vendor-price-table th:nth-child(5) { width: 27%; }
        .vendor-price-table th:nth-child(6) { width: 8%; }
        .vendor-price-table td { overflow-wrap: anywhere; }
        .vendor-price-table td:last-child { vertical-align: middle; }
        .purchase-order-table { min-width: 1080px; }
        .purchase-order-table td { vertical-align: middle; overflow-wrap: anywhere; }
        .purchase-order-table td:first-child { min-width: 180px; }
        .purchase-order-table td:nth-child(4) { min-width: 220px; }
        .po-item-summary { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 10px; padding: 3px 0; }
        .po-item-summary strong { color: #102a43; font-size: 12px; }
        .po-item-summary span { color: var(--muted); white-space: nowrap; }
        .po-approval-list { display: grid; gap: 5px; min-width: 190px; }
        .approval-state { display: block; padding-left: 10px; border-left: 3px solid var(--line-strong); color: var(--muted); font-size: 11px; font-weight: 800; line-height: 1.35; }
        .approval-state.done { border-left-color: var(--good); color: var(--good); }
        .approval-state.waiting { border-left-color: var(--amber); color: var(--warn); }
        .dispatch-create-panel { display: grid; gap: 18px; border-top: 4px solid var(--accent); }
        .dispatch-create-panel > .row { align-items: flex-start; padding-bottom: 14px; border-bottom: 1px solid var(--line); }
        .dispatch-create-panel form { display: grid; gap: 14px; }
        .dispatch-create-panel form > .three { padding: 14px; border: 1px solid var(--line); border-radius: 8px; background: var(--bg-soft); }
        .dispatch-item-row { margin: 0; box-shadow: none; }
        .credit-note-create-panel { display: grid; gap: 16px; }
        .credit-note-type-picker { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
        .credit-note-type { display: grid; justify-items: start; gap: 4px; min-height: 78px; padding: 14px 16px; border: 1px solid var(--line-strong); border-radius: 8px; background: #fff; color: var(--ink); text-align: left; white-space: normal; }
        .credit-note-type span { color: var(--muted); font-size: 12px; font-weight: 600; line-height: 1.4; }
        .credit-note-type.active { border-color: var(--accent); background: var(--accent-soft); box-shadow: inset 0 0 0 1px var(--accent); }
        .credit-note-form { margin: 0; padding: 18px; }
        .credit-note-form h3 { margin: 0 0 14px; font-size: 16px; }
        .credit-note-form button[type="submit"] { margin-top: 14px; }
        .credit-note-card { overflow: hidden; padding: 16px 18px; cursor: pointer; box-shadow: none; }
        .credit-note-card:hover, .credit-note-card:focus { border-color: #98cbc8; background: #fbfefe; outline: none; }
        .credit-note-card-header { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 18px; align-items: start; }
        .credit-note-card-heading { min-width: 0; }
        .credit-note-card-header > .actions { justify-content: flex-end; }
        .credit-note-card h3 { margin-bottom: 5px; }
        .credit-note-card-heading p { line-height: 1.5; }
        .credit-note-table-wrap { margin-top: 14px; overflow-x: auto; }
        .credit-note-card .credit-note-table { min-width: 820px; margin: 0; table-layout: fixed; }
        .credit-note-table th:nth-child(1) { width: 28%; }
        .credit-note-table th:nth-child(2) { width: 24%; }
        .credit-note-table th:nth-child(3) { width: 13%; }
        .credit-note-table th:nth-child(4) { width: 17%; }
        .credit-note-table th:nth-child(5) { width: 18%; }
        .credit-note-table td { vertical-align: middle; overflow-wrap: anywhere; }
        .credit-note-cell-primary, .credit-note-cell-secondary { display: block; }
        .credit-note-cell-primary { color: var(--ink); font-weight: 800; }
        .credit-note-cell-secondary { margin-top: 3px; color: var(--muted); font-size: 11px; line-height: 1.4; }
        .credit-note-qty { color: var(--ink); font-weight: 800; white-space: nowrap; }
        .credit-note-notes { color: var(--muted); }
        .credit-note-summary { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
        .credit-note-summary > div { min-width: 0; padding: 14px 16px; border: 1px solid var(--line); border-left: 4px solid var(--accent); border-radius: 7px; background: #fff; box-shadow: var(--shadow-soft); }
        .credit-note-summary span, .credit-note-summary strong, .credit-note-summary small { display: block; }
        .credit-note-summary span { color: var(--muted); font-size: 12px; font-weight: 800; }
        .credit-note-summary strong { margin-top: 4px; color: #102a43; font-size: 27px; line-height: 1.1; }
        .credit-note-summary small { margin-top: 5px; color: var(--muted); font-size: 12px; line-height: 1.35; }
        .logistics-table { table-layout: fixed; }
        .logistics-table th:nth-child(1) { width: 18%; }
        .logistics-table th:nth-child(2) { width: 12%; }
        .logistics-table th:nth-child(3) { width: 10%; }
        .logistics-table th:nth-child(4) { width: 20%; }
        .logistics-table th:nth-child(5) { width: 15%; }
        .logistics-table th:nth-child(6) { width: 13%; }
        .logistics-table th:nth-child(7) { width: 12%; }
        .logistics-table td { overflow-wrap: anywhere; vertical-align: middle; }
        .logistics-table td .muted { display: block; margin-top: 2px; font-size: 11px; line-height: 1.35; }
        .logistics-primary { color: #102a43; font-weight: 900; }
        .logistics-source { display: inline-flex; align-items: center; min-height: 24px; padding: 3px 8px; border-radius: 999px; background: #edf4f7; color: #345064; font-size: 11px; font-weight: 800; }
        .logistics-source.direct { background: #fff1d6; color: #8a5a00; }
        .actions {
            row-gap: 7px;
        }
        button,
        .button,
        .action-menu summary {
            white-space: nowrap;
        }
        .action-menu .menu .button,
        .action-menu .menu button {
            min-height: 34px;
            justify-content: center;
            padding: 8px 10px;
        }
        .notice {
            border-radius: var(--radius);
        }
        input[readonly] {
            background: #f8fafb;
            color: var(--muted);
        }
        @media (min-width: 721px) {
            section:has(> table) {
                overflow-x: auto;
            }
            section:has(> table) table {
                min-width: 760px;
            }
        }
        @media (max-width: 720px) {
            .master-toolbar {
                align-items: stretch;
                flex-direction: column;
            }
            .master-primary-action,
            .master-transfer-actions {
                display: grid;
                grid-template-columns: 1fr;
            }
            .master-primary-action:empty { display: none; }
            .master-toolbar .button,
            .master-import-form,
            .master-import-form label { width: 100%; }
            .vendor-price-card > .row { grid-template-columns: 1fr; }
            .vendor-price-card > .row > .actions { justify-content: flex-start; }
            .credit-note-card-header { grid-template-columns: 1fr; }
            .credit-note-card-header > .actions { justify-content: flex-start; }
            .vendor-price-table { min-width: 860px; }
            .usage-form-header { display: grid; grid-template-columns: 1fr; }
            .usage-form-header .button,
            .usage-form > .actions button { width: 100%; }
            .receipt-card-main,
            .receipt-record {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            .receipt-card .actions,
            .receipt-card .button {
                width: 100%;
            }
            .receipt-record-action { text-align:left; }
            .topbar-tools {
                display: grid;
                grid-template-columns: 1fr;
            }
            .date-badge {
                width: fit-content;
            }
            section,
            article,
            .card {
                padding: 14px;
            }
            h2 {
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
@php
    $currentUser = auth()->user()->loadMissing('role');
    $canSeePrices = $canSeePrices ?? $currentUser->canSeePrices();
    $canManageVendorPrices = $canManageVendorPrices ?? $currentUser->canManageVendorPrices();
    $isAdmin = $isAdmin ?? $currentUser->isAdmin();
    $canManageMasterData = $currentUser->hasPermission('master.manage');
    $roleName = $currentUser->roleName();
    $activeCompanyId = $activeCompanyId ?? (request('company_id') ? (int) request('company_id') : null);
    $companyQuery = $activeCompanyId ? ['company_id' => $activeCompanyId] : [];
    $companyTabCounts = $companyTabCounts ?? ['all' => 0];
    $companyScopedMenus = ['dashboard', 'requests', 'approvals', 'vendor-prices', 'purchase-orders', 'receipts', 'credit-notes', 'dispatches', 'usage'];
    $companyTabUrl = function (?int $companyId = null) {
        $params = request()->except('company_id', 'page');
        if ($companyId) {
            $params['company_id'] = $companyId;
        }

        return url()->current().($params ? '?'.http_build_query($params) : '');
    };
    $titles = [
        'dashboard' => 'Dashboard', 'master' => 'Master Data', 'requests' => 'Order Request',
        'approvals' => 'Approval Saya', 'vendor-prices' => 'Harga Vendor',
        'purchase-orders' => 'Purchase Order', 'receipts' => 'Penerimaan Gudang',
        'credit-notes' => 'Credit Note', 'dispatches' => 'Logistik Site',
        'usage' => 'Pemakaian Unit', 'stock' => 'Stok Barang',
        'stock-opname' => 'Stock Opname', 'reports' => 'Report',
        'maintenance' => 'Pemeliharaan Sistem',
    ];
    $nav = [];
    if ($currentUser->hasPermission('dashboard.view')) {
        $nav[] = ['dashboard', 'dashboard', route('dashboard', $companyQuery), 'Dashboard'];
    }
    if ($currentUser->hasPermission('master.view')) {
        $nav[] = ['master', 'master', route('master.camps'), 'Master Data'];
    }
    if ($currentUser->hasPermission('requests.view')) {
        $nav[] = ['requests', 'request', route('requests', $companyQuery), 'Order Request'];
    }
    if ($currentUser->hasPermission('approvals.view')) {
        $nav[] = ['approvals', 'shield', route('approvals', $companyQuery), 'Approval Saya'];
    }
    if ($currentUser->hasPermission('vendor_prices.view') || $currentUser->canApproveEquipment() || $currentUser->hasPermission('approvals.director')) {
        $nav[] = ['vendor-prices', 'tag', route('vendor-prices', $companyQuery), 'Harga Vendor'];
    }
    if ($currentUser->hasPermission('purchase_orders.view')) {
        $nav[] = ['purchase-orders', 'cart', route('purchase-orders', $companyQuery), 'Purchase Order'];
    }
    if ($currentUser->hasPermission('receipts.view')) {
        $nav[] = ['receipts', 'inbox', route('receipts', $companyQuery), 'Penerimaan'];
    }
    if ($currentUser->hasPermission('credit_notes.view')) {
        $nav[] = ['credit-notes', 'return', route('credit-notes', $companyQuery), 'Credit Note'];
    }
    if ($currentUser->hasPermission('dispatches.view')) {
        $dispatchDefaultTab = $currentUser->hasPermission('dispatches.manage')
            ? 'draft'
            : ($currentUser->hasPermission('site_receipts.manage') ? 'receiving' : 'transit');
        $nav[] = ['dispatches', 'truck', route('dispatches', array_merge($companyQuery, ['tab' => $dispatchDefaultTab])), 'Logistik Site'];
    }
    if ($currentUser->hasPermission('usage.view')) {
        $nav[] = ['usage', 'tool', route('usage', $companyQuery), 'Pemakaian'];
    }
    if ($currentUser->hasPermission('stock.view')) {
        $nav[] = ['stock', 'box', route('stock'), 'Stok'];
    }
    if ($currentUser->hasPermission('stock_opname.view')) {
        $nav[] = ['stock-opname', 'checklist', route('stock-opname'), 'Stock Opname'];
    }
    if ($currentUser->hasPermission('reports.view')) {
        $nav[] = ['reports', 'chart', route('reports'), 'Report'];
    }
    if ($currentUser->isAdmin()) {
        $nav[] = ['maintenance', 'database', route('system-maintenance'), 'Pemeliharaan Sistem'];
    }
    $masterNav = [
        ['companies', route('master.companies'), 'Perusahaan'],
        ['camps', route('master.camps'), 'Camp'],
        ['vendors', route('master.vendors'), 'Vendor'],
        ['units', route('master.units'), 'Unit'],
        ['item-categories', route('master.item-categories'), 'Kategori'],
        ['items', route('master.items'), 'Barang'],
        ['uoms', route('master.uoms'), 'Satuan'],
    ];
    $activeReport = $activeReport ?? request('report', 'requests');
    $reportNav = [
        'procurement_flow' => ['Proses Pengadaan', route('reports.show', 'procurement-flow'), 'Pengadaan'],
        'requests' => ['Order Request', route('reports'), 'Pengadaan'],
        'vendors' => ['Vendor', route('reports.show', 'vendors'), 'Pengadaan'],
        'purchase_orders' => ['Purchase Order', route('reports.show', 'purchase-orders'), 'Pengadaan'],
        'outstanding_po' => ['Outstanding PO', route('reports.show', 'outstanding-po'), 'Pengadaan'],
        'receipts' => ['Penerimaan', route('reports.show', 'receipts'), 'Pengadaan'],
        'credit_notes' => ['Credit Note', route('reports.show', 'credit-notes'), 'Pengadaan'],
        'stock' => ['Stok Kritis', route('reports.show', 'stock'), 'Persediaan'],
        'stock_locations' => ['Stok per Lokasi', route('reports.show', 'stock-locations'), 'Persediaan'],
        'opnames' => ['Stock Opname', route('reports.show', 'opnames'), 'Persediaan'],
        'stock_movements' => ['Kartu Stok', route('reports.show', 'stock-movements'), 'Persediaan'],
        'inventory_planning' => ['EOQ / ROP', route('reports.show', 'inventory-planning'), 'Persediaan'],
        'usage' => ['Pemakaian', route('reports.show', 'usage'), 'Operasional Site'],
        'dispatches' => ['Pengiriman Site', route('reports.show', 'dispatches'), 'Operasional Site'],
        'activity_logs' => ['Log User', route('reports.show', 'activity-logs'), 'Audit'],
    ];
    if (! $currentUser->hasPermission('reports.stock_movements')) {
        unset($reportNav['stock_movements']);
    }
    if (! $currentUser->hasPermission('reports.activity_logs')) {
        unset($reportNav['activity_logs']);
    }
    // Status berbeda makna pada setiap report, jadi tidak dibawa saat berpindah report.
    $reportFilterQuery = request()->except('report', 'page', 'status');
    $workflowTab = in_array(request('tab'), ['approval', 'history'], true) ? request('tab') : 'active';
    $requestedDispatchTab = request('tab') === 'outbound' ? 'draft' : request('tab');
    $dispatchTab = in_array($requestedDispatchTab, ['draft', 'transit', 'receiving', 'history'], true)
        ? $requestedDispatchTab
        : ($currentUser->hasPermission('dispatches.manage')
            ? 'draft'
            : ($currentUser->hasPermission('site_receipts.manage') ? 'receiving' : 'transit'));
    $workflowSubnav = [
        'requests' => [
            ['active', route('requests', $companyQuery), 'Order Request'],
            ['approval', route('requests', array_merge($companyQuery, ['tab' => 'approval'])), 'Approval Order Request'],
            ['history', route('requests', array_merge($companyQuery, ['tab' => 'history'])), 'History Order Request'],
        ],
        'vendor-prices' => [
            ['active', route('vendor-prices', $companyQuery), 'Harga Vendor'],
            ['approval', route('vendor-prices', array_merge($companyQuery, ['tab' => 'approval'])), 'Approval Pengadaan'],
            ['history', route('vendor-prices', array_merge($companyQuery, ['tab' => 'history'])), 'History Harga Vendor'],
        ],
        'purchase-orders' => [
            ['active', route('purchase-orders', $companyQuery), 'Purchase Order'],
            ['approval', route('purchase-orders', array_merge($companyQuery, ['tab' => 'approval'])), 'Approval Purchase Order'],
            ['history', route('purchase-orders', array_merge($companyQuery, ['tab' => 'history'])), 'History Purchase Order'],
        ],
        'receipts' => [
            ['active', route('receipts', $companyQuery), 'Penerimaan'],
            ['history', route('receipts', array_merge($companyQuery, ['tab' => 'history'])), 'History Penerimaan'],
        ],
        'credit-notes' => [
            ['active', route('credit-notes', $companyQuery), 'Credit Note'],
            ['history', route('credit-notes', array_merge($companyQuery, ['tab' => 'history'])), 'History Credit Note'],
        ],
        'usage' => [
            ['active', route('usage', $companyQuery), 'Pemakaian Bulan Ini'],
            ['history', route('usage', array_merge($companyQuery, ['tab' => 'history'])), 'History Pemakaian'],
        ],
        'dispatches' => array_values(array_filter([
            $currentUser->hasPermission('dispatches.manage') ? ['draft', route('dispatches', array_merge($companyQuery, ['tab' => 'draft'])), 'Draft Packing List'] : null,
            ($currentUser->hasPermission('dispatches.manage') || ! $currentUser->hasPermission('site_receipts.manage')) ? ['transit', route('dispatches', array_merge($companyQuery, ['tab' => 'transit'])), 'Dalam Perjalanan'] : null,
            $currentUser->hasPermission('site_receipts.manage') ? ['receiving', route('dispatches', array_merge($companyQuery, ['tab' => 'receiving'])), 'Penerimaan Site'] : null,
            ['history', route('dispatches', array_merge($companyQuery, ['tab' => 'history'])), 'Riwayat'],
        ])),
    ];
    if ($currentUser->hasPermission('users.manage')) {
        $masterNav[] = ['users', route('master.users'), 'User'];
    }
    if ($currentUser->hasPermission('roles.manage')) {
        $masterNav[] = ['roles', route('master.roles'), 'Role'];
    }
    $activeMaster = $activeMaster ?? 'camps';
    $pageTitle = $titles[$activeMenu] ?? $titles['dashboard'];
@endphp

<div class="app">
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-mark" aria-label="Logo inventory">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M3 9.5 12 4l9 5.5-9 5.5z"></path>
                    <path d="M3 9.5v5L12 20l9-5.5v-5"></path>
                    <path d="M12 15v5"></path>
                    <path d="M7.5 7 16.5 12"></path>
                    <path d="M16.5 7 7.5 12"></path>
                </svg>
            </div>
            <div>
                <strong>Inventory AL</strong>
                <span>Procurement, gudang, dan site</span>
            </div>
        </div>
        <nav class="nav">
            @foreach ($nav as [$key, $icon, $url, $label])
                <a class="{{ $activeMenu === $key ? 'active' : '' }}" href="{{ $url }}">
                    <span class="ico">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            @switch($icon)
                                @case('dashboard')
                                    <rect x="3" y="3" width="7" height="8"></rect><rect x="14" y="3" width="7" height="5"></rect><rect x="14" y="12" width="7" height="9"></rect><rect x="3" y="15" width="7" height="6"></rect>
                                    @break
                                @case('master')
                                    <path d="M4 7h16"></path><path d="M4 12h16"></path><path d="M4 17h16"></path><circle cx="7" cy="7" r="1"></circle><circle cx="7" cy="12" r="1"></circle><circle cx="7" cy="17" r="1"></circle>
                                    @break
                                @case('request')
                                    <path d="M7 3h7l4 4v14H7z"></path><path d="M14 3v5h5"></path><path d="M9 13h6"></path><path d="M9 17h4"></path>
                                    @break
                                @case('shield')
                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><path d="m9 12 2 2 4-5"></path>
                                    @break
                                @case('tag')
                                    <path d="M20 13 11 22 2 13V2h11z"></path><circle cx="7.5" cy="7.5" r="1.5"></circle>
                                    @break
                                @case('inbox')
                                    <path d="M4 4h16v16H4z"></path><path d="M4 14h4l2 3h4l2-3h4"></path>
                                    @break
                                @case('return')
                                    <path d="M9 7H5v4"></path><path d="M5 11c1.8-4 7-6 11-3 3.5 2.6 3.5 8 0 10.5-2.6 1.9-6.2 1.8-8.8-.2"></path><path d="M19 17v-4h-4"></path>
                                    @break
                                @case('cart')
                                    <circle cx="9" cy="20" r="1"></circle><circle cx="18" cy="20" r="1"></circle><path d="M3 4h2l2.5 11h10.5l2-7H7"></path>
                                    @break
                                @case('truck')
                                    <path d="M3 7h11v9H3z"></path><path d="M14 10h4l3 3v3h-7z"></path><circle cx="7" cy="18" r="2"></circle><circle cx="17" cy="18" r="2"></circle>
                                    @break
                                @case('tool')
                                    <path d="M14 7 7 14"></path><path d="m5 16 3 3"></path><path d="m16 5 3 3"></path><path d="M14 7a4 4 0 0 1 5-5 4 4 0 0 1-5 5z"></path>
                                    @break
                                @case('box')
                                    <path d="m21 8-9-5-9 5 9 5z"></path><path d="M3 8v8l9 5 9-5V8"></path><path d="M12 13v8"></path>
                                    @break
                                @case('checklist')
                                    <path d="M8 6h13"></path><path d="M8 12h13"></path><path d="M8 18h13"></path><path d="m3 6 1 1 2-2"></path><path d="m3 12 1 1 2-2"></path><path d="m3 18 1 1 2-2"></path>
                                    @break
                                @case('chart')
                                    <path d="M4 19V5"></path><path d="M4 19h16"></path><rect x="7" y="11" width="3" height="5"></rect><rect x="12" y="7" width="3" height="9"></rect><rect x="17" y="9" width="3" height="7"></rect>
                                    @break
                                @case('database')
                                    <ellipse cx="12" cy="5" rx="8" ry="3"></ellipse><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5"></path><path d="M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"></path>
                                    @break
                            @endswitch
                        </svg>
                    </span>
                    <span>{{ $label }}</span>
                </a>
                @if ($key === 'master' && $activeMenu === 'master')
                    <div class="subnav">
                        @foreach ($masterNav as [$masterKey, $masterUrl, $masterLabel])
                            <a class="{{ $activeMaster === $masterKey ? 'active' : '' }}" href="{{ $masterUrl }}">{{ $masterLabel }}</a>
                        @endforeach
                    </div>
                @endif
                @if (isset($workflowSubnav[$key]) && $activeMenu === $key)
                    <div class="subnav">
                        @foreach ($workflowSubnav[$key] as [$tabKey, $tabUrl, $tabLabel])
                            <a class="{{ ($key === 'dispatches' ? $dispatchTab : $workflowTab) === $tabKey ? 'active' : '' }}" href="{{ $tabUrl }}">{{ $tabLabel }}</a>
                        @endforeach
                    </div>
                @endif
                @if ($key === 'reports' && $activeMenu === 'reports')
                    <div class="subnav">
                        @php $lastReportGroup = null; @endphp
                        @foreach ($reportNav as $reportKey => [$reportLabel, $reportUrl, $reportGroup])
                            @if ($reportGroup !== $lastReportGroup)
                                <span class="subnav-label">{{ $reportGroup }}</span>
                                @php $lastReportGroup = $reportGroup; @endphp
                            @endif
                            <a class="{{ $activeReport === $reportKey ? 'active' : '' }}" href="{{ $reportUrl }}{{ $reportFilterQuery ? '?'.http_build_query($reportFilterQuery) : '' }}">{{ $reportLabel }}</a>
                        @endforeach
                    </div>
                @endif
            @endforeach
        </nav>
        <div class="sidebar-bottom">
            <strong>AL Inventory</strong>
            <span>Versi 1.0 - {{ now()->format('Y') }}</span>
        </div>
    </aside>
    <button class="sidebar-backdrop" type="button" data-sidebar-close aria-label="Tutup menu"></button>

    <div class="content">
        <header class="topbar">
            <button class="mobile-nav-toggle" type="button" data-sidebar-toggle aria-label="Buka menu" title="Buka menu">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"></path></svg>
            </button>
            <div class="topbar-title">
                <h1>{{ $pageTitle }}</h1>
            </div>
            <div class="topbar-tools">
                @if (($pendingToleranceReviews ?? collect())->isNotEmpty())
                    <a class="pill wait date-badge" href="{{ route('approvals') }}">{{ $pendingToleranceReviews->count() }} review selisih</a>
                @endif
                <details class="notification-menu">
                    <summary>
                        Notifikasi
                        @if (($unreadNotificationsCount ?? 0) > 0)
                            <span class="notification-badge">{{ $unreadNotificationsCount }}</span>
                        @endif
                    </summary>
                    <div class="notification-dropdown">
                        @forelse (($notifications ?? collect()) as $notification)
                            <form method="post" action="{{ route('notifications.read', $notification) }}">
                                @csrf
                                <button class="notification-item {{ $notification->read_at ? '' : 'unread' }}" type="submit">
                                    <span class="notification-dot" aria-hidden="true"></span>
                                    <span class="notification-copy">
                                        <strong>{{ $notification->title }}</strong>
                                        <span class="notification-body">{{ $notification->body ?: 'Ada proses yang perlu ditinjau.' }}</span>
                                        <time class="notification-time" datetime="{{ $notification->created_at?->toIso8601String() }}">{{ $notification->created_at?->diffForHumans() }}</time>
                                    </span>
                                </button>
                            </form>
                        @empty
                            <div class="notification-item notification-empty">
                                <span class="notification-dot" aria-hidden="true"></span>
                                <span class="notification-copy">
                                    <strong>Tidak ada notifikasi</strong>
                                </span>
                            </div>
                        @endforelse
                    </div>
                </details>
                <span class="pill date-badge">{{ now()->format('d M Y') }}</span>
                <details class="account-menu">
                    <summary>
                        <div class="account-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 2)) }}</div>
                        <div class="account-meta">
                            <strong>{{ auth()->user()->name }}</strong>
                            <span>{{ $roleName }}</span>
                        </div>
                        <span class="account-caret">v</span>
                    </summary>
                    <div class="account-dropdown">
                        <div class="account-meta">
                            <strong>{{ auth()->user()->name }}</strong>
                            <span>{{ auth()->user()->email }}</span>
                        </div>
                        <span class="role-badge">Role: {{ $roleName }}</span>
                        <a class="button ghost account-action" href="{{ route('profile.edit') }}">Edit Profil</a>
                        <form method="post" action="{{ route('logout') }}">
                            @csrf
                            <button class="logout">Logout</button>
                        </form>
                    </div>
                </details>
            </div>
        </header>

        <main>
            @if (session('success'))
                <div class="notice success" role="status">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="notice error" role="alert">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="notice error" role="alert">{{ $errors->first() }}</div>
            @endif

            @if (in_array($activeMenu, $companyScopedMenus, true) && (($companies ?? collect())->count() > 1 || $activeCompanyId))
                <section class="company-tabs-wrap" aria-label="Filter perusahaan">
                    <div class="company-tabs">
                        <a class="company-tab {{ ! $activeCompanyId ? 'active' : '' }}" href="{{ $companyTabUrl(null) }}">
                            Semua Perusahaan
                            <span class="company-tab-count">{{ $companyTabCounts['all'] ?? 0 }}</span>
                        </a>
                        @foreach ($companies as $company)
                            <a class="company-tab {{ (int) $activeCompanyId === (int) $company->id ? 'active' : '' }}" href="{{ $companyTabUrl((int) $company->id) }}">
                                {{ $company->name }}
                                <span class="company-tab-count">{{ $companyTabCounts[$company->id] ?? 0 }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            @include('inventory.sections.dashboard')
            @include('inventory.sections.master')
            @include('inventory.sections.requests')
            @include('inventory.sections.approvals')
            @include('inventory.sections.vendor-prices')
            @include('inventory.sections.purchase-orders')
            @include('inventory.sections.receipts')
            @include('inventory.sections.credit-notes')
            @include('inventory.sections.dispatches')
            @include('inventory.sections.usage')
            @include('inventory.sections.stock')
            @include('inventory.sections.stock-opname')
            @include('inventory.sections.reports-v2')
            @include('inventory.sections.maintenance')
        </main>
    </div>
</div>
<script>
    document.querySelectorAll('[data-request-form]').forEach((form) => {
        const list = form.querySelector('[data-item-list]');
        const template = form.querySelector('[data-item-template]');

        form.querySelector('[data-add-item]')?.addEventListener('click', () => {
            const index = list.querySelectorAll('[data-item-row]').length;
            const html = template.innerHTML.replaceAll('__INDEX__', index);
            list.insertAdjacentHTML('beforeend', html);
        });

        form.addEventListener('click', (event) => {
            if (! event.target.matches('[data-remove-item]')) {
                return;
            }

            const rows = list.querySelectorAll('[data-item-row]');
            if (rows.length > 1) {
                event.target.closest('[data-item-row]').remove();
            }
        });
    });

    document.querySelectorAll('[data-vendor-form]').forEach((form) => {
        const list = form.querySelector('[data-vendor-list]');
        const template = form.querySelector('[data-vendor-template]');

        form.querySelector('[data-add-vendor]')?.addEventListener('click', () => {
            const index = list.querySelectorAll('[data-vendor-row]').length;
            const html = template.innerHTML.replaceAll('__INDEX__', index);
            list.insertAdjacentHTML('beforeend', html);
        });

        form.addEventListener('click', (event) => {
            if (! event.target.matches('[data-remove-vendor]')) {
                return;
            }

            event.target.closest('[data-vendor-row]').remove();
        });
    });

    document.querySelectorAll('[data-edit-target]').forEach((button) => {
        button.addEventListener('click', () => {
            const target = document.getElementById(button.dataset.editTarget);
            if (! target) {
                return;
            }

            target.hidden = ! target.hidden;
            button.closest('details')?.removeAttribute('open');
        });
    });

    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (! window.confirm(form.dataset.confirm)) {
                event.preventDefault();
                const fileInput = form.querySelector('input[type="file"]');
                if (fileInput) fileInput.value = '';
            }
        });
    });

    document.querySelectorAll('[data-href]').forEach((row) => {
        row.addEventListener('click', (event) => {
            if (event.target.closest('a, button, form, input, select, textarea, details, summary')) {
                return;
            }

            window.location.href = row.dataset.href;
        });

        row.addEventListener('keydown', (event) => {
            if ((event.key === 'Enter' || event.key === ' ') && ! event.target.closest('a, button, form, input, select, textarea, details, summary')) {
                event.preventDefault();
                window.location.href = row.dataset.href;
            }
        });
    });

    document.querySelectorAll('table').forEach((table, index) => {
        const bodyRows = Array.from(table.querySelectorAll('tbody tr'));
        if (bodyRows.length < 5 || table.classList.contains('report-table') || table.closest('.mini-table') || table.closest('section')?.querySelector('.transaction-toolbar')) {
            return;
        }

        const toolbar = document.createElement('div');
        toolbar.className = 'table-toolbar';
        const search = document.createElement('input');
        search.className = 'table-search';
        search.type = 'search';
        search.placeholder = 'Cari data tabel...';
        search.setAttribute('aria-label', 'Cari data tabel');
        toolbar.appendChild(search);
        table.parentNode.insertBefore(toolbar, table);

        search.addEventListener('input', () => {
            const keyword = search.value.trim().toLowerCase();
            bodyRows.forEach((row) => {
                row.hidden = keyword !== '' && ! row.textContent.toLowerCase().includes(keyword);
            });
        });
    });

    document.querySelector('[data-export-report]')?.addEventListener('click', () => {
        const tables = Array.from(document.querySelectorAll('main section table'));
        const visibleTables = tables.filter((table) => table.offsetParent !== null);
        const rows = [];

        visibleTables.forEach((table) => {
            const title = table.closest('section')?.querySelector('h2')?.textContent?.trim();
            if (title) {
                rows.push([title]);
            }

            table.querySelectorAll('tr').forEach((tr) => {
                const cells = Array.from(tr.querySelectorAll('th, td')).map((cell) =>
                    cell.textContent.replace(/\s+/g, ' ').trim()
                );
                if (cells.length) {
                    rows.push(cells);
                }
            });
            rows.push([]);
        });

        const csv = rows.map((row) => row.map((value) => `"${String(value).replaceAll('"', '""')}"`).join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `report-${new Date().toISOString().slice(0, 10)}.csv`;
        link.click();
        URL.revokeObjectURL(link.href);
    });

    document.querySelectorAll('[data-usage-form]').forEach((form) => {
        const unitSelect = form.querySelector('[data-usage-unit]');
        const itemSelect = form.querySelector('[name="inventory_item_id"]');
        const dependents = form.querySelectorAll('[data-usage-dependent]');
        const message = form.querySelector('[data-usage-unit-message]');

        const syncUsageUnit = () => {
            const hasUnit = Boolean(unitSelect.value);
            const campId = unitSelect.selectedOptions[0]?.dataset.campId;
            dependents.forEach((field) => {
                field.disabled = ! hasUnit;
            });
            if (itemSelect) {
                Array.from(itemSelect.options).forEach((option) => {
                    option.hidden = Boolean(option.dataset.campId) && option.dataset.campId !== campId;
                });
                if (itemSelect.selectedOptions[0]?.hidden) itemSelect.value = '';
            }
            if (message) {
                message.hidden = hasUnit;
            }
        };

        unitSelect.addEventListener('change', syncUsageUnit);
        syncUsageUnit();
    });

    document.querySelector('[data-sidebar-toggle]')?.addEventListener('click', () => {
        document.body.classList.toggle('nav-open');
    });

    document.querySelector('[data-sidebar-close]')?.addEventListener('click', () => {
        document.body.classList.remove('nav-open');
    });

    document.querySelectorAll('.sidebar a').forEach((link) => {
        link.addEventListener('click', () => document.body.classList.remove('nav-open'));
    });
</script>
</body>
</html>
