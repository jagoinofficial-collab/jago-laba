<?php
declare(strict_types=1);

final class RpcRequest
{
    public function __construct(public readonly string $method, public readonly array $args)
    {
    }

    public static function fromGlobals(): self
    {
        $raw = file_get_contents('php://input');
        $request = json_decode($raw ?: '', true);
        if (!is_array($request)) {
            throw new RuntimeException('Request JSON tidak valid.');
        }

        $method = (string)($request['method'] ?? '');
        if ($method === '') {
            throw new RuntimeException('Nama RPC kosong.');
        }

        $args = $request['args'] ?? [];
        return new self($method, is_array($args) ? $args : []);
    }
}
