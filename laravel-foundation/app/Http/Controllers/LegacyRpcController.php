<?php

namespace App\Http\Controllers;

use App\Auth\LegacyAuthenticationService;
use App\Auth\LegacySessionStore;
use App\Auth\LegacyUser;
use App\Authorization\CompanyContext;
use App\Authorization\WorkspaceContext;
use App\Http\Responses\LegacyResponse;
use App\Pilots\Konveksi\KonveksiPilotReadService;
use App\Security\CsrfTokenValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class LegacyRpcController
{
    public function __construct(
        private readonly LegacyAuthenticationService $authentication,
        private readonly LegacySessionStore $legacySession,
        private readonly CsrfTokenValidator $csrf,
        private readonly CompanyContext $company,
        private readonly WorkspaceContext $workspace,
        private readonly KonveksiPilotReadService $konveksi,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->json()->all();
        $method = is_array($payload) ? trim((string) ($payload['method'] ?? '')) : '';
        $args = is_array($payload) && isset($payload['args']) && is_array($payload['args']) ? $payload['args'] : null;

        if ($method === '' || $args === null) {
            return $this->error('Payload RPC tidak valid.', 'RPC_INVALID_REQUEST', 422);
        }

        if ($method === 'prosesLoginBackend') {
            return $this->login($request, $args);
        }

        if (! Auth::guard('web')->check()) {
            return $this->error('Sesi login berakhir. Silakan login kembali.', 'AUTH_REQUIRED', 401);
        }

        if (! $this->csrf->valid($request)) {
            return $this->error('Token CSRF tidak valid.', 'CSRF_INVALID', 403);
        }

        if ($method === 'loadSemuaData') {
            return $this->loadKonveksiData($request);
        }

        return LegacyResponse::unavailable('rpc');
    }

    private function loadKonveksiData(Request $request): JsonResponse
    {
        $user = Auth::guard('web')->user();
        $activeWorkspace = $this->workspace->active($user);

        if ($activeWorkspace !== WorkspaceContext::KONVEKSI) {
            return $this->error(
                'Workspace Konveksi belum aktif.',
                $this->workspace->can($user, WorkspaceContext::KONVEKSI) ? 'WORKSPACE_NOT_ACTIVE' : 'WORKSPACE_FORBIDDEN',
                403,
            );
        }

        $companyKey = $this->company->key($user);
        if ($companyKey === null) {
            return $this->error('Company context tidak tersedia.', 'COMPANY_REQUIRED', 403);
        }

        return response()->json([
            'ok' => true,
            'result' => $this->konveksi->allKnown(),
        ])->withHeaders([
            'Cache-Control' => 'no-store, private',
            'X-Request-Id' => bin2hex(random_bytes(8)),
        ]);
    }

    /**
     * @param  array<int, mixed>  $args
     */
    private function login(Request $request, array $args): JsonResponse
    {
        $username = is_scalar($args[0] ?? null) ? trim((string) $args[0]) : '';
        $password = is_scalar($args[1] ?? null) ? (string) $args[1] : '';

        if ($username === '' || $password === '') {
            return response()->json([
                'ok' => true,
                'result' => [
                    'status' => 'error',
                    'code' => 'AUTH_REQUIRED',
                    'message' => 'Username dan password wajib diisi.',
                ],
            ]);
        }

        $user = $this->authentication->attempt($request, $username, $password);

        if (! $user instanceof LegacyUser) {
            return response()->json([
                'ok' => true,
                'result' => [
                    'status' => 'error',
                    'code' => 'AUTH_INVALID_CREDENTIALS',
                    'message' => 'Username atau password tidak valid.',
                ],
            ]);
        }

        $legacySessionId = $this->legacySession->syncUser($request, $user);
        $response = response()->json([
            'ok' => true,
            'result' => $this->authentication->legacyResult($user, $request),
        ]);

        if ($legacySessionId !== null) {
            $response->withCookie(cookie()->make(
                $this->legacySession->cookieName(),
                $legacySessionId,
                (int) config('session.lifetime', 120),
                config('session.path', '/'),
                config('session.domain'),
                (bool) config('session.secure', false),
                (bool) config('session.http_only', true),
                false,
                config('session.same_site', 'lax'),
            ));
        }

        return $response;
    }

    private function error(string $message, string $code, int $status): JsonResponse
    {
        $requestId = bin2hex(random_bytes(8));

        return response()->json([
            'ok' => false,
            'error' => [
                'message' => $message,
                'code' => $code,
                'request_id' => $requestId,
            ],
        ], $status)->header('X-Request-Id', $requestId);
    }
}
