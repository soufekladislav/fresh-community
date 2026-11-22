/* AR Community 0.2.0-beta.5 – in-app notices, never email. */
(()=>{'use strict';
 if(typeof ARC==='undefined'||!ARC.loggedIn)return;
 const app=document.querySelector('[data-arc-notifications]');if(!app)return;
 const list=app.querySelector('[data-arc-notifications-list]');
 const counter=app.querySelector('[data-arc-notification-count]');
 const escape=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const endpoint=path=>ARC.root+'live/'+path;
 const get=async(path,opts={})=>{
  const res=await fetch(endpoint(path),{credentials:'same-origin',cache:'no-store',...opts,headers:{'X-WP-Nonce':ARC.nonce,...(opts.body?{'Content-Type':'application/json'}:{})}});
  const data=await res.json().catch(()=>({}));if(!res.ok)throw Error(data.message||'Upozornění nejsou dostupná.');return data;
 };
 let loading=false;
 const refresh=async()=>{
  if(loading)return;loading=true;
  try{
   const data=await get('notifications');const entries=data.items||[];
   counter.hidden=!(data.unread>0);counter.textContent=String(data.unread||0);
   const count=Number(data.unread||0);
   list.textContent=count>0?(count===1?'Máš nové upozornění.':`Máš nová upozornění (${count}).`):'Nemáš žádná nová upozornění.';
  }catch(error){list.textContent=error.message;}finally{loading=false;}
 };
 app.querySelector('[data-arc-notifications-read]')?.addEventListener('click',async e=>{
  const btn=e.currentTarget;btn.disabled=true;
  try{await get('notifications/read',{method:'POST',body:'{}'});await refresh();btn.textContent='✓ Přečteno';}
  catch(error){list.textContent=error.message;}finally{btn.disabled=false;}
 });
 refresh();
 // Lightweight polling only while the page is visible and the current tab is active.
 setInterval(()=>{if(!document.hidden)refresh();},60000);
 window.addEventListener('focus',refresh);
})();
