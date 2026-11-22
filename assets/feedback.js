(function(){'use strict';
function init(){
 const root=document.querySelector('[data-arc-feedback]');if(!root)return;
 const toggle=root.querySelector('[data-arc-feedback-toggle]');
 const close=root.querySelector('[data-arc-feedback-close]');
 const panel=root.querySelector('[data-arc-feedback-panel]');
 const form=root.querySelector('[data-arc-feedback-form]');
 const submit=root.querySelector('[data-arc-feedback-submit]');
 const field=root.querySelector('[name="message"]');
 const result=root.querySelector('[data-arc-feedback-result]');
 let pending=false;
 function setOpen(open,focus=true){
  root.classList.toggle('is-open',open);
  toggle.setAttribute('aria-expanded',String(open));
  panel.setAttribute('aria-hidden',String(!open));
  if(open){panel.removeAttribute('inert');if(focus)field.focus({preventScroll:true});}
  else{panel.setAttribute('inert','');if(focus)toggle.focus({preventScroll:true});}
 }
 setOpen(false,false);
 toggle.addEventListener('click',()=>setOpen(!root.classList.contains('is-open')));
 close.addEventListener('click',()=>setOpen(false));
 document.addEventListener('keydown',e=>{if(e.key==='Escape'&&root.classList.contains('is-open'))setOpen(false)});
 document.addEventListener('pointerdown',e=>{if(root.classList.contains('is-open')&&!root.contains(e.target))setOpen(false,false)});
 form.addEventListener('submit',async e=>{
  e.preventDefault();if(pending)return;
  const msg=field.value.trim();if(msg.length<5||msg.length>3000){result.textContent='Zpráva musí mít 5 až 3000 znaků.';result.classList.add('is-error');return;}
  pending=true;submit.disabled=true;result.textContent='Odesílám…';result.classList.remove('is-error');
  try{
   const data=new FormData();data.set('action','arc_feedback_send');data.set('nonce',window.ARCFeedback?.nonce||'');data.set('message',msg);
   const resp=await fetch(window.ARCFeedback?.ajaxUrl||'/wp-admin/admin-ajax.php',{method:'POST',credentials:'same-origin',body:data,cache:'no-store'});
   const json=await resp.json();
   if(!resp.ok||!json?.success)throw new Error(json?.data?.message||'Zprávu se nepodařilo odeslat.');
   field.value='';result.textContent=json.data.message||'Zpráva byla uložena.';
  }catch(err){result.classList.add('is-error');result.textContent=err.message||'Došlo k chybě. Zkus to znovu.';}
  finally{pending=false;submit.disabled=false;}
 });
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
