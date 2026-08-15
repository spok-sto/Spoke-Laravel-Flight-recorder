<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Spoke — Laravel Dev Monitoring</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
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

        .topbar-product {
            color: var(--text-main);
            font-weight: 700;
        }

        .topbar-separator {
            color: var(--text-dim);
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
</head>
<body>
<div class="spoke-shell">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="brand-title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--accent-cyan)">
                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                </svg>
                <span>Spoke</span>
            </div>
            <span class="brand-badge">Dev</span>
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
                <span class="topbar-product">Spoke — Laravel Dev Monitoring</span>
                <span class="topbar-separator">/</span>
                <span id="page-title">Server & Metrics</span>
            </div>

            <div class="topbar-actions">
                <button class="btn btn-sm" id="capture-btn" onclick="SpokeApp.toggleCapture()"
                        title="Capture requests and outgoing HTTP">
                    <span id="capture-dot" style="width:8px; height:8px; border-radius:50%; background:var(--text-dim); display:inline-block;"></span>
                    <span id="capture-label">Capture</span>
                </button>

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

<script>
const SpokeApp = {
    apiBase: @json($apiBase),
    csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
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
        exceptions: { date: '', search: '', page: 1, activeSubTab: 'groups' },
        queries: { date: '', search: '', page: 1, activeSubTab: 'log', rankingSort: 'total_ms' },
        requests: { date: '', search: '', page: 1 },
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
        document.getElementById('modal-title').textContent = title;
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

    async loadServer() {
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
        }).join('') || `<tr><td colspan="5" class="empty-state">No exceptions recorded for this date.</td></tr>`;

        document.getElementById('tab-content').innerHTML = `
            <div class="toolbar">
                <div class="toolbar-group">
                    <button class="pill-tab active" onclick="SpokeApp.setExceptionSubTab('groups')">Grouped</button>
                    <button class="pill-tab" onclick="SpokeApp.setExceptionSubTab('log')">Log</button>
                    <select class="select" onchange="SpokeApp.setExceptionDate(this.value)">${datesOptions}</select>
                    <input type="text" class="input" placeholder="Search class, message, URI..." value="${this.escapeHtml(s.search)}" oninput="SpokeApp.searchExceptions(this.value)" style="width:260px;">
                </div>
            </div>
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
            rowsHtml = `<tr><td colspan="5" class="empty-state">No recorded SQL queries for the selected date.</td></tr>`;
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
                <button class="btn btn-sm btn-primary" onclick="SpokeApp.explainQuery(false)">EXPLAIN</button>
                <button class="btn btn-sm btn-danger" onclick="SpokeApp.explainQuery(true)">ANALYZE</button>
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

    async loadRequests() {
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
            rowsHtml = `<tr><td colspan="7" class="empty-state">No recorded HTTP requests for the selected date.</td></tr>`;
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
                        <td style="text-align:right;">${r.trace_id ? '<button class="btn btn-sm btn-primary">Flight Recorder</button>' : '-'}</td>
                    </tr>
                `;
            }).join('');
        }

        const html = `
            <div class="toolbar">
                <div class="toolbar-group">
                    <select class="select" onchange="SpokeApp.setRequestDate(this.value)">${datesOptions}</select>
                    <input type="text" class="input" placeholder="Search URI or status..." value="${this.escapeHtml(s.search)}" oninput="SpokeApp.searchRequests(this.value)" style="width:260px;">
                </div>
            </div>

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
                            <th style="width:130px; text-align:right;">Trace</th>
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
            rowsHtml = `<tr><td colspan="6" class="empty-state">No recorded outgoing HTTP calls for the selected date.</td></tr>`;
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
            rowsHtml = `<tr><td colspan="4" class="empty-state">No recorded emails for the selected date.</td></tr>`;
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
                    <tr>
                        <td style="font-family:var(--font-mono); font-size:0.75rem; color:var(--text-dim);">${this.escapeHtml(j.failed_at || 'n/a')}</td>
                        <td><span class="badge badge-muted">${this.escapeHtml(j.queue)}</span></td>
                        <td style="font-weight:600; font-family:var(--font-mono);">${this.escapeHtml(j.job)}</td>
                        <td><span class="code-snippet" style="color:var(--accent-red); cursor:pointer;" onclick="SpokeApp.showFailedJobDetail(${idx})">${this.escapeHtml((j.exception || '').substring(0, 120))}...</span></td>
                        <td style="text-align:right;">
                            <div style="display:flex; justify-content:flex-end; gap:0.4rem;">
                                <button class="btn btn-sm btn-primary" data-id="${this.escapeHtml(j.id)}" onclick="SpokeApp.retryJob(this.dataset.id)">Retry</button>
                                <button class="btn btn-sm btn-danger" data-id="${this.escapeHtml(j.id)}" onclick="SpokeApp.forgetJob(this.dataset.id)">✕</button>
                            </div>
                        </td>
                    </tr>
                `).join('');
            }
        } else {
            if (pending.jobs.length === 0) {
                jobsHtml = `<tr><td colspan="5" class="empty-state">Queue is empty (no pending jobs).</td></tr>`;
            } else {
                jobsHtml = pending.jobs.map(j => `
                    <tr>
                        <td style="font-family:var(--font-mono); font-size:0.75rem; color:var(--text-dim);">${this.escapeHtml(j.created_at || 'n/a')}</td>
                        <td><span class="badge badge-muted">${this.escapeHtml(j.queue)}</span></td>
                        <td style="font-weight:600; font-family:var(--font-mono);">${this.escapeHtml(j.job)}</td>
                        <td><span class="badge badge-info">${j.status && j.status !== 'pending' ? this.escapeHtml(j.status) : 'Attempt #' + this.escapeHtml(j.attempts)}</span></td>
                        <td style="color:var(--text-dim); font-size:0.75rem;">${this.escapeHtml(j.available_at || j.status || 'n/a')}</td>
                    </tr>
                `).join('');
            }
        }

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
        const rows = (data.data || []).map(j => {
            const ev = j.event || '';
            const badge = ev === 'failed' ? 'badge-danger' : (ev === 'processed' ? 'badge-success' : 'badge-purple');
            return `
                <tr>
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

    showFailedJobDetail(idx) {
        const j = this.currentFailedJobs[idx];
        if (!j) return;
        const content = `
            <div style="margin-bottom:0.75rem;">
                <span class="badge badge-danger">Failed</span>
                <span style="font-family:var(--font-mono); font-size:0.8rem; margin-left:0.5rem;">ID: ${this.escapeHtml(j.id)}</span>
            </div>
            <div style="margin-bottom:0.4rem; font-weight:600;">Job: ${this.escapeHtml(j.job)} (Queue: ${this.escapeHtml(j.queue)})</div>
            <div class="code-block" style="color:var(--accent-red);">${this.escapeHtml(j.exception)}</div>
        `;
        this.openModal('Failed Job Details', content, j.exception);
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
            this.fetchJson(`/redis/keys?connection=${encodeURIComponent(this.state.redis.connection)}&pattern=${encodeURIComponent(this.state.redis.pattern)}`)
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
            </div>

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
</body>
</html>
