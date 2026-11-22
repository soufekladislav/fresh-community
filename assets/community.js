(()=>{'use strict';
  if(typeof ARC==='undefined') return;
  const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const guestKey='arc_guest_favorites_v1';
  const guestRead=()=>{try {const a=JSON.parse(localStorage.getItem(guestKey)||'[]');return Array.isArray(a)?[...new Set(a.map(Number).filter(x=>Number.isInteger(x)&&x>0))].slice(0,60):[];}catch{return [];}};
  const guestSave=a=>{try{localStorage.setItem(guestKey,JSON.stringify(a.slice(0,60)));}catch{}};
  const api=async(path,opts={})=>{
    const response=await fetch(ARC.root+path,{credentials:'same-origin',cache:'no-store',...opts,
      headers:{'Content-Type':'application/json','X-WP-Nonce':ARC.nonce,...(opts.headers||{})}});
    const data=await response.json().catch(()=>({}));
    if(!response.ok) throw new Error(data.message||`Chyba ${response.status}`);
    return data;
  };
  const setStatus=(el,message,error=false)=>{if(!el)return;el.textContent=message;el.classList.toggle('arc-error',error);};
  const syncFavoriteButtons=ids=>document.querySelectorAll('[data-arc-favorite]').forEach(btn=>{
    const active=ids.includes(Number(btn.dataset.arcFavorite));btn.setAttribute('aria-pressed',String(active));
    const label=active?'Odebrat z oblíbených':'Přidat do oblíbených';
    btn.setAttribute('aria-label',label);btn.title=label;
    const symbol=btn.querySelector('[aria-hidden="true"]');
    if(symbol)symbol.textContent=active?'♥':'♡';else btn.textContent=active?'♥':'♡';
    const word=btn.querySelector('.arc-fav-word');if(word)word.textContent=active?' Odebrat':' Uložit';
  });
  const broadcastFavorites=ids=>{window.ARC_FAVORITES_IDS=ids;window.dispatchEvent(new CustomEvent('arc:favorites-change',{detail:{ids}}));};
  window.ARCRefreshFavoriteButtons=syncFavoriteButtons;
  const getServerFavorites=async()=>{const list=await api('favorites');return (list.items||[]).map(i=>Number(i.product_id));};
  const mergeGuest=async()=>{
    if(!ARC.loggedIn) return guestRead();
    const pending=guestRead();if(pending.length){await api('favorites/merge',{method:'POST',body:JSON.stringify({ids:pending})});guestSave([]);}
    return getServerFavorites();
  };
  document.addEventListener('click',async e=>{
    const btn=e.target.closest('[data-arc-favorite]');if(!btn)return;
    e.preventDefault();const id=Number(btn.dataset.arcFavorite);btn.disabled=true;
    try{
      if(!ARC.loggedIn){const ids=guestRead();guestSave(ids.includes(id)?ids.filter(x=>x!==id):[...ids,id]);syncFavoriteButtons(guestRead());broadcastFavorites(guestRead());return;}
      const data=await api('favorites/toggle',{method:'POST',body:JSON.stringify({product_id:id})});
      {const ids=(data.collection.items||[]).map(i=>i.product_id);syncFavoriteButtons(ids);broadcastFavorites(ids);}
    }catch(err){alert(err.message);}finally{btn.disabled=false;}
  });
  mergeGuest().then(ids=>{syncFavoriteButtons(ids);broadcastFavorites(ids);}).catch(()=>{syncFavoriteButtons(guestRead());broadcastFavorites(guestRead());});
  const app=document.querySelector('[data-arc-account]');if(!app)return;
  const status=app.querySelector('[data-arc-status]');
  let publicProfile=false;
  const notice=(t,error=false)=>setStatus(status,t,error);
  const local=(sel,t)=>setStatus(app.querySelector(sel),t);
  const reload=async()=>{
    const profile=await api('me');
    publicProfile=!!profile.public;
    app.querySelector('[name="bio"]').value=profile.bio||'';
    const rich=app.querySelector('[data-arc-bio-editor]');if(rich)rich.innerHTML=profile.bio||'';
    app.querySelector('[name="alias"]').value=profile.alias&&profile.alias.startsWith('Člen #')?'':(profile.alias||'');
    app.querySelector('[name="interests"]').value=profile.interests||'';
    for(const name of ['sport_relation','sport_profession','sport_experience']){
      const field=app.querySelector('[name="'+name+'"]');if(field)field.value=profile[name]||'';
    }
    app.querySelector('[name="visibility"]').value=profile.visibility||'private';
    const optin=app.querySelector('[name="marketing_optin"]');if(optin)optin.checked=!!profile.marketing_optin;
    const email=app.querySelector('[name="email_notifications"]');if(email)email.checked=profile.email_notifications!==false;
  };
  app.querySelector('[data-arc-profile]').addEventListener('submit',async e=>{
    e.preventDefault();const f=e.currentTarget;const old=publicProfile;
    try{
      const result=await api('me',{method:'POST',body:JSON.stringify({alias:f.elements.namedItem('alias').value,bio:app.querySelector('[data-arc-bio-editor]')?.innerHTML||f.elements.namedItem('bio').value,interests:f.elements.namedItem('interests').value,sport_relation:f.elements.namedItem('sport_relation')?.value||'',sport_profession:f.elements.namedItem('sport_profession')?.value||'',sport_experience:f.elements.namedItem('sport_experience')?.value||'',visibility:f.elements.namedItem('visibility').value,marketing_optin:!!f.elements.namedItem('marketing_optin')?.checked,email_notifications:!!f.elements.namedItem('email_notifications')?.checked})});
      if(typeof window.ARCUploadSelectedAvatar==='function')await window.ARCUploadSelectedAvatar();
      await reload();publicProfile=!!result.public;notice('Profil byl uložen a znovu načten.');local('[data-arc-profile-saved]','✓ Uloženo');
      if(old&&!publicProfile) notice('Profil je soukromý. Veřejné balíčky už nejsou návštěvníkům dostupné.');
    }catch(err){local('[data-arc-profile-saved]',err.message);notice(err.message,true);}
  });
  app.querySelector('[data-arc-copy-profile]')?.addEventListener('click',async e=>{
    const url=app.querySelector('#arc-share-url')?.value||'';if(!url)return;
    const btn=e.currentTarget;try{await navigator.clipboard.writeText(url);btn.textContent='✓ Zkopírováno';}
    catch{const input=app.querySelector('#arc-share-url');input?.select();btn.textContent='Označený odkaz zkopíruj';}
  });
  reload().catch(err=>notice('Načítání profilu selhalo: '+err.message,true));
})();
