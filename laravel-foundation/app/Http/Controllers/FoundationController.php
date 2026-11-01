<?php

namespace App\Http\Controllers;

use App\Auth\LegacyUser;
use App\Authorization\WorkspaceContext;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Auth;

final class FoundationController
{
    public function __invoke(WorkspaceContext $workspace): Response
    {
        $user = Auth::guard('web')->user();

        return Inertia::render('Foundation', [
            'title' => 'Jago Laba Laravel Foundation',
            'status' => 'read-only',
            'authenticated' => $user instanceof LegacyUser,
            'user' => $user instanceof LegacyUser ? $user->toArray() : null,
            'workspaces' => $workspace->allowed($user),
            'active_workspace' => $workspace->active($user),
        ]);
    }
}
