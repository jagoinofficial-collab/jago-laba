<?php

namespace App\Http\Controllers;

use App\Http\Requests\MarketplaceAnalyticsRequest;
use App\Pilots\Marketplace\MarketplaceAnalyticsReadService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

final class MarketplaceAnalyticsController
{
    public function __construct(private readonly MarketplaceAnalyticsReadService $analytics) {}

    public function __invoke(MarketplaceAnalyticsRequest $request): Response|RedirectResponse|SymfonyResponse
    {
        $companyKey = (string) $request->attributes->get('company_key');

        try {
            return Inertia::render('Pilot/MarketplaceAnalytics', $this->analytics->overview(
                $companyKey,
                $request->storeId(),
                $request->filters(),
            ));
        } catch (Throwable $exception) {
            if ($exception instanceof InvalidArgumentException) {
                return Inertia::render('Pilot/MarketplaceAnalytics', [
                    'read_only' => true,
                    'workspace' => 'marketplace',
                    'scope' => ['company_key' => $companyKey, 'store_id' => $request->storeId()],
                    'stores' => $this->analytics->stores($companyKey),
                    'active_store' => null,
                    'filters' => $request->filters(),
                    'analytics' => null,
                    'query' => ['tab' => 'analitik', 'read_only' => true, 'provider_call' => false, 'write' => false],
                    'message' => $exception->getMessage(),
                ])->toResponse($request)->setStatusCode(422);
            }

            if ($exception instanceof \DomainException && $exception->getCode() === MarketplaceAnalyticsReadService::NO_INTEGRATED_STORE) {
                return $this->emptyPage($companyKey, $request, 'Belum ada store Marketplace yang terhubung.', [
                    'tab' => 'analitik',
                    'redirect' => 'marketplace.php?tab=pengaturan&peng_sub=tambah_toko',
                ]);
            }

            if ($exception instanceof \DomainException && $exception->getCode() === MarketplaceAnalyticsReadService::STORE_SELECTION_REQUIRED) {
                return $this->emptyPage($companyKey, $request, $exception->getMessage(), ['tab' => 'analitik']);
            }

            if ($exception instanceof \DomainException && $exception->getCode() === MarketplaceAnalyticsReadService::STORE_FORBIDDEN) {
                abort(403, $exception->getMessage());
            }

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function emptyPage(string $companyKey, MarketplaceAnalyticsRequest $request, string $message, array $query): Response
    {
        return Inertia::render('Pilot/MarketplaceAnalytics', [
            'read_only' => true,
            'workspace' => 'marketplace',
            'scope' => ['company_key' => $companyKey, 'store_id' => null],
            'stores' => $this->analytics->stores($companyKey),
            'active_store' => null,
            'filters' => $request->filters(),
            'analytics' => null,
            'query' => $query + ['read_only' => true, 'provider_call' => false, 'write' => false],
            'message' => $message,
        ]);
    }
}
