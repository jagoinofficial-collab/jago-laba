<?php

namespace App\Authorization;

use App\Auth\LegacyUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

final class CompanyContext
{
    public function key(?Authenticatable $user): ?string
    {
        $userKey = $user instanceof LegacyUser ? $user->getCompanyKey() : null;

        if ($userKey !== null && $userKey !== '') {
            return $userKey;
        }

        $configured = trim((string) config('jago.company_key', ''));

        return $configured !== '' ? $configured : null;
    }

    public function matchesRequest(Request $request, ?Authenticatable $user): bool
    {
        $company = $this->requestValue($request);
        $context = $this->key($user);

        $request->attributes->set('company_key', $context);

        return $company === null || ($context !== null && hash_equals($context, $company));
    }

    private function requestValue(Request $request): ?string
    {
        $value = $request->route('company_key') ?? $request->input('company_key') ?? $request->header('X-Company-Key');

        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
