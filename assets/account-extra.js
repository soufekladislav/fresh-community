/* AR Community 0.2.0-beta.3 — account profile, avatar and explicit social invitations. */
(()=>{'use strict';
  if(typeof ARC==='undefined')return;
  const app=document.querySelector('[data-arc-account]')||document.querySelector('[data-arc-friends-page]');if(!app)return;
  const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const api=async(path,options={})=>{
    const headers={'X-WP-Nonce':ARC.nonce,...options.headers};
    if(options.body && !(options.body instanceof FormData))headers['Content-Type']='application/json';
    const r=await fetch(ARC.root+'live/'+path,{credentials:'same-origin',cache:'no-store',...options,headers});
    const data=await r.json().catch(()=>({}));if(!r.ok)throw Error(data.message||'Požadavek selhal.');return data;
  };
  const status=(sel,text)=>{const n=app.querySelector(sel);if(n)n.textContent=text;};
  const acceptedBox=app.querySelector('[data-arc-friends]');
  const refresh=async()=>{
    try{
      const [friends,requests]=await Promise.all([api('friends'),api('requests')]);
      acceptedBox.innerHTML=(friends.items||[]).slice(0,10).map(p=>`<div class="arc-personline">${p.hidden?`<span>${esc(p.alias)} · neveřejný profil</span>`:`<a href="${esc(p.url)}">${p.avatar?`<img class="arc-friend-avatar" src="${esc(p.avatar)}" alt="">`:'<span class="arc-friend-avatar arc-friend-placeholder">AR</span>'}${esc(p.alias)}</a>`}</div>`).join('') || '<p class="arc-small">Zatím nemáš potvrzené přátele.</p>';
    }catch(e){status('[data-arc-friends-status]',e.message);}
  };
  const results=app.querySelector('[data-arc-friend-results]');
  const searchForm=app.querySelector('[data-arc-friend-search]');
  const queryInput=searchForm?.elements.namedItem('query');
  let debounce=0,searchToken=0;
  const drawResults=(list)=>{
    results.innerHTML=(list||[]).map(p=>`<div class="arc-suggestion" role="option" aria-selected="false">${p.avatar?`<img src="${esc(p.avatar)}" alt="" loading="lazy">`:'<span class="arc-suggestion-placeholder" aria-hidden="true">◉</span>'}${p.url?`<a href="${esc(p.url)}">${esc(p.alias)}</a>`:`<span>${esc(p.alias)}</span>`}${p.connected?'<small>Jste přátelé</small>':`<button type="button" class="arc-btn arc-btn-subtle" data-arc-friend-action="request" data-id="${p.id}">Pozvat</button>`}</div>`).join('')||'<p class="arc-small">Žádné dostupné profily. Skryté účty nejsou v nabídce.</p>';
  };
  const search=async()=>{
    const query=String(queryInput?.value||'').trim();if(!results)return;
    const current=++searchToken;
    if(query.length<2){results.replaceChildren();return;}
    results.textContent='Vyhledávám…';
    try{const data=await api('search?q='+encodeURIComponent(query));if(current===searchToken)drawResults(data.items||[]);}
    catch(e){if(current===searchToken)results.textContent=e.message;}
  };
  queryInput?.addEventListener('input',()=>{clearTimeout(debounce);debounce=setTimeout(search,260);});
  queryInput?.addEventListener('keydown',e=>{if(e.key==='Escape'){results.replaceChildren();queryInput.value='';}});
  searchForm?.addEventListener('submit',e=>{e.preventDefault();clearTimeout(debounce);search();});
  app.addEventListener('click',async event=>{
    const button=event.target.closest('[data-arc-friend-action]');if(!button)return;
    const action=button.dataset.arcFriendAction,id=Number(button.dataset.id);
    if(!id)return;button.disabled=true;
    try{
      const data=await api('invite',{method:'POST',body:JSON.stringify({action,target_id:id})});
      status('[data-arc-friends-status]',data.message||'Hotovo.');
      if(action==='request')button.textContent='Pozvánka odeslána';
      else await refresh();
    }catch(e){status('[data-arc-friends-status]',e.message);button.disabled=false;}
  });
  const avatarForm=app.querySelector('[data-arc-avatar-form]');
  const imageInput=avatarForm?.querySelector('[name="avatar"]'),preview=app.querySelector('[data-arc-avatar-preview]');
  app.querySelectorAll('[data-arc-photo-action]').forEach(button=>button.addEventListener('click',()=>{
    if(!imageInput)return;
    if(button.dataset.arcPhotoAction==='camera')imageInput.setAttribute('capture','user');
    else imageInput.removeAttribute('capture');
    imageInput.click();
  }));
  const compressAvatar=async file=>{
    if(!file || !file.type.startsWith('image/'))throw Error('Vyber obrazový soubor.');
    if(file.size>18*1024*1024)throw Error('Vyber fotografii menší než 18 MB.');
    if(!('createImageBitmap' in window)){
      if(file.size<=2*1024*1024)return file;
      throw Error('Prohlížeč nepodporuje zmenšení tak velké fotografie. Vyber menší JPG.');
    }
    let bitmap;try{bitmap=await createImageBitmap(file);}catch(e){if(file.size<=2*1024*1024)return file;throw Error('Fotografii se nepodařilo zpracovat. Zkus JPG nebo WebP.');}
    if(file.size<=2*1024*1024 && Math.max(bitmap.width,bitmap.height)<=1600){bitmap.close?.();return file;}
    const scale=Math.min(1,1600/Math.max(bitmap.width,bitmap.height));
    const canvas=document.createElement('canvas');canvas.width=Math.max(1,Math.round(bitmap.width*scale));canvas.height=Math.max(1,Math.round(bitmap.height*scale));
    canvas.getContext('2d').drawImage(bitmap,0,0,canvas.width,canvas.height);bitmap.close?.();
    let quality=.82;
    while(quality>=.42){const blob=await new Promise(resolve=>canvas.toBlob(resolve,'image/jpeg',quality));if(blob&&blob.size<=2*1024*1024)return new File([blob],'ar-profil.jpg',{type:'image/jpeg'});quality-=.12;}
    throw Error('Fotografii se nepodařilo zmenšit pod 2 MB.');
  };
  imageInput?.addEventListener('change',()=>{
    const f=imageInput.files&&imageInput.files[0];if(!f)return;
    if(!f.type.startsWith('image/')){status('[data-arc-avatar-message]','Vyber obrázek.');imageInput.value='';return;}
    const url=URL.createObjectURL(f);
    const img=document.createElement('img');img.src=url;img.alt='Náhled vybrané fotografie';img.onload=()=>URL.revokeObjectURL(url);
    preview?.replaceChildren(img);
    status('[data-arc-avatar-message]','Fotografie je připravena. Ulož ji společně s údaji tlačítkem Uložit profil.');
  });
  window.ARCUploadSelectedAvatar=async()=>{
    if(!imageInput?.files?.[0])return null;
    const consent=avatarForm.querySelector('[name="consent"]');
    if(!consent?.checked)throw Error('Potvrď upozornění o veřejné adrese fotografie.');
    const data=new FormData();data.set('avatar',await compressAvatar(imageInput.files[0]));data.set('consent','1');
    const uploaded=await api('avatar',{method:'POST',body:data});
    if(uploaded.avatar&&preview){const img=document.createElement('img');img.src=uploaded.avatar+(uploaded.avatar.includes('?')?'&':'?')+'v='+Date.now();img.alt='Profilová fotografie';preview.replaceChildren(img);}
    status('[data-arc-avatar-message]','✓ Fotografie uložena.');
    imageInput.value='';
    document.dispatchEvent(new CustomEvent('arc:profile-photo-updated',{detail:{url:uploaded.avatar}}));
    return uploaded;
  };
  const bioEditor=app.querySelector('[data-arc-bio-editor]');
  if(bioEditor){
    const editorForm=bioEditor.closest('form');
    const fallback=editorForm?.elements.namedItem('bio');
    app.querySelectorAll('[data-arc-rich]').forEach(btn=>{
      btn.addEventListener('mousedown',e=>e.preventDefault());
      btn.addEventListener('click',()=>{
        bioEditor.focus();
        if(btn.dataset.arcRich==='bold')document.execCommand('bold',false,null);
        if(btn.dataset.arcRich==='link'){
          const url=prompt('Vlož HTTPS adresu odkazu (https://...)');
          if(!url)return;
          try{const u=new URL(url);if(u.protocol!=='https:'){alert('Použij adresu https://');return;}}
          catch{alert('Neplatná adresa.');return;}
          document.execCommand('createLink',false,url);
          bioEditor.querySelectorAll('a').forEach(a=>{a.target='_blank';a.rel='noopener noreferrer nofollow';});
        }
        if(fallback)fallback.value=bioEditor.innerHTML;
      });
    });
    bioEditor.addEventListener('input',()=>{if(fallback)fallback.value=bioEditor.innerHTML;});
    editorForm?.addEventListener('submit',()=>{if(fallback)fallback.value=bioEditor.innerHTML;},true);
  }
  // When person uses Google linking, no switching WP account or extra dashboard prompt occurs.
  refresh();
})();
