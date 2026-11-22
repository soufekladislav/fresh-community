(()=>{'use strict';
if(!window.ARC)return;
const cfg=window.ARC;
const escapeHtml=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const guestIds=()=>{try{const rows=JSON.parse(localStorage.getItem('arc_guest_favorites_v1')||'[]');return Array.isArray(rows)?[...new Set(rows.map(Number).filter(Number.isInteger))].filter(id=>id>0).slice(0,60):[];}catch{return []}};
const api=async(path,opt={})=>{
 const headers={'X-WP-Nonce':cfg.nonce,...(opt.body?{'Content-Type':'application/json'}:{})};
 const res=await fetch(cfg.root+path,{credentials:'same-origin',cache:'no-store',...opt,headers});
 const data=await res.json().catch(()=>({}));if(!res.ok)throw Error(data.message||'Požadavek nebylo možné dokončit.');return data;
};
const lists=[...document.querySelectorAll('[data-arc-favorites-list]')];
let activeIds=null;let seq=0;
const readIds=async()=>{
 if(!cfg.loggedIn)return guestIds();
 const data=await api('favorites');return (data.items||[]).map(p=>Number(p.product_id)).filter(Number.isInteger);
};
const refreshIcon=ids=>document.querySelectorAll('[data-arc-fav-count]').forEach(el=>{
 el.textContent=String(ids.length);el.hidden=!ids.length;
});
const draw=async ids=>{
 activeIds=[...new Set(ids.map(Number).filter(id=>id>0))];refreshIcon(activeIds);
 if(!lists.length)return;
 const ticket=++seq;
 if(!activeIds.length){lists.forEach(el=>el.innerHTML='<p class="arc-small">Zatím tu nemáš žádné oblíbené produkty. V obchodě klepni na ♡ u produktu.</p>');return;}
 try{
  const result=await api('products?ids='+encodeURIComponent(activeIds.join(',')));
  if(ticket!==seq)return;
  const lookup=new Map((result.items||[]).map(p=>[Number(p.id),p]));
  lists.forEach(box=>{
   const limit=Math.max(1,Number(box.dataset.limit||60));
   const products=activeIds.slice(0,limit).map(id=>lookup.get(id)).filter(Boolean);
   box.innerHTML=products.length?products.map(p=>`<article class="arc-saved-product">
    <a href="${escapeHtml(p.url)}" class="arc-saved-product-link">${p.image?`<img loading="lazy" src="${escapeHtml(p.image)}" alt="">`:'<span class="arc-saved-no-photo">AR</span>'}
    <strong>${escapeHtml(p.title)}</strong><span class="arc-saved-product-price">${escapeHtml(p.price_html||'Cena na webu')}</span></a>
    <button type="button" class="arc-saved-favorite-toggle arc-fav arc-fav-icon" data-arc-favorite="${Number(p.id)}" aria-pressed="true" aria-label="Odebrat z oblíbených" title="Odebrat z oblíbených"><span aria-hidden="true">♥</span></button>
   </article>`).join(''):'<p class="arc-small">Žádný z uložených produktů není nyní dostupný.</p>';
  });
 }catch(_){if(ticket===seq)lists.forEach(box=>box.innerHTML='<p class="arc-small">Oblíbené produkty nelze načíst. Zkus obnovit stránku.</p>');}
};
window.addEventListener('arc:favorites-change',e=>{draw(e.detail?.ids||[])});
if(lists.length||document.querySelector('[data-arc-fav-count]'))readIds().then(draw).catch(()=>draw(guestIds()));
document.addEventListener('click',async e=>{
 const btn=e.target.closest('[data-arc-unsave-post]');if(!btn)return;
 e.preventDefault();
 const type=btn.dataset.arcUnsavePost,id=Number(btn.dataset.postId);
 if(!cfg.loggedIn || !['article','community'].includes(type) || !id)return;
 btn.disabled=true;btn.setAttribute('aria-busy','true');
 try{
  const result=await api('live/items/'+type+'/'+id+'/save',{method:'POST'});
  if(result.saved)throw Error('Příspěvek se nepodařilo odebrat.');
  const item=btn.closest('[data-arc-saved-post-row]');item?.remove();
  document.querySelectorAll('[data-arc-saved-posts]').forEach(list=>{
   if(!list.querySelector('[data-arc-saved-post-row]'))list.innerHTML='<p class="arc-small">Zatím tu nemáš žádné další uložené příspěvky.</p>';
  });
 }catch(ex){btn.disabled=false;btn.removeAttribute('aria-busy');alert(ex.message);}
});
})();
