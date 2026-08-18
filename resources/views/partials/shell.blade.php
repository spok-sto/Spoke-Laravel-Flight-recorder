<div class="spoke-shell">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="brand-title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--accent-cyan)">
                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                </svg>
                <span>Spoke</span>
            </div>
            <span class="brand-badge {{ $debugTools ? '' : 'brand-badge-monitor' }}">{{ $debugTools ? 'Debug' : 'Monitor' }}</span>
        </div>

        <nav class="sidebar-nav">
            <button class="nav-item active" data-tab="server" onclick="SpokeApp.setTab('server')">
                <div class="nav-label">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect><rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect><line x1="6" y1="6" x2="6.01" y2="6"></line><line x1="6" y1="18" x2="6.01" y2="18"></line></svg>
                    <span>Server & Info</span>
                </div>
            </button>

            <button class="nav-item" data-tab="logs" onclick="SpokeApp.setTab('logs')">
                <div class="nav-label">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    <span>Logs</span>
                </div>
                <span class="nav-count" id="nav-logs-count">-</span>
            </button>

            <button class="nav-item" data-tab="exceptions" onclick="SpokeApp.setTab('exceptions')">
                <div class="nav-label">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                    <span>Exceptions</span>
                </div>
                <span class="nav-count" id="nav-exceptions-count">-</span>
            </button>

            <button class="nav-item" data-tab="queries" onclick="SpokeApp.setTab('queries')">
                <div class="nav-label">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path></svg>
                    <span>Queries (SQL)</span>
                </div>
                <span class="nav-count" id="nav-queries-count">-</span>
            </button>

            <button class="nav-item" data-tab="requests" onclick="SpokeApp.setTab('requests')">
                <div class="nav-label">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                    <span>Requests</span>
                </div>
                <span class="nav-count" id="nav-requests-count">-</span>
            </button>

            <button class="nav-item" data-tab="http" onclick="SpokeApp.setTab('http')">
                <div class="nav-label">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                    <span>Outgoing HTTP</span>
                </div>
                <span class="nav-count" id="nav-http-count">-</span>
            </button>

            <button class="nav-item" data-tab="mails" onclick="SpokeApp.setTab('mails')">
                <div class="nav-label">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                    <span>Mails</span>
                </div>
                <span class="nav-count" id="nav-mails-count">-</span>
            </button>

            <button class="nav-item" data-tab="queue" onclick="SpokeApp.setTab('queue')">
                <div class="nav-label">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                    <span>Queue</span>
                </div>
                <span class="nav-count" id="nav-queue-count">-</span>
            </button>

            <button class="nav-item" data-tab="scheduler" onclick="SpokeApp.setTab('scheduler')">
                <div class="nav-label">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    <span>Scheduler</span>
                </div>
            </button>

            <button class="nav-item" data-tab="redis" onclick="SpokeApp.setTab('redis')">
                <div class="nav-label">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect><rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect><line x1="6" y1="6" x2="6.01" y2="6"></line><line x1="6" y1="18" x2="6.01" y2="18"></line></svg>
                    <span>Redis & Keys</span>
                </div>
            </button>
        </nav>

        <div class="sidebar-footer">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span>Live Mode</span>
                <span class="pulse-dot"></span>
            </div>
            <div style="font-family:var(--font-mono); font-size:0.7rem; color:var(--text-dim);" id="sidebar-meta">
                v{{ config('spoke.version') }} · {{ date('Y') }}
            </div>
            <a href="https://github.com/spok-sto/spoke" target="_blank" rel="noopener noreferrer">github.com/spok-sto/spoke</a>
        </div>
    </aside>

    <main class="main-wrapper">
        <header class="topbar">
            <div class="topbar-title">
                <span id="page-title">Server & Metrics</span>
            </div>

            <div class="topbar-actions">
                @if ($debugTools)
                <button class="btn btn-sm" id="capture-btn" onclick="SpokeApp.toggleCapture()"
                        title="Capture requests and outgoing HTTP">
                    <span id="capture-dot" style="width:8px; height:8px; border-radius:50%; background:var(--text-dim); display:inline-block;"></span>
                    <span id="capture-label">Capture</span>
                </button>
                @else
                <span class="capture-off-chip" title="Full payload capture requires APP_DEBUG=true. Exceptions, slow SQL, failed requests, outbound HTTP and mail still record.">Capture off</span>
                @endif

                <select class="select" id="auto-refresh-select" onchange="SpokeApp.setAutoRefresh(this.value)">
                    <option value="0">Auto-refresh: Off</option>
                    <option value="5">Auto-refresh: 5s</option>
                    <option value="10" selected>Auto-refresh: 10s</option>
                    <option value="30">Auto-refresh: 30s</option>
                </select>

                <button class="btn btn-sm" onclick="SpokeApp.refreshCurrentTab()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
                    <span>Refresh</span>
                </button>
            </div>
        </header>

        <section class="content-area" id="tab-content">
        </section>
    </main>
</div>

<div class="modal-overlay" id="detail-modal" onclick="if(event.target === this) SpokeApp.closeModal()">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 style="font-size: 1rem; font-weight: 600;" id="modal-title">Details</h3>
            <button class="btn btn-sm" onclick="SpokeApp.closeModal()">✕</button>
        </div>
        <div class="modal-body" id="modal-body">
        </div>
        <div class="modal-footer" id="modal-footer">
            <button class="btn btn-sm" onclick="SpokeApp.copyModalContent()">Copy</button>
            <button class="btn btn-sm btn-primary" onclick="SpokeApp.closeModal()">Close</button>
        </div>
    </div>
</div>

<div class="toast" id="spoke-toast">Message</div>
