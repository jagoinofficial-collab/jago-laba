(()=>{'use strict';
const dialog=document.getElementById('store-delete-dialog');if(!dialog)return;
const el=id=>document.getElementById(id),input=el('delete-confirm'),submit=el('delete-submit'),state=el('delete-state'),counts=el('delete-counts');
let id='',name='',busy=false,started=false,timer;
async function api(action,extra={}){
 const response=await fetch('api/marketplace-store-deletion.php',{method:'POST',credentials:'same-origin',headers:{'X-CSRF-Token':el('settings-integration').dataset.csrf},body:new URLSearchParams({action,store_id:id,...extra})});
 const data=await response.json();if(!response.ok||!data.success)throw Error(data.message||'Permintaan gagal.');return data.data;
}
function update(){submit.disabled=busy||(!started&&(!name||input.value.trim()!==name.trim()));}
function showJob(job){
 const n=Object.values(job.counts||{}).reduce((sum,value)=>sum+Number(value||0),0);
 if(job.status==='completed'){state.textContent=`Penghapusan selesai. ${n.toLocaleString('id-ID')} baris dibersihkan dan berkas terdaftar diverifikasi.`;submit.hidden=true;setTimeout(()=>location.reload(),1500);return;}
 if(job.status==='failed'){state.textContent=`Pembersihan belum selesai (${job.error_code||'kesalahan server'}). Coba lagi untuk melanjutkan.`;busy=false;submit.textContent='Coba lagi';update();return;}
 state.textContent=`Sedang menghapus: ${n.toLocaleString('id-ID')} baris telah dibersihkan. Halaman boleh ditutup.`;
 timer=setTimeout(poll,2000);
}
async function poll(){try{const job=await api('status');if(!job)throw Error('Status penghapusan belum tersedia.');showJob(job);}catch(error){busy=false;state.textContent=error.message;submit.textContent='Coba lagi';update();}}
function reset(storeId){
 clearTimeout(timer);id=storeId;name='';started=false;busy=true;input.value='';input.disabled=false;submit.hidden=false;submit.textContent='Hapus Integrasi & Data';el('delete-name').textContent='Memuat pratinjau…';el('delete-platform').textContent='';
 const logo=el('delete-logo-container');if(logo)logo.innerHTML='';
 counts.replaceChildren();state.textContent='Menghitung data milik toko…';update();dialog.showModal();
}
document.querySelectorAll('.delete-store').forEach(button=>button.addEventListener('click',async()=>{
 reset(button.dataset.storeId);
 try{
  const preview=await api('preview');
  name=preview.name;
  el('delete-name').textContent=name;
  el('delete-platform').textContent=`${preview.platform} · ID ${preview.external_id||preview.store_id} · ${preview.environment}`;
  const logo=el('delete-logo-container');
  if(logo){
   const isShopee=(preview.platform||'').toLowerCase().includes('shopee');
   const src=isShopee?'assets/marketplace/images/marketplaces/shopee.png':'assets/marketplace/images/marketplaces/tiktok.png';
   logo.innerHTML=`<img src="${src}" alt="${preview.platform}" class="w-10 h-10 object-contain rounded-lg border border-slate-200 p-1 bg-white" />`;
  }
  for(const [table,count] of Object.entries(preview.counts||{})){if(!count)continue;const li=document.createElement('li'),label=document.createElement('span'),value=document.createElement('strong');label.textContent=table.replace(/^marketplace_/,'').replaceAll('_',' ');value.textContent=Number(count).toLocaleString('id-ID');li.append(label,value);counts.append(li);}
  const files=document.createElement('li');files.textContent=`Berkas teridentifikasi: ${preview.file_count}`;counts.append(files);
  for(const type of preview.file_types||[]){const li=document.createElement('li');li.textContent=type;counts.append(li);}
  if(preview.job){started=true;input.value=name;input.disabled=true;busy=true;poll();}else{busy=false;state.textContent='Periksa dampak di atas, lalu ketik nama toko persis.';input.focus();}update();
 }catch(error){
  busy=false;el('delete-name').textContent='Gagal Memuat Pratinjau';state.textContent=error.message;update();
 }
}));
input.addEventListener('input',update);
submit.addEventListener('click',async()=>{if(busy)return;busy=true;update();state.textContent=started?'Melanjutkan pembersihan…':'Memulai penghapusan…';try{await api(started?'resume':'request',started?{}:{confirmation:input.value.trim()});started=true;input.disabled=true;poll();}catch(error){busy=false;state.textContent=error.message;submit.textContent=started?'Coba lagi':'Hapus Integrasi & Data';update();}});
document.querySelectorAll('.delete-resume').forEach(button=>button.addEventListener('click',async()=>{if(button.disabled)return;button.disabled=true;id=button.dataset.storeId;try{await api('resume');location.reload();}catch(error){state.textContent=error.message;button.disabled=false;}}));
dialog.querySelectorAll('[data-delete-close]').forEach(button=>button.addEventListener('click',()=>dialog.close()));dialog.addEventListener('close',()=>clearTimeout(timer));
})();
