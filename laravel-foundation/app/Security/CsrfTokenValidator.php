<?php

namespace App\Security;

use Illuminate\Http\Request;

final class CsrfTokenValidator
{
    public function valid(Request $request): bool
    {
        $expected = (string) $request->session()->token();
        $provided = $request->header('X-CSRF-TOKEN')
            ?? $request->header('X-XSRF-TOKEN')
            ?? $request->input('_token')
            ?? $request->input('csrf');

        return is_scalar($provided)
            && (string) $provided !== ''
            && hash_equals($expected, (string) $provided);
    }
}
