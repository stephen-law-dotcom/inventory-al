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
