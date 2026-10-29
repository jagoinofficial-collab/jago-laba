(()=>{
 'use strict';
 if(window.jlBrowserAutoSync)return;window.jlBrowserAutoSync=true;
 const csrf=document.currentScript?.dataset.csrf;if(!csrf)return;
 let busy=false,stopped=false,timer;
 async function pulse(){
  if(stopped||document.hidden||busy)return;
  busy=true;
  try{
   const send=async()=>{
    if(stopped||document.hidden)return;
    const response=await fetch('api/marketplace-browser-pulse.php',{method:'POST',credentials:'same-origin',headers:{'X-CSRF-Token':csrf}});
    if(response.status===401||response.status===403){stopped=true;clearInterval(timer);}
   };
   if(navigator.locks)await navigator.locks.request('jago-laba-browser-autosync',{ifAvailable:true},lock=>lock?send():undefined);
   else await send();
  }catch{/* Retry on the next browser interval; never launch a detached worker. */}
  finally{busy=false;}
 }
 timer=setInterval(pulse,15000);
 document.addEventListener('visibilitychange',()=>{if(!document.hidden)pulse();});
 window.addEventListener('pagehide',()=>{stopped=true;clearInterval(timer);});
 window.addEventListener('pageshow',event=>{if(event.persisted){stopped=false;clearInterval(timer);timer=setInterval(pulse,15000);pulse();}});
 pulse();
})();
