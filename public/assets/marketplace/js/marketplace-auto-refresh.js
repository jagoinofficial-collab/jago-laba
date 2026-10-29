(()=>{
 const script=document.currentScript;const store=script?.dataset.storeId;if(!store)return;let version=null;
 const refresh=async()=>{if(document.hidden)return;try{const r=await fetch('api/marketplace-auto-sync.php?'+new URLSearchParams({store_id:store}),{credentials:'same-origin'});if(!r.ok)return;const data=await r.json();if(!data.success)return;const next=Object.values(data.states).filter(s=>s.enabled).map(s=>s.last_success_at||'').join('|');if(version!==null&&next!==version&&next.replaceAll('|','')!==''&&!document.querySelector('dialog[open], [role="dialog"]:not(.hidden)')&&!['INPUT','TEXTAREA','SELECT'].includes(document.activeElement?.tagName)){location.reload();return;}version=next;}catch{}};
 refresh();setInterval(refresh,30000);
})();
