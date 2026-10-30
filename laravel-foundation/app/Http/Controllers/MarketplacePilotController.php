<?php

namespace App\Http\Controllers;

use App\Http\Requests\MarketplacePilotRequest;
use App\Pilots\Marketplace\MarketplacePilotReadService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final class MarketplacePilotController
{
    public function __construct(private readonly MarketplacePilotReadService $pilot) {}

    public function __invoke(MarketplacePilotRequest $request): Response|RedirectResponse
    {
        $companyKey = (string) $request->attributes->get('company_key');

        try {
            $payload = $this->pilot->overview($companyKey, $request->storeId(), $request->filters());
        } catch (Throwable $exception) {
            if ($exception instanceof \DomainException && $exception->getCode() === MarketplacePilotReadService::NO_INTEGRATED_STORE) {
                return Inertia::render('Pilot/Marketplace', [
                    'read_only' => true,
                    'workspace' => 'marketplace',
                    'scope' => ['company_key' => $companyKey, 'store_id' => null],
                    'stores' => $this->pilot->stores($companyKey),
                    'filters' => $request->filters(),
                    'dashboard' => null,
                    'query' => ['tab' => 'dashboard', 'redirect' => 'marketplace.php?tab=pengaturan&peng_sub=tambah_toko'],
                    'message' => 'Belum ada store Marketplace yang terhubung.',
                ]);
            }

            if ($exception instanceof \DomainException && $exception->getCode() === MarketplacePilotReadService::STORE_SELECTION_REQUIRED) {
                return Inertia::render('Pilot/Marketplace', [
                    'read_only' => true,
                    'workspace' => 'marketplace',
                    'scope' => ['company_key' => $companyKey, 'store_id' => null],
                    'stores' => $this->pilot->stores($companyKey),
                    'filters' => $request->filters(),
                    'dashboard' => null,
                    'query' => ['tab' => 'dashboard'],
                    'message' => $exception->getMessage(),
                ]);
            }

            if ($exception instanceof \DomainException && $exception->getCode() === MarketplacePilotReadService::STORE_FORBIDDEN) {
                abort(403, $exception->getMessage());
            }

            throw $exception;
        }

        return Inertia::render('Pilot/Marketplace', $payload);
    }
}
