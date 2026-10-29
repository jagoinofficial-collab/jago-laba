<?php

namespace App\Auth;

use Illuminate\Http\Request;

final class LegacySessionStore
{
    public function syncUser(Request $request, LegacyUser $user): ?string
    {
        $cookie = $this->cookieName();
        $existingId = (string) $request->cookies->get($cookie, '');

        if (! $this->canOpen($existingId)) {
            return null;
        }

        return $this->withNativeSession($existingId, function () use ($user): string {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user->getSource() === 'auth_users_v2'
                ? 'AUTHV2-'.$user->getLegacyUserId()
                : $user->getLegacyUserId();
            $_SESSION['username'] = $user->getUsername();
            $_SESSION['role'] = $user->getRole();
            $_SESSION['name'] = $user->getDisplayName();
            unset($_SESSION['workspace'], $_SESSION['store_id'], $_SESSION['workspace_filters']);
            $_SESSION['csrf'] = bin2hex(random_bytes(32));

            return session_id();
        });
    }

    public function syncWorkspace(Request $request, string $workspace): void
    {
        $cookie = $this->cookieName();
        $existingId = (string) $request->cookies->get($cookie, '');

        if (! $this->canOpen($existingId)) {
            return;
        }

        $this->withNativeSession($existingId, function () use ($workspace): string {
            $_SESSION['workspace'] = $workspace;
            unset($_SESSION['store_id'], $_SESSION['workspace_filters']);

            return session_id();
        });
    }

    public function destroy(Request $request): void
    {
        $cookie = $this->cookieName();
        $existingId = (string) $request->cookies->get($cookie, '');

        if (! $this->canOpen($existingId)) {
            return;
        }

        $this->withNativeSession($existingId, function (): string {
            $_SESSION = [];
            session_destroy();

            return '';
        });
    }

    public function cookieName(): string
    {
        return (string) config('jago.legacy_session.cookie', 'JAGO_LABA_LOCAL_SESSION');
    }

    private function canOpen(string $id): bool
    {
        return session_status() === PHP_SESSION_NONE
            && ($id === '' || preg_match('/^[A-Za-z0-9,-]{1,128}$/D', $id) === 1);
    }

    /**
     * @template T of string
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function withNativeSession(string $id, callable $callback): string
    {
        $cookie = $this->cookieName();
        $previousName = session_name();
        $previousPath = session_save_path();
        $savePath = config('jago.legacy_session.save_path');

        try {
            session_name($cookie);
            if (is_string($savePath) && $savePath !== '') {
                session_save_path($savePath);
            }
            if ($id !== '') {
                session_id($id);
            }
            session_start();

            return $callback();
        } finally {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            session_id('');
            session_name($previousName);
            session_save_path($previousPath);
        }
    }
}
