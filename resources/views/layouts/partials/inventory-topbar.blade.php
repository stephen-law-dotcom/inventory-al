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
                <a class="notification-item notification-view-all" href="{{ route('notifications.index') }}">
                    <span class="notification-copy"><strong>Lihat semua notifikasi</strong></span>
                </a>
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
