<?php

namespace App\Http\Controllers;

use App\Http\Responses\LegacyResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class LegacyPilotPageController
{
    public function marketplace(Request $request): RedirectResponse|Response
    {
        $tab = strtolower(trim((string) $request->query('tab', 'dashboard')));

        if ($tab !== 'dashboard') {
            return LegacyResponse::unavailable('page');
        }

        return redirect()->route('pilot.marketplace', $this->query($request, [
            'store', 'store_id', 'tab', 'scope', 'period', 'date_start', 'date_end', 'finance_mode',
        ]), 303);
    }

    public function konveksi(Request $request): RedirectResponse|Response
    {
        $tab = strtolower(trim((string) $request->query('tab', 'dashboard')));

        if ($tab !== 'dashboard') {
            return LegacyResponse::unavailable('page');
        }

        return redirect()->route('pilot.konveksi', ['tab' => 'dashboard'], 303);
    }

    /**
     * @param  list<string>  $allowed
     * @return array<string, string>
     */
    private function query(Request $request, array $allowed): array
    {
        $query = [];
        foreach ($allowed as $key) {
            $value = $request->query($key);
            if (is_scalar($value) && trim((string) $value) !== '') {
                $query[$key] = (string) $value;
            }
        }

        $query['tab'] = 'dashboard';

        return $query;
    }
}
