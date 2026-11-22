(()=>{'use strict';
const root=document.querySelector('[data-arc-welcome]');
const flow=root?.querySelector('[data-arc-welcome-flow]');
if(!root||!flow)return;
let current=Number(flow.dataset.activeStep)||0;
const panels=[...flow.querySelectorAll('[data-arc-welcome-slide]')];
const tabs=[...flow.querySelectorAll('[data-arc-welcome-go]')];
const prev=flow.querySelector('[data-arc-welcome-prev]');
const next=flow.querySelector('[data-arc-welcome-next]');
const label=flow.querySelector('[data-arc-welcome-current]');
const fill=flow.querySelector('[data-arc-welcome-progress-fill]');
const status=root.querySelector('[data-arc-welcome-status]');
const url=window.ARCWelcome?.url||'';
const nonce=window.ARCWelcome?.nonce||'';
let busy=false;
function render(){
  panels.forEach((panel,i)=>{panel.hidden=i!==current;});
  tabs.forEach((tab,i)=>{tab.classList.toggle('is-active',i===current);tab.classList.toggle('is-past',i<current);tab.setAttribute('aria-current',i===current?'step':'false');});
  if(prev)prev.hidden=current===0;
  if(next)next.hidden=current===panels.length-1;
  flow.querySelectorAll('[data-arc-welcome-done]').forEach(b=>b.hidden=current!==panels.length-1);
  if(label)label.textContent=String(current+1);
  if(fill)fill.style.width=`${100*(current+1)/panels.length}%`;
}
async function post(action, extra={}){
  if(!url)throw Error('Chybí adresa pro uložení.');
  const body=new FormData();body.set('action',action);body.set('nonce',nonce);
  Object.entries(extra).forEach(([k,v])=>body.set(k,String(v)));
  const r=await fetch(url,{method:'POST',credentials:'same-origin',body,cache:'no-store'});
  const result=await r.json();
  if(!r.ok||!result.success)throw Error(result?.data?.message||'Uložení se nezdařilo.');
  return result;
}
async function go(step){
  if(busy||step<0||step>=panels.length||step===current)return;
  const old=current;current=step;render();busy=true;
  try{await post('arc_welcome_step',{step});if(status)status.textContent='';}
  catch(e){current=old;render();if(status)status.textContent=e.message;}
  finally{busy=false;}
}
tabs.forEach((btn,i)=>btn.addEventListener('click',()=>go(i)));
prev?.addEventListener('click',()=>go(current-1));
next?.addEventListener('click',()=>go(current+1));
root.querySelectorAll('[data-arc-welcome-done]').forEach(btn=>btn.addEventListener('click',async()=>{
  if(busy)return;busy=true;btn.disabled=true;
  try{await post('arc_welcome_done');root.querySelector('.arc-welcome-first')?.remove();if(status)status.textContent='Průvodce dokončen. Vítej v komunitě!';}
  catch(e){if(status)status.textContent=e.message;btn.disabled=false;busy=false;}
}));
render();
})();
