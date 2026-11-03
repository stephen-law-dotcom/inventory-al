@extends('layouts.inventory')

@section('content')
    @php
        $notificationStatus = $notificationStatus ?? 'all';
        $notificationPaginator = $notificationPaginator ?? null;
    @endphp

    <div class="stack">
        <section>
            <div class="section-heading">
                <div>
                    <h2>Riwayat Notifikasi</h2>
                    <p class="muted">Aktivitas dan proses yang ditujukan untuk akun Anda.</p>
                </div>
                @if (($unreadNotificationsCount ?? 0) > 0)
                    <form method="post" action="{{ route('notifications.read-all') }}">
                        @csrf
                        <button class="ghost" type="submit">Tandai Semua Dibaca</button>
                    </form>
                @endif
            </div>

            <div class="company-tabs" aria-label="Filter notifikasi">
                <a class="company-tab {{ $notificationStatus === 'all' ? 'active' : '' }}" href="{{ route('notifications.index') }}">Semua</a>
                <a class="company-tab {{ $notificationStatus === 'unread' ? 'active' : '' }}" href="{{ route('notifications.index', ['status' => 'unread']) }}">
                    Belum Dibaca
                    <span class="company-tab-count">{{ $unreadNotificationsCount ?? 0 }}</span>
                </a>
            </div>

            <div class="stack">
                @forelse (($notificationPaginator ?? collect()) as $notification)
                    <article class="receipt-card">
                        <div class="receipt-card-main">
                            <div>
                                <h3>
                                    {{ $notification->title }}
                                    <span class="pill {{ $notification->read_at ? 'ok' : 'wait' }}">{{ $notification->read_at ? 'Sudah dibaca' : 'Belum dibaca' }}</span>
                                </h3>
                                <p>{{ $notification->body ?: 'Ada proses yang perlu ditinjau.' }}</p>
                                <p class="muted">{{ $notification->created_at?->format('d M Y H:i') }} - {{ $notification->created_at?->diffForHumans() }}</p>
                            </div>
                            <form method="post" action="{{ route('notifications.read', $notification) }}">
                                @csrf
                                <button type="submit">Buka</button>
                            </form>
                        </div>
                    </article>
                @empty
                    <div class="empty">Tidak ada notifikasi pada filter ini.</div>
                @endforelse
            </div>

            @include('inventory.components.transaction-pagination', ['transactionPaginator' => $notificationPaginator])
        </section>
    </div>
@endsection
