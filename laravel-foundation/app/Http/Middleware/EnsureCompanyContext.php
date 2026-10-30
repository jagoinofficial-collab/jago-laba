<?php

namespace App\Http\Middleware;

use App\Authorization\CompanyContext;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnsureCompanyContext
{
    public function __construct(private readonly CompanyContext $company) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        if (! $user instanceof Authenticatable) {
            return response()->json(['message' => 'Autentikasi diperlukan.', 'code' => 'AUTH_REQUIRED'], 401);
        }

        if (! $this->company->matchesRequest($request, $user)) {
            return response()->json(['message' => 'Company context tidak sesuai.', 'code' => 'COMPANY_FORBIDDEN'], 403);
        }

        return $next($request);
    }
}
