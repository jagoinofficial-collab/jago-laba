<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

final class FoundationController
{
    public function __invoke(): Response
    {
        return Inertia::render('Foundation', [
            'title' => 'Jago Laba Laravel Foundation',
            'status' => 'read-only',
        ]);
    }
}
