const {chromium}=require('C:/Users/PT JAGO LABA/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
(async()=>{
 const root=path.resolve(__dirname,'../..'),out=path.join(root,'storage/temporary/integration-qa');fs.mkdirSync(out,{recursive:true});
 const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 const failures=[];let begins=0;
 try{
  for(const [name,width,height] of [['desktop',1440,1080],['mobile',390,844]]){
   const context=await browser.newContext({viewport:{width,height},deviceScaleFactor:1});const page=await context.newPage();let fixture;
   page.on('pageerror',e=>failures.push(name+': '+e.message));
   await page.route('**/marketplace-settings-preview.html',route=>route.fulfill({contentType:'text/html',body:fs.readFileSync(path.join(out,'preview.html'),'utf8')}));
   await page.route('**/api/**',async route=>{
    if(route.request().url().includes('marketplace-branding.php'))return route.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="36" height="36"><circle cx="18" cy="18" r="18" fill="#1e40af"/><text x="18" y="23" fill="white" text-anchor="middle">JL</text></svg>'});
    const u=new URL(route.request().url()),params=new URLSearchParams(route.request().postData()||u.search);let data={};
    if(u.pathname.endsWith('marketplace-store-deletion.php')){
     assert.equal(params.get('action'),'preview','UI verification must never request real deletion');
     data={name:'Toko Arsip Uji',platform:'TikTok Shop',counts:{marketplace_orders:12,marketplace_stores:1},file_count:2};
    }
    else if(u.pathname.endsWith('marketplace-integration-status.php'))data=fixture;
    else if(u.pathname.endsWith('marketplace-oauth.php'))data={is_admin:true,platforms:{'TikTok Shop':{ready:true,environment:'development'},Shopee:{ready:false,environment:'sandbox'}},callback_urls:{}};
    else if(params.get('action')==='preview')data={stores:[{id:'PREVIEW-SANDBOX',store_name:'Toko Preview',platform:'TikTok Shop',window:{window_start:1790787600,window_end:1793034000}}]};
    else if(params.get('action')==='background'){begins++;data={can_close:true,jobs:[],pending:1};}
    else if(u.pathname.endsWith('marketplace-auto-sync.php'))data={};
    await route.fulfill({status:200,contentType:'application/json',body:JSON.stringify({success:true,data})});
   });
   await page.goto('http://localhost/jago-laba/public/marketplace-settings-preview.html',{waitUntil:'networkidle',timeout:30000});
   fixture=await page.locator('[data-integration-initial]').evaluate(el=>JSON.parse(el.textContent));
   await page.locator('[data-capability-grid] .ih-capability').first().waitFor();
   assert.equal(await page.locator('.ih-capability').count(),9,'Capability matrix missing');
   assert.equal(await page.getByRole('button',{name:'Hubungkan Shopee Ads',exact:false}).count(),0,'Unavailable Ads action shown');
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>window.innerWidth+1),false,name+' page overflows horizontally');
   assert.match(await page.locator('[data-resource="finance"]').innerText(),/Memerlukan izin/);
   await page.screenshot({path:path.join(out,name+'.png'),fullPage:true});
   await page.locator('.delete-archived-store').click();
   await page.locator('#delete-name').filter({hasText:'Toko Arsip Uji'}).waitFor();
   assert.equal(await page.locator('#delete-submit').isDisabled(),true);
   await page.locator('#delete-confirm').fill('Nama salah');
   assert.equal(await page.locator('#delete-submit').isDisabled(),true);
   await page.locator('#delete-confirm').fill('Toko Arsip Uji');
   assert.equal(await page.locator('#delete-submit').isEnabled(),true);
   assert.match(await page.locator('#delete-counts').innerText(),/12/);
   const modal=await page.locator('#store-delete-dialog').boundingBox();
   assert.ok(modal.x>=0&&modal.x+modal.width<=width,'Deletion modal overflows');
   await page.screenshot({path:path.join(out,name+'-delete-preview.png')});
   await page.keyboard.press('Escape');
   assert.equal(await page.locator('#store-delete-dialog').isVisible(),false);
   await page.locator('[data-settings-connect="TikTok Shop"]').first().click();
   await page.locator('#settings-connect-dialog').waitFor({state:'visible'});
   assert.match(await page.locator('#settings-dialog-content').innerText(),/Development Shop/);
   await page.screenshot({path:path.join(out,name+'-authorization.png'),fullPage:false});
   await page.keyboard.press('Escape');
   assert.equal(await page.locator('#settings-connect-dialog').isVisible(),false,'Modal cannot close by keyboard');
   await page.locator('[data-integration-sync]').click();
   await page.getByRole('button',{name:'Mulai Sinkronisasi',exact:true}).waitFor();
   await page.getByRole('button',{name:'Mulai Sinkronisasi',exact:true}).click();
   await page.locator('[data-integration-message]').filter({hasText:'Worker server berhasil dimulai'}).waitFor();
   await context.close();
  }
  assert.equal(begins,2,'Sync click count wrong');assert.deepEqual(failures,[],'Browser runtime errors');
  fs.writeFileSync(path.join(out,'result.json'),JSON.stringify({viewports:['1440x1080','390x844'],horizontalOverflow:false,browserErrors:failures,apiCalls:'mocked; no live authorization or writes',syncStarts:begins},null,2));
  console.log('PASS: desktop/mobile, no overflow, 9 capability states, unsupported Ads hidden, OAuth preflight, Escape close, and one sync start per click.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e.message);process.exit(1);});
