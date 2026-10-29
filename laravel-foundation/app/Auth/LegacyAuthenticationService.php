<?php

namespace App\Auth;

use App\Policies\RolePolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class LegacyAuthenticationService
{
    public function attempt(Request $request, string $username, string $password): ?LegacyUser
    {
        if (! Auth::guard('web')->attempt(['username' => $username, 'password' => $password])) {
            return null;
        }

        $request->session()->forget(['workspace', 'store_id', 'workspace_filters']);
        $user = Auth::guard('web')->user();

        return $user instanceof LegacyUser ? $user : null;
    }

    /**
     * Keep the response fields consumed by the old Konveksi login client.
     *
     * @return array<string, mixed>
     */
    public function legacyResult(LegacyUser $user, Request $request): array
    {
        return [
            'status' => 'success',
            'role' => RolePolicy::normalize($user->getRole()) === RolePolicy::OWNER ? 'owner' : 'user',
            'token' => $request->session()->getId(),
            'nama' => $user->getDisplayName(),
            'name' => $user->getDisplayName(),
            'username' => $user->getUsername(),
            'auth_source' => $user->getSource(),
        ];
    }
}
