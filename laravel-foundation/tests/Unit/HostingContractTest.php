<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\URL;
use Tests\TestCase;

final class HostingContractTest extends TestCase
{
    public function test_laravel_callback_is_public_root_relative_without_public_segment(): void
    {
        config([
            'app.url' => 'https://staging.example.test',
            'jago.oauth.return_path' => 'marketplace.php?scope=global&tab=integrasi',
        ]);
        URL::forceRootUrl('https://staging.example.test');
        URL::forceScheme('https');

        $callback = route('legacy.marketplace.oauth', ['action' => 'callback']);
        $laravelCallback = route('pilot.marketplace.oauth.callback');

        $this->assertSame('https://staging.example.test/api/marketplace-oauth.php?action=callback', $callback);
        $this->assertSame('https://staging.example.test/pilot/marketplace/oauth/callback', $laravelCallback);
        $this->assertStringNotContainsString('/public/', $callback);
        $this->assertStringNotContainsString('/public/', $laravelCallback);
    }

    public function test_legacy_callback_switch_preserves_xampp_compatibility_and_supports_public_document_root(): void
    {
        $path = dirname(__DIR__, 2).'/../config/integrations.php';
        $originalBase = getenv('JL_APP_BASE_URL');
        $originalDocumentRoot = getenv('JL_DOCUMENT_ROOT_PUBLIC');

        try {
            putenv('JL_APP_BASE_URL=https://staging.example.test');
            putenv('JL_DOCUMENT_ROOT_PUBLIC=true');
            $publicRoot = require $path;

            putenv('JL_DOCUMENT_ROOT_PUBLIC=false');
            $legacyRoot = require $path;
        } finally {
            $this->restoreEnvironment('JL_APP_BASE_URL', $originalBase);
            $this->restoreEnvironment('JL_DOCUMENT_ROOT_PUBLIC', $originalDocumentRoot);
        }

        $this->assertSame('https://staging.example.test/api/marketplace-oauth.php', $publicRoot['oauth']['callback_url']);
        $this->assertTrue($publicRoot['oauth']['document_root_public']);
        $this->assertSame('https://staging.example.test/public/api/marketplace-oauth.php', $legacyRoot['oauth']['callback_url']);
        $this->assertFalse($legacyRoot['oauth']['document_root_public']);
    }

    private function restoreEnvironment(string $name, string|false $value): void
    {
        if ($value === false) {
            putenv($name);
            return;
        }

        putenv($name.'='.$value);
    }
}
