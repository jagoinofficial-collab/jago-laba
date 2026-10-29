<?php
$rangeStart = (string)($_GET['date_start'] ?? '');
$rangeEnd = (string)($_GET['date_end'] ?? '');
?>
<link rel="stylesheet" href="assets/shared/css/date-range.css">
<div class="jl-date-range xl:col-span-2" data-date-range>
    <span class="block text-[11px] font-bold text-slate-500 mb-1">Periode tanggal</span>
    <input type="hidden" name="date_start" value="<?= htmlspecialchars($rangeStart) ?>">
    <input type="hidden" name="date_end" value="<?= htmlspecialchars($rangeEnd) ?>">
    <button type="button" class="jl-range-trigger" aria-haspopup="dialog" aria-expanded="false"><span aria-hidden="true">▦</span> <span data-range-label>Pilih tanggal</span> <span aria-hidden="true">⌄</span></button>
    <div class="jl-range-popover" role="dialog" aria-label="Pilih rentang tanggal" hidden>
        <div class="jl-range-body">
            <div class="jl-range-presets" aria-label="Pilihan cepat">
                <?php foreach (['today'=>'Hari ini','yesterday'=>'Kemarin','week'=>'Minggu ini','month'=>'Bulan ini','7'=>'7 hari terakhir','14'=>'14 hari terakhir','30'=>'30 hari terakhir','lastmonth'=>'Bulan lalu'] as $key => $label): ?><button type="button" data-preset="<?= $key ?>"><?= $label ?></button><?php endforeach; ?>
            </div>
            <div class="jl-range-calendars" data-calendars></div>
        </div>
        <div class="jl-range-footer"><span data-range-summary role="status" aria-live="polite">Pilih tanggal mulai dan akhir</span><div><button type="button" data-range-clear>Hapus filter</button><span>Maks. 45 hari</span><button type="button" data-range-close aria-label="Tutup kalender">✕</button></div></div>
    </div>
</div>
<script src="assets/shared/js/date-range.js" defer></script>
