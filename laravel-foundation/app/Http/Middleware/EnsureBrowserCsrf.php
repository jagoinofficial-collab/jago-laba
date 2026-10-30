<?php

namespace App\Http\Middleware;

use App\Security\CsrfTokenValidator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureBrowserCsrf
{
    public function __construct(private readonly CsrfTokenValidator $csrf) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->csrf->valid($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Token CSRF tidak valid.',
                'code' => 'CSRF_INVALID',
            ], 419);
        }

        return $next($request);
    }
}
