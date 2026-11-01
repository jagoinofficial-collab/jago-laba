<?php

namespace App\Marketplace;

use Illuminate\Database\DatabaseManager;

final class LegacyReadContext
{
    public function __construct(private readonly DatabaseManager $database) {}

    public function run(string $companyKey, callable $read): mixed
    {
        if (trim($companyKey) === '') {
            throw new \DomainException('Company context wajib tersedia.');
        }
        $previous = $_SESSION ?? [];
        $_SESSION = ['company_key' => $companyKey];

        try {
            return $read($this->database->connection()->getPdo());
        } finally {
            $_SESSION = $previous;
        }
    }
}
