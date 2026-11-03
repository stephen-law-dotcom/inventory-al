<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sistem Inventory AL</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=2">
    <link rel="stylesheet" href="{{ asset('css/inventory.css') }}">
</head>
<body>

<div class="app">
    @include('layouts.partials.inventory-sidebar')

    <div class="content">
        @include('layouts.partials.inventory-topbar')

        <main>
            @include('layouts.partials.flash-message')

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

            @yield('content')
        </main>
    </div>
</div>
<script src="{{ asset('js/inventory.js') }}" defer></script>
</body>
</html>


