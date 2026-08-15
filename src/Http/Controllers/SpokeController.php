<?php

declare(strict_types=1);

namespace Konekt\Spoke\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;

class SpokeController extends Controller
{
    public function index(): View
    {
        return view('spoke::index', [
            'apiBase' => url(config('spoke.path') . '/api'),
        ]);
    }
}
