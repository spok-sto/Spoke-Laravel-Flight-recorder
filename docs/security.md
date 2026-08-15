# Security Guide

Spoke is disabled by default. Enable it only after credentials, IP policy, and
host authorization are in place.

## Access order

Dashboard access is evaluated in this order:

1. **IP allowlist**
2. **HTTP Basic Authentication** (when enabled)
3. **Laravel `viewSpoke` Gate**

The host Gate always wins. A host application may define a stricter gate:

```php
use App\Models\User;
use Illuminate\Support\Facades\Gate;

Gate::define('viewSpoke', function (?User $user = null): bool {
    return $user?->role_name === 'superadmin';
});
```

Unauthorized IPs and denied Gate requests receive `404` to reduce route enumeration.

## Defaults

- `SPOKE_ENABLED=false`
- HTTP Basic Auth enabled when Spoke is turned on
- incoming request bodies off
- outgoing HTTP bodies truncated and redacted
- Capture Mode expires automatically
- sensitive headers and JSON/form keys are redacted before write

## Additional safeguards

- mutating dashboard endpoints use Laravel CSRF protection
- log and mail readers enforce path boundaries
- email HTML previews run in a sandbox with restrictive CSP
- Redis browsing uses `SCAN`, never blocking `KEYS`
- telemetry is stored outside the public web root
- recorders fail silently and never break the host request or background job

## Nginx + PHP-FPM

Forward the authorization header:

```nginx
fastcgi_param HTTP_AUTHORIZATION $http_authorization;
```

## Production checklist

- change `SPOKE_AUTH_USER` and `SPOKE_AUTH_PASS`
- restrict `SPOKE_ALLOWED_IPS`
- define a host `viewSpoke` Gate
- keep request-body recording off unless needed
- use Capture Mode only for short debugging windows
- review `redact_keys` for domain-specific secrets
- schedule `php artisan spoke:prune`
