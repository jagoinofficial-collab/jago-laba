<?php
declare(strict_types=1);

final class JsonResponse
{
    public static function rpcSuccess(mixed $result): void
    {
        self::send(['ok' => true, 'result' => $result]);
    }

    public static function error(Throwable $error, int $status = 400): void
    {
        http_response_code($status);
        self::send(['ok' => false, 'error' => ['message' => $error->getMessage()]]);
    }

    public static function send(array $payload): void
    {
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
