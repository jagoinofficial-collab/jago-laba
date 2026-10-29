(()=>{
 let busy=false;
 window.syncStoreCatalog=async(store)=>{
  if(busy)return;busy=true;const button=document.querySelector('[data-sync-catalog]');const notice=document.getElementById('catalog-sync-notice');button.disabled=true;
  const request=async(action)=>{const r=await fetch('api/marketplace-sync.php',{method:'POST',credentials:'same-origin',headers:{'X-CSRF-Token':button.dataset.csrf},body:new URLSearchParams({action,store_id:store})});const result=await r.json();if(!r.ok||!result.success)throw Error(result.message||'Katalog belum dapat disinkronkan.');return result.data;};
  try{await request('catalog');notice.textContent='Menarik produk dan varian dari API toko. Proses berjalan di latar belakang; halaman boleh ditutup.';button.textContent='Menarik produk…';
   const poll=setInterval(async()=>{try{const data=await request('catalog_status');const products=data.jobs.filter(j=>j.job_type==='products');if(products.length&&products.every(j=>j.status==='succeeded')){clearInterval(poll);location.reload();return;}const failed=products.find(j=>j.status==='failed');if(failed||['failed','stalled'].includes(data.worker?.status)){clearInterval(poll);notice.textContent='Sinkronisasi belum berhasil: '+(failed?.last_error_code||data.worker?.error_code||'WORKER_STOPPED');busy=false;button.disabled=false;button.textContent='Coba sinkronisasi lagi';}}catch(error){clearInterval(poll);notice.textContent=error.message;busy=false;button.disabled=false;}},5000);
  }catch(error){notice.textContent=error.message;busy=false;button.disabled=false;}
 };
})();
