<script>
const SpokeApp = {
    apiBase: @json($apiBase),
    csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
    debugTools: @json($debugTools),
    currentTab: 'server',
    refreshTimer: null,
    refreshInterval: 10,
    modalRawText: '',

    state: {
        logs: {
            file: '',
            level: '',
            search: '',
            page: 1,
            cursor: null,
            cursorHistory: [],
            nextCursor: null,
            hasMore: false
        },
        server: { view: 'live', range: '24h', days: 30 },
        exceptions: { date: '', search: '', page: 1, activeSubTab: 'groups' },
        queries: { date: '', search: '', page: 1, activeSubTab: 'log', rankingSort: 'total_ms' },
        requests: { date: '', search: '', page: 1, view: 'log' },
        http: { date: '', search: '', page: 1 },
        mails: { date: '', search: '', page: 1 },
        queue: { activeTab: 'pending', date: '', search: '', page: 1 },
        scheduler: { activeSubTab: 'tasks', date: '', search: '', page: 1, commandsDate: '', commandsSearch: '', commandsPage: 1 },
        redis: { connection: 'default', pattern: '*', activeSubTab: 'explorer', commandsDate: '', commandsSearch: '', commandsPage: 1 }
    },

    init() {
        this.setTab('server');
        this.setAutoRefresh(10);
        this.loadCaptureState();
    },

    captureActive: false,

    async loadCaptureState() {
        const data = await this.fetchJson('/capture');
        if (data) this.renderCaptureButton(data);
    },

    async toggleCapture() {
        if (!this.debugTools) {
            this.showToast('Capture requires APP_DEBUG=true.');
            return;
        }
        const data = await this.fetchJson('/capture', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ active: !this.captureActive })
        });
        if (!data) return;
        this.renderCaptureButton(data);
        this.showToast(data.active
            ? `Full payload capture ON (until ${data.expires_at})`
            : 'Full payload capture OFF');
    },

    renderCaptureButton(state) {
        this.captureActive = !!state.active;
        const btn = document.getElementById('capture-btn');
        const dot = document.getElementById('capture-dot');
        const label = document.getElementById('capture-label');
        if (!btn) return;
        dot.style.background = this.captureActive ? 'var(--accent-red)' : 'var(--text-dim)';
        dot.style.boxShadow = this.captureActive ? '0 0 6px var(--accent-red)' : 'none';
        label.textContent = this.captureActive ? 'Capture: ON' : 'Capture';
        btn.title = this.captureActive
            ? `Capturing requests and outgoing HTTP (until ${state.expires_at})`
            : 'Capture requests and outgoing HTTP';
    },

    prettyJson(raw) {
        if (raw === null || raw === undefined || raw === '') return '';
        try { return JSON.stringify(JSON.parse(raw), null, 2); } catch (e) { return String(raw); }
    },

    payloadBlock(title, raw) {
        const text = this.prettyJson(raw);
        if (!text) return '';
        return `
            <div style="margin:0.65rem 0 0;">
                <div style="color:var(--text-muted); font-size:0.68rem; letter-spacing:0.08em; text-transform:uppercase; margin-bottom:0.3rem;">${title}</div>
                <div class="code-block" style="white-space:pre-wrap; word-break:break-all; max-height:240px; overflow:auto;">${this.escapeHtml(text)}</div>
            </div>
        `;
    },

    setTab(tab) {
        this.currentTab = tab;
        document.querySelectorAll('.nav-item').forEach(el => {
            el.classList.toggle('active', el.dataset.tab === tab);
        });

        const titleMap = {
            server: 'Server & Metrics',
            logs: 'Laravel Logs',
            exceptions: 'Exception Center',
            queries: 'SQL Queries',
            requests: 'HTTP Requests',
            http: 'Outgoing HTTP',
            mails: 'Sent Mails',
            queue: 'Queue Jobs',
            scheduler: 'Scheduler & Commands',
            redis: 'Redis & Key Explorer'
        };
        document.getElementById('page-title').textContent = titleMap[tab] || 'Spoke';

        this.refreshCurrentTab();
    },

    setAutoRefresh(seconds) {
        this.refreshInterval = parseInt(seconds, 10);
        if (this.refreshTimer) clearInterval(this.refreshTimer);

        if (this.refreshInterval > 0) {
            this.refreshTimer = setInterval(() => {
                this.refreshCurrentTab(true);
            }, this.refreshInterval * 1000);
        }
    },

    refreshCurrentTab(isBackground = false) {
        if (this.currentTab === 'server') this.loadServer();
        else if (this.currentTab === 'logs') this.loadLogs();
        else if (this.currentTab === 'exceptions') this.loadExceptions();
        else if (this.currentTab === 'queries') this.loadQueries();
        else if (this.currentTab === 'requests') this.loadRequests();
        else if (this.currentTab === 'http') this.loadHttpCalls();
        else if (this.currentTab === 'mails') this.loadMails();
        else if (this.currentTab === 'queue') this.loadQueue();
        else if (this.currentTab === 'scheduler') this.loadScheduler();
        else if (this.currentTab === 'redis') this.loadRedis();
    },

    async fetchJson(endpoint, options = {}) {
        try {
            const { headers = {}, ...fetchOptions } = options;
            const res = await fetch(this.apiBase + endpoint, {
                ...fetchOptions,
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                    ...headers
                }
            });
            if (!res.ok) throw new Error('Status: ' + res.status);
            return await res.json();
        } catch (e) {
            console.error('Spoke API Error:', e);
            return null;
        }
    },

    showToast(msg) {
        const toast = document.getElementById('spoke-toast');
        toast.textContent = msg;
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 3000);
    },

    openModal(title, content, rawText = '') {
        const prefix = 'Flight control - ';
        const labeled = String(title || '').startsWith(prefix) ? title : prefix + title;
        document.getElementById('modal-title').textContent = labeled;
        document.getElementById('modal-body').innerHTML = content;
        this.modalRawText = rawText || content;
        document.getElementById('detail-modal').classList.add('open');
    },

    closeModal() {
        document.getElementById('detail-modal').classList.remove('open');
    },

    copyModalContent() {
        if (!this.modalRawText) return;
        navigator.clipboard.writeText(this.modalRawText).then(() => {
            this.showToast('Copied to clipboard!');
        });
    },

    escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    },

    formatBytes(bytes) {
        const value = Number(bytes) || 0;
        if (value <= 0) return '0 B';

        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        const unit = Math.min(Math.floor(Math.log(value) / Math.log(1024)), units.length - 1);
        const amount = value / Math.pow(1024, unit);

        return `${amount.toFixed(unit === 0 || amount >= 100 ? 0 : 1)} ${units[unit]}`;
    },

    formatMs(ms) {
        if (ms === null || ms === undefined || ms === '') return '-';
        const n = Number(ms);
        if (!Number.isFinite(n)) return '-';
        return Math.round(n) + ' ms';
    },

    monitorNotice(topic) {
        if (this.debugTools) return '';
        const copy = {
            exceptions: 'APP_DEBUG is off. Exceptions still record. Capture is disabled.',
            queries: 'APP_DEBUG is off. Slow SQL (≥50ms) and N+1 still record. Fast queries are skipped. EXPLAIN is disabled.',
            requests: 'APP_DEBUG is off. Errors and sampled requests still record. Full bodies need Capture, which requires APP_DEBUG=true.',
            http: 'APP_DEBUG is off. Slow or failed outbound HTTP still records. Full bodies need Capture, which requires APP_DEBUG=true.',
            mails: 'APP_DEBUG is off. Sent mail and HTML preview still record. Capture remains disabled.'
        };
        const text = copy[topic];
        if (!text) return '';
        return `<div class="monitor-notice" role="status">${text}</div>`;
    },

    shortJobName(name) {
        if (!name) return 'unknown';
        const parts = String(name).split('\\');
        return parts[parts.length - 1];
    },

    flightQueueHtml(summary, timeline) {
        const jobs = summary.jobs || [];
        if (jobs.length) {
            return jobs.map(j => {
                const ev = j.event || 'queued';
                return `${this.escapeHtml(this.shortJobName(j.name))} ${this.escapeHtml(ev)}`
                    + (j.ms != null ? ` ${this.formatMs(j.ms)}` : '');
            }).join('<br>') + (summary.jobs_count > jobs.length ? `<div style="color:var(--text-dim)">+${summary.jobs_count - jobs.length} more</div>` : '');
        }
        const fromTimeline = (timeline || []).filter(i => i.type === 'job');
        if (!fromTimeline.length) return 'none';
        return fromTimeline.slice(0, 8).map(j => {
            return `${this.escapeHtml(this.shortJobName(j.name))} ${this.escapeHtml(j.event || '')}`
                + (j.ms != null ? ` ${this.formatMs(j.ms)}` : '');
        }).join('<br>');
    },

    flightExceptionHtml(summary, timeline) {
        if (summary.exception && (summary.exception.class || summary.exception.message)) {
            return `${this.escapeHtml(this.shortJobName(summary.exception.class || ''))}: ${this.escapeHtml(summary.exception.message || '')}`;
        }
        const ex = (timeline || []).find(i => i.type === 'exception');
        if (ex) {
            return `${this.escapeHtml(this.shortJobName(ex.class || ''))}: ${this.escapeHtml(ex.message || '')}`;
        }
        const failed = (timeline || []).find(i => i.type === 'job' && i.event === 'failed');
        if (failed) {
            return `${this.escapeHtml(this.shortJobName(failed.exception_class || failed.name || ''))}: ${this.escapeHtml(failed.exception || 'failed')}`;
        }
        return 'none';
    },

    serverViewPills() {
        const view = this.state.server.view;
        return `
            <button class="pill-tab ${view === 'live' ? 'active' : ''}" onclick="SpokeApp.setServerView('live')">Live</button>
            <button class="pill-tab ${view === 'history' ? 'active' : ''}" onclick="SpokeApp.setServerView('history')">History</button>
        `;
    },

    setServerView(view) {
        this.state.server.view = view;
        this.loadServer();
    },

    setServerRange(range) {
        this.state.server.range = range;
        this.loadServer();
    },

    async loadServer() {
        if (this.state.server.view === 'history') {
            await this.loadServerHistory();
            return;
        }

        const [data, health] = await Promise.all([
            this.fetchJson('/server'),
            this.fetchJson('/health')
        ]);
        if (!data) return;

        const os = data.os || {};
        const http = data.http || {};
        const cpu = data.cpu || {};
        const mem = data.memory || {};
        const disk = data.disk || {};
        const php = data.php || {};
        const opc = data.opcache || {};
        const db = data.database || {};
        const redis = data.redis || {};
        const h = health || {};
        const checks = h.checks || [];
        const alerts = h.alerts || [];
        const hourly = (h.exceptions && h.exceptions.hourly) || [];
        const maxHour = Math.max(1, ...hourly);
        const scoreBadge = h.status === 'crit' ? 'badge-danger' : (h.status === 'warn' ? 'badge-warning' : 'badge-success');
        const scoreColor = h.status === 'crit' ? 'var(--accent-red)' : (h.status === 'warn' ? 'var(--accent-amber)' : 'var(--accent-green)');

        if (h.exceptions) {
            document.getElementById('nav-exceptions-count').textContent = h.exceptions.groups || h.exceptions.today || 0;
            if ((h.exceptions.today || 0) > 0) {
                document.getElementById('nav-exceptions-count').classList.add('danger');
            } else {
                document.getElementById('nav-exceptions-count').classList.remove('danger');
            }
        }

        const checkHtml = checks.map(c => {
            const badge = c.status === 'crit' ? 'badge-danger' : (c.status === 'warn' ? 'badge-warning' : 'badge-success');
            return `<div style="display:flex; justify-content:space-between; gap:0.75rem;"><span style="color:var(--text-muted)">${this.escapeHtml(c.label)}</span><span class="badge ${badge}">${this.escapeHtml(String(c.value))}</span></div>`;
        }).join('');

        const alertHtml = alerts.length
            ? alerts.map(a => {
                const sev = a.severity === 'crit' ? 'crit' : 'warn';
                const badge = sev === 'crit' ? 'badge-danger' : 'badge-warning';
                return `<div class="alert-item ${sev}"><span class="badge ${badge}">${sev === 'crit' ? 'CRIT' : 'WARN'}</span><span>${this.escapeHtml(a.message)}</span></div>`;
            }).join('')
            : '<div class="alert-empty"><span class="badge badge-success">OK</span> All clear — no alerts.</div>';

        const sparkHtml = hourly.map(v => `<i style="height:${Math.max(4, Math.round((v / maxHour) * 44))}px; opacity:${v ? 0.9 : 0.2};"></i>`).join('');
        const exToday = (h.exceptions && h.exceptions.today) || 0;
        const exColor = exToday > 0 ? 'var(--accent-red)' : 'var(--accent-green)';

        this.currentDb = db;

        const html = `
            <div class="toolbar">
                <div class="toolbar-group">${this.serverViewPills()}</div>
            </div>
            <div class="grid-hero">
                <div class="card">
                    <div class="card-header"><span class="card-label">Health Score</span><span class="badge ${scoreBadge}">${this.escapeHtml(h.status || 'ok')}</span></div>
                    <div class="health-score" style="color:${scoreColor};">${h.score != null ? h.score : '—'}</div>
                    <div class="card-sub"><span>Application health</span><span>${alerts.length} alert${alerts.length === 1 ? '' : 's'}</span></div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <span class="card-label">Alerts</span>
                        <span class="badge ${alerts.length ? (alerts.some(a => a.severity === 'crit') ? 'badge-danger' : 'badge-warning') : 'badge-success'}">${alerts.length}</span>
                    </div>
                    <div class="alert-list">${alertHtml}</div>
                </div>
            </div>
            <div class="grid-kpi">
                <div class="card">
                    <div class="card-header"><span class="card-label">Checks</span></div>
                    <div style="display:flex; flex-direction:column; gap:0.4rem; margin-top:0.35rem;">${checkHtml || '<div class="empty-state">No checks.</div>'}</div>
                </div>
                <div class="card">
                    <div class="card-header"><span class="card-label">Exceptions today</span><span class="badge ${exToday > 0 ? 'badge-danger' : 'badge-success'}">${exToday}</span></div>
                    <div class="stat-number" style="color:${exColor};">${exToday}</div>
                    <div class="spark" title="Hourly exception counts" style="margin-top:0.65rem;">${sparkHtml}</div>
                    <div class="card-sub"><span>Yesterday: ${(h.exceptions && h.exceptions.yesterday) || 0}</span><span>${(h.exceptions && h.exceptions.groups) || 0} groups</span></div>
                </div>
            </div>
            <div class="grid-kpi">
                <div class="card">
                    <div class="card-header">
                        <span class="card-label">CPU Load</span>
                        <span class="badge ${cpu.load_pct > 80 ? 'badge-danger' : 'badge-info'}">${cpu.cores || 1} Cores</span>
                    </div>
                    <div class="card-value">${cpu.load_1m !== null ? cpu.load_1m : 'n/a'}</div>
                    <div class="card-sub">
                        <span>Load: ${cpu.load_1m} / ${cpu.load_5m} / ${cpu.load_15m}</span>
                        <span>${cpu.load_pct !== null ? cpu.load_pct + '%' : ''}</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill ${cpu.load_pct > 80 ? 'red' : 'cyan'}" style="width: ${Math.min(100, cpu.load_pct || 0)}%"></div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <span class="card-label">RAM Memory</span>
                        <span class="badge ${mem.used_pct > 85 ? 'badge-danger' : 'badge-success'}">${mem.total_mb ? Math.round(mem.total_mb/1024) + ' GB' : 'n/a'}</span>
                    </div>
                    <div class="card-value">${mem.used_mb ? (mem.used_mb/1024).toFixed(1) + ' GB' : 'n/a'}</div>
                    <div class="card-sub">
                        <span>Free: ${mem.available_mb ? (mem.available_mb/1024).toFixed(1) + ' GB' : 'n/a'}</span>
                        <span>${mem.used_pct ? mem.used_pct + '%' : ''}</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill ${mem.used_pct > 85 ? 'red' : 'green'}" style="width: ${mem.used_pct || 0}%"></div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <span class="card-label">Disk Storage</span>
                        <span class="badge ${disk.used_pct > 90 ? 'badge-danger' : 'badge-info'}">${disk.total_gb ? disk.total_gb + ' GB' : 'n/a'}</span>
                    </div>
                    <div class="card-value">${disk.used_gb ? disk.used_gb + ' GB' : 'n/a'}</div>
                    <div class="card-sub">
                        <span>Free: ${disk.free_gb ? disk.free_gb + ' GB' : 'n/a'}</span>
                        <span>${disk.used_pct ? disk.used_pct + '%' : ''}</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill ${disk.used_pct > 90 ? 'red' : 'cyan'}" style="width: ${disk.used_pct || 0}%"></div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <span class="card-label">Database (${this.escapeHtml(db.driver || 'DB')})</span>
                        <button class="btn btn-sm" onclick="SpokeApp.showDatabaseDetails()">Details</button>
                    </div>
                    <div class="card-value">${this.escapeHtml(db.size || 'n/a')}</div>
                    <div class="card-sub">
                        <span>${db.active_connections ?? 0} / ${db.max_connections ?? 'n/a'} conn</span>
                        <span style="font-family:var(--font-mono); color:var(--accent-cyan); font-weight:600;">⏱ ${this.escapeHtml(db.uptime || 'n/a')}</span>
                    </div>
                </div>
            </div>

            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 1rem; margin-bottom:1.5rem;">
                <div class="card">
                    <div class="card-header"><span class="card-label">Server & Network</span><span class="badge badge-info">⏱ ${os.uptime || 'n/a'}</span></div>
                    <div style="display:flex; flex-direction:column; gap:0.5rem; margin-top:0.5rem;">
                        <div style="display:flex; justify-content:space-between;"><span style="color:var(--text-muted)">HTTP Server:</span><span class="badge badge-info" style="font-family:var(--font-mono)">${http.software || 'n/a'}</span></div>
                        <div style="display:flex; justify-content:space-between;"><span style="color:var(--text-muted)">HTTP Uptime:</span><span style="font-family:var(--font-mono); color:var(--accent-cyan); font-weight:600;">${http.uptime || 'n/a'}</span></div>
                        <div style="display:flex; justify-content:space-between;"><span style="color:var(--text-muted)">Protocol:</span><span style="font-family:var(--font-mono)">${http.protocol || 'HTTP/1.1'} ${http.https ? '(HTTPS)' : ''}</span></div>
                        <div style="display:flex; justify-content:space-between;"><span style="color:var(--text-muted)">OS Distribution:</span><span style="font-family:var(--font-mono)">${os.name || 'n/a'}</span></div>
                        <div style="display:flex; justify-content:space-between;"><span style="color:var(--text-muted)">OS Uptime:</span><span style="font-family:var(--font-mono); font-weight:600;">${os.uptime || 'n/a'}</span></div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><span class="card-label">PHP & OPcache</span><span class="badge badge-success">⏱ ${php.uptime || opc.uptime || 'n/a'}</span></div>
                    <div style="display:flex; flex-direction:column; gap:0.5rem; margin-top:0.5rem;">
                        <div style="display:flex; justify-content:space-between;"><span style="color:var(--text-muted)">PHP Version:</span><span style="font-family:var(--font-mono)">${php.version} (${php.sapi})</span></div>
                        <div style="display:flex; justify-content:space-between;"><span style="color:var(--text-muted)">APP_DEBUG:</span><span class="badge ${php.debug ? 'badge-warning' : 'badge-success'}">${php.debug ? 'true (debug tools on)' : 'false (monitor only)'}</span></div>
                        <div style="display:flex; justify-content:space-between;"><span style="color:var(--text-muted)">APP_ENV:</span><span style="font-family:var(--font-mono)">${this.escapeHtml(php.environment || 'n/a')}</span></div>
                        <div style="display:flex; justify-content:space-between;"><span style="color:var(--text-muted)">PHP Process Uptime:</span><span style="font-family:var(--font-mono); color:var(--accent-cyan); font-weight:600;">${php.uptime || 'n/a'}</span></div>
                        <div style="display:flex; justify-content:space-between;"><span style="color:var(--text-muted)">Memory Limit:</span><span style="font-family:var(--font-mono)">${php.memory_limit}</span></div>
                        <div style="display:flex; justify-content:space-between;"><span style="color:var(--text-muted)">OPcache Status:</span><span class="badge ${opc.enabled ? 'badge-success' : 'badge-danger'}">${opc.enabled ? 'Enabled (' + opc.hit_rate_pct + '% hit)' : 'Disabled'}</span></div>
                        <div style="display:flex; justify-content:space-between;"><span style="color:var(--text-muted)">validate_timestamps:</span><span class="badge ${opc.validate_timestamps ? 'badge-warning' : 'badge-info'}">${opc.validate_timestamps ? 'On (auto-reload)' : 'Off (restart required)'}</span></div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><span class="card-label">Redis Cache & Store</span><span class="badge badge-purple">⏱ ${redis.uptime || (redis.uptime_days ? redis.uptime_days + 'd' : 'n/a')}</span></div>
                    <div style="display:flex; flex-direction:column; gap:0.5rem; margin-top:0.5rem;">
                        <div style="display:flex; justify-content:space-between;"><span style="color:var(--text-muted)">Status:</span><span class="badge ${redis.available ? 'badge-success' : 'badge-danger'}">${redis.available ? 'Connected (v' + redis.version + ')' : 'Unavailable'}</span></div>
                        <div style="display:flex; justify-content:space-between;"><span style="color:var(--text-muted)">Redis Uptime:</span><span style="font-family:var(--font-mono); color:var(--accent-cyan); font-weight:600;">${redis.uptime || (redis.uptime_days ? redis.uptime_days + 'd' : 'n/a')}</span></div>
                        <div style="display:flex; justify-content:space-between;"><span style="color:var(--text-muted)">Used Memory:</span><span style="font-family:var(--font-mono)">${redis.used_memory_human || 'n/a'} (peak: ${redis.used_memory_peak_human || 'n/a'})</span></div>
                        <div style="display:flex; justify-content:space-between;"><span style="color:var(--text-muted)">Total Keys:</span><span style="font-family:var(--font-mono)">${redis.total_keys !== undefined ? redis.total_keys : 'n/a'}</span></div>
                        <div style="display:flex; justify-content:space-between;"><span style="color:var(--text-muted)">Hit Rate:</span><span style="font-family:var(--font-mono)">${redis.hit_rate_pct !== null ? redis.hit_rate_pct + '%' : 'n/a'}</span></div>
                    </div>
                </div>
            </div>
        `;
        document.getElementById('tab-content').innerHTML = html;
    },

    metricChart(points, color) {
        const w = 600, h = 120, pad = 4;
        const values = points.map(p => p.v).filter(v => v !== null);
        if (!values.length) return '<div class="empty-state">No samples in this range.</div>';

        const max = Math.max(...values) * 1.15 || 1;
        const step = points.length > 1 ? (w - pad * 2) / (points.length - 1) : 0;
        const x = i => pad + i * step;
        const y = v => h - pad - (v / max) * (h - pad * 2);

        let paths = '';
        let run = [];

        const flush = () => {
            if (run.length === 1) {
                paths += `<circle cx="${run[0][0].toFixed(1)}" cy="${run[0][1].toFixed(1)}" r="1.6" fill="${color}"/>`;
            } else if (run.length > 1) {
                const line = run.map(p => `${p[0].toFixed(1)},${p[1].toFixed(1)}`).join(' ');
                const area = `${run[0][0].toFixed(1)},${h - pad} ${line} ${run[run.length - 1][0].toFixed(1)},${h - pad}`;
                paths += `<polygon points="${area}" fill="${color}" opacity="0.12"/>`;
                paths += `<polyline points="${line}" fill="none" stroke="${color}" stroke-width="1.5" vector-effect="non-scaling-stroke"/>`;
            }
            run = [];
        };

        points.forEach((p, i) => {
            if (p.v === null) { flush(); return; }
            run.push([x(i), y(p.v)]);
        });
        flush();

        return `<svg viewBox="0 0 ${w} ${h}" preserveAspectRatio="none" style="width:100%; height:110px; display:block;">
            <line x1="0" y1="${h - pad}" x2="${w}" y2="${h - pad}" stroke="var(--border)" stroke-width="1" vector-effect="non-scaling-stroke"/>
            ${paths}
        </svg>`;
    },

    metricCard(s) {
        const color = s.unit === '%' && s.max > 85 ? 'var(--accent-red)' : 'var(--accent-cyan)';
        const fmt = v => v === null ? 'n/a' : v + (s.unit ? (s.unit === '%' ? '%' : ' ' + s.unit) : '');
        return `
            <div class="card">
                <div class="card-header">
                    <span class="card-label">${this.escapeHtml(s.label)}</span>
                    <span class="badge badge-muted">${fmt(s.latest)}</span>
                </div>
                ${this.metricChart(s.points, color)}
                <div class="card-sub">
                    <span>avg ${fmt(s.avg)}</span>
                    <span>max ${fmt(s.max)}</span>
                </div>
            </div>
        `;
    },

    async loadServerHistory() {
        const s = this.state.server;
        const [metrics, rollups] = await Promise.all([
            this.fetchJson('/server/metrics?range=' + encodeURIComponent(s.range)),
            this.fetchJson('/server/rollups?days=' + s.days)
        ]);
        if (!metrics) return;

        const ranges = ['1h', '24h', '7d'].map(r =>
            `<button class="pill-tab ${s.range === r ? 'active' : ''}" onclick="SpokeApp.setServerRange('${r}')">${r}</button>`
        ).join('');

        const series = metrics.series || [];
        let metricsHtml;

        if (!metrics.meta.enabled) {
            metricsHtml = `<div class="card"><div class="empty-state">
                Metrics sampling is disabled. Set <code>SPOKE_METRICS_ENABLED=true</code> and schedule
                <code>spoke:sample</code> every minute to build server history.
            </div></div>`;
        } else if (!series.length) {
            metricsHtml = `<div class="card"><div class="empty-state">
                No samples yet. Add <code>$schedule->command('spoke:sample')->everyMinute();</code> to your scheduler.
            </div></div>`;
        } else {
            metricsHtml = `<div class="grid-kpi">${series.map(x => this.metricCard(x)).join('')}</div>`;
        }

        const days = (rollups && rollups.data) || [];
        let rollupHtml;

        if (!rollups || !rollups.meta.enabled) {
            rollupHtml = `<div class="empty-state">
                Daily rollup is disabled. Set <code>SPOKE_ROLLUP_ENABLED=true</code> and schedule
                <code>spoke:rollup</code> once a day to keep trends beyond the raw retention window.
            </div>`;
        } else if (!days.length) {
            rollupHtml = '<div class="empty-state">No rollup days yet — the first one is written by the next daily run.</div>';
        } else {
            const rows = days.slice().reverse().map(d => `
                <tr>
                    <td style="font-family:var(--font-mono);">${this.escapeHtml(d.date)}</td>
                    <td style="text-align:right;">${d.requests_count != null ? d.requests_count : '-'}</td>
                    <td style="text-align:right;">${d.requests_p95_ms != null ? this.formatMs(d.requests_p95_ms) : '-'}</td>
                    <td style="text-align:right;">${d.requests_error_count ? `<span class="badge badge-danger">${d.requests_error_count}</span>` : '0'}</td>
                    <td style="text-align:right;">${d.exceptions_count != null ? d.exceptions_count : '-'}</td>
                    <td style="text-align:right;">${d.jobs_failed_count ? `<span class="badge badge-warning">${d.jobs_failed_count}</span>` : '0'}</td>
                    <td style="text-align:right;">${d.load_pct_max != null ? d.load_pct_max + '%' : '-'}</td>
                    <td style="text-align:right;">${d.disk_used_pct_max != null ? d.disk_used_pct_max + '%' : '-'}</td>
                </tr>
            `).join('');

            rollupHtml = `
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th style="text-align:right;">Requests</th>
                            <th style="text-align:right;">P95</th>
                            <th style="text-align:right;">5xx</th>
                            <th style="text-align:right;">Exceptions</th>
                            <th style="text-align:right;">Jobs failed</th>
                            <th style="text-align:right;">Load max</th>
                            <th style="text-align:right;">Disk max</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            `;
        }

        document.getElementById('tab-content').innerHTML = `
            <div class="toolbar">
                <div class="toolbar-group">
                    ${this.serverViewPills()}
                    ${ranges}
                    <span style="color:var(--text-dim); font-size:0.75rem;">
                        ${metrics.meta.samples} samples · ${metrics.meta.from} → ${metrics.meta.to}
                    </span>
                </div>
            </div>
            ${metricsHtml}
            <div class="table-container" style="margin-top:1rem;">
                <div style="padding:0.75rem 1rem; border-bottom:1px solid var(--border); color:var(--text-muted); font-size:0.75rem; letter-spacing:0.06em; text-transform:uppercase;">
                    Daily rollup${rollups && rollups.meta.enabled ? ' · last ' + rollups.meta.days + ' days' : ''}
                </div>
                ${rollupHtml}
            </div>
        `;
    },

    showDatabaseDetails() {
        const db = this.currentDb || {};
        const row = (label, value) => `
            <div style="display:flex; justify-content:space-between; gap:1rem; padding:0.5rem 0; border-bottom:1px solid var(--border);">
                <span style="color:var(--text-muted); font-size:0.75rem; letter-spacing:0.06em; text-transform:uppercase;">${label}</span>
                <span style="font-family:var(--font-mono); text-align:right;">${value}</span>
            </div>
        `;

        const hot = Array.isArray(db.hot_tables) ? db.hot_tables : [];
        const hotHtml = hot.length
            ? `<table class="table" style="margin-top:0.35rem;">
                    <thead><tr><th>Table</th><th>Seq scans</th><th>Index scans</th></tr></thead>
                    <tbody>${hot.map(t => `
                        <tr>
                            <td style="font-family:var(--font-mono);">${this.escapeHtml(t.table || t.relname || '')}</td>
                            <td><span class="badge ${(t.seq_scan || 0) > (t.idx_scan || 0) ? 'badge-warning' : 'badge-muted'}">${t.seq_scan ?? 0}</span></td>
                            <td><span class="badge badge-info">${t.idx_scan ?? 0}</span></td>
                        </tr>
                    `).join('')}</tbody>
               </table>`
            : '<div class="empty-state" style="padding:0.75rem 0;">No table stats available.</div>';

        const content = `
            ${row('Driver', this.escapeHtml(db.driver || 'n/a'))}
            ${row('Database', this.escapeHtml(db.database || 'n/a'))}
            ${row('Version', this.escapeHtml(String(db.version || 'n/a')))}
            ${row('Size', this.escapeHtml(db.size || 'n/a'))}
            ${row('Connections', `${db.active_connections ?? 0} / ${db.max_connections ?? 'n/a'}`)}
            ${row('Uptime', this.escapeHtml(db.uptime || 'n/a'))}
            ${row('Cache hit', db.cache_hit_pct != null ? db.cache_hit_pct + '%' : 'n/a')}
            ${row('Slow queries', db.slow_queries != null ? String(db.slow_queries) : 'n/a')}
            <div style="margin:1rem 0 0.4rem; font-size:0.75rem; font-weight:600; color:var(--text-muted); letter-spacing:0.06em; text-transform:uppercase;">Hot tables</div>
            ${hotHtml}
        `;

        this.openModal('Database details', content, JSON.stringify(db, null, 2));
    },

    async loadLogs() {
        const s = this.state.logs;
        const cursor = s.cursor === null ? '' : `&cursor=${encodeURIComponent(s.cursor)}`;
        const query = `?file=${encodeURIComponent(s.file)}&level=${encodeURIComponent(s.level)}&search=${encodeURIComponent(s.search)}&page=${s.page}${cursor}`;
        const data = await this.fetchJson('/logs' + query);
        if (!data) return;

        const files = data.meta.files || [];
        const levels = data.meta.levels || {};
        const entries = data.data || [];
        const countSuffix = data.meta.has_more ? '+' : '';

        s.file = data.meta.file || s.file;
        s.nextCursor = data.meta.next_cursor ?? null;
        s.hasMore = Boolean(data.meta.has_more);

        document.getElementById('nav-logs-count').textContent = `${data.meta.loaded || 0}${countSuffix}`;

        let filesOptions = files.map(f => `
            <option value="${this.escapeHtml(f.name)}" ${f.name === data.meta.file ? 'selected' : ''}>
                ${this.escapeHtml(f.name)} (${this.formatBytes(f.size)})
            </option>
        `).join('');

        let levelsPills = `<button class="pill-tab ${s.level === '' ? 'active' : ''}" onclick="SpokeApp.setLogLevel('')">All (${data.meta.scanned_entries || 0}${countSuffix})</button>`;
        for (const [lvl, count] of Object.entries(levels)) {
            levelsPills += `<button class="pill-tab ${s.level === lvl ? 'active' : ''}" onclick="SpokeApp.setLogLevel('${lvl}')">${this.escapeHtml(lvl)} (${count}${countSuffix})</button>`;
        }

        let rowsHtml = '';
        if (entries.length === 0) {
            rowsHtml = `<tr><td colspan="4" class="empty-state">${data.meta.has_more ? 'No matching entries in this scan window. Continue to the next window.' : 'No log entries found matching the filter.'}</td></tr>`;
        } else {
            rowsHtml = entries.map((e, idx) => `
                <tr style="cursor:pointer;" onclick="SpokeApp.showLogDetail(${idx})">
                    <td style="font-family:var(--font-mono); font-size:0.75rem; white-space:nowrap; color:var(--text-dim);">${this.escapeHtml(e.time)}</td>
                    <td><span class="badge badge-${e.level.toLowerCase()}">${this.escapeHtml(e.level)}</span></td>
                    <td><span class="badge badge-muted">${this.escapeHtml(e.env)}</span></td>
                    <td><span class="code-snippet">${this.escapeHtml(e.message)}</span></td>
                </tr>
            `).join('');
        }

        this.currentLogEntries = entries;

        const html = `
            <div class="toolbar">
                <div class="toolbar-group">
                    <select class="select" onchange="SpokeApp.setLogFile(this.value)">
                        ${filesOptions}
                    </select>
                    <input type="text" class="input" placeholder="Search logs..." value="${this.escapeHtml(s.search)}" oninput="SpokeApp.searchLogs(this.value)" style="width:240px;">
                </div>
                <div class="toolbar-group">
                    ${levelsPills}
                </div>
            </div>

            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:160px;">Time</th>
                            <th style="width:90px;">Level</th>
                            <th style="width:80px;">Env</th>
                            <th>Message</th>
                        </tr>
                    </thead>
                    <tbody>${rowsHtml}</tbody>
                </table>
                <div class="pagination">
                    <span>
                        Page ${data.meta.page} · ${data.meta.loaded || 0} loaded ·
                        ${this.formatBytes(data.meta.scanned_bytes)} scanned of ${this.formatBytes(data.meta.file_size)}
                        ${data.meta.scan_limited ? '<span class="badge badge-warning" style="margin-left:0.4rem;">Scan window reached</span>' : ''}
                    </span>
                    <div style="display:flex; gap:0.5rem;">
                        <button class="btn btn-sm" ${s.cursorHistory.length === 0 ? 'disabled' : ''} onclick="SpokeApp.previousLogPage()">Previous</button>
                        <button class="btn btn-sm" ${!s.hasMore || s.nextCursor === null ? 'disabled' : ''} onclick="SpokeApp.nextLogPage()">Next</button>
                    </div>
                </div>
            </div>
        `;
        document.getElementById('tab-content').innerHTML = html;
    },

    resetLogPagination() {
        const s = this.state.logs;
        s.page = 1;
        s.cursor = null;
        s.cursorHistory = [];
        s.nextCursor = null;
        s.hasMore = false;
    },
    setLogFile(file) {
        this.state.logs.file = file;
        this.resetLogPagination();
        this.loadLogs();
    },
    setLogLevel(level) {
        this.state.logs.level = level;
        this.resetLogPagination();
        this.loadLogs();
    },
    nextLogPage() {
        const s = this.state.logs;
        if (!s.hasMore || s.nextCursor === null) return;

        s.cursorHistory.push(s.cursor);
        s.cursor = s.nextCursor;
        s.page++;
        this.loadLogs();
    },
    previousLogPage() {
        const s = this.state.logs;
        if (s.cursorHistory.length === 0) return;

        s.cursor = s.cursorHistory.pop();
        s.page = Math.max(1, s.page - 1);
        this.loadLogs();
    },
    searchLogs(term) {
        clearTimeout(this._logSearchTimer);
        this._logSearchTimer = setTimeout(() => {
            this.state.logs.search = term;
            this.resetLogPagination();
            this.loadLogs();
        }, 300);
    },

    showLogDetail(idx) {
        const e = this.currentLogEntries[idx];
        if (!e) return;
        const content = `
            <div style="display:flex; gap:0.5rem; margin-bottom:0.75rem;">
                <span class="badge badge-${e.level.toLowerCase()}">${e.level}</span>
                <span class="badge badge-muted">${e.env}</span>
                <span style="color:var(--text-dim); font-family:var(--font-mono); font-size:0.8rem;">${e.time}</span>
            </div>
            <div class="code-block">${this.escapeHtml(e.full)}</div>
        `;
        this.openModal('Log Entry Details', content, e.full);
    },

    async loadExceptions() {
        if (this.state.exceptions.activeSubTab === 'log') {
            await this.loadExceptionLog();
            return;
        }

        const s = this.state.exceptions;
        const params = new URLSearchParams();
        if (s.date) params.set('date', s.date);
        if (s.search) params.set('search', s.search);
        const data = await this.fetchJson('/exceptions/groups?' + params.toString());
        if (!data) return;

        document.getElementById('nav-exceptions-count').textContent = data.meta.groups || 0;
        if ((data.meta.total || 0) > 0) {
            document.getElementById('nav-exceptions-count').classList.add('danger');
        } else {
            document.getElementById('nav-exceptions-count').classList.remove('danger');
        }

        const dates = data.meta.dates || [];
        const datesOptions = dates.map(d => `<option value="${d}" ${d === data.meta.date ? 'selected' : ''}>${d}</option>`).join('');
        this.currentExceptionGroups = data.data || [];

        const rows = this.currentExceptionGroups.map((g, idx) => {
            const shortClass = (g.class || '').split('\\').pop();
            const routes = (g.uris || []).slice(0, 3).map(u => `${this.escapeHtml(u.uri)} ×${u.count}`).join('<br>') || '—';
            return `
                <tr style="cursor:pointer;" onclick="SpokeApp.showExceptionGroup(${idx})">
                    <td style="font-weight:600; font-family:var(--font-mono);">${this.escapeHtml(shortClass)}</td>
                    <td><span class="code-snippet">${this.escapeHtml(g.message || '')}</span></td>
                    <td><span class="badge ${g.count > 10 ? 'badge-danger' : 'badge-warning'}">${g.count}×</span></td>
                    <td style="font-size:0.75rem; color:var(--text-dim);">${routes}</td>
                    <td style="font-family:var(--font-mono); font-size:0.75rem; color:var(--text-dim);">${this.escapeHtml((g.last_seen || '').split(' ')[1] || g.last_seen || '')}</td>
                </tr>
            `;
        }).join('') || `<tr><td colspan="5" class="empty-state">No exceptions recorded for this date.${this.debugTools ? '' : ' Thrown exceptions still record in monitor mode.'}</td></tr>`;

        document.getElementById('tab-content').innerHTML = `
            <div class="toolbar">
                <div class="toolbar-group">
                    <button class="pill-tab active" onclick="SpokeApp.setExceptionSubTab('groups')">Grouped</button>
                    <button class="pill-tab" onclick="SpokeApp.setExceptionSubTab('log')">Log</button>
                    <select class="select" onchange="SpokeApp.setExceptionDate(this.value)">${datesOptions}</select>
                    <input type="text" class="input" placeholder="Search class, message, URI..." value="${this.escapeHtml(s.search)}" oninput="SpokeApp.searchExceptions(this.value)" style="width:260px;">
                </div>
            </div>
            ${this.monitorNotice('exceptions')}
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:200px;">Exception</th>
                            <th>Message</th>
                            <th style="width:80px;">Count</th>
                            <th style="width:220px;">Routes</th>
                            <th style="width:100px;">Last seen</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
        `;
    },

    async loadExceptionLog() {
        const s = this.state.exceptions;
        const params = new URLSearchParams({ page: s.page || 1 });
        if (s.date) params.set('date', s.date);
        if (s.search) params.set('search', s.search);
        const data = await this.fetchJson('/exceptions?' + params.toString());
        if (!data) return;

        const dates = data.meta.dates || [];
        const datesOptions = dates.map(d => `<option value="${d}" ${d === data.meta.date ? 'selected' : ''}>${d}</option>`).join('');
        this.currentExceptions = data.data || [];

        const rows = this.currentExceptions.map((e, idx) => {
            const shortClass = (e.class || '').split('\\').pop();
            return `
                <tr style="cursor:pointer;" onclick="SpokeApp.showExceptionLog(${idx})">
                    <td style="font-family:var(--font-mono); font-size:0.75rem; color:var(--text-dim);">${this.escapeHtml((e.t || '').split(' ')[1] || e.t || '')}</td>
                    <td style="font-family:var(--font-mono); font-weight:600;">${this.escapeHtml(shortClass)}</td>
                    <td><span class="code-snippet">${this.escapeHtml(e.message || '')}</span></td>
                    <td style="font-size:0.75rem; color:var(--text-dim);">${this.escapeHtml(e.uri || '—')}</td>
                </tr>
            `;
        }).join('') || `<tr><td colspan="4" class="empty-state">No exception log for this date.</td></tr>`;

        document.getElementById('tab-content').innerHTML = `
            <div class="toolbar">
                <div class="toolbar-group">
                    <button class="pill-tab" onclick="SpokeApp.setExceptionSubTab('groups')">Grouped</button>
                    <button class="pill-tab active" onclick="SpokeApp.setExceptionSubTab('log')">Log</button>
                    <select class="select" onchange="SpokeApp.setExceptionDate(this.value)">${datesOptions}</select>
                    <input type="text" class="input" placeholder="Search..." value="${this.escapeHtml(s.search)}" oninput="SpokeApp.searchExceptions(this.value)" style="width:240px;">
                </div>
            </div>
            ${this.monitorNotice('exceptions')}
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:110px;">Time</th>
                            <th style="width:200px;">Class</th>
                            <th>Message</th>
                            <th style="width:200px;">URI</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
                <div class="pagination">
                    <span>Page ${data.meta.page} (${data.meta.total} total)</span>
                    <div style="display:flex; gap:0.5rem;">
                        <button class="btn btn-sm" ${data.meta.page <= 1 ? 'disabled' : ''} onclick="SpokeApp.setExceptionPage(${data.meta.page - 1})">Previous</button>
                        <button class="btn btn-sm" ${(data.meta.page * data.meta.per_page) >= data.meta.total ? 'disabled' : ''} onclick="SpokeApp.setExceptionPage(${data.meta.page + 1})">Next</button>
                    </div>
                </div>
            </div>
        `;
    },

    setExceptionSubTab(tab) {
        this.state.exceptions.activeSubTab = tab;
        this.state.exceptions.page = 1;
        this.loadExceptions();
    },
    setExceptionDate(d) { this.state.exceptions.date = d; this.state.exceptions.page = 1; this.loadExceptions(); },
    setExceptionPage(p) { this.state.exceptions.page = p; this.loadExceptions(); },
    searchExceptions(t) {
        clearTimeout(this._exSearchTimer);
        this._exSearchTimer = setTimeout(() => {
            this.state.exceptions.search = t;
            this.state.exceptions.page = 1;
            this.loadExceptions();
        }, 300);
    },
    showExceptionGroup(idx) {
        const g = (this.currentExceptionGroups || [])[idx];
        if (!g) return;
        const routes = (g.uris || []).map(u => `${u.uri} ×${u.count}`).join('\n') || '—';
        const content = `
            <div style="display:flex; gap:0.5rem; flex-wrap:wrap; margin-bottom:0.75rem;">
                <span class="badge badge-danger">${this.escapeHtml((g.class || '').split('\\').pop())}</span>
                <span class="badge badge-muted">${g.count} occurrences</span>
            </div>
            <div style="margin-bottom:0.5rem;"><span style="color:var(--text-muted)">First:</span> ${this.escapeHtml(g.first_seen || '')} &nbsp; <span style="color:var(--text-muted)">Last:</span> ${this.escapeHtml(g.last_seen || '')}</div>
            <div style="margin-bottom:0.75rem; font-family:var(--font-mono);">${this.escapeHtml(g.message || '')}</div>
            <div style="margin-bottom:0.5rem; font-weight:600;">Affected routes</div>
            <div class="code-block">${this.escapeHtml(routes)}</div>
            ${g.trace_id ? `<div style="margin:0.75rem 0;"><button class="btn btn-sm btn-primary" onclick="SpokeApp.showTrace('${this.escapeHtml(g.trace_id)}', '${this.escapeHtml((g.last_seen || '').split(' ')[0] || '')}')">Flight Recorder</button></div>` : ''}
            <div style="margin:0.75rem 0 0.35rem; font-weight:600;">Stack trace</div>
            <div class="code-block">${this.escapeHtml(g.stack || '')}</div>
        `;
        this.openModal('Exception Group', content, g.stack || JSON.stringify(g, null, 2));
    },
    showExceptionLog(idx) {
        const e = (this.currentExceptions || [])[idx];
        if (!e) return;
        const content = `
            <div style="margin-bottom:0.5rem;"><span class="badge badge-danger">${this.escapeHtml((e.class || '').split('\\').pop())}</span></div>
            <div class="code-block">${this.escapeHtml(e.stack || e.message || '')}</div>
            ${e.trace_id ? `<div style="margin-top:0.75rem;"><button class="btn btn-sm btn-primary" onclick="SpokeApp.showTrace('${this.escapeHtml(e.trace_id)}', '${this.escapeHtml((e.t || '').split(' ')[0] || '')}')">Flight Recorder</button></div>` : ''}
        `;
        this.openModal('Exception', content, e.stack || e.message || '');
    },

    async loadQueries() {
        if (this.state.queries.activeSubTab === 'ranking') {
            await this.loadQueryRanking();
            return;
        }

        const s = this.state.queries;
        const query = `?date=${encodeURIComponent(s.date)}&search=${encodeURIComponent(s.search)}&page=${s.page}`;
        const data = await this.fetchJson('/queries' + query);
        if (!data) return;

        const dates = data.meta.dates || [];
        const entries = data.data || [];
        document.getElementById('nav-queries-count').textContent = data.meta.total || 0;

        let datesOptions = dates.map(d => `<option value="${d}" ${d === data.meta.date ? 'selected' : ''}>${d}</option>`).join('');

        let rowsHtml = '';
        if (entries.length === 0) {
            rowsHtml = `<tr><td colspan="5" class="empty-state">No recorded SQL queries for the selected date.${this.debugTools ? '' : ' Slow queries (≥50ms) and N+1 still record in monitor mode.'}</td></tr>`;
        } else {
            rowsHtml = entries.map((q, idx) => {
                const isSlow = q.ms > 100;
                return `
                    <tr style="cursor:pointer;" onclick="SpokeApp.showQueryDetail(${idx})">
                        <td style="font-family:var(--font-mono); font-size:0.75rem; color:var(--text-dim); white-space:nowrap;">${q.t.split(' ')[1] || q.t}</td>
                        <td><span class="badge ${isSlow ? 'badge-danger' : (q.ms < 10 ? 'badge-success' : 'badge-warning')}">${this.formatMs(q.ms)}</span></td>
                        <td><span class="badge badge-muted">${q.origin}</span></td>
                        <td><span class="code-snippet" style="color:var(--accent-cyan); font-family:var(--font-mono);">${this.escapeHtml(q.sql)}</span></td>
                        <td style="color:var(--text-dim); font-size:0.75rem;">${this.escapeHtml(q.uri || '-')}</td>
                    </tr>
                `;
            }).join('');
        }

        this.currentQueries = entries;

        const html = `
            <div class="toolbar">
                <div class="toolbar-group">
                    <button class="pill-tab active" onclick="SpokeApp.setQuerySubTab('log')">Log</button>
                    <button class="pill-tab" onclick="SpokeApp.setQuerySubTab('ranking')">Ranking</button>
                </div>
                <div class="toolbar-group">
                    <select class="select" onchange="SpokeApp.setQueryDate(this.value)">${datesOptions}</select>
                    <input type="text" class="input" placeholder="Search SQL or URI..." value="${this.escapeHtml(s.search)}" oninput="SpokeApp.searchQueries(this.value)" style="width:260px;">
                </div>
            </div>
            ${this.monitorNotice('queries')}
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:110px;">Time</th>
                            <th style="width:90px;">Duration</th>
                            <th style="width:80px;">Origin</th>
                            <th>SQL Query</th>
                            <th style="width:200px;">URI / Command</th>
                        </tr>
                    </thead>
                    <tbody>${rowsHtml}</tbody>
                </table>
                <div class="pagination">
                    <span>Page ${data.meta.page} (${data.meta.total} total queries)</span>
                    <div style="display:flex; gap:0.5rem;">
                        <button class="btn btn-sm" ${data.meta.page <= 1 ? 'disabled' : ''} onclick="SpokeApp.setQueryPage(${data.meta.page - 1})">Previous</button>
                        <button class="btn btn-sm" ${(data.meta.page * data.meta.per_page) >= data.meta.total ? 'disabled' : ''} onclick="SpokeApp.setQueryPage(${data.meta.page + 1})">Next</button>
                    </div>
                </div>
            </div>
        `;
        document.getElementById('tab-content').innerHTML = html;
    },

    async loadQueryRanking() {
        const s = this.state.queries;
        const query = `?date=${encodeURIComponent(s.date)}&search=${encodeURIComponent(s.search)}&sort=${encodeURIComponent(s.rankingSort)}`;
        const data = await this.fetchJson('/queries/stats' + query);
        if (!data) return;

        const dates = data.meta.dates || [];
        const entries = data.data || [];
        document.getElementById('nav-queries-count').textContent = data.meta.total || 0;
        let datesOptions = dates.map(d => `<option value="${d}" ${d === data.meta.date ? 'selected' : ''}>${d}</option>`).join('');
        const sort = data.meta.sort || s.rankingSort;

        let rowsHtml = '';
        if (entries.length === 0) {
            rowsHtml = `<tr><td colspan="6" class="empty-state">No query fingerprints for the selected date.</td></tr>`;
        } else {
            rowsHtml = entries.map((q, idx) => `
                <tr style="cursor:pointer;" onclick="SpokeApp.showRankingDetail(${idx})">
                    <td><span class="badge ${q.avg_ms > 100 ? 'badge-danger' : 'badge-warning'}">${q.count}×</span></td>
                    <td><span class="badge badge-muted">${this.formatMs(q.total_ms)}</span></td>
                    <td><span class="badge ${q.avg_ms > 100 ? 'badge-danger' : 'badge-success'}">${this.formatMs(q.avg_ms)} avg</span></td>
                    <td>${q.regression_pct != null ? `<span class="badge badge-danger">▲ ${q.regression_pct}%</span>` : '<span class="badge badge-muted">—</span>'}</td>
                    <td><span class="code-snippet" style="color:var(--accent-cyan); font-family:var(--font-mono);">${this.escapeHtml(q.sql)}</span></td>
                    <td style="font-size:0.75rem; color:var(--text-dim);">${this.escapeHtml((q.uris || []).join(', ') || '-')}</td>
                </tr>
            `).join('');
        }

        this.currentRanking = entries;

        const html = `
            <div class="toolbar">
                <div class="toolbar-group">
                    <button class="pill-tab" onclick="SpokeApp.setQuerySubTab('log')">Log</button>
                    <button class="pill-tab active" onclick="SpokeApp.setQuerySubTab('ranking')">Ranking</button>
                </div>
                <div class="toolbar-group">
                    <select class="select" onchange="SpokeApp.setQueryDate(this.value)">${datesOptions}</select>
                    <select class="select" onchange="SpokeApp.setRankingSort(this.value)">
                        <option value="total_ms" ${sort === 'total_ms' ? 'selected' : ''}>Sort: total ms</option>
                        <option value="count" ${sort === 'count' ? 'selected' : ''}>Sort: count</option>
                        <option value="avg_ms" ${sort === 'avg_ms' ? 'selected' : ''}>Sort: avg ms</option>
                        <option value="max_ms" ${sort === 'max_ms' ? 'selected' : ''}>Sort: max ms</option>
                    </select>
                    <input type="text" class="input" placeholder="Search SQL or URI..." value="${this.escapeHtml(s.search)}" oninput="SpokeApp.searchQueries(this.value)" style="width:220px;">
                </div>
            </div>
            ${this.monitorNotice('queries')}
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:80px;">Count</th>
                            <th style="width:110px;">Total</th>
                            <th style="width:110px;">Avg</th>
                            <th style="width:100px;">Regression</th>
                            <th>Normalized SQL</th>
                            <th style="width:180px;">URIs</th>
                        </tr>
                    </thead>
                    <tbody>${rowsHtml}</tbody>
                </table>
            </div>
        `;
        document.getElementById('tab-content').innerHTML = html;
    },

    setQuerySubTab(tab) {
        this.state.queries.activeSubTab = tab;
        this.state.queries.page = 1;
        this.loadQueries();
    },

    setRankingSort(sort) {
        this.state.queries.rankingSort = sort;
        this.loadQueryRanking();
    },

    setQueryDate(d) { this.state.queries.date = d; this.state.queries.page = 1; this.loadQueries(); },
    setQueryPage(p) { this.state.queries.page = p; this.loadQueries(); },
    searchQueries(t) {
        clearTimeout(this._querySearchTimer);
        this._querySearchTimer = setTimeout(() => {
            this.state.queries.search = t;
            this.state.queries.page = 1;
            this.loadQueries();
        }, 300);
    },

    showQueryDetail(idx) {
        const q = this.currentQueries[idx];
        if (!q) return;
        this.renderQueryDetail(q);
    },

    showRankingDetail(idx) {
        const q = this.currentRanking?.[idx];
        if (!q) return;
        this.renderQueryDetail({
            sql: q.sql,
            bindings: [],
            conn: '',
            origin: 'fingerprint',
            ms: q.avg_ms,
            fingerprint: q.fingerprint,
        });
    },

    renderQueryDetail(q) {
        this.currentQueryDetail = q;
        const bindingsJson = JSON.stringify(q.bindings || [], null, 2);
        const content = `
            <div style="display:flex; gap:0.5rem; margin-bottom:0.75rem; flex-wrap:wrap;">
                <span class="badge ${q.ms > 100 ? 'badge-danger' : 'badge-success'}">${this.formatMs(q.ms)}</span>
                ${q.conn ? `<span class="badge badge-muted">Connection: ${this.escapeHtml(q.conn)}</span>` : ''}
                <span class="badge badge-info">${this.escapeHtml(q.origin || '')}</span>
                ${q.fingerprint ? `<span class="badge badge-purple">${this.escapeHtml(String(q.fingerprint).slice(0, 10))}</span>` : ''}
            </div>
            <div style="display:flex; gap:0.5rem; margin-bottom:1rem; flex-wrap:wrap;">
                <button class="btn btn-sm" onclick="SpokeApp.copyQuerySql()">Copy SQL</button>
                ${this.debugTools
                    ? `<button class="btn btn-sm btn-primary" onclick="SpokeApp.explainQuery(false)">EXPLAIN</button>
                       <button class="btn btn-sm btn-danger" onclick="SpokeApp.explainQuery(true)">ANALYZE</button>`
                    : `<span class="badge badge-muted">EXPLAIN requires APP_DEBUG=true</span>`}
            </div>
            <div style="margin-bottom:0.5rem; font-weight:600; color:var(--text-muted);">SQL:</div>
            <div class="code-block" style="margin-bottom:1rem; color:var(--accent-cyan);">${this.escapeHtml(q.sql)}</div>
            <div style="margin-bottom:0.5rem; font-weight:600; color:var(--text-muted);">Bindings:</div>
            <div class="code-block">${this.escapeHtml(bindingsJson)}</div>
            <div id="explain-result"></div>
        `;
        this.openModal('SQL Query Details', content, q.sql + "\n\nBindings: " + bindingsJson);
    },

    copyQuerySql() {
        const sql = this.currentQueryDetail?.sql;
        if (!sql) return;
        navigator.clipboard.writeText(sql).then(() => this.showToast('SQL copied.'));
    },

    async explainQuery(analyze) {
        const q = this.currentQueryDetail;
        if (!q) return;
        if (!this.debugTools) {
            this.showToast('EXPLAIN requires APP_DEBUG=true.');
            return;
        }
        if (analyze && !confirm('EXPLAIN ANALYZE will execute this SELECT on the live database. Continue?')) {
            return;
        }

        const target = document.getElementById('explain-result');
        if (target) target.innerHTML = '<div class="empty-state">Running EXPLAIN…</div>';

        try {
            const res = await fetch(this.apiBase + '/queries/explain', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken
                },
                body: JSON.stringify({
                    sql: q.sql,
                    bindings: q.bindings || [],
                    conn: q.conn || '',
                    analyze: !!analyze
                })
            });
            const data = await res.json();
            if (!data || !data.ok) {
                if (target) target.innerHTML = `<div class="code-block" style="color:var(--accent-red); margin-top:1rem;">${this.escapeHtml(data?.error || 'EXPLAIN failed')}</div>`;
                this.showToast(data?.error || 'EXPLAIN failed');
                return;
            }

            const h = data.health || {};
            const healthHtml = `
                <div style="margin-top:1rem; font-weight:600; color:var(--text-muted);">Query Health</div>
                <div style="display:flex; flex-wrap:wrap; gap:0.4rem; margin:0.5rem 0 0.75rem;">
                    <span class="badge ${h.seq_scan ? 'badge-danger' : 'badge-success'}">${h.seq_scan ? 'Seq Scan YES' : 'Seq Scan no'}</span>
                    <span class="badge badge-muted">${this.escapeHtml(h.node_type || 'plan')}</span>
                    ${h.relation ? `<span class="badge badge-purple">${this.escapeHtml(h.relation)}</span>` : ''}
                    ${h.total_cost != null ? `<span class="badge badge-muted">cost ${h.total_cost}</span>` : ''}
                    ${h.plan_rows != null ? `<span class="badge badge-muted">plan rows ${h.plan_rows}</span>` : ''}
                    ${h.actual_ms != null ? `<span class="badge badge-info">${this.formatMs(h.actual_ms)} actual</span>` : ''}
                    ${h.actual_rows != null ? `<span class="badge badge-info">${h.actual_rows} actual rows</span>` : ''}
                    ${h.index_used === true ? '<span class="badge badge-success">Index used</span>' : ''}
                    ${h.index_used === false ? '<span class="badge badge-danger">Index used NO</span>' : ''}
                </div>
                <div class="code-block">${this.escapeHtml(JSON.stringify(data.plan, null, 2))}</div>
            `;
            if (target) target.innerHTML = healthHtml;
            this.modalRawText = (q.sql || '') + "\n\n" + JSON.stringify(data.plan, null, 2);
        } catch (e) {
            this.showToast('EXPLAIN error');
        }
    },

    requestViewPills() {
        const view = this.state.requests.view;
        return `
            <button class="pill-tab ${view === 'log' ? 'active' : ''}" onclick="SpokeApp.setRequestView('log')">Log</button>
            <button class="pill-tab ${view === 'trends' ? 'active' : ''}" onclick="SpokeApp.setRequestView('trends')">Trends</button>
        `;
    },

    setRequestView(view) {
        this.state.requests.view = view;
        this.loadRequests();
    },

    async loadRequests() {
        if (this.state.requests.view === 'trends') {
            await this.loadRequestTrends();
            return;
        }

        const s = this.state.requests;
        const query = `?date=${encodeURIComponent(s.date)}&search=${encodeURIComponent(s.search)}&page=${s.page}`;
        const data = await this.fetchJson('/requests' + query);
        if (!data) return;

        const dates = data.meta.dates || [];
        const entries = data.data || [];
        document.getElementById('nav-requests-count').textContent = data.meta.total || 0;

        let datesOptions = dates.map(d => `<option value="${d}" ${d === data.meta.date ? 'selected' : ''}>${d}</option>`).join('');

        let rowsHtml = '';
        if (entries.length === 0) {
            rowsHtml = `<tr><td colspan="6" class="empty-state">No recorded HTTP requests for the selected date.${this.debugTools ? '' : ' Errors and sampled requests still record in monitor mode.'}</td></tr>`;
        } else {
            rowsHtml = entries.map(r => {
                const statusBadge = r.status >= 500 ? 'badge-500' : (r.status >= 400 ? 'badge-400' : 'badge-200');
                const methodBadge = r.method === 'GET' ? 'badge-info' : (r.method === 'POST' ? 'badge-success' : 'badge-warning');
                const n1 = r.summary && r.summary.n_plus_one_count ? r.summary.n_plus_one_count : 0;
                const n1Badge = n1 > 0 ? `<span class="badge badge-danger" title="Possible N+1 groups">N+1 ×${n1}</span>` : '';
                const clickable = r.trace_id
                    ? `style="cursor:pointer;" onclick="SpokeApp.showTrace('${this.escapeHtml(r.trace_id)}', '${this.escapeHtml(data.meta.date)}')"`
                    : '';
                return `
                    <tr ${clickable}>
                        <td style="font-family:var(--font-mono); font-size:0.75rem; color:var(--text-dim); white-space:nowrap;">${r.t.split(' ')[1] || r.t}</td>
                        <td><span class="badge ${methodBadge}">${r.method}</span></td>
                        <td><span class="badge ${statusBadge}">${r.status}</span></td>
                        <td style="font-family:var(--font-mono); font-weight:500;">${this.escapeHtml(r.uri)} ${n1Badge}</td>
                        <td><span class="badge badge-muted">${this.formatMs(r.ms)}</span></td>
                        <td style="color:var(--text-dim); font-size:0.75rem;">${r.memory_mb ? r.memory_mb + ' MB' : '-'}</td>
                    </tr>
                `;
            }).join('');
        }

        const html = `
            <div class="toolbar">
                <div class="toolbar-group">
                    ${this.requestViewPills()}
                    <select class="select" onchange="SpokeApp.setRequestDate(this.value)">${datesOptions}</select>
                    <input type="text" class="input" placeholder="Search URI or status..." value="${this.escapeHtml(s.search)}" oninput="SpokeApp.searchRequests(this.value)" style="width:260px;">
                </div>
            </div>
            ${this.monitorNotice('requests')}
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:110px;">Time</th>
                            <th style="width:80px;">Method</th>
                            <th style="width:80px;">Status</th>
                            <th>URI</th>
                            <th style="width:100px;">Duration</th>
                            <th style="width:90px;">RAM</th>
                        </tr>
                    </thead>
                    <tbody>${rowsHtml}</tbody>
                </table>
                <div class="pagination">
                    <span>Page ${data.meta.page} (${data.meta.total} total requests)</span>
                    <div style="display:flex; gap:0.5rem;">
                        <button class="btn btn-sm" ${data.meta.page <= 1 ? 'disabled' : ''} onclick="SpokeApp.setRequestPage(${data.meta.page - 1})">Previous</button>
                        <button class="btn btn-sm" ${(data.meta.page * data.meta.per_page) >= data.meta.total ? 'disabled' : ''} onclick="SpokeApp.setRequestPage(${data.meta.page + 1})">Next</button>
                    </div>
                </div>
            </div>
        `;
        document.getElementById('tab-content').innerHTML = html;
    },

    sparkline(series) {
        const points = series.filter(s => s.p95_ms != null);
        if (!points.length) return '<span style="color:var(--text-dim)">-</span>';
        const max = Math.max(...points.map(s => s.p95_ms), 1);
        const w = 120, h = 26;
        const step = series.length > 1 ? w / (series.length - 1) : 0;
        const coords = series
            .map((s, i) => s.p95_ms == null ? null : `${(i * step).toFixed(1)},${(h - 3 - (s.p95_ms / max) * (h - 6)).toFixed(1)}`)
            .filter(Boolean)
            .join(' ');
        return `<svg width="${w}" height="${h}" style="display:block;"><polyline fill="none" stroke="var(--accent-cyan)" stroke-width="1.5" points="${coords}"/></svg>`;
    },

    async loadRequestTrends() {
        const data = await this.fetchJson('/requests/trends');
        if (!data) return;

        const rows = (data.data || []).map(r => {
            const trend = r.trend_pct == null
                ? '<span style="color:var(--text-dim)">-</span>'
                : (r.trend_pct > 0
                    ? `<span class="badge badge-danger">▲ ${r.trend_pct}%</span>`
                    : `<span class="badge badge-success">▼ ${Math.abs(r.trend_pct)}%</span>`);
            return `
                <tr>
                    <td style="font-family:var(--font-mono); font-weight:500;">${this.escapeHtml(r.uri)}</td>
                    <td>${this.sparkline(r.series || [])}</td>
                    <td style="text-align:right;">${this.formatMs(r.latest_p95_ms)}</td>
                    <td style="text-align:center;">${trend}</td>
                    <td style="text-align:right;">${r.total_count}</td>
                </tr>
            `;
        }).join('') || `<tr><td colspan="5" class="empty-state">Not enough retained days for route trends yet.</td></tr>`;

        document.getElementById('tab-content').innerHTML = `
            <div class="toolbar">
                <div class="toolbar-group">
                    ${this.requestViewPills()}
                    <span style="color:var(--text-dim); font-size:0.75rem;">P95 per route across retained days (${(data.meta.dates || []).length} days)</span>
                </div>
            </div>
            ${this.monitorNotice('requests')}
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Route</th>
                            <th style="width:140px;">P95 trend</th>
                            <th style="width:100px; text-align:right;">Latest P95</th>
                            <th style="width:100px; text-align:center;">Change</th>
                            <th style="width:100px; text-align:right;">Requests</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
        `;
    },

    async showTrace(traceId, date) {
        const data = await this.fetchJson(`/trace/${encodeURIComponent(traceId)}?date=${encodeURIComponent(date || '')}`);
        if (!data || !data.found) {
            this.showToast('Trace not found for this date.');
            return;
        }

        const req = data.request || {};
        const summary = req.summary || {};
        const n1Groups = summary.n_plus_one || [];
        const status = req.status || 0;
        const statusBadge = status >= 500 ? 'badge-500' : (status >= 400 ? 'badge-400' : 'badge-200');
        const slow = summary.slow_queries || 0;
        const n1 = summary.n_plus_one_count || 0;
        const httpCalls = summary.http_calls || [];
        const memStart = summary.memory_start_mb;
        const memPeak = summary.memory_peak_mb ?? req.memory_mb;

        const frRow = (label, valueHtml, warn = false) => `
            <div style="display:flex; justify-content:space-between; gap:1.25rem; padding:0.55rem 0; border-bottom:1px solid var(--border);">
                <span style="color:var(--text-muted); font-size:0.7rem; letter-spacing:0.08em; text-transform:uppercase; min-width:110px;">${label}</span>
                <span style="font-family:var(--font-mono); text-align:right; ${warn ? 'color:var(--accent-red); font-weight:600;' : ''}">${valueHtml}</span>
            </div>
        `;

        let httpHtml = 'none';
        if (httpCalls.length) {
            httpHtml = httpCalls.map(c => {
                const warn = c.failed || (c.ms != null && c.ms >= 200);
                return `<div ${warn ? 'style="color:var(--accent-red)"' : ''}>${this.escapeHtml(c.method || '')} ${this.escapeHtml(c.host || '')} ${c.failed ? 'FAIL' : (c.status || '')} ${this.formatMs(c.ms)}</div>`;
            }).join('');
        } else if (summary.http_count) {
            httpHtml = `${summary.http_count} calls · ${this.formatMs(summary.http_total_ms)}`;
        }

        const dbHtml = `${summary.queries_count || 0} queries`
            + (slow ? ` · <span style="color:var(--accent-red)">${slow} slow</span>` : '')
            + (n1 ? ` · <span style="color:var(--accent-red)">${n1} possible N+1</span>` : '');

        const card = `
            <div style="font-family:var(--font-mono); font-size:0.8rem; color:var(--text-dim); margin-bottom:0.75rem;">${this.escapeHtml(req.t || '')}</div>
            <div style="display:flex; flex-wrap:wrap; gap:0.5rem; align-items:center; margin-bottom:1rem;">
                <span class="badge ${req.method === 'GET' ? 'badge-info' : 'badge-success'}">${this.escapeHtml(req.method || '')}</span>
                <span style="font-family:var(--font-mono); font-weight:600; font-size:1.05rem;">${this.escapeHtml(req.uri || '')}</span>
                <span class="badge ${statusBadge}">HTTP ${status || '-'}</span>
            </div>
            ${frRow('User', req.user_id != null ? '#' + this.escapeHtml(String(req.user_id)) : 'guest')}
            ${frRow('Request', this.formatMs(req.ms), (req.ms || 0) >= 500)}
            ${frRow('Database', dbHtml, slow > 0 || n1 > 0)}
            ${frRow('Redis', `${summary.redis_count || 0} commands` + (summary.redis_total_ms ? ' · ' + this.formatMs(summary.redis_total_ms) : ''))}
            ${frRow('External API', httpHtml, httpCalls.some(c => c.failed || (c.ms != null && c.ms >= 200)))}
            ${frRow('Queue', this.flightQueueHtml(summary, data.timeline || []), (summary.jobs || []).some(j => j.event === 'failed'))}
            ${frRow('Exception', this.flightExceptionHtml(summary, data.timeline || []), !!(summary.exception || (data.timeline || []).some(i => i.type === 'exception' || i.event === 'failed')))}
            ${frRow('Memory', (memStart != null ? memStart + ' → ' : '') + (memPeak != null ? memPeak + ' MB' : '-'))}
            ${frRow('Response', 'HTTP ' + (status || '-'), status >= 400)}
            ${req.body ? this.payloadBlock('Request payload', req.body) : ''}
        `;

        let n1Html = '';
        if (n1Groups.length) {
            n1Html = `
                <div style="margin:1rem 0 0.5rem; font-weight:600; color:var(--accent-red);">Possible N+1 groups</div>
                ${n1Groups.map(g => `
                    <div class="code-block" style="margin-bottom:0.5rem;">
                        <div style="margin-bottom:0.35rem;">
                            <span class="badge badge-danger">×${g.count}</span>
                            <span class="badge badge-muted">${this.formatMs(g.total_ms)}</span>
                            ${g.possible_relation ? `<span class="badge badge-purple">${this.escapeHtml(g.possible_relation)}</span>` : ''}
                        </div>
                        <div style="color:var(--accent-cyan);">${this.escapeHtml(g.normalized_sql)}</div>
                    </div>
                `).join('')}
            `;
        }

        const timelineHtml = (data.timeline || []).map(item => {
            const type = item.type || 'event';
            let label = type.toUpperCase();
            let detail = '';
            let badge = 'badge-muted';
            let extra = '';

            if (type === 'request') {
                badge = 'badge-info';
                detail = `${item.method || ''} ${item.uri || ''} → ${item.status || ''} (${this.formatMs(item.ms)})`;
            } else if (type === 'query') {
                badge = item.n_plus_one ? 'badge-danger' : 'badge-warning';
                label = item.n_plus_one ? 'SQL N+1' : 'SQL';
                detail = `${this.formatMs(item.ms)} — ${item.sql || ''}`;
            } else if (type === 'redis') {
                badge = 'badge-purple';
                detail = `${this.formatMs(item.ms)} — ${item.command || ''} ${(item.parameters || []).slice(0, 3).join(' ')}`;
            } else if (type === 'http') {
                badge = item.failed ? 'badge-danger' : 'badge-success';
                detail = `${item.method || ''} ${item.url || ''} → ${item.failed ? 'FAIL' : (item.status || '-')} (${this.formatMs(item.ms)})`;
                extra = this.payloadBlock('Request body', item.request_body) + this.payloadBlock('Response body', item.response_body);
            } else if (type === 'job') {
                badge = item.event === 'failed' ? 'badge-danger' : (item.event === 'processed' ? 'badge-success' : 'badge-purple');
                label = 'QUEUE ' + (item.event || '').toUpperCase();
                detail = `${item.name || ''} [${item.queue || 'default'}]`
                    + (item.ms != null ? ` · ${this.formatMs(item.ms)}` : '')
                    + (item.wait_ms != null ? ` · wait ${this.formatMs(item.wait_ms)}` : '')
                    + (item.exception ? ` — ${item.exception}` : '');
            } else if (type === 'exception') {
                badge = 'badge-danger';
                label = 'EXCEPTION';
                detail = `${item.class || ''} — ${item.message || ''}`;
            }

            return `
                <div style="display:flex; gap:0.75rem; margin-bottom:0.65rem; align-items:flex-start;">
                    <div style="width:90px; font-family:var(--font-mono); font-size:0.7rem; color:var(--text-dim); padding-top:0.15rem;">${this.escapeHtml((item.t || '').split(' ')[1] || item.t || '')}</div>
                    <div style="flex:1;">
                        <span class="badge ${badge}">${label}</span>
                        <div class="code-block" style="margin-top:0.35rem; white-space:pre-wrap;">${this.escapeHtml(detail)}</div>
                        ${extra}
                    </div>
                </div>
            `;
        }).join('') || '<div class="empty-state">No timeline events persisted for this trace.</div>';

        const content = `
            ${card}
            <div style="font-family:var(--font-mono); font-size:0.72rem; color:var(--text-dim); margin:1rem 0 0.5rem;">trace_id: ${this.escapeHtml(data.trace_id)}</div>
            ${n1Html}
            <div style="margin:1rem 0 0.5rem; font-weight:600; color:var(--text-muted);">Event log</div>
            ${timelineHtml}
        `;

        this.openModal('Flight Recorder', content, JSON.stringify(data, null, 2));
    },

    setRequestDate(d) { this.state.requests.date = d; this.state.requests.page = 1; this.loadRequests(); },
    setRequestPage(p) { this.state.requests.page = p; this.loadRequests(); },
    searchRequests(t) {
        clearTimeout(this._reqSearchTimer);
        this._reqSearchTimer = setTimeout(() => {
            this.state.requests.search = t;
            this.state.requests.page = 1;
            this.loadRequests();
        }, 300);
    },

    async loadHttpCalls() {
        const s = this.state.http;
        const query = `?date=${encodeURIComponent(s.date)}&search=${encodeURIComponent(s.search)}&page=${s.page}`;
        const data = await this.fetchJson('/http' + query);
        if (!data) return;

        const dates = data.meta.dates || [];
        const entries = data.data || [];
        const navHttp = document.getElementById('nav-http-count');
        if (navHttp) navHttp.textContent = data.meta.total || 0;

        let datesOptions = dates.map(d => `<option value="${d}" ${d === data.meta.date ? 'selected' : ''}>${d}</option>`).join('');
        let rowsHtml = '';

        if (entries.length === 0) {
            rowsHtml = `<tr><td colspan="6" class="empty-state">No recorded outgoing HTTP calls for the selected date.${this.debugTools ? '' : ' Slow or failed calls still record in monitor mode.'}</td></tr>`;
        } else {
            rowsHtml = entries.map((h, idx) => {
                const statusBadge = h.failed ? 'badge-danger' : (h.status >= 500 ? 'badge-500' : (h.status >= 400 ? 'badge-400' : 'badge-200'));
                const methodBadge = h.method === 'GET' ? 'badge-info' : (h.method === 'POST' ? 'badge-success' : 'badge-warning');
                return `
                    <tr style="cursor:pointer;" onclick="${h.trace_id
                        ? `SpokeApp.showTrace('${this.escapeHtml(h.trace_id)}', '${this.escapeHtml(data.meta.date)}')`
                        : `SpokeApp.showHttpDetail(${idx})`}">
                        <td style="font-family:var(--font-mono); font-size:0.75rem; color:var(--text-dim); white-space:nowrap;">${(h.t || '').split(' ')[1] || h.t || ''}</td>
                        <td><span class="badge ${methodBadge}">${this.escapeHtml(h.method || '')}</span></td>
                        <td><span class="badge ${statusBadge}">${h.failed ? 'FAIL' : (h.status || '-')}</span></td>
                        <td style="font-family:var(--font-mono); font-weight:500; word-break:break-all;">${this.escapeHtml(h.url || '')}</td>
                        <td><span class="badge badge-muted">${this.formatMs(h.ms)}</span></td>
                        <td style="font-family:var(--font-mono); font-size:0.75rem; color:var(--text-dim);">${this.escapeHtml(h.uri || '-')}</td>
                    </tr>
                `;
            }).join('');
        }

        this.currentHttpCalls = entries;

        const html = `
            <div class="toolbar">
                <div class="toolbar-group">
                    <select class="select" onchange="SpokeApp.setHttpDate(this.value)">${datesOptions}</select>
                    <input type="text" class="input" placeholder="Search URL, method, status..." value="${this.escapeHtml(s.search)}" oninput="SpokeApp.searchHttp(this.value)" style="width:280px;">
                </div>
            </div>
            ${this.monitorNotice('http')}
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:110px;">Time</th>
                            <th style="width:80px;">Method</th>
                            <th style="width:80px;">Status</th>
                            <th>URL</th>
                            <th style="width:100px;">Duration</th>
                            <th style="width:160px;">Caller URI</th>
                        </tr>
                    </thead>
                    <tbody>${rowsHtml}</tbody>
                </table>
                <div class="pagination">
                    <span>Page ${data.meta.page} (${data.meta.total} total calls)</span>
                    <div style="display:flex; gap:0.5rem;">
                        <button class="btn btn-sm" ${data.meta.page <= 1 ? 'disabled' : ''} onclick="SpokeApp.setHttpPage(${data.meta.page - 1})">Previous</button>
                        <button class="btn btn-sm" ${(data.meta.page * data.meta.per_page) >= data.meta.total ? 'disabled' : ''} onclick="SpokeApp.setHttpPage(${data.meta.page + 1})">Next</button>
                    </div>
                </div>
            </div>
        `;
        document.getElementById('tab-content').innerHTML = html;
    },

    setHttpDate(d) { this.state.http.date = d; this.state.http.page = 1; this.loadHttpCalls(); },
    setHttpPage(p) { this.state.http.page = p; this.loadHttpCalls(); },
    searchHttp(t) {
        clearTimeout(this._httpSearchTimer);
        this._httpSearchTimer = setTimeout(() => {
            this.state.http.search = t;
            this.state.http.page = 1;
            this.loadHttpCalls();
        }, 300);
    },

    showHttpDetail(idx) {
        const h = this.currentHttpCalls?.[idx];
        if (!h) return;
        const content = `
            <div style="margin-bottom:0.75rem; display:flex; flex-wrap:wrap; gap:0.4rem;">
                <span class="badge badge-info">${this.escapeHtml(h.method || '')}</span>
                <span class="badge ${h.failed ? 'badge-danger' : 'badge-200'}">${h.failed ? 'FAIL' : (h.status || '-')}</span>
                <span class="badge badge-muted">${this.formatMs(h.ms)}</span>
                ${h.trace_id ? `<span class="badge badge-purple">${this.escapeHtml(h.trace_id)}</span>` : ''}
            </div>
            <div style="margin-bottom:0.5rem; font-weight:600; color:var(--text-muted);">URL</div>
            <div class="code-block" style="color:var(--accent-cyan); margin-bottom:1rem;">${this.escapeHtml(h.url || '')}</div>
            <div style="margin-bottom:0.5rem; font-weight:600; color:var(--text-muted);">Request headers</div>
            <div class="code-block" style="margin-bottom:1rem;">${this.escapeHtml(JSON.stringify(h.request_headers || {}, null, 2))}</div>
            <div style="margin-bottom:0.5rem; font-weight:600; color:var(--text-muted);">Request body</div>
            <div class="code-block" style="margin-bottom:1rem;">${this.escapeHtml(h.request_body || '(empty)')}</div>
            <div style="margin-bottom:0.5rem; font-weight:600; color:var(--text-muted);">Response body</div>
            <div class="code-block">${this.escapeHtml(h.response_body || h.error || '(empty)')}</div>
        `;
        this.openModal('Outgoing HTTP Call', content, JSON.stringify(h, null, 2));
    },

    async loadMails() {
        const s = this.state.mails;
        const query = `?date=${encodeURIComponent(s.date)}&search=${encodeURIComponent(s.search)}&page=${s.page}`;
        const data = await this.fetchJson('/mails' + query);
        if (!data) return;

        const dates = data.meta.dates || [];
        const entries = data.data || [];
        document.getElementById('nav-mails-count').textContent = data.meta.total || 0;

        let datesOptions = dates.map(d => `<option value="${d}" ${d === data.meta.date ? 'selected' : ''}>${d}</option>`).join('');

        let rowsHtml = '';
        if (entries.length === 0) {
            rowsHtml = `<tr><td colspan="4" class="empty-state">No recorded emails for the selected date.${this.debugTools ? '' : ' Sent mail still records in monitor mode.'}</td></tr>`;
        } else {
            rowsHtml = entries.map((m, idx) => `
                <tr style="cursor:pointer;" onclick="SpokeApp.showMailDetail(${idx})">
                    <td style="font-family:var(--font-mono); font-size:0.75rem; color:var(--text-dim); white-space:nowrap;">${m.t.split(' ')[1] || m.t}</td>
                    <td style="font-weight:600;">${this.escapeHtml((m.to || []).join(', '))}</td>
                    <td><span class="code-snippet">${this.escapeHtml(m.subject || '(no subject)')}</span></td>
                    <td style="text-align:right;">
                        ${m.body_file ? '<button class="btn btn-sm btn-primary">Preview HTML</button>' : '<span style="color:var(--text-dim)">No body</span>'}
                    </td>
                </tr>
            `).join('');
        }

        this.currentMails = entries;

        const html = `
            <div class="toolbar">
                <div class="toolbar-group">
                    <select class="select" onchange="SpokeApp.setMailDate(this.value)">${datesOptions}</select>
                    <input type="text" class="input" placeholder="Search recipient or subject..." value="${this.escapeHtml(s.search)}" oninput="SpokeApp.searchMails(this.value)" style="width:260px;">
                </div>
            </div>
            ${this.monitorNotice('mails')}
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:110px;">Time</th>
                            <th style="width:240px;">Recipient (To)</th>
                            <th>Subject</th>
                            <th style="width:140px; text-align:right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>${rowsHtml}</tbody>
                </table>
                <div class="pagination">
                    <span>Page ${data.meta.page} (${data.meta.total} total emails)</span>
                    <div style="display:flex; gap:0.5rem;">
                        <button class="btn btn-sm" ${data.meta.page <= 1 ? 'disabled' : ''} onclick="SpokeApp.setMailPage(${data.meta.page - 1})">Previous</button>
                        <button class="btn btn-sm" ${(data.meta.page * data.meta.per_page) >= data.meta.total ? 'disabled' : ''} onclick="SpokeApp.setMailPage(${data.meta.page + 1})">Next</button>
                    </div>
                </div>
            </div>
        `;
        document.getElementById('tab-content').innerHTML = html;
    },

    setMailDate(d) { this.state.mails.date = d; this.state.mails.page = 1; this.loadMails(); },
    setMailPage(p) { this.state.mails.page = p; this.loadMails(); },
    searchMails(t) {
        clearTimeout(this._mailSearchTimer);
        this._mailSearchTimer = setTimeout(() => {
            this.state.mails.search = t;
            this.state.mails.page = 1;
            this.loadMails();
        }, 300);
    },

    showMailDetail(idx) {
        const m = this.currentMails[idx];
        if (!m) return;
        let bodyHtml = '';
        if (m.body_file) {
            bodyHtml = `<iframe sandbox referrerpolicy="no-referrer" src="${this.apiBase}/mails/body?file=${encodeURIComponent(m.body_file)}" style="width:100%; height:450px; border:1px solid var(--border); border-radius:var(--radius-sm); background:#fff;"></iframe>`;
        } else {
            bodyHtml = `<div class="empty-state">No saved email body.</div>`;
        }
        const content = `
            <div style="display:flex; flex-direction:column; gap:0.4rem; margin-bottom:1rem; font-size:0.8rem;">
                <div><span style="color:var(--text-muted)">To:</span> <b>${this.escapeHtml((m.to || []).join(', '))}</b></div>
                <div><span style="color:var(--text-muted)">Subject:</span> <b>${this.escapeHtml(m.subject)}</b></div>
                <div><span style="color:var(--text-muted)">Time:</span> ${m.t}</div>
            </div>
            ${bodyHtml}
        `;
        this.openModal('Email Message Preview', content, JSON.stringify(m, null, 2));
    },

    async loadQueue() {
        const tab = this.state.queue.activeTab;

        if (tab === 'history') {
            await this.loadQueueHistory();
            return;
        }
        if (tab === 'analytics') {
            await this.loadQueueAnalytics();
            return;
        }

        const data = await this.fetchJson('/queue');
        if (!data) return;

        const pending = data.pending || { jobs: [], total: 0 };
        const failed = data.failed || { jobs: [], total: 0 };
        const isFailedTab = tab === 'failed';

        document.getElementById('nav-queue-count').textContent = failed.total > 0 ? failed.total : (pending.total || 0);
        if (failed.total > 0) {
            document.getElementById('nav-queue-count').classList.add('danger');
        } else {
            document.getElementById('nav-queue-count').classList.remove('danger');
        }

        let jobsHtml = '';
        if (isFailedTab) {
            if (failed.jobs.length === 0) {
                jobsHtml = `<tr><td colspan="5" class="empty-state">🎉 No failed jobs in queue!</td></tr>`;
            } else {
                jobsHtml = failed.jobs.map((j, idx) => `
                    <tr style="cursor:pointer;" onclick="SpokeApp.showJobDetail('failed', ${idx})">
                        <td style="font-family:var(--font-mono); font-size:0.75rem; color:var(--text-dim);">${this.escapeHtml(j.failed_at || 'n/a')}</td>
                        <td><span class="badge badge-muted">${this.escapeHtml(j.queue)}</span></td>
                        <td style="font-weight:600; font-family:var(--font-mono);">${this.escapeHtml(j.job)}</td>
                        <td><span class="code-snippet" style="color:var(--accent-red);">${this.escapeHtml((j.exception || '').substring(0, 120))}...</span></td>
                        <td style="text-align:right;">
                            <div style="display:flex; justify-content:flex-end; gap:0.4rem;">
                                <button class="btn btn-sm btn-primary" data-id="${this.escapeHtml(j.id)}" onclick="event.stopPropagation(); SpokeApp.retryJob(this.dataset.id)">Retry</button>
                                <button class="btn btn-sm btn-danger" data-id="${this.escapeHtml(j.id)}" onclick="event.stopPropagation(); SpokeApp.forgetJob(this.dataset.id)">✕</button>
                            </div>
                        </td>
                    </tr>
                `).join('');
            }
        } else {
            if (pending.jobs.length === 0) {
                jobsHtml = `<tr><td colspan="5" class="empty-state">Queue is empty (no pending jobs).</td></tr>`;
            } else {
                jobsHtml = pending.jobs.map((j, idx) => `
                    <tr style="cursor:pointer;" onclick="SpokeApp.showJobDetail('pending', ${idx})">
                        <td style="font-family:var(--font-mono); font-size:0.75rem; color:var(--text-dim);">${this.escapeHtml(j.created_at || 'n/a')}</td>
                        <td><span class="badge badge-muted">${this.escapeHtml(j.queue)}</span></td>
                        <td style="font-weight:600; font-family:var(--font-mono);">${this.escapeHtml(j.job)}</td>
                        <td><span class="badge badge-info">${j.status && j.status !== 'pending' ? this.escapeHtml(j.status) : 'Attempt #' + this.escapeHtml(j.attempts)}</span></td>
                        <td style="color:var(--text-dim); font-size:0.75rem;">${this.escapeHtml(j.available_at || j.status || 'n/a')}</td>
                    </tr>
                `).join('');
            }
        }

        this.currentPendingJobs = pending.jobs || [];
        this.currentFailedJobs = failed.jobs || [];

        const html = `
            ${this.queueToolbar(pending.total, failed.total, isFailedTab && failed.total > 0)}
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:160px;">Time</th>
                            <th style="width:100px;">Queue</th>
                            <th>Job Class</th>
                            <th>${isFailedTab ? 'Exception' : 'Status'}</th>
                            <th style="width:140px; text-align:right;">${isFailedTab ? 'Actions' : 'Available At'}</th>
                        </tr>
                    </thead>
                    <tbody>${jobsHtml}</tbody>
                </table>
            </div>
        `;
        document.getElementById('tab-content').innerHTML = html;
    },

    queueToolbar(pendingTotal, failedTotal, showRetryAll) {
        const tab = this.state.queue.activeTab;
        return `
            <div class="toolbar">
                <div class="toolbar-group">
                    <button class="pill-tab ${tab === 'pending' ? 'active' : ''}" onclick="SpokeApp.setQueueTab('pending')">Pending (${pendingTotal ?? '—'})</button>
                    <button class="pill-tab ${tab === 'failed' ? 'active' : ''}" onclick="SpokeApp.setQueueTab('failed')">Failed (${failedTotal ?? '—'})</button>
                    <button class="pill-tab ${tab === 'history' ? 'active' : ''}" onclick="SpokeApp.setQueueTab('history')">History</button>
                    <button class="pill-tab ${tab === 'analytics' ? 'active' : ''}" onclick="SpokeApp.setQueueTab('analytics')">Analytics</button>
                </div>
                ${showRetryAll ? `
                    <button class="btn btn-sm btn-primary" onclick="SpokeApp.retryJob('all')">Retry All Failed (${failedTotal})</button>
                ` : ''}
            </div>
        `;
    },

    async loadQueueHistory() {
        const s = this.state.queue;
        const params = new URLSearchParams({ page: s.page || 1 });
        if (s.date) params.set('date', s.date);
        if (s.search) params.set('search', s.search);
        const data = await this.fetchJson('/jobs?' + params.toString());
        if (!data) return;

        const dates = data.meta.dates || [];
        const datesOptions = dates.map(d => `<option value="${d}" ${d === data.meta.date ? 'selected' : ''}>${d}</option>`).join('');
        const history = data.data || [];
        this.currentHistoryJobs = history;
        const rows = history.map((j, idx) => {
            const ev = j.event || '';
            const badge = ev === 'failed' ? 'badge-danger' : (ev === 'processed' ? 'badge-success' : 'badge-purple');
            return `
                <tr style="cursor:pointer;" onclick="SpokeApp.showJobDetail('history', ${idx})">
                    <td style="font-family:var(--font-mono); font-size:0.75rem; color:var(--text-dim);">${this.escapeHtml(j.t || '')}</td>
                    <td><span class="badge ${badge}">${this.escapeHtml(ev)}</span></td>
                    <td style="font-weight:600; font-family:var(--font-mono);">${this.escapeHtml(j.name || '')}</td>
                    <td><span class="badge badge-muted">${this.escapeHtml(j.queue || 'default')}</span></td>
                    <td style="text-align:right;">${this.formatMs(j.ms)}</td>
                    <td style="text-align:right;">${this.formatMs(j.wait_ms)}</td>
                </tr>
            `;
        }).join('') || `<tr><td colspan="6" class="empty-state">No recorded jobs for this date.</td></tr>`;

        document.getElementById('tab-content').innerHTML = `
            ${this.queueToolbar()}
            <div class="toolbar">
                <div class="toolbar-group">
                    <select class="select" onchange="SpokeApp.setQueueHistoryDate(this.value)">${datesOptions}</select>
                    <input type="text" class="input" placeholder="Search job class..." value="${this.escapeHtml(s.search)}" oninput="SpokeApp.searchQueueHistory(this.value)" style="width:240px;">
                </div>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:170px;">Time</th>
                            <th style="width:110px;">Event</th>
                            <th>Job Class</th>
                            <th style="width:110px;">Queue</th>
                            <th style="width:90px; text-align:right;">Runtime</th>
                            <th style="width:90px; text-align:right;">Wait</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
                <div class="pagination">
                    <span>Page ${data.meta.page} (${data.meta.total} total events)</span>
                    <div style="display:flex; gap:0.5rem;">
                        <button class="btn btn-sm" ${data.meta.page <= 1 ? 'disabled' : ''} onclick="SpokeApp.setQueueHistoryPage(${data.meta.page - 1})">Previous</button>
                        <button class="btn btn-sm" ${(data.meta.page * data.meta.per_page) >= data.meta.total ? 'disabled' : ''} onclick="SpokeApp.setQueueHistoryPage(${data.meta.page + 1})">Next</button>
                    </div>
                </div>
            </div>
        `;
    },

    async loadQueueAnalytics() {
        const s = this.state.queue;
        const params = new URLSearchParams();
        if (s.date) params.set('date', s.date);
        const data = await this.fetchJson('/jobs/stats?' + params.toString());
        if (!data) return;

        const dates = (data.meta && data.meta.dates) || [];
        const datesOptions = dates.map(d => `<option value="${d}" ${d === data.meta.date ? 'selected' : ''}>${d}</option>`).join('');
        const tput = data.throughput || {};
        const runtime = data.runtime || {};
        const wait = data.wait || {};
        const classes = data.by_class || [];
        const failedRows = data.failed_analytics || [];

        const classRows = classes.map(c => `
            <tr>
                <td style="font-family:var(--font-mono);">${this.escapeHtml(c.name)}</td>
                <td>${c.queued}</td>
                <td>${c.processed}</td>
                <td>${c.failed ? `<span class="badge badge-danger">${c.failed}</span>` : '0'}</td>
                <td style="text-align:right;">${this.formatMs(c.avg_ms)}</td>
                <td style="text-align:right;">${this.formatMs(c.max_ms)}</td>
                <td style="text-align:right;">${this.formatMs(c.avg_wait_ms)}</td>
            </tr>
        `).join('') || `<tr><td colspan="7" class="empty-state">No job analytics for this date.</td></tr>`;

        const failedHtml = failedRows.length
            ? failedRows.map(c => `<div style="display:flex; justify-content:space-between; padding:0.35rem 0; border-bottom:1px solid var(--border);"><span style="font-family:var(--font-mono);">${this.escapeHtml(this.shortJobName(c.name))}</span><span class="badge badge-danger">${c.failed} failed</span></div>`).join('')
            : '<div class="empty-state">No failed job events in JSONL for this date.</div>';

        document.getElementById('tab-content').innerHTML = `
            ${this.queueToolbar()}
            <div class="toolbar">
                <div class="toolbar-group">
                    <select class="select" onchange="SpokeApp.setQueueHistoryDate(this.value)">${datesOptions}</select>
                </div>
            </div>
            <div class="grid-kpi" style="margin-bottom:1rem;">
                <div class="card"><div class="card-header"><span class="card-label">Processed</span></div><div style="font-size:1.4rem; font-weight:700;">${tput.processed || 0}</div><div style="color:var(--text-dim); font-size:0.75rem;">${tput.queued || 0} queued</div></div>
                <div class="card"><div class="card-header"><span class="card-label">Failed</span></div><div style="font-size:1.4rem; font-weight:700; ${tput.failed ? 'color:var(--accent-red);' : ''}">${tput.failed || 0}</div></div>
                <div class="card"><div class="card-header"><span class="card-label">Throughput</span></div><div style="font-size:1.4rem; font-weight:700;">${tput.jobs_per_minute != null ? tput.jobs_per_minute : '—'}</div><div style="color:var(--text-dim); font-size:0.75rem;">jobs / min</div></div>
                <div class="card"><div class="card-header"><span class="card-label">Runtime</span></div><div style="font-size:1.05rem; font-weight:600;">avg ${this.formatMs(runtime.avg_ms)}</div><div style="color:var(--text-dim); font-size:0.75rem;">p95 ${this.formatMs(runtime.p95_ms)} · max ${this.formatMs(runtime.max_ms)}</div></div>
                <div class="card"><div class="card-header"><span class="card-label">Wait</span></div><div style="font-size:1.05rem; font-weight:600;">avg ${this.formatMs(wait.avg_ms)}</div><div style="color:var(--text-dim); font-size:0.75rem;">p95 ${this.formatMs(wait.p95_ms)}</div></div>
            </div>
            <div class="card" style="margin-bottom:1rem;">
                <div class="card-header"><span class="card-label">Failed job analytics</span></div>
                ${failedHtml}
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Job Class</th>
                            <th style="width:80px;">Queued</th>
                            <th style="width:90px;">Processed</th>
                            <th style="width:80px;">Failed</th>
                            <th style="width:90px; text-align:right;">Avg</th>
                            <th style="width:90px; text-align:right;">Max</th>
                            <th style="width:90px; text-align:right;">Wait</th>
                        </tr>
                    </thead>
                    <tbody>${classRows}</tbody>
                </table>
            </div>
        `;
    },

    setQueueTab(tab) {
        this.state.queue.activeTab = tab;
        this.state.queue.page = 1;
        this.loadQueue();
    },
    setQueueHistoryDate(d) { this.state.queue.date = d; this.state.queue.page = 1; this.loadQueue(); },
    setQueueHistoryPage(p) { this.state.queue.page = p; this.loadQueue(); },
    searchQueueHistory(t) {
        clearTimeout(this._queueSearchTimer);
        this._queueSearchTimer = setTimeout(() => {
            this.state.queue.search = t;
            this.state.queue.page = 1;
            this.loadQueue();
        }, 300);
    },

    showJobDetail(kind, idx) {
        const sources = {
            pending: this.currentPendingJobs || [],
            failed: this.currentFailedJobs || [],
            history: this.currentHistoryJobs || [],
        };
        const j = (sources[kind] || [])[idx];
        if (!j) return;

        const name = j.job || j.name || 'unknown';
        const event = kind === 'failed' ? 'failed' : (j.event || j.status || kind);
        const badge = event === 'failed' ? 'badge-danger' : (event === 'processed' ? 'badge-success' : 'badge-purple');
        const date = (j.t || j.failed_at || j.created_at || '').toString().split(' ')[0] || '';
        const traceId = (j.payload && j.payload.trace_id) || j.trace_id || '';
        const payload = j.payload || null;

        const content = `
            <div style="margin-bottom:0.75rem;">
                <span class="badge ${badge}">${this.escapeHtml(event)}</span>
                ${j.id || j.job_id ? `<span style="font-family:var(--font-mono); font-size:0.8rem; margin-left:0.5rem;">ID: ${this.escapeHtml(j.id || j.job_id)}</span>` : ''}
            </div>
            <div style="margin-bottom:0.4rem; font-weight:600;">Job: ${this.escapeHtml(name)}</div>
            <div style="margin-bottom:0.75rem; font-size:0.8rem; color:var(--text-muted);">
                Queue: ${this.escapeHtml(j.queue || 'default')}
                ${j.attempts != null ? ` · Attempts: ${this.escapeHtml(j.attempts)}` : ''}
                ${j.ms != null ? ` · Runtime: ${this.formatMs(j.ms)}` : ''}
                ${j.wait_ms != null ? ` · Wait: ${this.formatMs(j.wait_ms)}` : ''}
            </div>
            ${j.exception ? `<div class="code-block" style="color:var(--accent-red);">${this.escapeHtml(j.exception)}</div>` : ''}
            ${payload ? this.payloadBlock('Job payload', JSON.stringify(payload)) : (kind === 'history' ? '<div class="empty-state">History stores timing and class, not constructor arguments.</div>' : '<div class="empty-state">No job payload available.</div>')}
            ${traceId ? `<div style="margin-top:0.85rem;"><button class="btn btn-sm btn-primary" onclick="SpokeApp.showTrace('${this.escapeHtml(traceId)}', '${this.escapeHtml(date)}')">Flight Recorder</button></div>` : ''}
        `;
        this.openModal('Job Details', content, JSON.stringify(j, null, 2));
    },

    showFailedJobDetail(idx) {
        this.showJobDetail('failed', idx);
    },

    async retryJob(id) {
        if (id === 'all' && !confirm('Retry ALL failed jobs? This cannot be undone from this screen.')) {
            return;
        }
        const res = await this.fetchJson('/queue/retry', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        if (res && res.ok) {
            this.showToast('Job queued for retry!');
            this.loadQueue();
        } else {
            this.showToast('Retry error: ' + (res ? res.output : ''));
        }
    },

    async forgetJob(id) {
        if (!confirm('Are you sure you want to delete this failed job?')) return;
        const res = await this.fetchJson('/queue/forget', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        if (res && res.ok) {
            this.showToast('Failed job removed.');
            this.loadQueue();
        } else {
            this.showToast('Delete error: ' + (res ? res.output : ''));
        }
    },

    async loadScheduler() {
        if (this.state.scheduler.activeSubTab === 'commands') {
            await this.loadCommands();
            return;
        }

        const s = this.state.scheduler;
        const params = new URLSearchParams({ page: s.page || 1 });
        if (s.date) params.set('date', s.date);
        if (s.search) params.set('search', s.search);
        const data = await this.fetchJson('/scheduler?' + params.toString());
        if (!data) return;

        const dates = data.meta.dates || [];
        const datesOptions = dates.map(d => `<option value="${d}" ${d === data.meta.date ? 'selected' : ''}>${d}</option>`).join('');
        const rows = (data.data || []).map(t => {
            const failed = t.event === 'failed';
            return `
                <tr>
                    <td style="font-family:var(--font-mono); font-size:0.75rem; color:var(--text-dim);">${this.escapeHtml(t.t || '')}</td>
                    <td style="font-family:var(--font-mono);">${this.escapeHtml(t.description || t.command || '')}</td>
                    <td><span class="badge badge-muted">${this.escapeHtml(t.expression || '')}</span></td>
                    <td style="text-align:right;">${this.formatMs(t.ms)}</td>
                    <td>${failed ? `<span class="badge badge-danger">${this.escapeHtml(t.exception || 'failed')}</span>` : '<span class="badge badge-success">ok</span>'}</td>
                </tr>
            `;
        }).join('') || `<tr><td colspan="5" class="empty-state">No scheduled tasks recorded for this date.</td></tr>`;

        document.getElementById('tab-content').innerHTML = `
            <div class="toolbar">
                <div class="toolbar-group">
                    <button class="pill-tab active" onclick="SpokeApp.setSchedulerSubTab('tasks')">Tasks</button>
                    <button class="pill-tab" onclick="SpokeApp.setSchedulerSubTab('commands')">Commands</button>
                    <select class="select" onchange="SpokeApp.setSchedulerDate(this.value)">${datesOptions}</select>
                    <input type="text" class="input" placeholder="Search task..." value="${this.escapeHtml(s.search)}" oninput="SpokeApp.searchScheduler(this.value)" style="width:220px;">
                </div>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:170px;">Time</th>
                            <th>Task</th>
                            <th style="width:140px;">Cron</th>
                            <th style="width:90px; text-align:right;">Runtime</th>
                            <th style="width:180px;">Status</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
                <div class="pagination">
                    <span>Page ${data.meta.page} (${data.meta.total} total tasks)</span>
                    <div style="display:flex; gap:0.5rem;">
                        <button class="btn btn-sm" ${data.meta.page <= 1 ? 'disabled' : ''} onclick="SpokeApp.setSchedulerPage(${data.meta.page - 1})">Previous</button>
                        <button class="btn btn-sm" ${(data.meta.page * data.meta.per_page) >= data.meta.total ? 'disabled' : ''} onclick="SpokeApp.setSchedulerPage(${data.meta.page + 1})">Next</button>
                    </div>
                </div>
            </div>
        `;
    },

    async loadCommands() {
        const s = this.state.scheduler;
        const params = new URLSearchParams({ page: s.commandsPage || 1 });
        if (s.commandsDate) params.set('date', s.commandsDate);
        if (s.commandsSearch) params.set('search', s.commandsSearch);
        const data = await this.fetchJson('/commands?' + params.toString());
        if (!data) return;

        const dates = data.meta.dates || [];
        const datesOptions = dates.map(d => `<option value="${d}" ${d === data.meta.date ? 'selected' : ''}>${d}</option>`).join('');
        const rows = (data.data || []).map(c => {
            const bad = c.exit_code !== 0 && c.exit_code !== null && c.exit_code !== undefined;
            return `
                <tr>
                    <td style="font-family:var(--font-mono); font-size:0.75rem; color:var(--text-dim);">${this.escapeHtml(c.t || '')}</td>
                    <td style="font-family:var(--font-mono); font-weight:600;">${this.escapeHtml(c.command || '')}</td>
                    <td>${bad ? `<span class="badge badge-danger">${c.exit_code}</span>` : `<span class="badge badge-success">${c.exit_code ?? 0}</span>`}</td>
                    <td style="text-align:right;">${this.formatMs(c.ms)}</td>
                </tr>
            `;
        }).join('') || `<tr><td colspan="4" class="empty-state">No artisan commands recorded. Enable SPOKE_RECORD_COMMANDS=true (noisy; schedule:run is ignored).</td></tr>`;

        document.getElementById('tab-content').innerHTML = `
            <div class="toolbar">
                <div class="toolbar-group">
                    <button class="pill-tab" onclick="SpokeApp.setSchedulerSubTab('tasks')">Tasks</button>
                    <button class="pill-tab active" onclick="SpokeApp.setSchedulerSubTab('commands')">Commands</button>
                    <select class="select" onchange="SpokeApp.setCommandsDate(this.value)">${datesOptions}</select>
                    <input type="text" class="input" placeholder="Search command..." value="${this.escapeHtml(s.commandsSearch)}" oninput="SpokeApp.searchCommands(this.value)" style="width:220px;">
                </div>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:170px;">Time</th>
                            <th>Command</th>
                            <th style="width:90px;">Exit</th>
                            <th style="width:90px; text-align:right;">Runtime</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
                <div class="pagination">
                    <span>Page ${data.meta.page} (${data.meta.total} total commands)</span>
                    <div style="display:flex; gap:0.5rem;">
                        <button class="btn btn-sm" ${data.meta.page <= 1 ? 'disabled' : ''} onclick="SpokeApp.setCommandsPage(${data.meta.page - 1})">Previous</button>
                        <button class="btn btn-sm" ${(data.meta.page * data.meta.per_page) >= data.meta.total ? 'disabled' : ''} onclick="SpokeApp.setCommandsPage(${data.meta.page + 1})">Next</button>
                    </div>
                </div>
            </div>
        `;
    },

    setSchedulerSubTab(tab) {
        this.state.scheduler.activeSubTab = tab;
        this.loadScheduler();
    },
    setSchedulerDate(d) { this.state.scheduler.date = d; this.state.scheduler.page = 1; this.loadScheduler(); },
    setSchedulerPage(p) { this.state.scheduler.page = p; this.loadScheduler(); },
    searchScheduler(t) {
        clearTimeout(this._schedSearchTimer);
        this._schedSearchTimer = setTimeout(() => {
            this.state.scheduler.search = t;
            this.state.scheduler.page = 1;
            this.loadScheduler();
        }, 300);
    },
    setCommandsDate(d) { this.state.scheduler.commandsDate = d; this.state.scheduler.commandsPage = 1; this.loadScheduler(); },
    setCommandsPage(p) { this.state.scheduler.commandsPage = p; this.loadScheduler(); },
    searchCommands(t) {
        clearTimeout(this._cmdSearchTimer);
        this._cmdSearchTimer = setTimeout(() => {
            this.state.scheduler.commandsSearch = t;
            this.state.scheduler.commandsPage = 1;
            this.loadScheduler();
        }, 300);
    },

    async loadRedis() {
        if (this.state.redis.activeSubTab === 'commands') {
            await this.loadRedisCommands();
            return;
        }

        const [infoData, keysData] = await Promise.all([
            this.fetchJson('/redis'),
            this.debugTools
                ? this.fetchJson(`/redis/keys?connection=${encodeURIComponent(this.state.redis.connection)}&pattern=${encodeURIComponent(this.state.redis.pattern)}`)
                : Promise.resolve(null)
        ]);

        if (!infoData) return;

        if (!infoData.available) {
            document.getElementById('tab-content').innerHTML = `
                <div class="toolbar">
                    <div class="toolbar-group">
                        <button class="pill-tab active" onclick="SpokeApp.setRedisSubTab('explorer')">Explorer</button>
                        <button class="pill-tab" onclick="SpokeApp.setRedisSubTab('commands')">Commands</button>
                    </div>
                </div>
                <div class="card empty-state">
                    <h3>Redis is not available or not configured.</h3>
                    <p style="margin-top:0.5rem;">${this.escapeHtml(infoData.reason || '')}</p>
                </div>
            `;
            return;
        }

        const conns = infoData.connections || [];
        const connsRows = conns.map(c => `
            <tr>
                <td style="font-weight:600; font-family:var(--font-mono);">${c.name}</td>
                <td><span class="badge badge-muted">DB ${c.database !== null ? c.database : '-'}</span></td>
                <td style="font-family:var(--font-mono); font-weight:600; color:var(--accent-cyan);">${c.keys !== null ? c.keys : '-'} keys</td>
            </tr>
        `).join('');

        const availableConns = keysData?.connections || conns.map(c => c.name);
        const connSelectOptions = availableConns.map(c => `<option value="${c}" ${c === this.state.redis.connection ? 'selected' : ''}>${c}</option>`).join('');

        let explorerHtml = '';
        if (!this.debugTools) {
            explorerHtml = `
            <div class="card" style="margin-bottom:1.5rem;">
                <div class="card-header" style="margin-bottom:0.75rem;">
                    <span class="card-label" style="font-size:0.85rem; color:var(--text-main);">Redis Key Explorer</span>
                    <span class="badge badge-muted">APP_DEBUG</span>
                </div>
                <div class="empty-state">Key inspection requires APP_DEBUG=true. Recorded commands and INFO remain available.</div>
            </div>`;
        } else {
            const keysList = keysData?.keys || [];
            let keysRows = '';

            if (keysList.length === 0) {
                keysRows = `<tr><td colspan="4" class="empty-state">No keys found matching pattern <code>${this.escapeHtml(this.state.redis.pattern)}</code></td></tr>`;
            } else {
                keysRows = keysList.map(k => {
                    const ttlBadge = k.ttl === -1 ? '<span class="badge badge-muted">No expiry</span>' : (k.ttl === -2 ? '<span class="badge badge-danger">Expired</span>' : `<span class="badge badge-info">${k.ttl}s</span>`);
                    const typeBadge = `<span class="badge badge-purple">${k.type.toUpperCase()}</span>`;
                    return `
                        <tr style="cursor:pointer;" data-key="${this.escapeHtml(k.key)}" onclick="SpokeApp.inspectRedisKey(this.dataset.key)">
                            <td style="font-family:var(--font-mono); font-weight:600; color:var(--accent-cyan);">${this.escapeHtml(k.key)}</td>
                            <td style="width:100px;">${typeBadge}</td>
                            <td style="width:120px;">${ttlBadge}</td>
                            <td style="width:90px; text-align:right;">
                                <button class="btn btn-sm btn-primary">Inspect</button>
                            </td>
                        </tr>
                    `;
                }).join('');
            }

            explorerHtml = `
            <div class="card" style="margin-bottom:1.5rem;">
                <div class="card-header" style="margin-bottom:0.75rem;">
                    <span class="card-label" style="font-size:0.85rem; color:var(--text-main);">🔍 Redis Key Explorer (Reader)</span>
                    <span class="badge badge-muted">${keysData?.has_more ? (keysData.count + '+') : (keysData?.count || 0)} matched keys</span>
                </div>
                <div class="toolbar" style="margin-bottom:0.75rem;">
                    <div class="toolbar-group">
                        <select class="select" onchange="SpokeApp.setRedisConnection(this.value)">
                            ${connSelectOptions}
                        </select>
                        <input type="text" class="input" placeholder="Pattern (e.g. *, cache:*, *user*)" value="${this.escapeHtml(this.state.redis.pattern)}" oninput="SpokeApp.searchRedisKeys(this.value)" style="width:280px;">
                    </div>
                </div>

                <div class="table-container" style="max-height:400px; overflow-y:auto;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Key Name</th>
                                <th>Type</th>
                                <th>TTL</th>
                                <th style="text-align:right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>${keysRows}</tbody>
                    </table>
                </div>
            </div>`;
        }

        const html = `
            <div class="toolbar">
                <div class="toolbar-group">
                    <button class="pill-tab active" onclick="SpokeApp.setRedisSubTab('explorer')">Explorer</button>
                    <button class="pill-tab" onclick="SpokeApp.setRedisSubTab('commands')">Commands</button>
                </div>
            </div>

            <div class="grid-kpi">
                <div class="card">
                    <div class="card-header"><span class="card-label">Redis Memory</span><span class="badge badge-success">v${infoData.version}</span></div>
                    <div class="card-value">${infoData.used_memory_human || 'n/a'}</div>
                    <div class="card-sub"><span>Peak: ${infoData.used_memory_peak_human || 'n/a'}</span></div>
                </div>

                <div class="card">
                    <div class="card-header"><span class="card-label">Total Keys</span><span class="badge badge-info">${conns.length} connections</span></div>
                    <div class="card-value">${infoData.total_keys !== undefined ? infoData.total_keys : 'n/a'}</div>
                    <div class="card-sub"><span>Hit Rate: ${infoData.hit_rate_pct !== null ? infoData.hit_rate_pct + '%' : 'n/a'}</span></div>
                </div>

                <div class="card">
                    <div class="card-header"><span class="card-label">Connected Clients</span><span class="badge badge-muted">Uptime: ${infoData.uptime_days}d</span></div>
                    <div class="card-value">${infoData.connected_clients || 0}</div>
                    <div class="card-sub"><span>Processed: ${infoData.total_commands_processed || 0}</span></div>
                </div>
            </div>

            ${explorerHtml}

            <div class="table-container">
                <div style="padding:0.75rem 1rem; font-weight:600; border-bottom:1px solid var(--border); color:var(--text-muted); text-transform:uppercase; font-size:0.75rem;">
                    Database Connections & Key Counts
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Connection Name</th>
                            <th>Database (DB)</th>
                            <th>Keys (DBSIZE)</th>
                        </tr>
                    </thead>
                    <tbody>${connsRows}</tbody>
                </table>
            </div>
        `;
        document.getElementById('tab-content').innerHTML = html;
    },

    async loadRedisCommands() {
        const s = this.state.redis;
        const query = `?date=${encodeURIComponent(s.commandsDate)}&search=${encodeURIComponent(s.commandsSearch)}&page=${s.commandsPage}`;
        const data = await this.fetchJson('/redis/commands' + query);
        if (!data) return;

        const dates = data.meta.dates || [];
        const entries = data.data || [];
        let datesOptions = dates.map(d => `<option value="${d}" ${d === data.meta.date ? 'selected' : ''}>${d}</option>`).join('');
        let rowsHtml = '';

        if (entries.length === 0) {
            rowsHtml = `<tr><td colspan="5" class="empty-state">No recorded Redis commands for the selected date.</td></tr>`;
        } else {
            rowsHtml = entries.map(c => `
                <tr>
                    <td style="font-family:var(--font-mono); font-size:0.75rem; color:var(--text-dim); white-space:nowrap;">${(c.t || '').split(' ')[1] || c.t || ''}</td>
                    <td><span class="badge badge-purple">${this.escapeHtml(c.command || '')}</span></td>
                    <td style="font-family:var(--font-mono); word-break:break-all;">${this.escapeHtml((c.parameters || []).join(' '))}</td>
                    <td><span class="badge badge-muted">${this.formatMs(c.ms)}</span></td>
                    <td style="font-family:var(--font-mono); font-size:0.75rem; color:var(--text-dim);">${this.escapeHtml(c.uri || '-')}</td>
                </tr>
            `).join('');
        }

        const html = `
            <div class="toolbar">
                <div class="toolbar-group">
                    <button class="pill-tab" onclick="SpokeApp.setRedisSubTab('explorer')">Explorer</button>
                    <button class="pill-tab active" onclick="SpokeApp.setRedisSubTab('commands')">Commands</button>
                </div>
                <div class="toolbar-group">
                    <select class="select" onchange="SpokeApp.setRedisCommandsDate(this.value)">${datesOptions}</select>
                    <input type="text" class="input" placeholder="Search command or key..." value="${this.escapeHtml(s.commandsSearch)}" oninput="SpokeApp.searchRedisCommands(this.value)" style="width:240px;">
                </div>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:110px;">Time</th>
                            <th style="width:100px;">Command</th>
                            <th>Parameters</th>
                            <th style="width:100px;">Duration</th>
                            <th style="width:160px;">Caller URI</th>
                        </tr>
                    </thead>
                    <tbody>${rowsHtml}</tbody>
                </table>
                <div class="pagination">
                    <span>Page ${data.meta.page} (${data.meta.total} total commands)</span>
                    <div style="display:flex; gap:0.5rem;">
                        <button class="btn btn-sm" ${data.meta.page <= 1 ? 'disabled' : ''} onclick="SpokeApp.setRedisCommandsPage(${data.meta.page - 1})">Previous</button>
                        <button class="btn btn-sm" ${(data.meta.page * data.meta.per_page) >= data.meta.total ? 'disabled' : ''} onclick="SpokeApp.setRedisCommandsPage(${data.meta.page + 1})">Next</button>
                    </div>
                </div>
            </div>
        `;
        document.getElementById('tab-content').innerHTML = html;
    },

    setRedisSubTab(tab) {
        this.state.redis.activeSubTab = tab;
        this.loadRedis();
    },

    setRedisCommandsDate(d) {
        this.state.redis.commandsDate = d;
        this.state.redis.commandsPage = 1;
        this.loadRedisCommands();
    },

    setRedisCommandsPage(p) {
        this.state.redis.commandsPage = p;
        this.loadRedisCommands();
    },

    searchRedisCommands(t) {
        clearTimeout(this._redisCmdSearchTimer);
        this._redisCmdSearchTimer = setTimeout(() => {
            this.state.redis.commandsSearch = t;
            this.state.redis.commandsPage = 1;
            this.loadRedisCommands();
        }, 300);
    },

    setRedisConnection(conn) {
        this.state.redis.connection = conn;
        this.loadRedis();
    },

    searchRedisKeys(pattern) {
        clearTimeout(this._redisSearchTimer);
        this._redisSearchTimer = setTimeout(() => {
            this.state.redis.pattern = pattern || '*';
            this.loadRedis();
        }, 300);
    },

    async inspectRedisKey(key) {
        if (!this.debugTools) {
            this.showToast('Redis key inspection requires APP_DEBUG=true.');
            return;
        }
        const conn = this.state.redis.connection;
        const res = await this.fetchJson(`/redis/key?connection=${encodeURIComponent(conn)}&key=${encodeURIComponent(key)}`);
        if (!res || !res.ok) {
            this.showToast('Failed to load Redis key details: ' + (res?.error || ''));
            return;
        }

        const typeBadge = `<span class="badge badge-purple">${res.type.toUpperCase()}</span>`;
        const ttlBadge = res.ttl === -1 ? '<span class="badge badge-muted">No expiry</span>' : (res.ttl === -2 ? '<span class="badge badge-danger">Expired</span>' : `<span class="badge badge-info">TTL: ${res.ttl}s</span>`);

        const content = `
            <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:1rem; flex-wrap:wrap;">
                <span style="font-family:var(--font-mono); font-weight:600; color:var(--accent-cyan); font-size:0.95rem;">${this.escapeHtml(res.key)}</span>
                ${typeBadge}
                ${ttlBadge}
                <span class="badge badge-muted">Connection: ${this.escapeHtml(res.connection)}</span>
                ${res.truncated ? '<span class="badge badge-danger">Truncated</span>' : ''}
            </div>
            <div style="margin-bottom:0.4rem; font-weight:600; color:var(--text-muted);">Value / Payload:</div>
            <div class="code-block" style="color:#e2e8f0;">${this.escapeHtml(res.raw)}</div>
        `;

        this.openModal('Redis Key Details', content, res.raw);
    }
};

document.addEventListener('DOMContentLoaded', () => SpokeApp.init());
</script>
