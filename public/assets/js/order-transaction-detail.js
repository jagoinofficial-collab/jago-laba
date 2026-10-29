(() => {
    const money = value => value == null ? 'Belum tersedia' : 'Rp ' + Number(value).toLocaleString('id-ID');
    const element = (tag, text, cls = '') => { const node = document.createElement(tag); if (text != null) node.textContent = text; node.className = cls; return node; };
    const card = (label, value, note = '') => {
        const node = element('div', null, 'bg-white rounded-lg border border-gray-100 p-3 min-w-0');
        node.append(element('div', label, 'text-gray-500 text-[11px]'), element('div', value, 'font-semibold mt-1 break-words'));
        if (note) node.append(element('div', note, 'text-[10px] text-gray-400 mt-1'));
        return node;
    };
    function render(data,storeId,csrf) {
        const root = element('div', null, 'p-4 bg-gray-50 text-xs space-y-4');
        root.append(element('p', `No. Pesanan ${data.order_id} · ${data.status}`, 'font-bold'));
        root.append(element('p', `Resi: ${data.tracking_number || 'Belum tersedia'} · Pembayaran: ${data.is_cod === true ? 'COD' : data.payment_method || 'Belum tersedia'}${data.is_estimated === true ? ' · Estimasi Penghasilan' : ''}`, 'text-gray-500'));
        root.append(element('h3', 'Detail Produk', 'font-bold'));
        data.items.forEach(item => {
            const product = element('div', null, 'bg-white rounded-xl border border-gray-200 p-3');
            const header = element('div', null, 'flex gap-3');
            if (/^https?:\/\//i.test(item.image_url)) { const img = element('img'); img.src = item.image_url; img.alt = ''; img.className = 'w-12 h-12 object-cover rounded'; header.append(img); }
            const title = element('div'); title.append(element('p', item.name, 'font-semibold'), element('p', `Varian: ${item.variant || '—'} · SKU: ${item.sku || '—'}`, 'text-[11px] text-gray-400 mt-1')); header.append(title); product.append(header);
            const grid = element('div', null, 'grid grid-cols-2 lg:grid-cols-4 gap-3 mt-3');
            for (const [label, value] of [['Jumlah', item.quantity], ['Dikembalikan', item.returned_quantity ?? 'Belum tersedia'], ['Jumlah Aktual', item.actual_quantity ?? 'Belum tersedia'], ['Harga Satuan', money(item.unit_price)], ['Total Harga', money(item.total_price)], ['HPP Satuan', money(item.unit_cogs)], ['Total HPP', money(item.total_cogs)]]) grid.append(card(label, String(value)));
            product.append(grid); root.append(product);
        });
        if (!data.items.length) root.append(element('p', 'Rincian produk belum tersedia.', 'text-gray-400'));
        if(data.return_cases?.length){
            root.append(element('h3','Pengembalian Barang & Dana','font-bold'));
            data.return_cases.forEach(c=>{const box=element('div',null,'bg-white rounded-xl border p-3 space-y-2');
                box.append(element('strong',c.type_label+' · '+c.status),element('p','ID kasus: '+c.id+' · Status sumber: '+c.source_status),element('p','Refund: '+c.refund_status+' · Nominal sumber: '+(c.refund_amount===null?'Belum tersedia':(c.currency||'Mata uang belum tersedia')+' '+c.refund_amount)),element('p','Alasan: '+(c.reason||'Belum tersedia')),element('p','Pengiriman balik: '+(c.courier||'Belum tersedia')+' · '+(c.tracking||'Resi belum tersedia')),element('p','Pembaruan API: '+(c.updated?new Date(Number(c.updated)*1000).toLocaleString('id-ID'):'Belum tersedia')+' · Sinkron: '+c.synced_at));
                root.append(box);
            });
            root.append(element('p','Refund dari kasus tidak dikurangi lagi dari Untuk Dibayar yang sudah memuat penyesuaian. Stok dan HPP tidak dibalik otomatis.','text-amber-700'));
        }
        root.append(element('h3', 'Rincian Pendapatan', 'font-bold'));
        if(storeId&&data.platform==='TikTok Shop'){const refresh=element('button','Perbarui dari Finance API','rounded-lg border border-blue-200 bg-white px-3 py-2 text-xs font-bold text-blue-700');refresh.type='button';refresh.onclick=async()=>{refresh.disabled=true;refresh.textContent='Mengambil data resmi…';try{const response=await fetch('api/marketplace-order-finance.php',{method:'POST',credentials:'same-origin',headers:{'X-CSRF-Token':csrf},body:new URLSearchParams({store_id:storeId,order_id:data.order_id})});const result=await response.json();if(!response.ok||!result.success)throw Error((result.message||'Data belum tersedia.')+(result.error_code?' ['+result.error_code+']':''));if(result.synced){root.replaceWith(render(result.presentation,storeId,csrf));return;}refresh.textContent=result.message;}catch(error){refresh.textContent=error.message;}finally{refresh.disabled=false;}};root.append(refresh);}
        if(data.net_income===null)root.append(element('p','Rincian potongan belum tersedia dari API; tidak dianggap Rp0.','text-amber-700'));
        if(data.finance_schema){root.append(element('p',data.is_estimated?'Estimasi resmi marketplace · belum final hingga settlement.':'Data settlement resmi marketplace.','text-blue-700'));if(data.subtotal_before_discount!=null){const revenue=element('div',null,'grid grid-cols-2 lg:grid-cols-3 gap-2');revenue.append(card('Subtotal sebelum diskon',money(data.subtotal_before_discount)),card('Diskon Penjual',money(data.seller_discount)),card('Pendapatan setelah diskon',money(data.finance_revenue)));root.append(revenue);}}
        const fees = element('div', null, 'grid grid-cols-2 lg:grid-cols-4 gap-2');
        data.components.filter(component=>Number(component.amount)!==0).forEach(component => fees.append(card(component.label, money(component.amount), `${component.percent == null ? '' : component.percent + '%'}${component.effect < 0 ? ' · Mengurangi biaya' : ''}`)));
        root.append(fees);
        const totals = element('div', null, 'grid grid-cols-2 lg:grid-cols-6 gap-2 border-t border-gray-200 pt-3');
        for (const [label, value] of [['Total Jumlah', money(data.gross_amount)], ['Total Biaya', money(data.total_fees)], ['Untuk Dibayar', data.net_income===null?'Menunggu data keuangan':money(data.net_income)], ['Total Penyesuaian', data.adjustment_amount === null ? 'Belum tersedia' : Number(data.adjustment_amount) === 0 ? 'Tidak ada penyesuaian' : money(data.adjustment_amount)], ['Total HPP', money(data.total_cogs)], ['Laba Bersih', data.net_profit===null?(data.net_income===null?'Menunggu data keuangan':'HPP belum lengkap'):money(data.net_profit)]]) totals.append(card(label, value));
        root.append(totals);
        if (Math.abs(data.reconciliation_difference || 0) >= 1 || Math.abs(data.component_difference || 0) >= 1) root.append(element('p', `Rincian belum sepenuhnya cocok dengan total sumber. Selisih komponen: ${money(data.component_difference)}; selisih pendapatan: ${money(data.reconciliation_difference)}. Total sumber tetap ditampilkan.`, 'text-amber-700 bg-amber-50 p-3 rounded'));
        if(data.finance_updated_at)root.append(element('p','Pembaruan Finance API: '+data.finance_updated_at+' WIB','text-[11px] text-gray-400'));
        if (data.total_cogs === null){root.append(element('p', 'HPP belum lengkap; laba belum final. Atur HPP dengan tanggal berlaku yang mencakup tanggal pesanan.', 'text-amber-700'));if(storeId){const hpp=element('a','Atur HPP Produk →','inline-flex rounded-lg border px-3 py-2 text-xs font-bold text-orange-700');hpp.href='marketplace.php?'+new URLSearchParams({store:storeId,tab:'produk'});root.append(hpp);}}
        if (data.net_income === null) root.append(element('p', 'Menunggu data keuangan marketplace. Pendapatan dan laba belum dapat dipastikan.', 'text-amber-700'));
        if (data.source_updated_at) root.append(element('p', `Pembaruan sumber: ${data.source_updated_at}`, 'text-[11px] text-gray-400'));
        return root;
    }
    window.jlInspectOrder = async (orderId, storeId, csrf) => {
        const row = Array.from(document.querySelectorAll('[data-order-id]')).find(node => node.dataset.orderId === orderId);
        let host;
        if (row) {
            let detail = row.nextElementSibling;
            if (detail?.dataset.orderDetailFor === orderId) { detail.hidden = !detail.hidden; row.querySelectorAll('button[onclick*="inspectOrder"]').forEach(button => button.setAttribute('aria-expanded', String(!detail.hidden))); return; }
            detail = element('tr'); detail.dataset.orderDetailFor = orderId;
            host = element('td'); host.colSpan = 11; detail.append(host); row.after(detail);
            row.querySelectorAll('button[onclick*="inspectOrder"]').forEach(button => button.setAttribute('aria-expanded', 'true'));
        } else {
            host = document.getElementById('modOrderBody');
            document.getElementById('modOrderSn').textContent = orderId;
            document.getElementById('orderDetailModal').classList.remove('hidden');
        }
        host.replaceChildren(element('p', 'Memuat rincian transaksi…', 'p-6 text-gray-400'));
        try {
            const response = await fetch(`api/marketplace-ajax.php?action=get_order_detail&order_id=${encodeURIComponent(orderId)}&store_id=${encodeURIComponent(storeId)}`, {credentials: 'same-origin', headers: {'X-CSRF-Token': csrf}});
            const result = await response.json();
            if (!response.ok || !result.success || !result.data?.presentation) throw new Error(result.message || 'Rincian transaksi belum tersedia.');
            host.replaceChildren(render(result.data.presentation,storeId,csrf));
        } catch (error) { host.replaceChildren(element('p', error.message, 'p-4 text-red-600')); if (row) { const retry = element('button', 'Coba lagi', 'm-4 rounded border px-3 py-2'); retry.type = 'button'; retry.onclick = () => { row.nextElementSibling.remove(); window.jlInspectOrder(orderId, storeId, csrf); }; host.append(retry); } }
    };
})();
