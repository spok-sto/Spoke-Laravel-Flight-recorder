<?php

declare(strict_types=1);

namespace Konekt\Spoke\Support;

final class DebugTools
{
    public const DENIED_MESSAGE = 'This action requires APP_DEBUG=true.';

    public static function enabled(): bool
    {
        return (bool) config('app.debug');
    }
}
