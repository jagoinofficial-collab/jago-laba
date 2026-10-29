(() => {
    const destination='marketplace.php?tab=pengaturan&peng_sub=tambah_toko';
    const notice=text=>{const el=document.getElementById('settings-integration-notice');el.className='rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs text-amber-900';el.textContent=text;};

        const root=document.getElementById('settings-integration');if(!root)return;const csrf=root.dataset.csrf;
        document.querySelectorAll('.restore-archived-store').forEach(button => button.addEventListener('click', async () => {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            try {
                const body = new URLSearchParams({action:'restore_store', store_id:button.dataset.storeId});
                const response = await fetch('api/marketplace-ajax.php', {method:'POST',credentials:'same-origin',headers:{'X-CSRF-Token':csrf},body});
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Toko belum dapat ditampilkan kembali.');
                location.assign('marketplace.php?tab=pengaturan&settings_store=' + encodeURIComponent(button.dataset.storeId));
            } catch (error) {
                alert(error.message || 'Gagal menampilkan toko. Coba lagi.');
                button.disabled = false;
                button.removeAttribute('aria-busy');
            }
        }));
        document.querySelectorAll('.partner-test-form[data-platform="Shopee"]').forEach(form=>{
            const mode=form.querySelector('[name="mode"]'),id=form.querySelector('[name="partner_id"]'),key=form.querySelector('[name="partner_key"]');
            mode.addEventListener('change',()=>{const changed=mode.value!==form.dataset.savedMode;id.value=changed?'':form.dataset.savedId;key.value='';key.required=changed||form.dataset.hasSecret!=='1';key.placeholder=key.required?'Masukkan Partner Key untuk lingkungan ini':'Tersimpan - kosongkan jika tidak diubah';const result=form.querySelector('.partner-test-result');result.className='partner-test-result rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs leading-5';result.textContent=changed?'Lingkungan berubah. Masukkan Partner ID dan Partner Key yang sesuai. Kredensial lingkungan sebelumnya tidak digunakan.':'Menggunakan lingkungan yang tersimpan.';});
        });
        document.querySelectorAll('.partner-test-form').forEach(form => form.addEventListener('submit', async event => {
            event.preventDefault();
            const button = form.querySelector('[type="submit"]');
            const result = form.querySelector('.partner-test-result');
            const badge = form.closest('article').querySelector('[data-test-badge]');
            button.disabled = true;
            button.textContent = 'Menghubungi API partner...';
            result.className = 'partner-test-result rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs leading-5 text-slate-700';
            result.textContent = 'Menyimpan pengaturan dan menguji API...';
            try {
                const response = await fetch('api/marketplace-partner-settings.php', {
                    method: 'POST', credentials: 'same-origin',
                    headers: {'Accept': 'application/json', 'X-CSRF-Token': csrf},
                    body: new URLSearchParams(new FormData(form)),
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok || !payload.success) throw new Error(payload.message || 'Pengaturan belum dapat disimpan.');
                const passed = !!payload.data.test.ok;
                if(form.dataset.platform==='Shopee'){form.dataset.savedMode=payload.data.status.mode;form.dataset.savedId=payload.data.status.partner_id;form.dataset.hasSecret=payload.data.status.has_secret?'1':'0';}
                result.className = 'partner-test-result rounded-xl border px-3 py-2.5 text-xs leading-5 ' + (passed ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-900');
                result.textContent = 'Kredensial tersimpan terenkripsi. ' + payload.data.test.message + (payload.data.test.error_code ? ' [Kode: ' + payload.data.test.error_code + ']' : '');
                badge.className = 'rounded-full px-3 py-1 text-[10px] font-extrabold ' + (passed ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800');
                badge.textContent = passed ? 'Uji berhasil' : 'Uji belum berhasil';
                form.querySelectorAll('input[type="password"]').forEach(input => { input.value = ''; if (input.name !== 'test_access_token') input.required = false; });
                await updateReadiness();
            } catch (error) {
                result.className = 'partner-test-result rounded-xl border border-rose-200 bg-rose-50 px-3 py-2.5 text-xs leading-5 text-rose-800';
                result.textContent = error.message;
            } finally {
                button.disabled = false;
                button.textContent = 'Simpan & uji API';
            }
        }));

    async function oauth(action,body){
        const read=action==='pending'||action==='status';
        const response=await fetch('api/marketplace-oauth.php'+(read?'?'+new URLSearchParams({action,...body}):''),{method:read?'GET':'POST',credentials:'same-origin',headers:{'X-CSRF-Token':csrf,...(!read?{'Content-Type':'application/x-www-form-urlencoded'}:{})},...(!read?{body:new URLSearchParams({action,...body})}:{})});
        const result=await response.json();if(!response.ok||!result.success)throw Error(result.message||'Koneksi belum dapat diproses.');return result.data;
    }
    async function updateReadiness(){try{const status=await oauth('status',{});document.querySelectorAll('[data-settings-connect]').forEach(button=>button.disabled=!status.platforms?.[button.dataset.settingsConnect]?.ready);document.querySelectorAll('[data-auth-ready]').forEach(el=>el.textContent=status.platforms?.[el.dataset.authReady]?.ready?'Konfigurasi siap untuk otorisasi toko.':'Lengkapi konfigurasi aplikasi terlebih dahulu.');}catch(error){notice('Kredensial tersimpan. Muat ulang halaman untuk memperbarui kesiapan otorisasi.');}}
    document.querySelectorAll('[data-settings-connect]').forEach(button=>button.addEventListener('click',async()=>{button.disabled=true;try{const config=await oauth('status',{});const callback=config.callback_urls?.[button.dataset.settingsConnect];if(callback&&new URL(callback).origin!==location.origin){location.assign(new URL('../'+destination,callback).href);return;}const result=await oauth('start',{platform:button.dataset.settingsConnect,return_path:destination});location.assign(result.authorization_url);}catch(error){button.disabled=false;notice(error.message);}}));
    document.querySelectorAll('[data-copy-callback]').forEach(button=>button.addEventListener('click',async()=>{try{await navigator.clipboard.writeText(button.dataset.copyCallback);button.textContent='URL tersalin';}catch(error){notice('Pilih dan salin URL callback secara manual.');}}));
    document.querySelectorAll('.global-logout-trigger').forEach(button=>button.addEventListener('click',async()=>{button.disabled=true;try{const response=await fetch('api/logout.php',{method:'POST',credentials:'same-origin',headers:{'X-CSRF-Token':csrf}});if(!response.ok)throw Error('Keluar belum berhasil.');location.assign('index.php');}catch(error){button.disabled=false;notice(error.message);}}));
    const dialog=document.getElementById('settings-connect-dialog');
    const content=document.getElementById('settings-dialog-content');
    const openDialog=(title)=>{document.getElementById('settings-dialog-title').textContent=title;content.replaceChildren();dialog.showModal();};
    document.querySelector('[data-close-settings-dialog]')?.addEventListener('click',()=>dialog.close());
    document.querySelectorAll('[data-ads-info]').forEach(button=>button.addEventListener('click',()=>{openDialog('Hubungkan TikTok Ads');const p=document.createElement('p');p.textContent='Konektor TikTok Marketing API belum tersedia. Koneksi ini memerlukan konfigurasi aplikasi Marketing API, OAuth terpisah, izin advertiser, dan pemasangan akun iklan ke proyek. Token TikTok Shop tidak dapat dipakai untuk alur ini.';const a=document.createElement('a');a.href='marketplace.php?tab=pengaturan&peng_sub=integrasi#tiktok-ads-config';a.className='inline-flex rounded-xl bg-blue-600 text-white px-4 py-3 text-xs font-bold';a.textContent='Lihat konfigurasi Integrasi API';content.append(p,a);}));
    document.querySelectorAll('[data-shopee-ads-connect]').forEach(trigger=>trigger.addEventListener('click',async()=>{
      openDialog('Hubungkan Shopee Ads');
      const description=document.createElement('p');description.textContent='Hubungkan akun seller yang memiliki toko dan data iklan. Anda akan diarahkan ke halaman otorisasi resmi Shopee. Izin yang ditampilkan mengikuti aplikasi yang terdaftar di Shopee Open Platform.';
      const status=document.createElement('p');status.className='rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs leading-5 text-amber-900';status.textContent='Konektor sinkronisasi Shopee Ads belum tersedia. Otorisasi ini tidak menjamin izin data iklan disetujui. Biaya iklan dan performa tidak akan ditandai tersinkron sebelum endpoint iklan berhasil diakses.';
      const action=document.createElement('button');action.type='button';action.className='rounded-xl bg-orange-600 text-white px-4 py-3 text-xs font-bold disabled:opacity-50';action.textContent='Memeriksa konfigurasi Shopee…';action.disabled=true;
      const settings=document.createElement('a');settings.href='marketplace.php?tab=pengaturan&peng_sub=integrasi';settings.className='block text-xs font-bold text-brand-700';settings.textContent='Periksa konfigurasi aplikasi dan akses API';
      content.append(description,status,action,settings);
      try{const config=await oauth('status',{});if(!config.is_admin){action.textContent='Memerlukan administrator';return;}if(!config.platforms?.Shopee?.ready){action.textContent='Konfigurasi Shopee belum lengkap';return;}action.disabled=false;action.textContent='Lanjutkan ke Otorisasi Shopee';action.onclick=async()=>{action.disabled=true;action.textContent='Membuka otorisasi resmi…';try{const callback=config.callback_urls?.Shopee;if(callback&&new URL(callback).origin!==location.origin){location.assign(new URL('../'+destination,callback).href);return;}const result=await oauth('start',{platform:'Shopee',return_path:destination+'&ads_setup=shopee'});location.assign(result.authorization_url);}catch(error){action.disabled=false;action.textContent='Coba otorisasi lagi';status.textContent+=' '+error.message;}};}catch(error){action.textContent='Konfigurasi belum dapat diperiksa';status.textContent=error.message;}
    }));
    document.querySelectorAll('[data-settings-disconnect]').forEach(button=>button.addEventListener('click',()=>{openDialog('Putuskan koneksi toko?');const p=document.createElement('p');p.textContent='Akses sinkronisasi internal akan dihentikan. Data toko dan riwayat transaksi tetap tersimpan.';const confirm=document.createElement('button');confirm.type='button';confirm.className='rounded-xl bg-rose-600 text-white px-4 py-3 text-xs font-bold';confirm.textContent='Putuskan Koneksi';confirm.onclick=async()=>{confirm.disabled=true;try{await oauth('disconnect',{store_id:button.dataset.settingsDisconnect});location.assign(destination);}catch(error){confirm.disabled=false;p.textContent=error.message;}};content.append(p,confirm);}));
    const pending=new URLSearchParams(location.search).get('pending');
    if(pending)(async()=>{try{const result=await oauth('pending',{pending_id:pending});const box=document.getElementById('settings-pending-shops');box.hidden=false;if(dialog){openDialog('Pilih toko terotorisasi');content.append(box);}const heading=document.createElement('h2');heading.className='font-bold text-sm';heading.textContent='Pilih toko yang akan dihubungkan';box.append(heading);for(const shop of result.shops||[]){const button=document.createElement('button');button.type='button';button.className='block w-full rounded-xl border p-3 text-left text-xs font-semibold hover:bg-brand-50';button.textContent=shop.name+' · '+shop.id;button.onclick=async()=>{button.disabled=true;try{await oauth('select',{pending_id:pending,external_store_id:shop.id});location.assign(destination+'&oauth_status=success');}catch(error){button.disabled=false;notice(error.message);}};box.append(button);}}catch(error){notice(error.message);}})();
})();
