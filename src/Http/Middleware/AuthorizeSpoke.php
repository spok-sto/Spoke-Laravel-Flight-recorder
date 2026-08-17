<?php

declare(strict_types=1);

namespace Konekt\Spoke\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\IpUtils;

class AuthorizeSpoke
{
    public function handle(Request $request, Closure $next)
    {
        if (! $this->ipAllowed($request)) {
            abort(404);
        }

        if (config('spoke.auth.enabled') && ! $this->validCredentials($request)) {
            return response('Unauthorized.', 401, [
                'WWW-Authenticate' => 'Basic realm="Spoke", charset="UTF-8"',
            ]);
        }

        abort_unless(Gate::allows('viewSpoke'), 404);

        return $next($request);
    }

    private function ipAllowed(Request $request): bool
    {
        $allowed = $this->allowedCidrs();

        if ($allowed === []) {
            return true;
        }

        if (in_array('0.0.0.0/0', $allowed, true) || in_array('*', $allowed, true)) {
            return true;
        }

        $ip = (string) $request->ip();

        if ($ip === '') {
            return false;
        }

        try {
            return IpUtils::checkIp($ip, $allowed);
        } catch (\Throwable) {
            return false;
        }
    }

    private function allowedCidrs(): array
    {
        $raw = config('spoke.allowed_ips', '0.0.0.0/0');

        if (is_array($raw)) {
            $parts = $raw;
        } else {
            $parts = preg_split('/[\s,]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        return array_values(array_filter(array_map('trim', $parts), static function ($cidr) {
            return $cidr !== '';
        }));
    }

    private function validCredentials(Request $request): bool
    {
        $user = (string) $request->getUser();
        $pass = (string) $request->getPassword();

        if ($user === '' || $pass === '') {
            return false;
        }

        return hash_equals((string) config('spoke.auth.username'), $user)
            && hash_equals((string) config('spoke.auth.password'), $pass);
    }
}
