<?php
declare(strict_types=1);
final class MarketplaceStoreSelection
{
    /** Input must come from the company-authorized, integrated-store list. */
    public static function resolve(array $stores,string $requested,string $remembered):string
    {
        $ids=array_column($stores,'id');
        if($requested!==''&&!in_array($requested,$ids,true))throw new DomainException('Toko terintegrasi tidak ditemukan dalam perusahaan aktif.');
        return $requested!==''?$requested:(in_array($remembered,$ids,true)?$remembered:(count($ids)===1?$ids[0]:''));
    }
}
