(()=>{
 if(!document.getElementById('store-dashboard'))return;
 let loading=false,previous=null;
 document.addEventListener('click',e=>{if(e.target.closest('[data-sd-formula]'))document.getElementById('sd-formula')?.showModal();if(e.target.closest('[data-sd-close]'))document.getElementById('sd-formula')?.close();});
 document.addEventListener('change',e=>{if(e.target.matches('.sd-filters input[type=date]'))e.target.closest('form').querySelector('[name=period]').value='custom';});
 async function refresh(){
  const root=document.getElementById('store-dashboard');if(!root||loading||document.hidden||root.querySelector('dialog[open]')||document.activeElement?.closest('.sd-filters'))return;
  loading=true;root.setAttribute('aria-busy','true');
  try{const params=new URLSearchParams(location.search);params.set('store',root.dataset.store);const r=await fetch('api/marketplace-dashboard.php?'+params,{credentials:'same-origin',cache:'no-store'});if(!r.ok)throw Error('Data dashboard belum dapat diperbarui. Muat ulang untuk mencoba kembali.');const html=await r.text();const fresh=new DOMParser().parseFromString(html,'text/html').getElementById('store-dashboard');if(!fresh)throw Error('Dashboard belum dapat diperbarui.');const open=root.querySelector('.sd-detail')?.open;root.replaceWith(fresh);if(open)fresh.querySelector('.sd-detail').open=true;}
  catch(error){let note=root.querySelector('.sd-refresh-note');if(!note){note=document.createElement('p');note.className='sd-refresh-note';note.setAttribute('role','status');root.prepend(note);}note.textContent=error.message;}
  finally{loading=false;root.removeAttribute('aria-busy');}
 }
 window.addEventListener('jl-sync-status',e=>{
  const root=document.getElementById('store-dashboard');if(!root||e.detail.store_id!==root.dataset.store)return;
  const status=e.detail.data;const signature=JSON.stringify((status.jobs||[]).map(j=>[j.id,j.status,j.success_count]));
  if(previous===null){previous=signature;return;}if(signature===previous||status.pending)return;previous=signature;refresh();
 });
 setInterval(refresh,60000);document.addEventListener('visibilitychange',()=>{if(!document.hidden)refresh();});
})();
