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
| `APP_DEBUG` | Laravel default | Invasive debug tools: Capture, EXPLAIN, Redis inspect, mail preview |

Recorder switches, redaction keys, reader limits, health thresholds, regression
settings, mail storage, and Redis value limits are configured in `config/spoke.php`.

## APP_DEBUG vs monitoring

Spoke recorders and the dashboard keep working when `APP_DEBUG=false`. That is
the production monitoring mode.

These actions require `APP_DEBUG=true` (backend enforced, not only hidden in the UI):

- Capture Mode (full request / HTTP bodies)
- SQL `EXPLAIN` / `EXPLAIN ANALYZE`
- Redis key listing and value inspect
- Mail HTML preview
- Reading `capture-*.jsonl` payloads

Queue retry/forget, logs, Flight Recorder, health, and recorded telemetry stay
available whenever Spoke itself is enabled and authorized.
