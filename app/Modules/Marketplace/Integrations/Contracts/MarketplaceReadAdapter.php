<?php
declare(strict_types=1);
interface MarketplaceReadAdapter
{
    public function catalogPage(array $query,string $token): array;
    public function product(string $id,array $query,string $token): array;
    public function orderPage(array $query,string $token,array $filter): array;
    public function order(string $id,array $query,string $token): array;
}
