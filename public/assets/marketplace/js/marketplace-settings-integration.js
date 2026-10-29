(() => {
    const destination='marketplace.php?tab=pengaturan&peng_sub=tambah_toko';
    const notice=text=>{
        const el=document.getElementById('settings-integration-notice');
        if(!el)return;
        el.className='rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs text-amber-900 block';
        el.textContent=text;
    };

    const root=document.getElementById('settings-integration');
    if(!root)return;
    const csrf=root.dataset.csrf;

    document.querySelectorAll('[data-store-archive]').forEach(button=>button.addEventListener('click',async()=>{
        if(!window.confirm('Arsipkan toko ini? Sinkronisasi berhenti dan riwayat tetap tersimpan.'))return;
        button.disabled=true;
        try{
            const response=await fetch('api/marketplace-ajax.php',{
                method:'POST',credentials:'same-origin',
                headers:{'X-CSRF-Token':csrf},
                body:new URLSearchParams({action:'archive_store',store_id:button.dataset.storeArchive})
            });
            const result=await response.json();
            if(!response.ok||!result.success)throw new Error(result.message||'Toko belum dapat diarsipkan.');
            location.reload();
        }catch(error){
            notice(error.message);
            button.disabled=false;
        }
    }));

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
        mode.addEventListener('change',()=>{
            const changed=mode.value!==form.dataset.savedMode;
            id.value=changed?'':form.dataset.savedId;
            key.value='';
            key.required=changed||form.dataset.hasSecret!=='1';
            key.placeholder=key.required?'Masukkan Partner Key untuk lingkungan ini':'Tersimpan - kosongkan jika tidak diubah';
            const result=form.querySelector('.partner-test-result');
            result.className='partner-test-result rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs leading-5';
            result.textContent=changed?'Lingkungan berubah. Masukkan Partner ID dan Partner Key yang sesuai. Kredensial lingkungan sebelumnya tidak digunakan.':'Menggunakan lingkungan yang tersimpan.';
        });
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
            if(form.dataset.platform==='Shopee'){
                form.dataset.savedMode=payload.data.status.mode;
                form.dataset.savedId=payload.data.status.partner_id;
                form.dataset.hasSecret=payload.data.status.has_secret?'1':'0';
            }
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
        const response=await fetch('api/marketplace-oauth.php'+(read?'?'+new URLSearchParams({action,...body}):''),{
            method:read?'GET':'POST',
            credentials:'same-origin',
            headers:{'X-CSRF-Token':csrf,...(!read?{'Content-Type':'application/x-www-form-urlencoded'}:{})},
            ...(!read?{body:new URLSearchParams({action,...body})}:{})
        });
        const result=await response.json();
        if(!response.ok||!result.success)throw Error(result.message||'Koneksi belum dapat diproses.');
        return result.data;
    }

    async function updateReadiness(){
        try{
            const status=await oauth('status',{});
            document.querySelectorAll('[data-settings-connect]').forEach(button=>button.disabled=!status.platforms?.[button.dataset.settingsConnect]?.ready);
            document.querySelectorAll('[data-auth-ready]').forEach(el=>el.textContent=status.platforms?.[el.dataset.authReady]?.ready?'Konfigurasi siap untuk otorisasi toko.':'Lengkapi konfigurasi aplikasi terlebih dahulu.');
        }catch(error){
            notice('Kredensial tersimpan. Muat ulang halaman untuk memperbarui kesiapan otorisasi.');
        }
    }

    const dialog=document.getElementById('settings-connect-dialog');
    const content=document.getElementById('settings-dialog-content');
    const openDialog=(title)=>{
        document.getElementById('settings-dialog-title').textContent=title;
        content.replaceChildren();
        dialog.showModal();
    };
    document.querySelector('[data-close-settings-dialog]')?.addEventListener('click',()=>dialog.close());

    let connecting=false;
    document.querySelectorAll('[data-settings-connect]').forEach(button=>button.addEventListener('click',async()=>{
        if(connecting)return;connecting=true;button.disabled=true;button.setAttribute('aria-busy','true');
        try{
            const config=await oauth('status',{}),platform=button.dataset.settingsConnect;
            if(!config.platforms?.[platform]?.ready)throw Error('Konfigurasi aplikasi belum lengkap. Minta administrator memeriksa Integrasi API.');
            openDialog('Hubungkan Toko '+platform);
            const purpose=document.createElement('p');
            purpose.textContent='Anda akan diarahkan ke otorisasi resmi '+platform+' di tab browser baru. Akses data mengikuti izin aplikasi dan persetujuan akun seller.';
            const environment=document.createElement('p');
            environment.className='ih-dialog-note';
            const env=config.platforms[platform].environment;
            environment.textContent='Lingkungan aplikasi: '+({live:'Live',sandbox:'Sandbox',development:'Development Shop',unspecified:'belum ditetapkan'}[env]||'belum ditetapkan')+'. Token dan kredensial disimpan terenkripsi.';
            const scope=document.createElement('p');
            scope.textContent='Setelah otorisasi diberikan, Jago Laba akan memverifikasi identitas toko, katalog, pesanan, dan keuangan secara terpisah.';
            
            const confirm=document.createElement('button');
            confirm.type='button';
            confirm.className='w-full py-3 px-4 rounded-xl font-bold text-xs text-white bg-brand-600 hover:bg-brand-700 transition cursor-pointer flex items-center justify-center gap-2';
            confirm.innerHTML='<i class="fa-solid fa-arrow-up-right-from-square"></i> Buka Otorisasi '+platform+' di Tab Baru';
            content.append(purpose,environment,scope,confirm);

            confirm.onclick=async()=>{
                if(confirm.disabled)return;
                confirm.disabled=true;
                confirm.textContent='Mempersiapkan otorisasi resmi…';
                try{
                    const callback=config.callback_urls?.[platform];
                    if(callback&&new URL(callback).origin!==location.origin){
                        window.open(new URL('../'+destination,callback).href, '_blank', 'noopener,noreferrer');
                        confirm.disabled=false;
                        return;
                    }
                    const result=await oauth('start',{platform,return_path:destination});
                    // Open in a new tab as required
                    window.open(result.authorization_url, '_blank', 'noopener,noreferrer');
                    confirm.disabled=false;
                    confirm.innerHTML='<i class="fa-solid fa-arrow-up-right-from-square"></i> Buka Kembali Tab Otorisasi';
                    
                    const openedBox=document.createElement('div');
                    openedBox.className='mt-3 p-4 rounded-xl bg-blue-50 border border-blue-200 text-xs text-blue-900 space-y-2';
                    openedBox.innerHTML=`
                        <p class="font-bold flex items-center gap-1.5"><i class="fa-solid fa-circle-check text-blue-600"></i> Tab Otorisasi ${platform} Telah Dibuka di Tab Baru</p>
                        <p class="leading-relaxed">Silakan selesaikan izin di tab platform marketplace Anda. Halaman Jago Laba ini tetap aktif dan tidak tertutup.</p>
                        <div class="pt-2 flex items-center gap-2">
                            <button type="button" class="px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs cursor-pointer transition" onclick="location.reload()">
                                <i class="fa-solid fa-rotate-right mr-1"></i> Perbarui Status Toko
                            </button>
                            <a href="${result.authorization_url}" target="_blank" rel="noopener noreferrer" class="text-xs text-blue-700 underline font-semibold ml-2">Buka ulang jika tab tertutup</a>
                        </div>
                    `;
                    content.append(openedBox);
                }catch(error){
                    confirm.disabled=false;
                    confirm.textContent='Coba otorisasi kembali';
                    const problem=document.createElement('p');
                    problem.className='text-xs text-rose-600 font-semibold mt-2';
                    problem.textContent=error.message;
                    content.append(problem);
                }
            };
        }catch(error){
            notice(error.message);
        }finally{
            connecting=false;button.disabled=false;button.removeAttribute('aria-busy');
        }
    }));

    // Hubungkan Ads Handler (TikTok Ads & Shopee Ads di Tab Baru)
    document.querySelectorAll('[data-ads-connect]').forEach(button => button.addEventListener('click', () => {
        const platform = button.dataset.adsConnect;
        const isTikTok = platform.toLowerCase().includes('tiktok');
        openDialog('Hubungkan ' + (isTikTok ? 'TikTok Ads' : 'Shopee Ads'));
        
        const targetUrl = isTikTok 
            ? 'marketplace.php?tab=pengaturan&peng_sub=integrasi#tiktok-ads-config' 
            : 'https://seller.shopee.co.id/portal/marketing/pas';

        const p1 = document.createElement('p');
        p1.className = 'leading-relaxed';
        p1.textContent = isTikTok 
            ? 'Koneksi TikTok Ads memerlukan otorisasi advertiser resmi terpisah via TikTok Marketing API. Izin akun iklan tidak dapat digabungkan langsung dengan token toko TikTok Shop.' 
            : 'Shopee Ads disinkronkan secara otomatis menggunakan Shop ID toko Shopee yang telah terhubung resmi. Pastikan toko Shopee Anda memiliki izin Shopee Ads atau saldo promosi aktif.';

        const note = document.createElement('div');
        note.className = 'p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-600';
        note.innerHTML = '<strong>Informasi:</strong> Tautan akan dibuka di tab browser baru agar Anda dapat memverifikasi konfigurasi atau masuk ke portal iklan resmi.';

        const link = document.createElement('a');
        link.href = targetUrl;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        link.className = 'inline-flex items-center justify-center gap-2 w-full py-3 px-4 rounded-xl font-bold text-xs text-white bg-blue-600 hover:bg-blue-700 transition cursor-pointer shadow-sm';
        link.innerHTML = '<i class="fa-solid fa-arrow-up-right-from-square"></i> Buka Portal ' + (isTikTok ? 'TikTok Marketing API' : 'Shopee Ads') + ' di Tab Baru';

        content.append(p1, note, link);
    }));

    document.querySelectorAll('[data-copy-callback]').forEach(button=>button.addEventListener('click',async()=>{
        try{
            await navigator.clipboard.writeText(button.dataset.copyCallback);
            button.textContent='URL tersalin';
        }catch(error){
            notice('Pilih dan salin URL callback secara manual.');
        }
    }));

    document.querySelectorAll('.global-logout-trigger').forEach(button=>button.addEventListener('click',async()=>{
        button.disabled=true;
        try{
            const response=await fetch('api/logout.php',{method:'POST',credentials:'same-origin',headers:{'X-CSRF-Token':csrf}});
            if(!response.ok)throw Error('Keluar belum berhasil.');
            location.assign('index.php');
        }catch(error){
            button.disabled=false;notice(error.message);
        }
    }));

    document.querySelectorAll('[data-ads-info]').forEach(button=>button.addEventListener('click',()=>{
        openDialog('Hubungkan TikTok Ads');
        const p=document.createElement('p');
        p.textContent='Konektor TikTok Marketing API memerlukan konfigurasi aplikasi Marketing API, OAuth terpisah, izin advertiser, dan pemasangan akun iklan ke proyek. Token TikTok Shop tidak dapat dipakai untuk alur ini.';
        const a=document.createElement('a');
        a.href='marketplace.php?tab=pengaturan&peng_sub=integrasi#tiktok-ads-config';
        a.target='_blank';
        a.className='inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 text-white px-4 py-3 text-xs font-bold hover:bg-blue-700 transition';
        a.innerHTML='<i class="fa-solid fa-arrow-up-right-from-square"></i> Buka Konfigurasi TikTok Ads di Tab Baru';
        content.append(p,a);
    }));

    document.querySelectorAll('[data-settings-disconnect]').forEach(button=>button.addEventListener('click',()=>{
        openDialog('Putuskan koneksi toko?');
        const p=document.createElement('p');
        p.textContent='Akses sinkronisasi internal akan dihentikan. Data toko dan riwayat transaksi tetap tersimpan.';
        const confirm=document.createElement('button');
        confirm.type='button';
        confirm.className='rounded-xl bg-rose-600 text-white px-4 py-3 text-xs font-bold cursor-pointer';
        confirm.textContent='Putuskan Koneksi';
        confirm.onclick=async()=>{
            confirm.disabled=true;
            try{
                await oauth('disconnect',{store_id:button.dataset.settingsDisconnect});
                location.assign(destination);
            }catch(error){
                confirm.disabled=false;p.textContent=error.message;
            }
        };
        content.append(p,confirm);
    }));

    const pending=new URLSearchParams(location.search).get('pending');
    if(pending)(async()=>{
        try{
            const result=await oauth('pending',{pending_id:pending});
            const box=document.getElementById('settings-pending-shops');
            if(box)box.hidden=false;
            if(dialog){
                openDialog('Pilih toko terotorisasi');
                if(box)content.append(box);
            }
            const heading=document.createElement('h2');
            heading.className='font-bold text-sm';
            heading.textContent='Pilih toko yang akan dihubungkan';
            if(box)box.append(heading);
            for(const shop of result.shops||[]){
                const button=document.createElement('button');
                button.type='button';
                button.className='block w-full rounded-xl border p-3 text-left text-xs font-semibold hover:bg-brand-50 cursor-pointer';
                button.textContent=shop.name+' · '+shop.id;
                button.onclick=async()=>{
                    button.disabled=true;
                    try{
                        await oauth('select',{pending_id:pending,external_store_id:shop.id});
                        location.assign(destination+'&oauth_status=success');
                    }catch(error){
                        button.disabled=false;notice(error.message);
                    }
                };
                if(box)box.append(button);
            }
        }catch(error){
            notice(error.message);
        }
    })();
})();
