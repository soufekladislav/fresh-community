/* AR Community beta10: one set of collections, now managed from /ulozene/. */
(()=>{'use strict';
const root=document.querySelector('[data-arc-kits]');if(!root||typeof ARC==='undefined')return;
const cfg=ARC;
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const api=async(path,opts={})=>{
 const res=await fetch(cfg.root+path,{method:'GET',credentials:'same-origin',cache:'no-store',...opts,
  headers:{'Content-Type':'application/json','X-WP-Nonce':cfg.nonce,...(opts.headers||{})}});
 const d=await res.json().catch(()=>({}));if(!res.ok)throw Error(d.message||'Požadavek se nepodařilo dokončit.');return d;
};
const q=sel=>root.querySelector(sel), status=(message,error=false)=>{const p=q('[data-arc-kit-status]');p.textContent=message;p.classList.toggle('is-error',error);};
const list=q('[data-arc-kit-list]'),editor=q('[data-arc-kit-editor]'),share=q('[data-arc-kit-share]');
let collections=[],opened=0,activeEdit=0,activeShare=0,latestProducts=new Map();
const kit=id=>collections.find(x=>Number(x.id)===Number(id));
const publicKit=c=>c.visibility==='public';
const productCard=p=>`<a href="${esc(p.url)}" class="arc-kit-product" title="${esc(p.title)}"><span>${p.image?`<img loading="lazy" src="${esc(p.image)}" alt="">`:'AR'}</span><strong>${esc(p.title)}</strong><small>${esc(p.price_html||'Cena na webu')}</small></a>`;
const fetchProducts=async c=>{
 const ids=(c.items||[]).map(i=>Number(i.product_id)).filter(Number.isInteger);
 const missing=ids.filter(id=>!latestProducts.has(id));
 if(missing.length){const response=await api('products?ids='+encodeURIComponent(missing.join(',')));
  for(const p of response.items||[])latestProducts.set(Number(p.id),p);}
 return ids.map(id=>latestProducts.get(id)).filter(Boolean);
};
const draw=()=>{
 const kits=collections.filter(x=>x.kind==='kit');
 list.innerHTML=kits.length?kits.map(c=>`<article class="arc-kit-card" data-arc-kit-card="${c.id}">
  <div class="arc-kit-header"><div><strong>${esc(c.title)}</strong><small>${(c.items||[]).length} produktů · ${publicKit(c)?'Veřejný':'Soukromý'}</small></div>
  <button type="button" data-kit-toggle="${c.id}" aria-expanded="${opened===c.id}">${opened===c.id?'Sbalit':'Rozbalit'} <span aria-hidden="true">${opened===c.id?'⌃':'⌄'}</span></button></div>
  <div class="arc-kit-actions"><button type="button" data-kit-edit="${c.id}" title="Správa balíčku">✎ <span>Upravit</span></button>
  <button type="button" data-kit-friends="${c.id}" ${publicKit(c)?'':'disabled title="Nejprve zveřejni balíček i svůj profil"'} title="Sdílet přátelům">♧ <span>Přátelům</span></button>
  <button type="button" data-kit-email="${c.id}" ${publicKit(c)?'':'disabled'} title="Poslat e-mailem">✉</button>
  <button type="button" data-kit-wa="${c.id}" ${publicKit(c)?'':'disabled'} title="Poslat přes WhatsApp">◉</button>
  </div><div class="arc-kit-carousel" data-kit-carousel="${c.id}" ${opened===c.id?'':'hidden'}><p class="arc-small">Načítám produkty…</p></div>
  </article>`).join(''):'<p class="arc-small">Zatím nemáš vytvořený žádný balíček. Založ si svůj první.</p>';
 if(opened){const c=kit(opened);if(c)drawCarousel(c);}
};
const drawCarousel=async c=>{
 const node=q('[data-kit-carousel="'+c.id+'"]');if(!node)return;
 try{const products=await fetchProducts(c);if(!node.isConnected)return;
 node.innerHTML=products.length?`<div class="arc-kit-slider-controls"><button type="button" data-kit-prev aria-label="Předchozí produkty">‹</button><button type="button" data-kit-next aria-label="Další produkty">›</button></div><div class="arc-kit-slider" tabindex="0" aria-label="Produkty balíčku ${esc(c.title)}">${products.map(productCard).join('')}</div><a class="arc-kit-view" href="/ulozene/">Upravit výběr →</a>`:'<p class="arc-small">Tento balíček zatím neobsahuje produkty.</p>';
 node.querySelector('.arc-kit-view')?.addEventListener('click',e=>{e.preventDefault();editKit(c.id);});
 for(const [selector,direction] of [['[data-kit-prev]',-1],['[data-kit-next]',1]]){
  node.querySelector(selector)?.addEventListener('click',()=>node.querySelector('.arc-kit-slider')?.scrollBy({left:direction*240,behavior:'smooth'}));
 }
 }catch(err){node.textContent=err.message;}
};
const load=async()=>{
 const res=await api('collections');collections=res.items||[];draw();
 if(activeEdit){const c=kit(activeEdit);if(c)displayEditor(c);else editor.hidden=true;}
};
const displayEditor=async c=>{
 activeEdit=Number(c.id);editor.hidden=false;
 q('[data-arc-kit-edit-title]').textContent='Upravit balíček: '+c.title;
 const form=q('[data-arc-kit-settings]');form.elements.title.value=c.title;form.elements.public.checked=publicKit(c);
 const box=q('[data-arc-kit-items]');box.textContent='Načítám položky…';
 try{const products=await fetchProducts(c);box.innerHTML=(c.items||[]).map(i=>{
  const p=products.find(x=>Number(x.id)===Number(i.product_id));return `<div class="arc-kit-edit-row"><span>${esc(p?.title||'Produkt není dostupný')}</span><button type="button" data-kit-remove="${Number(i.product_id)}" title="Odebrat">×</button></div>`;
 }).join('')||'<p class="arc-small">Balíček je prázdný.</p>';
 }catch(e){box.textContent=e.message;}
};
const editKit=id=>{const c=kit(id);if(c){displayEditor(c);share.hidden=true;editor.scrollIntoView({behavior:'smooth',block:'nearest'});}};
if(!cfg.loggedIn){root.innerHTML='<h3>Moje balíčky</h3><p>Pro správu balíčků se přihlas.</p>';return;}
q('[data-arc-kit-new]')?.addEventListener('submit',async e=>{
 e.preventDefault();const f=e.currentTarget;const name=f.elements.title.value.trim();if(!name)return;
 const b=f.querySelector('[type=submit]');b.disabled=true;
 try{const c=await api('collections',{method:'POST',body:JSON.stringify({title:name,visibility:'private'})});f.reset();await load();editKit(c.id);status('Balíček vytvořen.');}
 catch(e){status(e.message,true);}finally{b.disabled=false;}
});
list.addEventListener('click',async e=>{
 const b=e.target.closest('button');if(!b)return;
 const id=Number(b.dataset.kitToggle||b.dataset.kitEdit||b.dataset.kitFriends||b.dataset.kitEmail||b.dataset.kitWa);
 if(!id)return;
 if(b.hasAttribute('data-kit-toggle')){opened=opened===id?0:id;draw();}
 if(b.hasAttribute('data-kit-edit'))editKit(id);
 if(b.hasAttribute('data-kit-friends'))openShare(id);
 if(b.hasAttribute('data-kit-email'))openExternal(id,'email');
 if(b.hasAttribute('data-kit-wa'))openExternal(id,'whatsapp');
});
q('[data-arc-kit-close]')?.addEventListener('click',()=>{editor.hidden=true;activeEdit=0;});
q('[data-arc-kit-settings]')?.addEventListener('submit',async e=>{
 e.preventDefault();if(!activeEdit)return;
 const f=e.currentTarget;const b=f.querySelector('[type=submit]');b.disabled=true;
 try{await api('collections/'+activeEdit,{method:'POST',body:JSON.stringify({title:f.elements.title.value,visibility:f.elements.public.checked?'public':'private'})});
  latestProducts.clear();await load();status('Nastavení balíčku uloženo.');}
 catch(err){status(err.message,true);}finally{b.disabled=false;}
});
q('[data-arc-kit-delete]')?.addEventListener('click',async()=>{
 if(!activeEdit||!confirm('Opravdu odstranit balíček? Produkty v obchodě ani oblíbené se nesmažou.'))return;
 try{await api('collections/'+activeEdit,{method:'DELETE'});activeEdit=0;editor.hidden=true;opened=0;await load();status('Balíček odstraněn.');}
 catch(err){status(err.message,true);}
});
q('[data-arc-kit-search]')?.addEventListener('submit',async e=>{
 e.preventDefault();const query=e.currentTarget.elements.query.value.trim();if(query.length<2)return;
 const box=q('[data-arc-kit-results]');box.textContent='Hledám…';
 try{const d=await api('products?search='+encodeURIComponent(query));box.innerHTML=(d.items||[]).map(p=>`<div class="arc-kit-edit-row"><span>${esc(p.title)}</span><button data-kit-add="${Number(p.id)}" type="button">＋ Přidat</button></div>`).join('')||'<p class="arc-small">Žádné výsledky.</p>';}
 catch(err){box.textContent=err.message;}
});
root.addEventListener('click',async e=>{
 const b=e.target.closest('[data-kit-add],[data-kit-remove]');if(!b||!activeEdit)return;
 const id=Number(b.dataset.kitAdd||b.dataset.kitRemove);b.disabled=true;
 try{if(b.hasAttribute('data-kit-add'))await api('collections/'+activeEdit+'/items',{method:'POST',body:JSON.stringify({product_id:id,qty:1})});
 else await api('collections/'+activeEdit+'/items/'+id,{method:'DELETE'});
 latestProducts.clear();await load();status('Složení balíčku bylo aktualizováno.');}
 catch(err){status(err.message,true);b.disabled=false;}
});
const openShare=async id=>{
 activeShare=id;share.hidden=false;editor.hidden=true;activeEdit=0;
 const box=q('[data-arc-kit-contacts]');box.textContent='Načítám přátele…';
 try{const result=await api('live/friends');box.innerHTML=(result.items||[]).filter(p=>!p.hidden).map(p=>
  `<label class="arc-kit-friend"><input type="checkbox" value="${Number(p.id)}">${p.avatar?`<img src="${esc(p.avatar)}" alt="">`:'<span class="arc-kit-friend-empty">AR</span>'}<span>${esc(p.alias)}</span></label>`).join('')||'<p class="arc-small">Zatím nemáš potvrzené přátele.</p>';}
 catch(err){box.textContent=err.message;}
 share.scrollIntoView({behavior:'smooth',block:'nearest'});
};
q('[data-arc-kit-share-close]')?.addEventListener('click',()=>{share.hidden=true;activeShare=0;});
q('[data-arc-kit-send]')?.addEventListener('click',async e=>{
 const targets=[...share.querySelectorAll('input:checked')].map(n=>Number(n.value));
 if(!targets.length||targets.length>5){status('Vyber 1 až 5 potvrzených přátel.',true);return;}
 const b=e.currentTarget;b.disabled=true;
 try{const res=await api('collections/'+activeShare+'/share',{method:'POST',body:JSON.stringify({mode:'friends',target_ids:targets})});
  if(!res.ok)throw Error(res.message||'Odeslání se nepodařilo dokončit.');
  status('Balíček sdílen '+Number(res.sent)+' přátelům; každý dostane upozornění.');share.hidden=true;activeShare=0;}
 catch(err){status(err.message,true);}finally{b.disabled=false;}
});
const openExternal=async(id,channel)=>{
 try{status('Připravuji veřejný odkaz na zeď…');
  const res=await api('collections/'+id+'/share',{method:'POST',body:JSON.stringify({mode:'wall'})});
  const c=kit(id),url=String(res.url||'');if(!url||!c)throw Error('Odkaz se nepodařilo vytvořit.');
  const body='Podívej se na můj balíček vybavení na Adrenaline Race: '+c.title+' '+url;
  if(channel==='email')location.href='mailto:?subject='+encodeURIComponent('Balíček výbavy z Adrenaline Race')+'&body='+encodeURIComponent(body);
  else location.href='https://wa.me/?text='+encodeURIComponent(body);
  status('Odkaz na veřejný příspěvek je připravený.');
 }catch(err){status(err.message,true);}
};
load().catch(e=>status('Balíčky se nepodařilo načíst: '+e.message,true));
})();
