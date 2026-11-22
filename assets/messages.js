(()=>{'use strict';
 const root=document.querySelector('[data-arc-msg]'),cfg=window.ARCChat;
 if(!root||!cfg)return;
 const list=root.querySelector('[data-arc-msg-threads]'),history=root.querySelector('[data-arc-msg-history]');
 const title=root.querySelector('[data-arc-msg-title]'),form=root.querySelector('[data-arc-msg-compose]');
 const status=root.querySelector('[data-arc-msg-status]'),field=form.querySelector('[name=body]');
 let selected=Number(cfg.peer)||0,selectedName='',loading=false,lastMessages='';
 async function api(endpoint='',method='GET',payload){
  const res=await fetch(cfg.root+endpoint,{method,credentials:'same-origin',cache:'no-store',headers:{'X-WP-Nonce':cfg.nonce,...(payload?{'Content-Type':'application/json'}:{})},...(payload?{body:JSON.stringify(payload)}:{})});
  const data=await res.json().catch(()=>({}));
  if(!res.ok)throw Error(data.message||'Požadavek se nepodařil.');
  return data;
 }
 function el(tag,cls,text){const x=document.createElement(tag);if(cls)x.className=cls;if(text!==undefined)x.textContent=String(text);return x;}
 async function friends(){
  const data=await api('threads'),users=data.items||[];
  list.replaceChildren();
  if(!users.length){list.append(el('p','arc-msg-empty','Zatím nemáš potvrzené přátele. Pozvi někoho a potom mu můžeš napsat.'));return;}
  for(const u of users){
   const b=el('button','arc-msg-peer'+(selected===u.id?' is-active':''));b.type='button';
   b.append(el('strong','',u.name));
   if(u.unread)b.append(el('span','arc-msg-unread',u.unread));
   if(u.latest)b.append(el('small','',u.latest));
   b.addEventListener('click',()=>{selected=u.id;selectedName=u.name;history.replaceState(null,'',location.pathname+'?with='+selected);friends();load();});
   list.append(b);
   if(selected===u.id)selectedName=u.name;
  }
  if(selected && !users.some(u=>u.id===selected)){selected=0;history.replaceChildren(el('p','','Vyber přítele ze seznamu.'));form.hidden=true;}
 }
 async function load(){
  if(!selected||loading)return;loading=true;
  try{
   const data=await api(String(selected));
   title.textContent=selectedName||'Konverzace';form.hidden=false;
   const key=(data.items||[]).map(m=>m.id).join(',');
   if(key!==lastMessages){lastMessages=key;history.replaceChildren();
    for(const m of data.items||[]){
     const article=el('article','arc-msg-bubble '+(m.mine?'is-mine':'is-theirs'));
     article.append(el('p','',m.body),el('time','',m.date));history.append(article);
    }
    history.scrollTop=history.scrollHeight;
   }
   await api(String(selected)+'/read','POST',{});
   status.textContent='';
  }catch(e){status.textContent=e.message;}
  finally{loading=false;}
 }
 form.addEventListener('submit',async e=>{
  e.preventDefault();if(!selected||!field.value.trim())return;
  const btn=form.querySelector('button');btn.disabled=true;status.textContent='Odesílám…';
  try{await api(String(selected),'POST',{body:field.value});field.value='';lastMessages='';await load();await friends();}
  catch(ex){status.textContent=ex.message;}
  finally{btn.disabled=false;}
 });
 friends().then(load).catch(e=>status.textContent=e.message);
 let hidden=false;document.addEventListener('visibilitychange',()=>hidden=document.hidden);
 window.setInterval(()=>{if(!hidden){friends().then(load).catch(()=>{});}},12000);
})();
