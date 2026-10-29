<?php /* Escaped API case details; shared by order rows. */ ?>
<details class="mp-detail-item" style="min-width:220px;max-width:460px;white-space:normal;margin-top:8px">
 <summary><strong><?= $oe($returnCase['type_label']) ?></strong> · <?= $oe($returnCase['status']) ?></summary>
 <p>ID kasus: <?= $oe($returnCase['id']) ?></p>
 <p>Status sumber: <?= $oe($returnCase['source_status']) ?></p>
 <p><?= $oe($returnCase['refund_status']) ?> · Nominal sumber: <?= $oe($returnCase['currency']??'Mata uang belum tersedia') ?> <?= $oe($returnCase['refund_amount']??'Belum tersedia') ?></p>
 <p>Alasan: <?= $oe($returnCase['reason']?:'Belum tersedia') ?></p>
 <p>Pengiriman balik: <?= $oe($returnCase['courier']?:'Belum tersedia') ?> · <?= $oe($returnCase['tracking']?:'Resi belum tersedia') ?></p>
 <?php foreach(['created'=>'Diajukan','updated'=>'Diperbarui'] as $field=>$label): if(is_numeric($returnCase[$field])&&(int)$returnCase[$field]>0): ?><p><?= $label ?>: <?= $oe(date('Y-m-d H:i:s',(int)$returnCase[$field])) ?> WIB</p><?php endif; endforeach; ?>
 <p>Arbitrase: <?= $oe($returnCase['arbitration']?:'Belum tersedia') ?></p>
 <p>Sumber: API marketplace · Disinkronkan <?= $oe($returnCase['synced_at']) ?></p>
 <small>Nominal kasus tidak dikurangi lagi dari Untuk Dibayar yang sudah mencakup refund. Stok dan HPP tidak dibalik otomatis.</small>
</details>
