/* Fresh Community PWA helper: non-invasive install CTA and update checks after rendering. */
(()=>{'use strict';
 const cfg=window.ARCInstallConfig||{};
 const appUrl=cfg.appUrl||'/';
 const appName=cfg.name||'Fresh Community';
 let installEvent=null, lastUpdateCheck=0, announcedUpdate=false;
 const hadControllerAtLaunch=!!navigator.serviceWorker?.controller;
 const standalone=()=>window.matchMedia?.('(display-mode: standalone)').matches===true||navigator.standalone===true;
 const isIOS=()=>/iPad|iPhone|iPod/.test(navigator.userAgent)||(navigator.platform==='MacIntel'&&navigator.maxTouchPoints>1);
 const isAndroid=()=>/Android/i.test(navigator.userAgent);
 const isSafari=()=>/Safari/i.test(navigator.userAgent)&&!/CriOS|FxiOS|EdgiOS|Chrome|Chromium|Edg\//i.test(navigator.userAgent);
 const widgets=()=>[...document.querySelectorAll('[data-arc-install-widget]')];
 const setStatus=(text)=>widgets().forEach(w=>{const node=w.querySelector('[data-arc-install-status]');if(node)node.textContent=text;});
 function refreshButtons(){
  widgets().forEach(w=>{
   const btn=w.querySelector('[data-arc-install-trigger]');if(!btn)return;
   const lab=w.querySelector('[data-arc-install-label]');
   if(standalone()){
    if(lab)lab.textContent='Otevřít aplikaci';
    w.classList.add('is-installed');
   } else { w.classList.remove('is-installed'); }
  });
 }
 window.addEventListener('beforeinstallprompt',e=>{
  e.preventDefault();installEvent=e;refreshButtons();
 });
 window.addEventListener('appinstalled',()=>{installEvent=null;setStatus('Aplikace je nainstalovaná.');refreshButtons();reportInstalled('installed_event');});
 // A launch in standalone mode confirms an app install; an install prompt acceptance alone does not.
 function deviceId(){
  try{let id=localStorage.getItem('arc_device_id');if(id)return id;
   id=(crypto.randomUUID?crypto.randomUUID():Array.from(crypto.getRandomValues(new Uint8Array(16)),x=>x.toString(16).padStart(2,'0')).join(''));
   localStorage.setItem('arc_device_id',id);return id;
  }catch(_){return '';}
 }
 function platform(){const s=navigator.userAgent||'';return isIOS()?'iOS / iPadOS':isAndroid()?'Android':/Win/i.test(s)?'Windows':/Mac/i.test(s)?'macOS':'Jiné zařízení';}
 function browser(){const s=navigator.userAgent||'';return /Edg/i.test(s)?'Edge':/Chrome|CriOS/i.test(s)?'Chrome':/Firefox|FxiOS/i.test(s)?'Firefox':isSafari()?'Safari':'Prohlížeč';}
 async function reportInstalled(source){
  if(!cfg.deviceEndpoint||!cfg.restNonce||!standalone()&&source!=='installed_event')return;
  const id=deviceId();if(!id)return;
  const key='arc_device_last_report';
  if(source!=='installed_event'){try{if(Date.now()-Number(localStorage.getItem(key)||0)<12*60*60*1000)return;}catch(_){}}
  try{const r=await fetch(cfg.deviceEndpoint,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-WP-Nonce':cfg.restNonce},body:JSON.stringify({id,source,platform:platform(),browser:browser()})});
   if(r.ok){try{localStorage.setItem(key,String(Date.now()));}catch(_){}}
  }catch(_){/* absence of telemetry must never break the application */}
 }
 const instructions=()=>{
  if(isIOS())return isSafari()
   ?'Na iPhonu nebo iPadu v Safari klepněte na Sdílet (čtvereček se šipkou) → Přidat na plochu. Potom aplikaci spusťte z její ikony. Oznámení se povolují zvlášť.'
   :'Na iPhonu/iPadu otevřete stránku '+appName+' v Safari a zvolte Sdílet → Přidat na plochu. Pro push upozornění pak aplikaci spouštějte z plochy.';
  if(isAndroid())return 'V Chrome otevřete nabídku ⋮ a zvolte „Nainstalovat aplikaci“ nebo „Přidat na plochu“. Pokud se volba nezobrazí, nejprve otevřete '+appName+'.';
  if(isSafari())return 'V Safari otevřete nabídku Soubor → Přidat do Docku (pokud je dostupná). Pro další možnosti použijte nabídku Sdílení.';
  return 'V Chrome nebo Edge na PC klikněte na ikonu instalace na pravé straně adresního řádku, případně otevřete nabídku ⋮ → Instalovat aplikaci. Otevřete nejprve Adrenaline Live, pokud se nabídka nezobrazuje.';
 };
 function showGuide(){
  const dialog=document.querySelector('[data-arc-install-guide]');
  if(!dialog){window.location.assign(appUrl);return;}
  const p=dialog.querySelector('[data-arc-install-instructions]');if(p)p.textContent=instructions();
  if(typeof dialog.showModal==='function'&&!dialog.open)dialog.showModal();else dialog.setAttribute('open','');
 }
 const install=async()=>{
  if(standalone()){window.location.assign(appUrl);return;}
  if(installEvent){
   const event=installEvent;installEvent=null;
   try{await event.prompt();const choice=await event.userChoice;
    setStatus(choice?.outcome==='accepted'?'Instalace byla zahájena.':'Instalaci lze spustit také z nabídky prohlížeče.');
   }catch(_){showGuide();}
  } else {showGuide();}
 };
 document.addEventListener('click',e=>{
  if(e.target.closest('[data-arc-install-trigger]')){e.preventDefault();void install();}
  if(e.target.closest('[data-arc-install-close]')){e.preventDefault();const d=e.target.closest('dialog');if(d){if(typeof d.close==='function')d.close();else d.removeAttribute('open');}}
 });
 // App updates are checked after first render, never on the critical loading path.
 async function updateCheck(){
  if(!cfg.isApp||!('serviceWorker' in navigator)||!window.isSecureContext)return;
  if(Date.now()-lastUpdateCheck<15*60*1000)return;
  lastUpdateCheck=Date.now();
  try{
   const reg=await navigator.serviceWorker.getRegistration(appUrl);
   if(!reg)return; // Registration remains owned by AR Community, not this helper.
   await reg.update();
  }catch(_){/* Offline or disabled SW: normal network-first app still works. */}
 }
 function showUpdateNotice(){
  if(announcedUpdate||!cfg.isApp||!hadControllerAtLaunch)return;announcedUpdate=true;
  const wrap=document.createElement('aside');wrap.className='arc-install-update-toast';
  wrap.setAttribute('role','status');
  wrap.style.cssText='position:fixed;right:14px;bottom:90px;z-index:99990;max-width:min(90vw,355px);padding:15px 17px;border-radius:14px;background:#2e3430;color:#fff;box-shadow:0 8px 22px #0004;font:500 13px/1.5 Poppins,system-ui,sans-serif;';
  const text=document.createElement('span');text.textContent='Je dostupná aktualizace '+appName+'. Obnovte stránku, až dokončíte rozepsané změny. ';
  const btn=document.createElement('button');btn.type='button';btn.textContent='Aktualizovat';
  btn.style.cssText='background:#9EC415;border:0;border-radius:9px;margin:7px 8px 0 0;padding:8px 12px;font-weight:700;color:#152000;cursor:pointer';
  btn.addEventListener('click',()=>location.reload());
  const dismiss=document.createElement('button');dismiss.type='button';dismiss.textContent='Později';
  dismiss.style.cssText='background:transparent;color:#fff;border:1px solid #eee8;border-radius:9px;padding:8px 12px;cursor:pointer';
  dismiss.addEventListener('click',()=>wrap.remove());
  wrap.append(text,document.createElement('br'),btn,dismiss);document.body.appendChild(wrap);
 }
 if(cfg.isApp && 'serviceWorker' in navigator){
  // The current worker already calls skipWaiting() on install. Do not force reload.
  navigator.serviceWorker.addEventListener('controllerchange',showUpdateNotice);
  if(document.readyState==='complete')setTimeout(updateCheck,1400);
  else window.addEventListener('load',()=>setTimeout(updateCheck,1400),{once:true});
  document.addEventListener('visibilitychange',()=>{if(!document.hidden)void updateCheck();});
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',()=>{refreshButtons();reportInstalled('standalone_launch');},{once:true});else {refreshButtons();reportInstalled('standalone_launch');}
})();
