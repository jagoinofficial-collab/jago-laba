<?php
declare(strict_types=1);

final class MarketplaceIntegrationException extends RuntimeException
{
    public function __construct(string $message, public readonly string $errorCode = 'MARKETPLACE_API_ERROR', public readonly ?int $retryAfterSeconds = null)
    {
        parent::__construct($message);
    }
}
