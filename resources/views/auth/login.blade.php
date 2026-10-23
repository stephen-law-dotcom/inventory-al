<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Sistem Inventory AL</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=2">
    <style>
        :root {
            --background: #eef3f5;
            --surface: #ffffff;
            --text: #102a43;
            --muted: #647484;
            --border: #d7e1e6;
            --primary: #07847d;
            --primary-hover: #066a65;
            --accent: #e4a62d;
            --danger: #b42318;
        }

        * { box-sizing: border-box; }

        html, body { min-height: 100%; }

        body {
            min-height: 100vh;
            margin: 0;
            display: grid;
            place-items: center;
            padding: 24px;
            background: var(--background);
            color: var(--text);
            font-family: Inter, "Segoe UI", Roboto, Arial, sans-serif;
            font-size: 14px;
            line-height: 1.5;
        }

        .login-shell {
            width: min(960px, 100%);
            min-height: 560px;
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(360px, .9fr);
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--surface);
            box-shadow: 0 18px 48px rgba(16, 42, 67, .12);
        }

        .brand-panel {
            position: relative;
            min-width: 0;
            padding: 32px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
            background: #102a43;
            color: #ffffff;
        }

        .brand-heading {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-mark {
            width: 42px;
            height: 42px;
            flex: 0 0 42px;
            display: grid;
            place-items: center;
            border-radius: 7px;
            background: #ffffff;
            color: var(--primary);
        }

        .brand-mark svg {
            width: 25px;
            height: 25px;
            fill: none;
            stroke: currentColor;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .brand-heading strong {
            display: block;
            font-size: 20px;
            line-height: 1.2;
        }

        .brand-heading span {
            display: block;
            margin-top: 3px;
            color: #c8d6df;
            font-size: 12px;
        }

        .brand-image {
            position: absolute;
            inset: 96px 0 0;
            width: 100%;
            height: calc(100% - 96px);
            object-fit: cover;
            object-position: center;
            opacity: .82;
        }

        .brand-caption {
            position: relative;
            z-index: 2;
            max-width: 390px;
            padding: 18px 20px;
            border-left: 3px solid var(--accent);
            background: rgba(16, 42, 67, .88);
        }

        .brand-caption strong {
            display: block;
            font-size: 18px;
        }

        .brand-caption span {
            display: block;
            margin-top: 4px;
            color: #d6e1e7;
            font-size: 13px;
        }

        .form-panel {
            min-width: 0;
            padding: 48px 42px;
            display: flex;
            align-items: center;
        }

        .form-content { width: 100%; }

        h1, p { margin: 0; }

        h1 {
            font-size: 27px;
            line-height: 1.25;
        }

        .intro {
            margin-top: 7px;
            color: var(--muted);
        }

        .error-message {
            margin-top: 22px;
            padding: 11px 12px;
            border: 1px solid #efb2ad;
            border-radius: 6px;
            background: #fff6f5;
            color: var(--danger);
            font-weight: 700;
        }

        form {
            display: grid;
            gap: 17px;
            margin-top: 28px;
        }

        label {
            display: grid;
            gap: 7px;
            color: #354b5b;
            font-size: 13px;
            font-weight: 700;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            min-height: 46px;
            padding: 11px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            outline: none;
            background: var(--surface);
            color: var(--text);
            font: inherit;
            transition: border-color .15s ease, box-shadow .15s ease;
        }

        input::placeholder { color: #98a5af; }

        input[type="email"]:focus,
        input[type="password"]:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(7, 132, 125, .12);
        }

        .remember {
            display: flex;
            align-items: center;
            gap: 9px;
            color: var(--muted);
            font-weight: 600;
        }

        .remember input {
            width: 17px;
            height: 17px;
            margin: 0;
            accent-color: var(--primary);
        }

        .submit-button {
            min-height: 46px;
            border: 0;
            border-radius: 6px;
            background: var(--primary);
            color: #ffffff;
            font: inherit;
            font-weight: 800;
            cursor: pointer;
            transition: background .15s ease;
        }

        .submit-button:hover,
        .submit-button:focus-visible { background: var(--primary-hover); }

        .access-note {
            margin-top: 24px;
            padding-top: 18px;
            border-top: 1px solid var(--border);
            color: var(--muted);
            font-size: 12px;
        }

        @media (max-width: 760px) {
            body { padding: 16px; }

            .login-shell {
                min-height: auto;
                grid-template-columns: 1fr;
            }

            .brand-panel {
                min-height: 190px;
                padding: 24px;
            }

            .brand-image {
                inset: 0;
                height: 100%;
                object-position: center 48%;
                opacity: .38;
            }

            .brand-caption { display: none; }

            .form-panel { padding: 34px 28px; }
        }

        @media (max-width: 420px) {
            body {
                display: block;
                padding: 0;
                background: var(--surface);
            }

            .login-shell {
                min-height: 100vh;
                border: 0;
                border-radius: 0;
                box-shadow: none;
            }

            .brand-panel {
                min-height: 150px;
                padding: 20px;
            }

            .form-panel { padding: 30px 22px; }

            h1 { font-size: 24px; }
        }
    </style>
</head>
<body>
<main class="login-shell">
    <section class="brand-panel" aria-label="Sistem Inventory AL">
        <img class="brand-image" src="{{ asset('assets/images/login-inventory-hero.webp') }}" alt="">

        <div class="brand-heading">
            <div class="brand-mark" aria-hidden="true">
                <svg viewBox="0 0 24 24">
                    <path d="M3 9.5 12 4l9 5.5-9 5.5z"></path>
                    <path d="M3 9.5v5L12 20l9-5.5v-5"></path>
                    <path d="M12 15v5"></path>
                </svg>
            </div>
            <div>
                <strong>Inventory AL</strong>
                <span>Procurement, gudang, dan site</span>
            </div>
        </div>

        <div class="brand-caption">
            <strong>Sistem Inventory Terintegrasi</strong>
            <span>Kelola pengadaan, stok, dan distribusi barang dalam satu sistem.</span>
        </div>
    </section>

    <section class="form-panel">
        <div class="form-content">
            <h1>Masuk ke sistem</h1>
            <p class="intro">Gunakan akun yang telah terdaftar.</p>

            @if ($errors->any())
                <div class="error-message" role="alert">{{ $errors->first() }}</div>
            @endif

            <form method="post" action="{{ route('login.post') }}">
                @csrf

                <label for="email">
                    Email
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" placeholder="nama@perusahaan.com">
                </label>

                <label for="password">
                    Password
                    <input id="password" type="password" name="password" required autocomplete="current-password" placeholder="Masukkan password">
                </label>

                <label class="remember" for="remember">
                    <input id="remember" type="checkbox" name="remember" value="1" @checked(old('remember'))>
                    Ingat sesi login
                </label>

                <button class="submit-button" type="submit">Masuk</button>
            </form>

            <p class="access-note">Akses menu dan data mengikuti role akun Anda.</p>
        </div>
    </section>
</main>
</body>
</html>
