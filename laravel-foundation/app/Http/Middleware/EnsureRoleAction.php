<?php

namespace App\Http\Middleware;

use App\Policies\RolePolicy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnsureRoleAction
{
    public function __construct(private readonly RolePolicy $policy) {}

    public function handle(Request $request, Closure $next, string $action): Response
    {
        if (! Auth::guard('web')->check()) {
            return response()->json(['message' => 'Autentikasi diperlukan.', 'code' => 'AUTH_REQUIRED'], 401);
        }

        if (! $this->policy->allows(Auth::guard('web')->user(), $action)) {
            return response()->json(['message' => 'Aksi tidak diizinkan.', 'code' => 'ROLE_FORBIDDEN'], 403);
        }

        $request->attributes->set('role_action', $action);

        return $next($request);
    }
}
