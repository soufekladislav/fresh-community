/* AR Live 2.0: near-real-time private notifications (visible-page polling, no background push). */
(()=>{'use strict';
 const cfg=window.ARCNav; if(!cfg||!cfg.endpoint||!cfg.nonce)return;
 const badges=()=>document.querySelectorAll('[data-arc-nav-count]');
 const interval=Math.max(10000,Number(cfg.interval)||12000);
 const storageKey='arc_live_last_notice_v96_'+String(Number(cfg.user)||0);
 let previousId=0,initialized=false,running=false;
 try{previousId=Number(sessionStorage.getItem(storageKey))||0;initialized=previousId>0;}catch(_){/* Private browsing restrictions */}
 const wrapper=document.createElement('div');wrapper.className='arc-live-toasts';wrapper.setAttribute('aria-live','polite');wrapper.setAttribute('aria-relevant','additions');
 const createWrapper=()=>{if(document.body&&!wrapper.isConnected)document.body.appendChild(wrapper);};
 const shown=new Set();
 function notify(item){
  const id=Number(item?.id)||0;if(!id||shown.has(id)||!item?.message)return;
  shown.add(id);createWrapper();
  const box=document.createElement('div');box.className='arc-live-toast';box.setAttribute('role','status');
  const icon=document.createElement('span');icon.className='arc-live-toast-icon';icon.textContent='AR';
  if(item.avatar){const img=document.createElement('img');img.src=item.avatar;img.alt='';img.loading='lazy';icon.replaceChildren(img);}
  const content=document.createElement('div');content.className='arc-live-toast-content';
  const title=document.createElement('strong');title.textContent='Nové oznámení';
  const txt=document.createElement('p');txt.textContent=item.message;
  const link=document.createElement('a');link.href=item.url||cfg.inbox||'/oznameni/';link.textContent='Zobrazit';
  const dismiss=document.createElement('button');dismiss.type='button';dismiss.className='arc-live-toast-close';dismiss.textContent='×';dismiss.setAttribute('aria-label','Zavřít oznámení');
  dismiss.addEventListener('click',()=>box.remove());
  content.append(title,txt,link);box.append(icon,content,dismiss);wrapper.prepend(box);
  while(wrapper.childElementCount>3)wrapper.lastElementChild?.remove();
 }
 async function refresh(){
  if(running||document.hidden)return;running=true;
  try{
   const resp=await fetch(cfg.endpoint,{credentials:'same-origin',cache:'no-store',headers:{'X-WP-Nonce':cfg.nonce,'Accept':'application/json'}});
   if(resp.status===401||resp.status===403)return;if(!resp.ok)return;
   const data=await resp.json();if(!Array.isArray(data?.items))return;
   const count=Math.max(0,Number(data.unread)||0);
   badges().forEach(b=>{b.hidden=!count;b.textContent=String(Math.min(count,99))+(count>99?'+':'');});
   const maxId=Math.max(previousId,...data.items.map(n=>Number(n?.id)||0));
   if(initialized&&maxId>previousId){
    data.items.filter(n=>!n.read&&Number(n.id)>previousId).sort((a,b)=>Number(a.id)-Number(b.id)).slice(-3).forEach(notify);
    document.dispatchEvent(new CustomEvent('arc:notifications-updated'));
   }
   initialized=true;previousId=maxId;
   try{sessionStorage.setItem(storageKey,String(previousId));}catch(_){}
  }catch(_){/* Offline/expired session: next visible refresh retries without interrupting page. */}
  finally{running=false;}
 }
 const start=()=>{
  createWrapper();refresh();
  document.addEventListener('visibilitychange',()=>{if(!document.hidden)refresh();});
  window.addEventListener('focus',refresh);
  window.setInterval(refresh,interval);
  document.addEventListener('arc:notifications-read',refresh);
 };
 // The floating bottom menu is printed at wp_footer priority 90, after WP's footer scripts.
 // Run only once the DOM is complete so its notification badge is present.
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start,{once:true});else start();
})();
