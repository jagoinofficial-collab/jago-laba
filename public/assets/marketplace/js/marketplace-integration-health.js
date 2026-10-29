(()=>{
 const host=document.getElementById('settings-integration');if(!host)return;
 const csrf=host.dataset.csrf;
 const icons={products:'box',prices:'tag',stock:'boxes-stacked',orders:'receipt',shipping:'file-pdf',returns:'rotate-left',finance:'wallet',withdrawals:'building-columns',ads:'bullhorn'};
 const create=(tag,cls,text)=>{const e=document.createElement(tag);if(cls)e.className=cls;if(text!==undefined)e.textContent=text;return e;};
 const icon=name=>{const e=create('i','fa-solid fa-'+name);e.setAttribute('aria-hidden','true');return e;};
 async function request(url,body){const response=await fetch(url,{method:body?'POST':'GET',credentials:'same-origin',headers:body?{'X-CSRF-Token':csrf}:{},...(body?{body:new URLSearchParams(body)}:{})});const result=await response.json();if(!response.ok||!result.success)throw Error(result.message||'Status belum dapat diperbarui.');return result.data;}
 const stores=[];
 for(const root of document.querySelectorAll('[data-integration-store]')){
  let busy=false,data;const id=root.dataset.integrationStore,grid=root.querySelector('[data-capability-grid]'),message=root.querySelector('[data-integration-message]');
  const say=text=>message.textContent=text;
  function paint(d){data=d;root.querySelector('[data-connection-label]').textContent=d.connection_label;root.querySelector('[data-connection-label]').className='ih-badge '+(d.connection_status==='verified'?'ih-available':'ih-reauthorize');root.querySelector('[data-environment-label]').textContent=d.environment_label;root.querySelector('[data-count-products]').textContent=d.counts.products;root.querySelector('[data-count-variants]').textContent=d.counts.variants;root.querySelector('[data-last-sync]').textContent=d.last_successful_sync_at||'Belum berhasil';
   const start=root.querySelector('[data-integration-sync]');if(start)start.disabled=busy||d.connection_status!=='verified';
   grid.replaceChildren();for(const resource of d.resources){const card=create('article','ih-capability');card.dataset.resource=resource.key;const heading=create('header');heading.append(icon(icons[resource.key]||'circle-info'),create('span','',resource.label));card.append(heading,create('span','ih-badge ih-'+resource.state,resource.state_label));
    if(resource.state!=='unsupported'){
     if(resource.key!=='prices'&&resource.key!=='stock'&&resource.key!=='shipping')card.append(create('p','',resource.sync_label+(resource.processed?' · '+Number(resource.processed).toLocaleString('id-ID')+' record diproses':'')));
     card.append(create('small','',resource.last_verified_at?'Akses berhasil: '+resource.last_verified_at+' WIB':'Belum ada bukti akses berhasil.'));
     if(resource.retry_at&&resource.retry_at*1000>Date.now())card.append(create('small','ih-retry','Coba lagi setelah '+new Date(resource.retry_at*1000).toLocaleTimeString('id-ID')+' · upaya '+resource.retry_attempt));
     if(resource.error_code)card.append(create('small','ih-error','Kode: '+resource.error_code));
     if(resource.retry_job_id&&d.can_manage&&d.connection_status==='verified'&&resource.key!=='prices'&&resource.key!=='stock'){
      const retry=create('button','','Coba lagi');retry.type='button';retry.disabled=busy;retry.onclick=async()=>{if(busy)return;busy=true;retry.disabled=true;say('Menjalankan kembali proses yang gagal…');try{paint(await request('api/marketplace-integration-status.php',{action:'retry',store_id:id,job_id:resource.retry_job_id}));say('Permintaan diterima. Pantau hasil proses di kartu data.');}catch(e){say(e.message);}finally{busy=false;refresh();}};card.append(retry);
     }
    }else card.append(create('p','','Belum ada konektor resmi yang berjalan untuk fitur ini.'));
    grid.append(card);
   }
  }
  async function refresh(){if(busy||document.hidden)return;const button=root.querySelector('[data-integration-refresh]');button.disabled=true;try{paint(await request('api/marketplace-integration-status.php?'+new URLSearchParams({store_id:id})));}catch(e){say(e.message+' Data terakhir tetap ditampilkan.');}finally{button.disabled=false;}}
  root.querySelector('[data-integration-refresh]').onclick=refresh;
  try{paint(JSON.parse(root.querySelector('[data-integration-initial]').textContent));}catch{say('Status akses sedang dimuat.');refresh();}
  root.querySelector('[data-integration-sync]')?.addEventListener('click',async()=>{
   if(busy)return;busy=true;const button=root.querySelector('[data-integration-sync]');button.disabled=true;
   const dialog=create('dialog','ih-dialog');dialog.setAttribute('aria-label','Konfirmasi sinkronisasi toko');const header=create('header'),title=create('h2','','Sinkronisasi '+data.platform),close=create('button','','×');close.type='button';close.setAttribute('aria-label','Tutup konfirmasi');header.append(title,close);const content=create('div');dialog.append(header,content);document.body.append(dialog);dialog.showModal();let submitting=false;
   const finish=()=>{if(submitting)return;dialog.close();dialog.remove();busy=false;button.disabled=false;};close.onclick=finish;dialog.addEventListener('cancel',e=>{e.preventDefault();finish();});
   try{const preview=await request('api/marketplace-sync.php?'+new URLSearchParams({action:'preview',store_id:id}));const selected=preview.stores[0];if(!selected)throw Error('Toko belum memiliki otorisasi yang valid.');content.append(create('p','','Katalog aktif akan diperbarui. Pesanan diambil sesuai penanda riwayat toko; HPP lokal tetap dipertahankan.'),create('p','ih-dialog-note',selected.store_name+' · '+new Date(selected.window.window_start*1000).toLocaleDateString('id-ID')+' – '+new Date(selected.window.window_end*1000).toLocaleDateString('id-ID')),create('p','','Jenis data yang berjalan mengikuti konektor dan izin toko. Jumlah perubahan baru diketahui setelah API diproses.'));
    const confirm=create('button','ih-button ih-button-primary','Mulai Sinkronisasi');confirm.type='button';content.append(confirm);confirm.onclick=async()=>{if(submitting)return;submitting=true;confirm.disabled=true;close.disabled=true;confirm.textContent='Memulai worker…';try{const state=await request('api/marketplace-sync.php',{action:'background',store_id:id});say(state.can_close?'Worker server berhasil dimulai. Halaman boleh ditutup; status akan diperbarui saat dibuka kembali.':'Job tercatat. Worker belum terkonfirmasi berjalan; periksa status sebelum menutup halaman.');submitting=false;finish();refresh();}catch(e){submitting=false;confirm.disabled=false;close.disabled=false;confirm.textContent='Coba mulai kembali';content.append(create('p','ih-error',e.message));}};
   }catch(e){content.append(create('p','ih-error',e.message));}
  });
  stores.push(refresh);
 }
 setInterval(()=>{if(!document.hidden)stores.forEach(refresh);},15000);
 document.addEventListener('visibilitychange',()=>{if(!document.hidden)stores.forEach(refresh);});
})();
