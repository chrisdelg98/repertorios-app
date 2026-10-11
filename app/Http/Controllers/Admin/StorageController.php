<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\R2Reconciler;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The bucket as it really is, next to what the database says about it.
 *
 * Behind its own button rather than loaded with the page: walking the bucket
 * is several round trips to Cloudflare, and nobody should pay for that every
 * time they open the panel.
 */
class StorageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Storage', ['report' => null]);
    }

    public function scan(R2Reconciler $reconciler): Response
    {
        return Inertia::render('Admin/Storage', ['report' => $reconciler->run()]);
    }
}
