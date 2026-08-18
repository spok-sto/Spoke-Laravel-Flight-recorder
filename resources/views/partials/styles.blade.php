    <style>
        :root {
            --bg-base: #06090e;
            --bg-surface: #0c111a;
            --bg-elevated: #121926;
            --bg-hover: #182234;
            --border: #1e293b;
            --border-subtle: #162032;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --text-dim: #64748b;
            --accent-cyan: #38bdf8;
            --accent-blue: #60a5fa;
            --accent-green: #34d399;
            --accent-emerald: #10b981;
            --accent-amber: #fbbf24;
            --accent-red: #f87171;
            --accent-purple: #a78bfa;
            --font-sans: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 14px;
            --topbar-height: 56px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-base);
            color: var(--text-main);
            font-family: var(--font-sans);
            font-size: 13px;
            line-height: 1.5;
            letter-spacing: -0.01em;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }

        a {
            color: var(--accent-cyan);
            text-decoration: none;
        }

        button, input, select {
            font-family: inherit;
            font-size: inherit;
            color: inherit;
        }

        .spoke-shell {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 240px;
            background: var(--bg-surface);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 40;
        }

        .sidebar-brand {
            height: var(--topbar-height);
            padding: 0 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border);
            flex: 0 0 var(--topbar-height);
        }

        .brand-title {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: #fff;
        }

        .brand-badge {
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            padding: 2px 6px;
            border-radius: 4px;
            background: rgba(56, 189, 248, 0.12);
            color: var(--accent-cyan);
            border: 1px solid rgba(56, 189, 248, 0.25);
            font-family: var(--font-mono);
        }

        .sidebar-nav {
            padding: 0.75rem 0.6rem;
            display: flex;
            flex-direction: column;
            gap: 2px;
            flex: 1;
            overflow-y: auto;
        }

        .nav-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.6rem 0.75rem;
            border-radius: var(--radius-sm);
            color: var(--text-muted);
            font-weight: 500;
            cursor: pointer;
            transition: all 0.15s ease;
            background: transparent;
            border: none;
            width: 100%;
            text-align: left;
        }

        .nav-item:hover {
            background: var(--bg-hover);
            color: var(--text-main);
        }

        .nav-item.active {
            background: rgba(56, 189, 248, 0.1);
            color: var(--accent-cyan);
            font-weight: 600;
            box-shadow: inset 2px 0 0 var(--accent-cyan);
        }

        .nav-label {
            display: flex;
            align-items: center;
            gap: 0.65rem;
        }

        .nav-icon {
            width: 16px;
            height: 16px;
            stroke-width: 2;
            opacity: 0.8;
        }

        .nav-count {
            font-size: 0.7rem;
            padding: 1px 6px;
            border-radius: 10px;
            background: var(--bg-elevated);
            color: var(--text-dim);
            font-family: var(--font-mono);
        }

        .nav-item.active .nav-count {
            background: rgba(56, 189, 248, 0.2);
            color: var(--accent-cyan);
        }

        .nav-count.danger {
            background: rgba(248, 113, 113, 0.15);
            color: var(--accent-red);
        }

        .sidebar-footer {
            padding: 0.85rem 1rem;
            border-top: 1px solid var(--border);
            font-size: 0.75rem;
            color: var(--text-dim);
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        .sidebar-footer a {
            color: var(--text-dim);
            text-decoration: none;
        }

        .sidebar-footer a:hover {
            color: var(--accent-cyan);
        }

        .main-wrapper {
            margin-left: 240px;
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .topbar {
            height: var(--topbar-height);
            min-height: var(--topbar-height);
            background: var(--bg-surface);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
            position: sticky;
            top: 0;
            z-index: 30;
            backdrop-filter: blur(8px);
        }

        .topbar-title {
            font-size: 0.95rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            min-width: 0;
            white-space: nowrap;
        }

        .brand-badge-monitor {
            background: rgba(16, 185, 129, 0.12);
            color: var(--accent-emerald);
            border-color: rgba(16, 185, 129, 0.25);
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-shrink: 0;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.45rem 0.85rem;
            border-radius: var(--radius-sm);
            font-weight: 500;
            cursor: pointer;
            transition: all 0.15s ease;
            border: 1px solid var(--border);
            background: var(--bg-elevated);
            color: var(--text-main);
        }

        .btn:hover {
            background: var(--bg-hover);
            border-color: #334155;
        }

        .btn-primary {
            background: var(--accent-cyan);
            border-color: var(--accent-cyan);
            color: #06090e;
            font-weight: 600;
        }

        .btn-primary:hover {
            background: #7dd3fc;
            border-color: #7dd3fc;
        }

        .btn-sm {
            padding: 0.3rem 0.6rem;
            font-size: 0.75rem;
        }

        .btn-danger {
            background: rgba(248, 113, 113, 0.12);
            color: var(--accent-red);
            border-color: rgba(248, 113, 113, 0.25);
        }

        .btn-danger:hover {
            background: rgba(248, 113, 113, 0.25);
        }

        .select, .input {
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 0.4rem 0.7rem;
            color: var(--text-main);
            outline: none;
            transition: border-color 0.15s ease;
        }

        .select:focus, .input:focus {
            border-color: var(--accent-cyan);
        }

        .content-area {
            padding: 1.5rem;
            flex: 1;
        }

        .grid-kpi {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .card {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 1rem 1.15rem;
            position: relative;
            overflow: hidden;
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .card-label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--text-muted);
        }

        .card-value {
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            font-family: var(--font-mono);
            display: flex;
            align-items: baseline;
            gap: 0.35rem;
        }

        .card-sub {
            font-size: 0.75rem;
            color: var(--text-dim);
            margin-top: 0.3rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .health-score {
            font-size: 2.4rem;
            font-weight: 700;
            font-family: var(--font-mono);
            letter-spacing: -0.04em;
        }

        .spark {
            display: flex;
            align-items: flex-end;
            gap: 2px;
            height: 44px;
        }

        .spark i {
            flex: 1;
            min-height: 2px;
            border-radius: 2px;
            background: var(--accent-red);
            opacity: 0.85;
        }

        .grid-hero {
            display: grid;
            grid-template-columns: minmax(220px, 0.8fr) minmax(320px, 1.2fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        @media (max-width: 900px) {
            .grid-hero { grid-template-columns: 1fr; }
        }

        .alert-list {
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
            max-height: 168px;
            overflow: auto;
            margin-top: 0.35rem;
        }

        .alert-item {
            display: flex;
            align-items: flex-start;
            gap: 0.6rem;
            padding: 0.55rem 0.7rem;
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 0.8rem;
            line-height: 1.4;
        }

        .alert-item.crit {
            border-color: rgba(248, 113, 113, 0.35);
            background: rgba(248, 113, 113, 0.06);
        }

        .alert-item.warn {
            border-color: rgba(251, 191, 36, 0.3);
            background: rgba(251, 191, 36, 0.05);
        }

        .alert-empty {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.85rem 0.2rem;
            color: var(--accent-green);
            font-size: 0.85rem;
            font-weight: 500;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            font-family: var(--font-mono);
            letter-spacing: -0.03em;
            line-height: 1.1;
        }

        .progress-bar {
            height: 4px;
            background: var(--bg-elevated);
            border-radius: 2px;
            margin-top: 0.6rem;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            border-radius: 2px;
            transition: width 0.3s ease;
        }

        .progress-fill.cyan { background: var(--accent-cyan); }
        .progress-fill.green { background: var(--accent-green); }
        .progress-fill.amber { background: var(--accent-amber); }
        .progress-fill.red { background: var(--accent-red); }

        .toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .toolbar-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .table-container {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            overflow: hidden;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        .table th {
            padding: 0.7rem 1rem;
            background: var(--bg-elevated);
            color: var(--text-muted);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            border-bottom: 1px solid var(--border);
        }

        .table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border-subtle);
            vertical-align: middle;
        }

        .table tr:last-child td {
            border-bottom: none;
        }

        .table tr:hover td {
            background: var(--bg-hover);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 2px 7px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
            font-family: var(--font-mono);
            letter-spacing: 0.02em;
        }

        .badge-error, .badge-danger, .badge-500 {
            background: rgba(248, 113, 113, 0.15);
            color: var(--accent-red);
            border: 1px solid rgba(248, 113, 113, 0.25);
        }

        .badge-warning, .badge-400 {
            background: rgba(251, 191, 36, 0.15);
            color: var(--accent-amber);
            border: 1px solid rgba(251, 191, 36, 0.25);
        }

        .badge-info, .badge-200 {
            background: rgba(56, 189, 248, 0.15);
            color: var(--accent-cyan);
            border: 1px solid rgba(56, 189, 248, 0.25);
        }

        .badge-success {
            background: rgba(52, 211, 153, 0.15);
            color: var(--accent-green);
            border: 1px solid rgba(52, 211, 153, 0.25);
        }

        .badge-purple {
            background: rgba(167, 139, 250, 0.15);
            color: var(--accent-purple);
            border: 1px solid rgba(167, 139, 250, 0.25);
        }

        .badge-muted {
            background: var(--bg-elevated);
            color: var(--text-muted);
            border: 1px solid var(--border);
        }

        .code-snippet {
            font-family: var(--font-mono);
            font-size: 0.8rem;
            color: var(--text-main);
            max-width: 520px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: block;
        }

        .pill-tab {
            padding: 0.3rem 0.65rem;
            border-radius: var(--radius-sm);
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            color: var(--text-muted);
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .pill-tab:hover {
            color: var(--text-main);
        }

        .pill-tab.active {
            background: var(--accent-cyan);
            color: #06090e;
            border-color: var(--accent-cyan);
            font-weight: 600;
        }

        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 100;
            padding: 1.5rem;
        }

        .modal-overlay.open {
            display: flex;
        }

        .modal-dialog {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 860px;
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 40px rgba(0,0,0,0.6);
            overflow: hidden;
        }

        .modal-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-body {
            padding: 1.25rem;
            overflow-y: auto;
            flex: 1;
        }

        .modal-footer {
            padding: 0.85rem 1.25rem;
            border-top: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.5rem;
        }

        .code-block {
            background: #04060a;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 1rem;
            font-family: var(--font-mono);
            font-size: 0.78rem;
            line-height: 1.6;
            color: #e2e8f0;
            white-space: pre-wrap;
            word-break: break-all;
            max-height: 500px;
            overflow-y: auto;
        }

        .pulse-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--accent-green);
            box-shadow: 0 0 8px var(--accent-green);
            display: inline-block;
        }

        .pulse-dot.danger {
            background: var(--accent-red);
            box-shadow: 0 0 8px var(--accent-red);
        }

        .empty-state {
            padding: 3rem 1.5rem;
            text-align: center;
            color: var(--text-dim);
        }

        .monitor-notice {
            margin: 0 0 1rem;
            padding: 0.7rem 0.9rem;
            border: 1px solid rgba(251, 191, 36, 0.35);
            background: rgba(251, 191, 36, 0.08);
            color: var(--accent-amber);
            border-radius: var(--radius-sm);
            font-size: 0.8rem;
            line-height: 1.45;
        }

        .capture-off-chip {
            font-size: 0.72rem;
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 4px;
            background: rgba(251, 191, 36, 0.12);
            color: var(--accent-amber);
            border: 1px solid rgba(251, 191, 36, 0.3);
            white-space: nowrap;
        }

        .pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 1rem;
            border-top: 1px solid var(--border);
            font-size: 0.75rem;
            color: var(--text-dim);
        }

        .toast {
            position: fixed;
            bottom: 1.5rem;
            right: 1.5rem;
            background: var(--bg-elevated);
            border: 1px solid var(--accent-cyan);
            color: #fff;
            padding: 0.6rem 1rem;
            border-radius: var(--radius-sm);
            font-size: 0.8rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            z-index: 200;
            display: none;
            animation: slideUp 0.2s ease;
        }

        .toast.show { display: block; }

        @keyframes slideUp {
            from { transform: translateY(10px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        @media (max-width: 900px) {
            .sidebar { width: 70px; }
            .sidebar-brand span, .nav-item span, .sidebar-footer { display: none; }
            .nav-item { justify-content: center; }
            .main-wrapper { margin-left: 70px; }
        }
    </style>
