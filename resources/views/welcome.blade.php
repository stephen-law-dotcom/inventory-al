<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Sistem Inventory AL') }}</title>
    <meta http-equiv="refresh" content="0;url={{ auth()->check() ? route(auth()->user()->defaultRouteName()) : route('login') }}">
    <style>
        :root {
            --bg: #eef6f8;
            --ink: #102a43;
            --muted: #667788;
            --accent: #087f78;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: var(--bg);
            color: var(--ink);
            font-family: Inter, "Segoe UI", Roboto, Arial, sans-serif;
        }

        .card {
            width: min(420px, calc(100% - 32px));
            padding: 24px;
            border: 1px solid #dce8ee;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 16px 36px rgba(16, 42, 67, .11);
            text-align: center;
        }

        h1 { margin: 0 0 8px; font-size: 24px; }
        p { margin: 0 0 18px; color: var(--muted); }

        a {
            display: inline-flex;
            min-height: 40px;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            border-radius: 7px;
            background: var(--accent);
            color: #fff;
            font-weight: 800;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <main class="card">
        <h1>Sistem Inventory AL</h1>
        <a href="{{ auth()->check() ? route(auth()->user()->defaultRouteName()) : route('login') }}">Masuk ke Sistem</a>
    </main>
</body>
</html>
