# Spoke 1.2.0 Laravel Flight control

<p align="center">
  <strong>One request. One trace. No telemetry database.</strong>
</p>

<p align="center">
  See exactly what happened inside every Laravel request — from SQL and Redis
  to queues, external APIs and exceptions — without sending telemetry to a SaaS
  or filling your application database with monitoring data.
</p>

<p align="center">
  Flight Recorder · SQL Intelligence · N+1 Detection · Exceptions · HTTP · Queues · Mail · Redis · Server Health
</p>

<p align="center">
  <a href="https://github.com/spok-sto/spoke">
    <img src="https://img.shields.io/github/stars/spok-sto/spoke?style=flat-square&logo=github&label=Stars" alt="GitHub stars">
  </a>
  <img src="https://img.shields.io/badge/Laravel-9.52%E2%80%9313-FF2D20?style=flat-square&logo=laravel&logoColor=white" alt="Laravel 9.52 through 13">
  <img src="https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.1 or newer">
  <img src="https://img.shields.io/badge/Storage-JSONL-38BDF8?style=flat-square" alt="JSONL telemetry storage">
  <img src="https://img.shields.io/badge/Hosting-Self--hosted-10B981?style=flat-square" alt="Self-hosted">
  <a href="LICENSE.md">
    <img src="https://img.shields.io/badge/License-Custom-F59E0B?style=flat-square" alt="Custom license">
  </a>
</p>

<p align="center">
  <a href="docs/spoke.png">
    <img src="docs/spoke.png" alt="Spoke dashboard — health score, alerts, SQL regressions, queue failures, and server metrics" width="100%">
  </a>
</p>

---

## What's new in 1.2.0

- Job Details popup
- Mail HTML preview stays available in monitor mode (`APP_DEBUG=false`)
- N+1 detection ignores INSERT/UPDATE loops, caps persisted examples, and alerts on Health
- Capture, EXPLAIN and Redis inspect stay behind `APP_DEBUG=true`
- Requests → Trends: p95 per route across retained days, with sparkline and change indicator
- Server → History: optional server metrics sampling and daily rollups (off by default)

---

## Why Spoke?

Debugging a slow or failing Laravel request often means jumping between logs,
database queries, queue workers, Redis, external API calls and server metrics.

Spoke brings those signals together into one correlated request timeline.

Every recorded request gets a `trace_id`. Spoke connects SQL queries, Redis
commands, outgoing HTTP calls, jobs, exceptions, runtime and memory into a
single **Flight Recorder**.

Telemetry stays on your server and is stored in append-only daily JSONL files
instead of dedicated monitoring tables.

- One request → one correlated trace
- No dedicated telemetry database
- Monitoring data stays on your server

### When should I use Spoke?

Use Spoke when you need to:

- diagnose a slow production request
- understand why an endpoint is generating too many queries
- correlate an exception with SQL, Redis and external API activity
- inspect queue delays and failures
- monitor Laravel without adding dedicated telemetry tables
- keep observability data on infrastructure you control

Spoke may not be the right choice if you want:

- fully managed multi-server observability with no infrastructure to maintain
- long-term centralized metrics across hundreds of services
- a browser toolbar focused exclusively on local development

## The question Spoke is built to answer

> **What actually happened during this request?**

```text
POST /api/orders/812                         1.42 s

14:22:03.124  Request started
14:22:03.141  SQL      SELECT customers...     16 ms
14:22:03.178  SQL      SELECT orders...        31 ms
              ⚠ Possible N+1 × 27
14:22:03.412  Redis    GET customer:812         3 ms
14:22:03.488  HTTP     POST ERP API           641 ms
              ⚠ Slow external request
14:22:04.184  Queue    InvoiceJob dispatched
14:22:04.218  Request completed               1.42 s

Peak memory: 74 MB
```

Slow events are written immediately, so useful diagnostics survive even if the
request fails before Laravel finishes normally. `TraceContext` is request-scoped
and Octane-safe. Queue payload hooks carry `spoke_trace_id` so background jobs
can rejoin the original request.

## Features

| | Capability | What it solves |
|---|---|---|
| 🔗 | **Flight Recorder** | Understand one request end-to-end |
| 🐘 | **SQL Intelligence** | Find slow, repeated and regressed queries |
| 💥 | **Exception Center** | Group failures and trace them back to requests |
| 🌐 | **HTTP Monitoring** | Find slow or failed external API calls |
| ⚙️ | **Queue Jobs** | Inspect pending, failed and history jobs, including redacted payload |
| 🔴 | **Redis Tools** | Inspect Redis activity and keys safely |
| 📅 | **Scheduler** | See completed and failed scheduled tasks |
| 🖥️ | **Server Health** | Monitor PHP, DB, Redis, CPU, RAM and disk |
| 🔒 | **Capture Mode** | Temporarily inspect redacted payloads |

## Quick start

Requires PHP 8.1+ and Laravel 9.52–13.

```bash
composer config repositories.spoke vcs https://github.com/spok-sto/spoke
composer require konekt/spoke:^1.1
```

```env
SPOKE_ENABLED=true
SPOKE_AUTH_ENABLED=true
SPOKE_AUTH_USER=spoke-admin
SPOKE_AUTH_PASS=use-a-long-random-password
SPOKE_ALLOWED_IPS=127.0.0.1,::1
```

Open `/spoke`.

Spoke is disabled by default. Never expose the dashboard with default credentials
or an unrestricted IP allowlist.

Laravel discovers `SpokeServiceProvider` automatically.

```bash
php artisan vendor:publish --tag=spoke-config
```

## How Spoke compares

Laravel positions [Pulse](https://github.com/laravel/pulse) for aggregated
performance and usage insights, and [Telescope](https://github.com/laravel/telescope)
for detailed event inspection. [Nightwatch](https://nightwatch.laravel.com) is a
managed monitoring platform. Spoke is request-centric diagnostics that stay on
your server.

| | Spoke | Telescope | Pulse | Nightwatch |
|---|---|---|---|---|
| **Focus** | Request diagnostics | Event inspection | Aggregated metrics | Managed monitoring |
| **Self-hosted** | Yes | Yes | Yes | No |
| **Request correlation** | Core concept | Entry-based | Not the primary purpose | Yes |
| **Flight Recorder (one request, one timeline)** | Yes | Separate entries | No | Yes (hosted) |
| **SQL EXPLAIN from the dashboard** | Yes | No | No | No |
| **Route p95 trends without a metrics DB** | Yes | No | Pulse tables | Yes (hosted) |
| **Telemetry tables in the app DB** | No | Yes | Uses app storage | No |
| **Server history (CPU / RAM / disk)** | Yes | No | Yes (app DB) | Yes (hosted) |
| **Daily rollups without a metrics DB** | Yes | No | No | N/A |
| **Managed SaaS** | No | No | No | Yes |

Spoke and Telescope overlap in several areas, but they are optimized for
different workflows. Telescope provides broad Laravel event inspection. Spoke
focuses on correlated request diagnostics and self-hosted JSONL telemetry.

Nightwatch is a different category: a managed platform with hosted retention,
request/job correlation, logs and deployment monitoring. Choose it when you
want that operational model. Choose Spoke when telemetry must stay on
infrastructure you control.


## Find the SQL that is slowing Laravel down

**SQL Intelligence** records the queries that matter:

- slow-query threshold (default `50 ms`)
- SQL normalization and stable fingerprints
- ranking by count, total time, average and maximum
- regression detection against previous retained days
- PostgreSQL/MySQL `EXPLAIN`
- query health indicators for sequential scans, index use, rows and cost

### Possible N+1 detected

```text
SELECT * FROM orders WHERE customer_id = ?

Executions      126
Total time      482 ms
Route           GET /customers
```

Spoke groups repeated query shapes, attaches them to the request that produced
them, and surfaces the pattern in Flight Recorder.

### EXPLAIN

`EXPLAIN` is available for recorded SQL when `APP_DEBUG=true`. PostgreSQL
`EXPLAIN ANALYZE` is guarded and allowed only for `SELECT` / `WITH`.

**EXPLAIN ANALYZE is never executed automatically.**

## Trace what happens outside your application

Outgoing HTTP calls persist when they are slow or failed. Sensitive headers and
JSON/form keys are redacted before write. Successful incoming requests can be
sampled; errors and exceptions are always retained. Requests → Trends shows
p95 per route across retained days.

Queue Jobs covers pending, processed and failed work, including wait time and
runtime. Click a row to open Job Details with a redacted payload. Redis
recording uses safe `SCAN` inspection, never blocking `KEYS`. Mail HTML preview
works in monitor mode; Capture, EXPLAIN and Redis key inspect require
`APP_DEBUG=true`.

## Server history — optional, off by default

Server info is a live snapshot, so history only exists if something records it.
Two opt-in switches add that, and both are disabled unless you enable them:

```env
SPOKE_METRICS_ENABLED=true
SPOKE_ROLLUP_ENABLED=true
```

```php
$schedule->command('spoke:sample')->everyMinute();
$schedule->command('spoke:rollup')->dailyAt('00:10');
```

`spoke:sample` writes one compact line per sample to `metrics-*.jsonl` — CPU load,
memory, disk, DB connections and cache hit, Redis memory and hit rate, pending and
failed jobs. Server → History charts it over `1h`, `24h` and `7d`. Cumulative
counters are charted as deltas, and OPcache is sampled from the web process
(throttled to once a minute) because the CLI has its own OPcache.

`spoke:rollup` aggregates one day into `rollups/daily-YYYY-MM.jsonl` with its own
retention, so daily trends outlive the raw retention window. Re-running a date
replaces its row.

## Capture Mode — deep diagnostics when you need them

Turn on detailed redacted payload capture for a limited debugging window.
Requires `APP_DEBUG=true`. Spoke automatically switches it off.

- captures incoming request and outgoing HTTP bodies
- writes them to a separate `capture-*.jsonl` file
- keeps normal `requests-*` and `http-*` files small
- redacts passwords, tokens, API keys and configured secrets
- limits each body to 256 KB by default
- expires after 60 minutes
- shows captured bodies inside the related Flight Recorder

Capture is for short debugging sessions, not permanent full-body logging.

## Security by default

- Disabled by default
- IP allowlist
- HTTP Basic Auth
- Laravel Gate support
- CSRF protection
- Payload redaction
- Sandboxed mail previews
- Redis `SCAN` instead of `KEYS`

[Read the Security Guide](docs/security.md)

## Storage and performance

Spoke uses append-only daily files under `storage/logs/spoke/`:

```text
storage/logs/spoke/
├── requests-2026-08-15.jsonl
├── queries-2026-08-15.jsonl
├── exceptions-2026-08-15.jsonl
├── http-2026-08-15.jsonl
├── jobs-2026-08-15.jsonl
├── redis-2026-08-15.jsonl
├── scheduler-2026-08-15.jsonl
├── capture-2026-08-15.jsonl
├── metrics-2026-08-15.jsonl
├── rollups/
└── mails/
```

### Performance philosophy

Spoke is designed to stay out of the way of the host request:

- `LOCK_EX` append writes instead of database inserts
- tail-based readers with bounded scan and output sizes
- no full-file loading for large Laravel logs
- persist slow, error and N+1 events by default
- separate capture storage so normal files stay small
- seven-day retention by default

Recorders fail silently and never break the host request or job.

```bash
php artisan spoke:prune
php artisan spoke:prune --days=3
```

```php
$schedule->command('spoke:prune')->daily();
```

Metrics and rollups keep their own retention (`SPOKE_METRICS_RETENTION_DAYS`,
`SPOKE_ROLLUP_RETENTION_DAYS`) and are pruned by the same command.

Measured overhead numbers will be published here once a documented benchmark
exists. Until then, treat “lightweight” as a design goal, not a published result.

## Configuration

```env
SPOKE_ENABLED=true
SPOKE_AUTH_ENABLED=true
SPOKE_AUTH_USER=spoke-admin
SPOKE_AUTH_PASS=use-a-long-random-password
SPOKE_ALLOWED_IPS=127.0.0.1,::1
```

[Full configuration reference](docs/configuration.md)

## Production guidance

- strong credentials and a restrictive IP allowlist
- a host-defined `viewSpoke` Gate
- sampling and slow-only thresholds
- short retention
- Capture Mode only when `APP_DEBUG=true` and you are actively debugging

Supported databases: PostgreSQL and MySQL/MariaDB.  
Queue inspection: Laravel **database** and **Redis** transports.

| Laravel | PHP |
|---|---:|
| 9.52+ | 8.1+ |
| 10.x | 8.1+ |
| 11.x | 8.2+ |
| 12.x | 8.2+ |
| 13.x | 8.3+ |

## FAQ

### How does Spoke fit alongside Telescope, Pulse, Horizon and Nightwatch?

Use **Telescope** for official Laravel event inspection. Use **Pulse** for
aggregated self-hosted metrics. Use **Horizon** to operate Redis queues. Use
**Nightwatch** for managed production monitoring. Use **Spoke** when you want
one correlated request timeline and JSONL telemetry that never leaves your
server.

### Does Spoke require a database?

No telemetry database is required. Spoke may read your configured database and
queue infrastructure for diagnostics, but it does not create Spoke tables.

### Can Spoke be used in production?

Yes, with deliberate configuration: credentials, IP allowlist, host Gate,
sampling, short retention and temporary payload capture.

### Does Spoke record passwords or API tokens?

Spoke redacts configured sensitive headers and JSON/form keys before writing
payload data. Request bodies are off by default. Review `redact_keys` before
enabling body capture.

### How long does Spoke keep telemetry?

Seven days by default. Change `SPOKE_RETENTION_DAYS` or run
`php artisan spoke:prune --days=N`.

## Feedback

If you use Spoke, these answers shape the roadmap:

- Which trace detail saved you the most time?
- What is still difficult to diagnose?
- Where does Spoke create too much overhead or noise?

[Open an issue](https://github.com/spok-sto/spoke/issues)

## Support Spoke

If Spoke saves you debugging time, you can support its continued development
through a donation.

Donations help fund Laravel compatibility, performance work, security
improvements and new Flight Recorder capabilities.

<p align="center">
  <a href="https://paypal.me/spoke26">
    <img src="https://img.shields.io/badge/Support%20Spoke-Donate%20with%20PayPal-00457C?style=for-the-badge&logo=paypal&logoColor=white" alt="Donate with PayPal">
  </a>
</p>

## License

| Use | Terms |
|---|---|
| **Personal / educational / non-commercial** | Free |
| **Commercial / business use** | Requires a commercial license |
| **Commercial license** | Any donation currently grants a commercial license. Keep the payment receipt as proof. |

Commercial use means use in a company, agency, enterprise, client project, or
commercial product/service.

Questions about licensing? [Open an issue](https://github.com/spok-sto/spoke/issues).

See [LICENSE.md](LICENSE.md) for the complete terms.

## Development

Local path install for package work:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "./packages/konekt/spoke"
        }
    ]
}
```

```bash
composer require konekt/spoke:^1.1
```

---

<p align="center">
  Built for Laravel teams who want to understand every request<br>
  while keeping observability data under their control.
</p>

<p align="center">
  <a href="https://github.com/spok-sto/spoke">GitHub</a>
  ·
  <a href="https://github.com/spok-sto/spoke/issues">Feedback</a>
  ·
  <a href="https://paypal.me/spoke26">Donate</a>
</p>
