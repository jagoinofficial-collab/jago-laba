<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FoundationController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\KonveksiPilotController;
use App\Http\Controllers\LegacyPilotPageController;
use App\Http\Controllers\LegacyRpcController;
use App\Http\Controllers\MarketplaceExpenseController;
use App\Http\Controllers\MarketplaceOAuthController;
use App\Http\Controllers\MarketplacePilotController;
use App\Http\Controllers\MarketplaceSyncController;
use App\Http\Controllers\SandboxMarketplaceOAuthController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Responses\LegacyResponse;
use Illuminate\Support\Facades\Route;

Route::get('/', FoundationController::class)->name('foundation');
Route::get('/foundation', FoundationController::class)->name('foundation.read');
Route::get('/api/health.php', HealthController::class)->name('legacy.health');

Route::middleware(['auth', 'company.context'])->group(function (): void {
    Route::get('/pilot/marketplace', MarketplacePilotController::class)
        ->middleware('workspace.context:marketplace')
        ->name('pilot.marketplace');
    Route::get('/pilot/konveksi', KonveksiPilotController::class)
        ->middleware('workspace.context:konveksi')
        ->name('pilot.konveksi');
    Route::post('/pilot/marketplace/expenses', MarketplaceExpenseController::class)
        ->middleware(['workspace.context:marketplace', 'role.action:expense.manage', 'store.context:store_id', 'browser.csrf'])
        ->name('pilot.marketplace.expenses');
    Route::post('/pilot/marketplace/sync', MarketplaceSyncController::class)
        ->middleware(['workspace.context:marketplace', 'role.action:sync.trigger', 'store.context:store_id', 'browser.csrf'])
        ->name('pilot.marketplace.sync');
    Route::get('/pilot/marketplace/oauth/status', MarketplaceOAuthController::class)
        ->defaults('oauth_action', 'status')
        ->middleware('workspace.context:marketplace')
        ->name('pilot.marketplace.oauth.status');
    Route::post('/pilot/marketplace/oauth/start', MarketplaceOAuthController::class)
        ->defaults('oauth_action', 'start')
        ->middleware(['workspace.context:marketplace', 'browser.csrf'])
        ->name('pilot.marketplace.oauth.start');
    Route::get('/pilot/marketplace/oauth/callback', MarketplaceOAuthController::class)
        ->defaults('oauth_action', 'callback')
        ->middleware('workspace.context:marketplace')
        ->name('pilot.marketplace.oauth.callback');
    Route::get('/pilot/marketplace/oauth/pending', MarketplaceOAuthController::class)
        ->defaults('oauth_action', 'pending')
        ->middleware('workspace.context:marketplace')
        ->name('pilot.marketplace.oauth.pending');
    Route::post('/pilot/marketplace/oauth/select', MarketplaceOAuthController::class)
        ->defaults('oauth_action', 'select')
        ->middleware(['workspace.context:marketplace', 'browser.csrf'])
        ->name('pilot.marketplace.oauth.select');
    Route::post('/pilot/marketplace/oauth/refresh', MarketplaceOAuthController::class)
        ->defaults('oauth_action', 'refresh')
        ->middleware(['workspace.context:marketplace', 'browser.csrf'])
        ->name('pilot.marketplace.oauth.refresh');
});

Route::prefix('auth')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
    Route::middleware('auth')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('/workspace', [WorkspaceController::class, 'select'])->name('auth.workspace.select');
    });
});

// The two dashboard entry points now forward only their read-only pilot tab.
Route::middleware('legacy.session')->group(function (): void {
    Route::middleware(['auth', 'company.context', 'workspace.context:konveksi'])
        ->get('/index.php', [LegacyPilotPageController::class, 'konveksi'])
        ->name('legacy.konveksi.page');
    Route::middleware(['auth', 'company.context', 'workspace.context:marketplace'])
        ->get('/marketplace.php', [LegacyPilotPageController::class, 'marketplace'])
        ->name('legacy.marketplace.page');

    // Other URL reservations cannot perform legacy business operations yet.
    foreach (['workspace.php'] as $path) {
        Route::get('/'.$path, fn () => LegacyResponse::unavailable('page'));
    }

    // Login remains available under the old RPC contract. Other methods stay
    // reserved until their business adapter is migrated.
    Route::post('/api/rpc.php', LegacyRpcController::class)->name('legacy.rpc');
    Route::post('/api/marketplace-ajax.php', MarketplaceExpenseController::class)
        ->middleware(['legacy.marketplace.expense', 'auth', 'company.context', 'workspace.context:marketplace', 'role.action:expense.manage', 'store.context:store_id', 'browser.csrf'])
        ->name('legacy.marketplace.ajax');
    Route::post('/api/workspace.php', [WorkspaceController::class, 'select'])
        ->middleware('auth')
        ->name('legacy.workspace.select');
    Route::post('/api/logout.php', [AuthController::class, 'logout'])
        ->middleware('auth')
        ->name('legacy.logout');
    Route::match(['GET', 'POST'], '/api/marketplace-oauth.php', MarketplaceOAuthController::class)
        ->name('legacy.marketplace.oauth');
});

Route::get('/__sandbox/marketplace/oauth/authorize', SandboxMarketplaceOAuthController::class)
    ->name('sandbox.marketplace.oauth.authorize');
