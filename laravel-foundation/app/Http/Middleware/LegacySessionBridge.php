<?php

namespace App\Http\Middleware;

use App\Auth\LegacyUserProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class LegacySessionBridge
{
    public function handle(Request $request, Closure $next): Response
    {
        $cookie = (string) config('jago.legacy_session.cookie');
        $id = (string) $request->cookies->get($cookie, '');
        $identity = [];

        if ($id !== '' && preg_match('/^[A-Za-z0-9,-]{1,128}$/D', $id) && session_status() === PHP_SESSION_NONE) {
            $previousName = session_name();
            $previousPath = session_save_path();
            $savePath = config('jago.legacy_session.save_path');

            try {
                session_name($cookie);
                if (is_string($savePath) && $savePath !== '') {
                    session_save_path($savePath);
                }
                session_id($id);
                if (session_start(['read_and_close' => true])) {
                    foreach (['user_id', 'username', 'role', 'workspace', 'company_key', 'company_id', 'store_id'] as $key) {
                        if (isset($_SESSION[$key]) && is_scalar($_SESSION[$key])) {
                            $identity[$key] = (string) $_SESSION[$key];
                        }
                    }
                }
            } finally {
                $_SESSION = [];
                session_id('');
                session_name($previousName);
                session_save_path($previousPath);
            }
        }

        $request->attributes->set('legacy_identity', $identity);

        if ($identity !== [] && ! Auth::guard('web')->check()) {
            try {
                $user = app(LegacyUserProvider::class)->retrieveByLegacyIdentity($identity);

                if ($user !== null) {
                    Auth::guard('web')->setUser($user);
                }
            } catch (Throwable) {
                // An unavailable legacy table must not make the foundation
                // unusable for routes that do not need legacy authentication.
            }
        }

        return $next($request);
    }
}
