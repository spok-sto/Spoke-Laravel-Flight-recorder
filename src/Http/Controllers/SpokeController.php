<?php

declare(strict_types=1);

namespace Konekt\Spoke\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use Konekt\Spoke\Support\DebugTools;

class SpokeController extends Controller
{
    public function index(): View
    {
        $version = (string) config('spoke.version');

        return view('spoke::index', [
            'apiBase' => url(config('spoke.path') . '/api'),
            'spokeVersion' => $version,
            'spokeProduct' => 'Spoke ' . $version . ' Laravel Flight control',
            'debugTools' => DebugTools::enabled(),
        ]);
    }
}
