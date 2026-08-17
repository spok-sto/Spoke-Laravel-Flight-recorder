<?php

declare(strict_types=1);

namespace Konekt\Spoke\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Konekt\Spoke\Support\DebugTools;
use Symfony\Component\HttpFoundation\Response;

class RequireSpokeDebugTools
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (DebugTools::enabled()) {
            return $next($request);
        }

        return response()->json([
            'ok' => false,
            'error' => DebugTools::DENIED_MESSAGE,
        ], 403);
    }
}
