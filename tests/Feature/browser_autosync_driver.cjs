const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const source=fs.readFileSync(require('node:path').join(require('node:path').resolve(__dirname, '../..', 'tests'),'../public/assets/marketplace/js/marketplace-browser-pulse.js'),'utf8');
async function run(){
 let calls=0,interval;const events={},windowEvents={};
 const document={hidden:false,currentScript:{dataset:{csrf:'test'}},addEventListener:(name,fn)=>events[name]=fn};
 const window={addEventListener:(name,fn)=>windowEvents[name]=fn};
 const context={document,window,navigator:{locks:{request:async(name,options,fn)=>fn({})}},fetch:async()=>{calls++;return {status:200}},setInterval:fn=>(interval=fn,1),clearInterval:()=>{}};
 const flush=()=>new Promise(resolve=>setImmediate(resolve));
 vm.runInNewContext(source,context);await flush();assert.equal(calls,1);
 document.hidden=true;await interval();assert.equal(calls,1);
 document.hidden=false;events.visibilitychange();await flush();assert.equal(calls,2);
 context.navigator.locks.request=async(name,options,fn)=>fn(null);await interval();assert.equal(calls,2);
 context.navigator.locks.request=async(name,options,fn)=>fn({});
 windowEvents.pagehide();await interval();assert.equal(calls,2);
 windowEvents.pageshow({persisted:true});await flush();assert.equal(calls,3);
 context.fetch=async()=>{calls++;return {status:401}};await interval();await interval();assert.equal(calls,4);
 console.log('PASS: visible tab only, tab lock, page close, page restore, stop on logout');
}
run().catch(error=>{console.error(error);process.exit(1)});
