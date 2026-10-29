<?php

namespace App\Exceptions;

use RuntimeException;

final class MarketplaceOAuthException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
