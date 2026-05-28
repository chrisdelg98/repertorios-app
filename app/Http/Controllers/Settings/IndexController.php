<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\BandAware;
use Inertia\Inertia;
use Inertia\Response;

class IndexController extends Controller
{
    use BandAware;

    public function __invoke(): Response
    {
        $this->requireAdmin();

        return Inertia::render('Settings/Index');
    }
}
