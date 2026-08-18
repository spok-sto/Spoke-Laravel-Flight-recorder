# Configuration Reference

Publish the config file:

```bash
php artisan vendor:publish --tag=spoke-config
```

The complete reference lives in [`config/spoke.php`](../config/spoke.php).

## Common environment variables

| Variable | Default | Purpose |
|---|---:|---|
| `SPOKE_ENABLED` | `false` | Master switch |
| `SPOKE_PATH` | `spoke` | Dashboard URI |
| `SPOKE_AUTH_ENABLED` | `true` | Require HTTP Basic Auth |
| `SPOKE_AUTH_USER` | `spoke` | Basic Auth username |
| `SPOKE_AUTH_PASS` | `spoke` | Basic Auth password — change before use |
| `SPOKE_ALLOWED_IPS` | `0.0.0.0/0` | Comma/space-separated IPs and CIDRs |
| `SPOKE_RETENTION_DAYS` | `7` | JSONL and mail-preview retention |
| `SPOKE_REQUESTS_SAMPLE_RATE` | `1.0` | Fraction of successful requests retained |
| `SPOKE_QUERIES_SLOW_ONLY_MS` | `50` | Slow SQL threshold |
| `SPOKE_REDIS_SLOW_ONLY_MS` | `5` | Slow Redis threshold |
| `SPOKE_HTTP_SLOW_ONLY_MS` | `200` | Slow outgoing HTTP threshold |
| `SPOKE_RECORD_REQUEST_BODY` | `false` | Store redacted failed-request bodies |
| `SPOKE_CAPTURE_TTL_MINUTES` | `60` | Automatic Capture expiry |
| `SPOKE_CAPTURE_MAX_BODY_BYTES` | `262144` | Capture body limit |
| `SPOKE_RECORD_COMMANDS` | `false` | Optional Artisan command recording |
| `SPOKE_RECORD_CLI` | `false` | Optional SQL recording for CLI/workers |
| `SPOKE_METRICS_ENABLED` | `false` | Server metrics sampling (`spoke:sample`) |
| `SPOKE_METRICS_RETENTION_DAYS` | `7` | Retention for `metrics-*.jsonl` |
| `SPOKE_ROLLUP_ENABLED` | `false` | Daily rollups (`spoke:rollup`) |
| `SPOKE_ROLLUP_RETENTION_DAYS` | `90` | Retention for `rollups/daily-*.jsonl` |
| `APP_DEBUG` | Laravel default | Invasive debug tools: Capture, EXPLAIN, Redis inspect |

Recorder switches, redaction keys, reader limits, health thresholds, regression
settings, mail storage, and Redis value limits are configured in `config/spoke.php`.

When a request is retained, Spoke also persists a capped Flight Recorder buffer
from that request (`persist_queries` 50, `persist_http` 20, `persist_redis` 20).
Fast outgoing HTTP is stored without bodies. Capture still owns full payloads.

## Server history (opt-in)

Server info is a live snapshot, so the Server → History tab stays empty until
sampling is enabled and scheduled:

```env
SPOKE_METRICS_ENABLED=true
SPOKE_ROLLUP_ENABLED=true
```

```php
$schedule->command('spoke:sample')->everyMinute();
$schedule->command('spoke:rollup')->dailyAt('00:10');
```

| Key | Default | Purpose |
|---|---:|---|
| `metrics.sample_database` | `true` | Sample DB connections and cache hit rate |
| `metrics.sample_redis` | `true` | Sample Redis memory, clients and hit counters |
| `metrics.sample_queue` | `true` | Sample pending jobs (database/redis drivers) and failed jobs |
| `metrics.sample_web_opcache` | `true` | Sample OPcache from the web process, once per minute |

One sample per minute is roughly 300 KB per day. Turn off individual sections when
a sample must stay cheaper — for example on a very large database.

If the scheduled entry stays in place after disabling the feature, both commands
exit successfully and write nothing.

Daily rollups aggregate requests, queries, exceptions, jobs and sampled metrics
into `rollups/daily-YYYY-MM.jsonl`. They live in a subdirectory with their own
retention, so they outlive the raw telemetry window. Re-running `spoke:rollup`
for a date replaces that row instead of appending a duplicate.

## APP_DEBUG vs monitoring

Spoke recorders and the dashboard keep working when `APP_DEBUG=false`. That is
the production monitoring mode.

These actions require `APP_DEBUG=true` (backend enforced, not only hidden in the UI):

- Capture Mode (full request / HTTP bodies)
- SQL `EXPLAIN` / `EXPLAIN ANALYZE`
- Redis key listing and value inspect
- Reading `capture-*.jsonl` payloads

Queue retry/forget, logs, Flight Recorder, mail preview, health, and recorded
telemetry stay available whenever Spoke itself is enabled and authorized.
