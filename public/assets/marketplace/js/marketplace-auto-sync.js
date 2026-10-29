(()=>{
 const root=document.getElementById('settings-integration');if(!root)return;
 const paint=(box,result)=>{
  for(const [mode,state] of Object.entries(result.states||{})){
   const button=box.querySelector(`[data-auto-mode="${mode}"]`);
   if(!button)continue;
   const isEnabled=Boolean(state.enabled);
   button.setAttribute('aria-checked',String(isEnabled));
   button.className='relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-40 disabled:cursor-not-allowed '+(isEnabled?'bg-emerald-500':'bg-slate-300');
   const thumb=button.querySelector('span');
   if(thumb){
    thumb.className='pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-md ring-0 transition duration-200 ease-in-out '+(isEnabled?'translate-x-5':'translate-x-0');
   }
   const textLabel=button.parentElement?.querySelector('span.text-\\[11px\\]');
   if(textLabel){
    textLabel.textContent=isEnabled?'Aktif':'Nonaktif';
    textLabel.className='text-[11px] font-bold '+(isEnabled?'text-emerald-700':'text-slate-400');
   }
   const inlineBadge=box.querySelector(`[data-auto-badge="${mode}"]`);
   if(inlineBadge){
    inlineBadge.textContent=isEnabled?'Aktif':'Nonaktif';
    inlineBadge.className='px-2 py-0.5 rounded-full text-[10px] font-bold border transition '+(isEnabled?'bg-emerald-50 text-emerald-700 border-emerald-200':'bg-slate-100 text-slate-500 border-slate-200');
   }
   const icon=button.closest('.flex')?.querySelector('.fa-arrows-rotate');
   if(icon){
    if(isEnabled)icon.classList.add('fa-spin','[animation-duration:8s]');
    else icon.classList.remove('fa-spin','[animation-duration:8s]');
   }
   const detail=box.querySelector(`[data-auto-detail="${mode}"]`);
   if(detail){
    detail.textContent=state.last_error_code?'Gagal: '+state.last_error_code:state.last_success_at?'Terakhir berhasil: '+state.last_success_at+' WIB':isEnabled?'Menunggu penarikan awal':'Belum ada penarikan berhasil';
   }
  }
  const sched=box.querySelector('[data-auto-scheduler]');
  if(sched){
   sched.textContent=result.scheduler_available?'AutoSync Aktif di Server':'Buka Aplikasi untuk AutoSync';
  }
 };
 document.querySelectorAll('[data-auto-store]').forEach(box=>{
  let busy=false;const notice=box.querySelector('[data-auto-notice]');
  box.querySelectorAll('[data-auto-mode]').forEach(button=>button.addEventListener('click',async()=>{
   if(busy)return;busy=true;button.disabled=true;button.setAttribute('aria-busy','true');
   const willEnable=button.getAttribute('aria-checked')!=='true';
   notice.textContent='Menyimpan pengaturan auto sync…';
   try{
    const response=await fetch('api/marketplace-auto-sync.php',{
     method:'POST',credentials:'same-origin',
     headers:{'X-CSRF-Token':root.dataset.csrf},
     body:new URLSearchParams({store_id:box.dataset.autoStore,mode:button.dataset.autoMode,enabled:willEnable?'1':'0'})
    });
    const result=await response.json();
    if(!response.ok||!result.success)throw Error(result.message||'Pengaturan belum tersimpan.');
    paint(box,result);
    if(button.dataset.autoMode==='new'){
     notice.textContent=willEnable?'Realtime Transaksi Baru AKTIF: transaksi baru ditarik otomatis dari server setiap 1 menit.':'Realtime Transaksi Baru NONAKTIF: penarikan transaksi baru otomatis dihentikan.';
    }else{
     notice.textContent=willEnable?'Sinkronisasi otomatis aktif. Berjalan di server dan saat tab Jago Laba dibuka.':'Pengaturan dinonaktifkan; data yang sudah tersimpan tetap utuh.';
    }
   }catch(error){
    notice.textContent=error.message;
   }finally{
    busy=false;button.disabled=false;button.removeAttribute('aria-busy');
   }
  }));
  setInterval(async()=>{
   if(busy||document.hidden)return;
   try{
    const response=await fetch('api/marketplace-auto-sync.php?'+new URLSearchParams({store_id:box.dataset.autoStore}),{credentials:'same-origin'});
    const result=await response.json();
    if(result.success)paint(box,result);
   }catch{}
  },15000);
 });
})();
