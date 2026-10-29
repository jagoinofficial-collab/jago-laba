<?php
declare(strict_types=1);

final class KonveksiModule
{
    private static bool $booted = false;

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        $appRoot = (dirname(__DIR__, 3) . '/app');
        foreach ([
            '/Modules/Konveksi/Services/KonveksiDatabase.php',
            '/Modules/Konveksi/Repositories/KonveksiCollectionStore.php',
            '/Modules/Konveksi/Services/KonveksiOperationGate.php',
            '/Shared/Support/BackupService.php',
            '/Shared/Support/DocumentService.php',
            '/Modules/Konveksi/Services/InventoryService.php',
            '/Modules/Konveksi/Services/HppService.php',
            '/Modules/Konveksi/Services/PurchaseService.php',
            '/Modules/Konveksi/Services/SalesService.php',
            '/Modules/Konveksi/Services/HutangKonveksiService.php',
            '/Modules/Konveksi/Services/GeminiService.php',
            '/Modules/Konveksi/Controllers/RpcService.php',
        ] as $file) {
            require_once $appRoot . $file;
        }

        self::$booted = true;
    }

    public static function viewNames(): array
    {
        return [
            '01_Dashboard', '03_Master_Produk', '04_Master_Bahan', '05_HPP_Produksi',
            '06_Master_Stok', '07_Pembelian_Bahan', '08_Pembelian_Vendor', '09_Distribusi',
            '11_SPK_Potong', '12_SPK_Jahit', '13_Penjualan_Produksi', '14_Pegawai',
            '16_Supplier', '17_Keuangan', '18_Rekening', '19_Kasbon', '20_Piutang',
            '21_Hutang_Usaha', '22_Hutang_Konveksi', '23_Setting_Profil', '24_Setting_Pengguna',
            '25_Setting_Hak_Akses', '26_Setting_Backup',
        ];
    }
}
