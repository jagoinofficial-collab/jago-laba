<link rel="stylesheet" href="assets/marketplace/css/marketplace-price.css">
<dialog id="marketplace-price-editor" aria-labelledby="price-editor-title" data-csrf="<?= htmlspecialchars(Workspace::csrf()) ?>">
 <header><div><span class="price-eyebrow">KATALOG MARKETPLACE</span><h2 id="price-editor-title">Edit Harga</h2><p data-price-title></p></div><button type="button" data-price-close aria-label="Tutup editor">×</button></header>
 <nav role="tablist" aria-label="Jenis perubahan harga"><button role="tab" data-price-tab="base" aria-selected="true">Harga Dasar</button><button role="tab" data-price-tab="discount" aria-selected="false">Diskon</button></nav>
 <main><p data-price-note></p><div data-price-message role="status" aria-live="polite"></div><button type="button" data-price-retry hidden>Coba lagi</button>
 <section data-price-promo hidden><label>Nama promo<input data-promo-title maxlength="100"></label><div class="price-period"><label>Mulai (waktu perangkat)<input type="datetime-local" data-promo-start></label><label>Selesai<input type="datetime-local" data-promo-end></label></div><div class="price-bulk"><label>Harga promo untuk varian dicentang (Rp)<input type="number" min="1" step="0.01" data-promo-bulk></label><button type="button" data-promo-apply>Terapkan</button><span data-promo-count></span></div><label class="price-confirm" hidden><input type="checkbox" data-promo-replace> Saya setuju mengganti promo lama pada varian yang dipilih.</label></section>
 <div class="price-table-wrap"><table><thead></thead><tbody></tbody></table></div>
 <p class="price-help">Bersih adalah estimasi harga setelah biaya marketplace berdasarkan transaksi SKU 30 hari terakhir, sebelum HPP. Tanda — berarti data biaya belum tersedia. Promo berlaku sesuai periode yang dikonfirmasi marketplace.</p></main>
 <footer><span data-price-summary>Hanya baris yang diubah akan dikirim.</span><button type="button" data-price-close>Batal</button><button type="button" data-price-save disabled>Simpan & Sinkronkan</button></footer>
</dialog>
<script src="assets/marketplace/js/marketplace-price.js" defer></script>
