/* AR Community social pages: friends, invitation links and notification inbox. */
(()=>{'use strict';
 const root=document.querySelector('[data-arc-social]');if(!root||typeof ARC==='undefined')return;
 const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const api=async(path,opt={})=>{
  const res=await fetch(ARC.root+'live/'+path,{credentials:'same-origin',cache:'no-store',...opt,headers:{'X-WP-Nonce':ARC.nonce,...(opt.body?{'Content-Type':'application/json'}:{}),...(opt.headers||{})}});
  const result=await res.json().catch(()=>({}));if(!res.ok)throw Error(result.message||'Požadavek se nepodařilo dokončit.');return result;
 };
 const $=sel=>root.querySelector(sel);const $$=sel=>[...root.querySelectorAll(sel)];
 const notice=msg=>{const el=$('[data-arc-social-status]');if(el)el.textContent=msg;};
 root.addEventListener('click',async e=>{
  const btn=e.target.closest('[data-arc-accept-invite]');if(!btn)return;
  btn.disabled=true;
  try{const result=await api('invite/accept-link',{method:'POST',body:JSON.stringify({token:btn.dataset.arcAcceptInvite})});
    btn.closest('[data-arc-invite-link-banner]')?.remove();notice(result.message||'Pozvánka přijata.');
    // Remove the bearer token from browser history to avoid accidental sharing.
    const u=new URL(location.href);u.searchParams.delete('arc_invite');history.replaceState(null,'',u.pathname+u.search+u.hash);
    location.reload();
  }catch(err){notice(err.message||'Pozvánku nelze potvrdit.');btn.disabled=false;}
 });
 const svg='';
 let friends=[],requests=[],sent=[],notices=[],notifyFilter='all',searchTimer=0,searchSeq=0;
 let matches=[],selected=null,refreshing=false;
 const face=p=>p.avatar?`<img class="arc-social-avatar" src="${esc(p.avatar)}" loading="lazy" alt="">`:'<span class="arc-social-avatar arc-social-avatar-empty">AR</span>';
 const person=(p,action)=>`<article class="arc-social-person">${face(p)}<div class="arc-social-person-info">${p.url?`<a href="${esc(p.url)}">${esc(p.alias)}</a>`:`<strong>${esc(p.alias)}</strong>`}${p.hidden?'<small>Soukromý profil</small>':''}</div><div class="arc-social-person-partner">${p.affiliate_status==='mine'?'<span class="arc-social-partner">Můj partnerský program</span>':p.affiliate_status==='other'?'<span class="arc-social-partner arc-social-partner-other">Již registrovaný jinde</span>':p.referral_partner?'<span class="arc-social-partner">V mé referenční síti</span>':''}</div><div class="arc-social-person-actions">${action==='disconnect'?`<button type="button" class="arc-social-more" data-friend-action="disconnect" data-id="${Number(p.id)}" aria-label="Odebrat přítele" title="Odebrat přítele">⋯</button>`:action==='request'?`<button type="button" class="arc-social-btn arc-social-primary" data-friend-action="request" data-id="${Number(p.id)}">Pozvat</button>`:action==='sent'?'<span class="arc-social-pending">Odesláno</span>':action==='pending'?`<button type="button" class="arc-social-btn arc-social-primary" data-friend-action="accept" data-id="${Number(p.id)}">Přijmout</button><button type="button" class="arc-social-btn" data-friend-action="decline" data-id="${Number(p.id)}">Odmítnout</button>`:''}</div></article>`;
 const renderFriends=()=>{
   const q=String($('[data-arc-friends-filter]')?.value||'').trim().toLocaleLowerCase('cs');
   const subset=q?friends.filter(p=>String(p.alias||'').toLocaleLowerCase('cs').includes(q)):friends;
   $('[data-arc-social-friends]').innerHTML=subset.length?subset.map(p=>person(p,'disconnect')).join(''):'<p class="arc-social-empty">Žádní přátelé neodpovídají výběru. Můžeš pozvat další členy.</p>';
 };
 const renderSent=()=>{
  const box=$('[data-arc-social-sent]');if(!box)return;
  box.innerHTML=sent.length?sent.map(p=>`<div class="arc-sent-row">${p.avatar?`<img src="${esc(p.avatar)}" alt="" loading="lazy">`:'<span class="arc-sent-placeholder">AR</span>'}<span class="arc-sent-meta"><span class="arc-sent-name">${esc(p.alias)}</span><time>${esc(p.created_at)}</time></span><span class="arc-social-pending">Čeká na potvrzení</span></div>`).join(''):'<p class="arc-social-empty">Nemáš žádné nevyřízené odeslané žádosti za posledních 30 dní.</p>';
 };
 const renderSelected=()=>{
  const box=$('[data-arc-selected-member]');if(!box)return;
  if(!selected){box.replaceChildren();return;}
  const member=selected;const connected=friends.some(p=>Number(p.id)===Number(member.id));
  const isSent=sent.some(p=>Number(p.id)===Number(member.id));
  box.innerHTML='<h3>Vybraný kontakt</h3>'+person(member,connected?'':isSent?'sent':'request');
 };
 const findSuggestions=async()=>{
  const box=$('[data-arc-suggestions]'),field=$('[data-arc-search-input]');if(!box||!field)return;
  const term=field.value.trim(),seq=++searchSeq;
  selected=null;renderSelected();matches=[];
  if(term.length<1){box.innerHTML='<p class="arc-social-help">Začni psát jméno nebo e-mail.</p>';return;}
  box.innerHTML='<p class="arc-social-help">Hledám v komunitě…</p>';
  try{
   const data=await api('directory?q='+encodeURIComponent(term)+'&page=1');
   if(seq!==searchSeq)return;
   matches=(data.items||[]).slice(0,12);
   box.innerHTML=matches.length?matches.map(p=>`<button type="button" role="option" class="arc-suggestion-option" data-arc-select-contact="${Number(p.id)}">${face(p)}<span>${esc(p.alias)}</span>${p.connected?'<small>Jste přátelé</small>':''}</button>`).join(''):'<p class="arc-social-help">Žádný dostupný kontakt neodpovídá hledání.</p>';
  }catch(e){if(seq===searchSeq){box.innerHTML='';notice(e.message);}}
 };
 const refreshFriends=async()=>{
   if(refreshing)return;refreshing=true;
   try{
    const [f,r,o]=await Promise.all([api('friends'),api('requests'),api('sent')]);
    friends=f.items||[];requests=r.items||[];sent=o.items||[];
    renderFriends();renderSent();renderSelected();root.removeAttribute('data-arc-loading');
    const requestsBox=$('[data-arc-social-requests]');
    if(requestsBox)requestsBox.innerHTML=requests.length?requests.map(p=>person(p,'pending')).join(''):'<p class="arc-social-empty">Žádné čekající žádosti.</p>';
    const badge=$('[data-arc-requests-count]');if(badge)badge.textContent=requests.length?`(${requests.length})`:'';
   }catch(e){notice(e.message);root.removeAttribute('data-arc-loading');}finally{refreshing=false;}
 };
 if(root.dataset.arcSocial==='friends'){
  const show=view=>{
   $$('[data-arc-panel]').forEach(panel=>{panel.hidden=panel.dataset.arcPanel!==view;});
   $$('[data-arc-view]').forEach(button=>button.classList.toggle('is-active',button.dataset.arcView===view));
  };
  const showInviteMode=mode=>{
   $$('[data-arc-invite-section]').forEach(panel=>{panel.hidden=panel.dataset.arcInviteSection!==mode;});
   $$('[data-arc-invite-mode]').forEach(button=>button.classList.toggle('is-active',button.dataset.arcInviteMode===mode));
  };
  root.addEventListener('click',async event=>{
   const tab=event.target.closest('[data-arc-view]');
   if(tab){show(tab.dataset.arcView);if(tab.dataset.arcView==='invite')showInviteMode('community');await refreshFriends();return;}
   const mode=event.target.closest('[data-arc-invite-mode]');
   if(mode){showInviteMode(mode.dataset.arcInviteMode);return;}
   const suggestion=event.target.closest('[data-arc-select-contact]');
   if(suggestion){
    const id=Number(suggestion.dataset.arcSelectContact);
    selected=matches.find(p=>Number(p.id)===id)||null;
    $('[data-arc-suggestions]').replaceChildren();renderSelected();return;
   }
   const btn=event.target.closest('[data-friend-action]');if(!btn)return;
   const action=btn.dataset.friendAction,id=Number(btn.dataset.id);if(!id)return;
   if(action==='disconnect'&&!confirm('Odebrat tohoto přítele?'))return;
   btn.disabled=true;
   try{
    const result=await api('invite',{method:'POST',body:JSON.stringify({action,target_id:id})});
    if(Number(result.target_id)!==id)throw Error('Adresát potvrzený serverem se neshoduje. Zkontroluj žádosti.');
    notice(result.message||'Hotovo.');
    if(action==='request')btn.textContent='Odesláno';
    await refreshFriends();
   }catch(e){notice(e.message);}finally{btn.disabled=false;}
  });
  $('[data-arc-friends-filter]')?.addEventListener('input',renderFriends);
  const field=$('[data-arc-search-input]');
  field?.addEventListener('input',()=>{clearTimeout(searchTimer);searchTimer=setTimeout(findSuggestions,250);});
  $('[data-arc-suggestions]').innerHTML='<p class="arc-social-help">Začni psát jméno nebo e-mail.</p>';
  const issue=async()=>{const data=await api('invite-link',{method:'POST',body:'{}'});return data.url;};
  const action=async(kind)=>{
   try{
    const url=await issue(),message='Přidej se ke mně do sportovní komunity Adrenaline Live: '+url;
    if(kind==='copy'){await navigator.clipboard.writeText(message);notice('Odkaz na pozvánku byl zkopírován.');}
    else if(kind==='whatsapp')window.location.href='https://wa.me/?text='+encodeURIComponent(message);
    else window.location.href='mailto:?subject='+encodeURIComponent('Pozvánka do Adrenaline Live')+'&body='+encodeURIComponent(message);
   }catch(e){notice(e.message);}
  };
  $('[data-arc-invite-copy]')?.addEventListener('click',()=>action('copy'));
  $('[data-arc-invite-whatsapp]')?.addEventListener('click',()=>action('whatsapp'));
  $('[data-arc-invite-email]')?.addEventListener('click',()=>action('email'));
  $('[data-arc-invite-email-form]')?.addEventListener('submit',async e=>{
   e.preventDefault();const form=e.currentTarget,button=form.querySelector('[type="submit"]');button.disabled=true;
   try{const result=await api('invite',{method:'POST',body:JSON.stringify({email:form.elements.email.value.trim()})});form.reset();notice(result.message||'Pozvánka zpracována.');await refreshFriends();}
   catch(error){notice(error.message);}finally{button.disabled=false;}
  });
  document.addEventListener('arc:notifications-updated',refreshFriends);
  document.addEventListener('visibilitychange',()=>{if(!document.hidden)refreshFriends();});
  // Lightweight, private check so two people can accept an invitation while both are on the page.
  window.setInterval(()=>{if(!document.hidden)refreshFriends();},12000);
  refreshFriends();
 }
 if(root.dataset.arcSocial==='notifications'){
  const render=()=>{
   const arr=notifyFilter==='unread'?notices.filter(n=>!n.read):notices;
   const list=$('[data-arc-social-notices]');if(!arr.length){list.innerHTML='<p class="arc-social-empty">Zatím žádná oznámení v této kategorii.</p>';return;}
   list.innerHTML=arr.map((n,i)=>`${i===0||Boolean(arr[i-1].read)!==Boolean(n.read)?`<h3 class="arc-social-notice-group">${n.read?'Starší':'Nové'}</h3>`:''}<article class="arc-social-notice ${n.read?'is-read':'is-unread'}" data-notice-id="${Number(n.id)}"><div class="arc-social-notice-icon">${n.avatar?`<img src="${esc(n.avatar)}" alt="">`:'<span aria-hidden="true">AR</span>'}</div><div class="arc-social-notice-body"><a href="${esc(n.url)}" data-arc-open-notice="${Number(n.id)}">${esc(n.message)}</a><time>${esc(n.date)}</time>${n.type==='invite'?`<div class="arc-social-notice-actions"><button class="arc-social-btn arc-social-primary" data-notice-action="accept" data-actor="${Number(n.actor_id)}">Přijmout</button><button class="arc-social-btn" data-notice-action="decline" data-actor="${Number(n.actor_id)}">Odmítnout</button></div>`:''}<div class="arc-social-notice-tools">${n.read?'':`<button type="button" class="arc-social-btn" data-arc-notice-read="${Number(n.id)}" aria-label="Označit oznámení jako přečtené">✓ Přečtené</button>`}<button type="button" class="arc-social-btn arc-social-delete" data-arc-notice-delete="${Number(n.id)}" aria-label="Smazat oznámení" title="Smazat oznámení">✕</button></div></div>${n.read?'':'<i class="arc-social-unread-dot" aria-label="Nepřečtené"></i>'}</article>`).join('');
  };
  const refresh=async()=>{try{const data=await api('notifications');notices=data.items||[];render();root.removeAttribute('data-arc-loading');}catch(e){notice(e.message);root.removeAttribute('data-arc-loading');}};
  root.addEventListener('click',async e=>{
   const filter=e.target.closest('[data-arc-notify-filter]');if(filter){notifyFilter=filter.dataset.arcNotifyFilter;$$('[data-arc-notify-filter]').forEach(b=>b.classList.toggle('is-active',b===filter));render();return;}
   const read=e.target.closest('[data-arc-notice-read]');
   if(read){read.disabled=true;try{await api('notifications/read',{method:'POST',body:JSON.stringify({id:Number(read.dataset.arcNoticeRead)})});await refresh();document.dispatchEvent(new CustomEvent('arc:notifications-read'));}catch(error){notice(error.message);read.disabled=false;}return;}
   const remove=e.target.closest('[data-arc-notice-delete]');
   if(remove){remove.disabled=true;try{await api('notifications/delete',{method:'POST',body:JSON.stringify({id:Number(remove.dataset.arcNoticeDelete)})});await refresh();document.dispatchEvent(new CustomEvent('arc:notifications-read'));}catch(error){notice(error.message);remove.disabled=false;}return;}
   const act=e.target.closest('[data-notice-action]');if(act){act.disabled=true;try{await api('invite',{method:'POST',body:JSON.stringify({action:act.dataset.noticeAction,target_id:Number(act.dataset.actor)})});notice('Žádost vyřízena.');await refresh();document.dispatchEvent(new CustomEvent('arc:notifications-read'));}catch(error){notice(error.message);act.disabled=false;}return;}
   const link=e.target.closest('[data-arc-open-notice]');if(link){e.preventDefault();try{await api('notifications/read',{method:'POST',body:JSON.stringify({id:Number(link.dataset.arcOpenNotice)})});}catch(_){}window.location.href=link.href;return;}
  });
  $('[data-arc-notify-readall]').addEventListener('click',async e=>{e.currentTarget.disabled=true;try{await api('notifications/read',{method:'POST',body:'{}'});await refresh();document.dispatchEvent(new CustomEvent('arc:notifications-read'));notice('Oznámení označena jako přečtená.');}catch(err){notice(err.message);}finally{e.currentTarget.disabled=false;}});
  document.addEventListener('arc:notifications-updated',refresh);
  refresh();
 }
})();
