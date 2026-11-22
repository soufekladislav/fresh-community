(()=>{'use strict';
const app=document.querySelector('[data-arc-live]');if(!app||!window.ARCLIVE)return;
const cfg=window.ARCLIVE;
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const api=async(path,opt={})=>{
 const headers={'X-WP-Nonce':cfg.nonce,...(opt.headers||{})};
 if(opt.body && !(opt.body instanceof FormData))headers['Content-Type']='application/json';
 const r=await fetch(cfg.root+path,{cache:'no-store',credentials:'same-origin',...opt,headers});
 const data=await r.json().catch(()=>({}));if(!r.ok)throw Error(data.message||'Požadavek selhal ('+r.status+').');return data;
};
const el=q=>app.querySelector(q);const feed=el('[data-arc-feed]');
let filter='all',page=1,loading=false,hasMore=false,friendsCache=null,restoringFeed=false;const cardCache=new Map();
// Only the page number, filter and scroll offset survive a magazine-article navigation.
// Never persist social data or rendered private HTML to web storage.
const feedStateKey='arc_feed_return_'+String(cfg.user||0)+'_'+location.pathname;
const rememberFeed=()=>{try{sessionStorage.setItem(feedStateKey,JSON.stringify({page,filter,y:window.scrollY,at:Date.now(),fromArticle:true}));}catch(_){}};
feed.addEventListener('click',e=>{const link=e.target.closest('a[href]');if(!link||!link.closest('[data-arc-card^="article:"]'))return;try{if(new URL(link.href).origin===location.origin)rememberFeed();}catch(_){}});
window.addEventListener('pagehide',()=>{try{if(!sessionStorage.getItem(feedStateKey))return;const state=JSON.parse(sessionStorage.getItem(feedStateKey));state.y=window.scrollY;sessionStorage.setItem(feedStateKey,JSON.stringify(state));}catch(_){}});

const msg=(selector,t)=>{const node=el(selector);if(node)node.textContent=t;};
const day=date=>{const d=new Date(date||'');return Number.isNaN(d.getTime())?esc((date||'').slice(0,10)):esc(d.toLocaleDateString('cs-CZ',{day:'numeric',month:'short',year:'numeric'}));};
const icon=(kind)=>{const strokes={like:'<path d="M7 10v11H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3zm0 0 4.5-7a2 2 0 0 1 3.6 1.1L14 10h5.4a2 2 0 0 1 2 2.5l-1.6 7a2 2 0 0 1-2 1.5H7"/>',comment:'<path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5 9 9 0 0 1-4-.9L3 21l1.9-5.5a9 9 0 0 1-.9-4A8.5 8.5 0 0 1 12.5 3 8.5 8.5 0 0 1 21 11.5z"/>',share:'<path d="m15 8 5-5 3 5-5 5M20 3C18 16 7 6 2 19c5-4 11-3 16-6"/>',save:'<path d="M6 4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18l-6-4-6 4V4z"/>'};return '<svg class="arc-post-action-icon" viewBox="0 0 24 24" width="19" height="19" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round">'+(strokes[kind]||strokes.like)+'</svg>';};
const avatar=(p)=>p.avatar?`<img src="${esc(p.avatar)}" class="arc-post-avatar" alt="">`:'<span class="arc-post-avatar arc-avatar-fallback" aria-hidden="true">AR</span>';
const badge=(p)=>p.visibility==='private'?'Jen já':p.visibility==='network'?'Přátelé':p.visibility==='friend_wall'?'Zeď přítele':'Veřejně';
const mediaImage=p=>p.image?`<div class="arc-post-media"><img loading="lazy" src="${esc(p.image)}" alt="Fotografie příspěvku"></div>`:'';
const videoInfo=url=>{
 try{const u=new URL(url);const host=u.hostname.toLowerCase().replace(/^www\./,'');let id='';let vertical=false;
  if(host==='youtu.be')id=u.pathname.split('/')[1]||'';
  else if(['youtube.com','m.youtube.com','youtube-nocookie.com'].includes(host)){
   if(u.pathname==='/watch')id=u.searchParams.get('v')||'';
   else if(/^\/(shorts|embed|live)\//.test(u.pathname)){vertical=u.pathname.startsWith('/shorts/');id=u.pathname.split('/')[2]||'';}
  }
  return /^[a-zA-Z0-9_-]{11}$/.test(id)?{id,vertical}:null;
 }catch(_){return null;}
};
const mediaVideo=p=>{
 if(!p.video)return '';
 const info=videoInfo(p.video);
 if(!info)return `<p class="arc-post-video-link"><a href="${esc(p.video)}" rel="noopener noreferrer" target="_blank">Otevřít video na YouTube ↗</a></p>`;
 return `<div class="arc-post-video ${info.vertical?'is-vertical':''}" data-arc-video-id="${info.id}" aria-label="YouTube video">
 <div class="arc-video-stage"><button type="button" data-arc-start-video aria-label="Přehrát YouTube video"><img loading="lazy" src="https://i.ytimg.com/vi/${info.id}/hqdefault.jpg" alt="Náhled videa"><span>▶ Přehrát video</span></button></div>
 <button class="arc-video-sound" data-arc-video-sound type="button" aria-label="Zapnout zvuk videa" hidden>Zapnout zvuk</button>
 </div>`;
};
// One video player for the entire feed, never multiple concurrent media streams.
// The previous iframe is destroyed (rather than just messaged to pause), so it cannot continue playing.
let currentVideo=null,videoObserver=null,videoRaf=0;
const reduceMotion=window.matchMedia?.('(prefers-reduced-motion: reduce)').matches||false;
const saveData=!!navigator.connection?.saveData;
const canAutoPlay=!reduceMotion&&!saveData;
const videoThumb=box=>box?.querySelector('.arc-video-stage');
const videoStop=()=>{
 if(!currentVideo)return;
 const stage=videoThumb(currentVideo);
 if(stage)stage.innerHTML=`<button type="button" data-arc-start-video aria-label="Přehrát YouTube video"><img loading="lazy" src="https://i.ytimg.com/vi/${currentVideo.dataset.arcVideoId}/hqdefault.jpg" alt="Náhled videa"><span>▶ Přehrát video</span></button>`;
 const sound=currentVideo.querySelector('[data-arc-video-sound]');if(sound){sound.hidden=true;sound.textContent='Zapnout zvuk';}
 currentVideo=null;
};
const videoPlay=(box,withSound=false)=>{
 if(!box||!box.isConnected)return;
 if(currentVideo===box)return;
 videoStop();
 const id=box.dataset.arcVideoId;
 if(!/^[a-zA-Z0-9_-]{11}$/.test(id))return;
 const stage=videoThumb(box);if(!stage)return;
 const iframe=document.createElement('iframe');
 iframe.title='Video z YouTube';iframe.allowFullscreen=true;
 iframe.referrerPolicy='strict-origin-when-cross-origin';
 iframe.allow='autoplay; encrypted-media; picture-in-picture; gyroscope; accelerometer; web-share';
 iframe.src='https://www.youtube-nocookie.com/embed/'+encodeURIComponent(id)+'?autoplay=1&mute='+(withSound?'0':'1')+'&playsinline=1&controls=1&enablejsapi=1&origin='+encodeURIComponent(location.origin);
 stage.replaceChildren(iframe);
 currentVideo=box;
 const sound=box.querySelector('[data-arc-video-sound]');if(sound){sound.hidden=withSound;sound.textContent='Zapnout zvuk';}
};
const pickVideo=()=>{
 videoRaf=0;
 if(document.hidden){videoStop();return;}
 // A manual play remains active only while it is visible in the upper half.
 const topEnd=window.innerHeight*.55;
 let chosen=null,best=0;
 feed.querySelectorAll('[data-arc-video-id]').forEach(box=>{
  const r=box.getBoundingClientRect();
  const visible=Math.max(0,Math.min(r.bottom,topEnd)-Math.max(r.top,0));
  if(visible<Math.min(60,r.height*.2))return;
  const score=visible/Math.min(r.height,topEnd);
  if(score>best){best=score;chosen=box;}
 });
 if(currentVideo && currentVideo!==chosen)videoStop();
 if(!currentVideo && chosen && canAutoPlay)videoPlay(chosen,false);
};
const scheduleVideo=()=>{if(!videoRaf)videoRaf=requestAnimationFrame(pickVideo);};
if('IntersectionObserver' in window){
 videoObserver=new IntersectionObserver(scheduleVideo,{rootMargin:'0px 0px -45% 0px',threshold:[0,.1,.25,.5,.75,1]});
}
const refreshVideo=()=>{videoObserver?.disconnect();feed.querySelectorAll('[data-arc-video-id]').forEach(box=>videoObserver?.observe(box));scheduleVideo();};
window.addEventListener('scroll',scheduleVideo,{passive:true});
window.addEventListener('resize',scheduleVideo,{passive:true});
document.addEventListener('visibilitychange',scheduleVideo);
const actions=p=>{
 const type=p.type==='article'?'article':'community';const id=Number(p.id);
 const comments=Number(p.type==='article'?p.comment_count:(p.comment_count??p.comments?.length??0));
 return `<div class="arc-post-actions" aria-label="Akce příspěvku">
 <button type="button" data-arc-react="${id}" data-arc-type="${type}" title="To se mi líbí" aria-label="To se mi líbí (${Number(p.reactions||0)})" aria-pressed="${p.reacted?'true':'false'}" ${cfg.loggedIn?'':'disabled'}>${icon('like')} <span class="arc-action-count">(${Number(p.reactions||0)})</span><small data-arc-like-label class="arc-screenreader-only">Líbí se</small></button>
 ${p.type==='article'?`<a class="arc-action-link" title="Komentáře pod článkem" aria-label="Komentáře (${comments})" href="${esc(p.url)}${p.comments_enabled?'#respond':'#comments'}">${icon('comment')} <span class="arc-action-count">(${comments})</span></a>`:`<button type="button" data-arc-view-comments="${id}" title="Komentáře" aria-label="Komentáře (${comments})">${icon('comment')} <span class="arc-action-count">(${comments})</span></button>`}
 <button type="button" data-arc-save="${id}" data-arc-type="${type}" title="${p.saved?'Odebrat z uložených':'Uložit'}" aria-label="${p.saved?'Odebrat z uložených':'Uložit'}" aria-pressed="${p.saved?'true':'false'}" ${cfg.loggedIn?'':'disabled'}>${icon('save')}</button>
 <button type="button" data-arc-share="${id}" data-arc-type="${type}" title="Sdílet" aria-label="Sdílet">${icon('share')}</button>
 </div>`;
};
const commentsHTML=items=>{
 const arr=items||[];if(!arr.length)return '<p class="arc-comment-empty">Zatím bez komentářů.</p>';
 const byParent=new Map();const ids=new Set(arr.map(c=>Number(c.id)));
 for(const c of arr){let parent=Number(c.parent_id||0);if(parent===Number(c.id)||!ids.has(parent))parent=0;if(!byParent.has(parent))byParent.set(parent,[]);byParent.get(parent).push(c);}
 const render=(parent,depth,seen=new Set())=>(byParent.get(parent)||[]).map(c=>{
  const id=Number(c.id);if(seen.has(id))return '';const next=new Set(seen);next.add(id);
  const tools=`<div class="arc-comment-tools">${cfg.loggedIn?`<button type="button" data-arc-comment-like="${id}" aria-pressed="${c.liked?'true':'false'}" title="Líbí se komentář">${icon('like')} (${Number(c.likes||0)})</button>${depth<2?`<button type="button" data-arc-comment-reply="${id}">Odpovědět</button>`:''}`:''}${c.can_manage?`<button type="button" data-arc-comment-edit="${id}">Upravit</button><button type="button" data-arc-comment-delete="${id}">Smazat</button>`:''}</div>`;
  return `<div class="arc-comment-thread arc-comment-depth-${Math.min(depth,2)}"><div class="arc-comment-row">${c.avatar?`<img class="arc-comment-avatar" alt="" src="${esc(c.avatar)}" loading="lazy">`:`<span class="arc-comment-avatar arc-comment-empty-avatar" aria-hidden="true">AR</span>`}<div class="arc-post-comment" data-arc-comment-id="${id}">${c.profile_url?`<a class="arc-comment-author" href="${esc(c.profile_url)}">${esc(c.alias)}</a>`:`<strong>${esc(c.alias)}</strong>`}<p>${esc(c.body)}</p>${tools}</div></div>${render(id,depth+1,next)}</div>`;
 }).join('');
 return render(0,0);
};
const showComments=(panel,items)=>{const list=panel?.querySelector('[data-arc-comment-list]');if(list){list.innerHTML=commentsHTML(items);panel.hidden=false;}};
const communityCard=p=>{
 const name=p.profile_url?`<a href="${esc(p.profile_url)}">${esc(p.author_alias)}</a>`:esc(p.author_alias);
 const original=p.shared?(p.shared.type==='kit'?`<div class="arc-repost-source arc-shared-kit"><small>Sdílený balíček · ${esc(p.shared.author_alias)}</small><strong>${esc(p.shared.title)}</strong><div class="arc-kit-preview-images">${(p.shared.images||[]).map(url=>`<img loading="lazy" src="${esc(url)}" alt="Produkt v balíčku">`).join('')}</div><a class="arc-kit-preview-link" href="${esc(p.shared.url)}">Prohlédnout balíček (${Number(p.shared.count)||0} produktů) →</a></div>`:p.shared.type==='article'?`<div class="arc-repost-source"><small>Původní článek · ${day(p.shared.date)}</small><strong><a href="${esc(p.shared.url)}">${esc(p.shared.title)}</a></strong>${mediaImage(p.shared)}<p>${esc(p.shared.excerpt)}</p><small>Autor: ${esc(p.shared.author_alias)}</small></div>`:`<div class="arc-repost-source"><strong>${esc(p.shared.author_alias)}</strong><small> Původní příspěvek · ${day(p.shared.date)}</small><p class="arc-live-story">${esc(p.shared.body)}</p>${mediaImage(p.shared)}${mediaVideo(p.shared)}</div>`):'';
 const edit=p.can_edit?`<details class="arc-post-menu"><summary aria-label="Správa příspěvku">•••</summary><div class="arc-post-menu-body"><button type="button" data-arc-edit="${p.id}">Upravit</button><button type="button" data-arc-delete="${p.id}">Smazat</button></div></details>`:'';
 const comments=commentsHTML(p.comments||[]);
 return `<article class="arc-live-card arc-live-entry arc-post" data-arc-card="community:${p.id}">
 <div class="arc-post-head">${avatar(p)}<div class="arc-post-byline"><strong>${name}</strong><small>${p.shared?'Sdíleno · ':''}${day(p.date)} · ${badge(p)}${p.shared_to?' · Na zeď: '+esc(p.shared_to):''}</small></div>${edit}</div>
 ${p.body?`<p class="arc-live-story">${esc(p.body)}</p>`:''}${original}${p.shared?'':mediaImage(p)+mediaVideo(p)}
 ${actions(p)}
 <div class="arc-live-comments" data-arc-comments-panel hidden>${p.comments_enabled?`<div data-arc-comment-list>${comments}</div>${cfg.loggedIn?`<form data-arc-comment="${p.id}"><input name="body" maxlength="800" minlength="2" placeholder="Napsat komentář…" aria-label="Napsat komentář" required><button type="submit">Odeslat</button></form><p class="arc-comment-status" role="status" aria-live="polite"></p>`:''}`:'<p>Komentáře autor vypnul.</p>'}</div>
 <div class="arc-inline-action-panel" data-arc-action-panel hidden></div>
 </article>`;
};
const articleCard=p=>{
 const name=p.author_profile?`<a href="${esc(p.author_profile)}">${esc(p.author_alias)}</a>`:esc(p.author_alias);
 return `<article class="arc-live-card arc-live-entry arc-post" data-arc-card="article:${p.id}">
 <div class="arc-post-head"><span class="arc-post-avatar arc-avatar-fallback">AR</span><div class="arc-post-byline"><strong>${name}</strong><small>Magazín · ${day(p.date)}</small></div>${p.can_edit&&p.edit_url?`<a class="arc-post-edit-link" href="${esc(p.edit_url)}">Upravit článek</a>`:''}</div>
 <h3 class="arc-article-title"><a href="${esc(p.url)}">${esc(p.title)}</a></h3>
 ${p.image?`<a class="arc-post-media" href="${esc(p.url)}"><img loading="lazy" src="${esc(p.image)}" alt="Úvodní fotografie článku"></a>`:''}
 <p class="arc-article-excerpt">${esc(p.excerpt)}</p><a class="arc-read-article" href="${esc(p.url)}">Přečíst článek →</a>
 ${actions(p)}<div class="arc-inline-action-panel" data-arc-action-panel hidden></div>
 </article>`;
};
const card=p=>p.type==='article'?articleCard(p):communityCard(p);
// A saved community entry can be opened directly even if it is older than the first feed page.
const deepPost=new URLSearchParams(window.location.search).get('arc_post');
const showDeepPost=async()=>{
 if(!/^[1-9][0-9]{0,9}$/.test(deepPost||''))return;
 const holder=document.createElement('section');holder.className='arc-deep-post';
 holder.innerHTML='<p class="arc-loading">Načítám uložený příspěvek…</p>';
 feed.before(holder);
 try{
  const post=await api('post/'+Number(deepPost));cardCache.set('community:'+post.id,post);
  holder.innerHTML='<h3>Vybraný příspěvek</h3>'+card(post);
 }catch(e){holder.innerHTML='<p class="arc-live-card">'+esc(e.message||'Příspěvek není dostupný.')+'</p>';}
};

const loadFeed=async(reset=true)=>{
 if(loading)return;loading=true;
 if(reset){page=1;cardCache.clear();feed.innerHTML='<div class="arc-content-loader" role="status"><span class="arc-content-loader-spin" aria-hidden="true"></span><strong>Načítám příspěvky…</strong><small>Komunita připravuje obsah.</small></div>';}
 try{
  const data=await api('feed?filter='+encodeURIComponent(filter)+'&page='+page);
  const html=(data.items||[]).map(item=>{cardCache.set(item.type+':'+item.id,item);return card(item);}).join('');
  if(reset){videoStop();feed.innerHTML=html||'<div class="arc-live-card">Zatím tu nic není.</div>';}
  else feed.insertAdjacentHTML('beforeend',html);
  refreshVideo();
  hasMore=!!data.has_more;el('[data-arc-more]').hidden=!hasMore;feed.dataset.arcLoaded='1';document.dispatchEvent(new Event('arc:feed-rendered'));window.dispatchEvent(new Event('arc:feed-rendered'));
 }catch(e){if(reset)feed.textContent=e.message;else alert(e.message);if(!reset)page=Math.max(1,page-1);}finally{loading=false;}
};
const nextPage=()=>{if(loading||!hasMore)return;page++;loadFeed(false);};
const sentinel=el('[data-arc-scroll-sentinel]');
if('IntersectionObserver' in window){
 const obs=new IntersectionObserver(entries=>{if(!restoringFeed&&entries.some(e=>e.isIntersecting))nextPage();},{rootMargin:'0px 0px 300px 0px'});
 if(sentinel)obs.observe(sentinel);
}
el('[data-arc-more]')?.addEventListener('click',nextPage);
app.querySelectorAll('[data-arc-filter]').forEach(b=>b.addEventListener('click',()=>{
 filter=b.dataset.arcFilter;try{sessionStorage.removeItem(feedStateKey);}catch(_){}app.querySelectorAll('[data-arc-filter]').forEach(x=>x.setAttribute('aria-pressed',String(x===b)));loadFeed();
}));
// Compact mobile-first composer with a usable non-script fallback.
const compose=el('[data-arc-compose]'),shell=el('[data-arc-compose-shell]');
const scopeSelector=compose?.elements?.visibility;
const applyAudienceVisibility=visibility=>{
 if(!scopeSelector)return;const allowed=visibility==='public'?['public','network','private']:visibility==='network'?['network','private']:['private'];
 Array.from(scopeSelector.options).forEach(option=>{option.disabled=!allowed.includes(option.value);option.hidden=!allowed.includes(option.value);});
 if(!allowed.includes(scopeSelector.value))scopeSelector.value=allowed[0];
 const help=compose.querySelector('[data-arc-audience-help]');if(help)help.textContent=visibility==='private'?'Tvůj profil je soukromý. Publikovat lze jen pro sebe.':visibility==='network'?'Příspěvky mohou vidět jen potvrzení přátelé nebo pouze ty.':'Veřejný profil umožňuje všechny tři úrovně viditelnosti.';
};
if(cfg.loggedIn)api('me').then(me=>applyAudienceVisibility(me.visibility)).catch(()=>applyAudienceVisibility('private'));
let composeMode='text';
const setComposeMode=mode=>{
 composeMode=['text','photo','video'].includes(mode)?mode:'text';
 const fields={photo:compose?.querySelector('[data-arc-compose-photo-fields]'),video:compose?.querySelector('[data-arc-compose-video-fields]')};
 if(fields.photo)fields.photo.hidden=composeMode!=='photo';
 if(fields.video)fields.video.hidden=composeMode!=='video';
 const title=compose?.querySelector('[data-arc-compose-title]');if(title)title.textContent=({text:'Textový příspěvek',photo:'Fotografie s popisem',video:'Video s popisem'}[composeMode]);
 const label=compose?.querySelector('[data-arc-body-label]');if(label)label.textContent=({text:'Tvůj příběh nebo zážitek',photo:'Popis fotografie (nepovinné)',video:'Popis videa (nepovinné)'}[composeMode]);
 shell?.querySelectorAll('[data-arc-compose-mode]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.arcComposeMode===composeMode)));
};
const setCompose=(show,mode='')=>{
 if(!compose)return;compose.hidden=!show;
 if(mode)setComposeMode(mode);
 shell?.querySelectorAll('[data-arc-compose-open]').forEach(b=>b.setAttribute('aria-expanded',String(show)));
 if(show){(composeMode==='text'?compose.elements.body:composeMode==='photo'?compose.elements.image:compose.elements.video)?.focus();}
};
setComposeMode('text');
shell?.querySelectorAll('[data-arc-compose-open]').forEach(b=>b.addEventListener('click',()=>setCompose(!!compose?.hidden,'text')));
shell?.querySelectorAll('[data-arc-compose-mode]').forEach(b=>b.addEventListener('click',()=>setCompose(compose?.hidden||composeMode!==b.dataset.arcComposeMode,b.dataset.arcComposeMode)));
shell?.querySelectorAll('[data-arc-compose-photo]').forEach(b=>b.addEventListener('click',()=>setCompose(compose?.hidden||composeMode!=='photo','photo')));
shell?.querySelector('[data-arc-compose-close]')?.addEventListener('click',()=>setCompose(false));
shell?.querySelector('[data-arc-video-paste]')?.addEventListener('click',async()=>{
 const field=compose?.elements.video;if(!field)return;
 try{const value=String(await navigator.clipboard.readText()).trim();if(!/^https:\/\/(?:www\.|m\.)?(?:youtube\.com|youtu\.be)\//i.test(value))throw Error('Ve schránce není odkaz na YouTube.');field.value=value;field.focus();}
 catch(_){field.focus();msg('[data-arc-form-message]','Vlož odkaz do pole ručně (Ctrl+V nebo podržením prstu).');}
});
shell?.querySelectorAll('[data-arc-compose-source]').forEach(button=>button.addEventListener('click',()=>{
 const file=compose?.elements.image;if(!file)return;
 if(button.dataset.arcComposeSource==='camera')file.setAttribute('capture','environment');else file.removeAttribute('capture');
 file.click();
}));
compose?.elements.image?.addEventListener('change',()=>{
 const file=compose.elements.image.files?.[0];if(!file)return;
 msg('[data-arc-image-filename]',file.name);
 msg('[data-arc-form-message]','Fotografie je připravena. Před odesláním ji případně zmenšíme.');
});
// Phones often produce 6–20 MB images. Compress client-side before the existing 5MB API endpoint.
const compressPhoto=async(file)=>{
 if(!file || !String(file.type).startsWith('image/'))throw Error('Vyber fotografii.');
 if(file.size<=5*1024*1024 && ['image/jpeg','image/png','image/webp'].includes(file.type))return file;
 let bitmap=null,blobUrl='';
 try {
  if('createImageBitmap' in window){bitmap=await createImageBitmap(file);}
  else {
    blobUrl=URL.createObjectURL(file);
    bitmap=await new Promise((resolve,reject)=>{const img=new Image();img.onload=()=>resolve(img);img.onerror=reject;img.src=blobUrl;});
  }
  const ratio=Math.min(1,2000/Math.max(bitmap.width,bitmap.height));
  const canvas=document.createElement('canvas');canvas.width=Math.max(1,Math.round(bitmap.width*ratio));canvas.height=Math.max(1,Math.round(bitmap.height*ratio));
  canvas.getContext('2d').drawImage(bitmap,0,0,canvas.width,canvas.height);
  for(let q=.85;q>=.4;q-=.1){const blob=await new Promise(resolve=>canvas.toBlob(resolve,'image/jpeg',q));if(blob&&blob.size<=5*1024*1024)return new File([blob],'ar-prispevek.jpg',{type:'image/jpeg'});}
  throw Error('Fotografie je příliš velká. Zkus menší snímek.');
 }catch(e){throw Error('Fotografii nelze zpracovat. Použij JPG, PNG nebo WebP.');}
 finally{bitmap?.close?.();if(blobUrl)URL.revokeObjectURL(blobUrl);}
};
compose?.addEventListener('submit',async e=>{
 e.preventDefault();const btn=compose.querySelector('[type=submit]');btn.disabled=true;msg('[data-arc-form-message]','Ukládám…');
 try{
  const values=new FormData(compose),image=composeMode==='photo'?values.get('image'):null;let imageId=0,privatePhoto='';
  const video=composeMode==='video'?String(values.get('video')||'').trim():'';
  if(composeMode==='photo'&&(!image||!image.size))throw Error('Vyber fotografii.');
  if(composeMode==='video'&&!video)throw Error('Vlož odkaz na video z YouTube nebo použij komunitní nahrávání, pokud je povoleno.');
  if(composeMode==='text'&&String(values.get('body')||'').trim().length<2)throw Error('Napiš alespoň dva znaky.');
  if(image && image.size>0){
   msg('[data-arc-form-message]','Připravuji fotografii…');
   const processed=await compressPhoto(image);
   const upload=new FormData();upload.append('image',processed,processed.name);upload.append('visibility',String(values.get('visibility')||'private'));
   msg('[data-arc-form-message]','Nahrávám fotografii…');
   const res=await api('photo',{method:'POST',body:upload});imageId=Number(res.id||0);privatePhoto=String(res.private_photo||'');
   if(!imageId&&!privatePhoto)throw Error('Fotografii se nepodařilo potvrdit po nahrání.');
  }
  await api('posts',{method:'POST',body:JSON.stringify({body:values.get('body'),visibility:values.get('visibility'),video,image_id:imageId,private_photo:privatePhoto})});
  compose.reset();msg('[data-arc-form-message]','Zveřejněno podle zvolené viditelnosti.');msg('[data-arc-image-filename]','Vybrat fotografii nebo vyfotit mobilem');
  setComposeMode('text');setCompose(false);filter='all';app.querySelectorAll('[data-arc-filter]').forEach(x=>x.setAttribute('aria-pressed',String(x.dataset.arcFilter==='all')));loadFeed();
 }catch(ex){msg('[data-arc-form-message]',ex.message);}finally{btn.disabled=false;}
});
const actionPanel=c=>c.querySelector('[data-arc-action-panel]');
const openEdit=(c,pid)=>{
 const panel=actionPanel(c);if(!panel)return;
 const body=c.querySelector('.arc-live-story')?.textContent||'';
 const visible=c.querySelector('.arc-post-byline small')?.textContent||'';
 panel.hidden=false;
 panel.innerHTML=`<form data-arc-edit-form="${pid}"><label>Upravit text<textarea name="body" maxlength="3000" rows="3">${esc(body)}</textarea></label><label>Viditelnost<select name="visibility"><option value="public">Veřejně</option><option value="network">Přátelé</option><option value="private">Jen já</option></select></label><label>Odkaz na YouTube<input type="url" name="video" placeholder="https://youtu.be/…"></label><label class="arc-inline-checkbox"><input type="checkbox" name="comments_enabled" checked> Povolit komentáře</label><div class="arc-edit-buttons"><button type="submit">Uložit</button><button type="button" data-arc-action-close>Zavřít</button></div></form>`;
 // Populate from server, avoiding stale presentation data.
 const item=cardCache.get('community:'+pid);
 if(item){panel.querySelector('[name=body]').value=item.body||'';panel.querySelector('[name=visibility]').value=item.visibility==='friend_wall'?'network':item.visibility;
  panel.querySelector('[name=visibility]').disabled=item.visibility==='friend_wall';panel.querySelector('[name=video]').value=item.video||'';
  panel.querySelector('[name=comments_enabled]').checked=!!item.comments_enabled;}
};
// Pull friend choices directly from the existing authenticated friendship endpoint.
const shareForm=async(c,id,type)=>{
 const panel=actionPanel(c);if(!panel)return;panel.hidden=false;
 const item=cardCache.get(type+':'+id);
 const publicUrl=(item&&item.visibility==='public'&&item.url)?String(item.url):'';
 const text=type==='article'?(item?.title||'Článek z komunity'):'Zajímavý příspěvek z '+(cfg.name||'komunity');
 const ext=publicUrl?`<div class="arc-external-shares"><strong>Sdílet také mimo komunitu</strong><a target="_blank" rel="noopener noreferrer nofollow" href="https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(publicUrl)}">Facebook</a><a target="_blank" rel="noopener noreferrer nofollow" href="https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(publicUrl)}">LinkedIn</a><a target="_blank" rel="noopener noreferrer nofollow" href="https://api.whatsapp.com/send?text=${encodeURIComponent(text+' '+publicUrl)}">WhatsApp</a></div>`:'<p class="arc-share-private-note">Externí sdílení je dostupné jen u veřejných příspěvků a článků.</p>';
 if(!cfg.loggedIn){panel.innerHTML=ext+`<p>Pro sdílení na svou zeď se nejprve přihlas.</p>`;return;}
 panel.textContent='Načítám přátele…';
 try{
  if(!friendsCache)friendsCache=(await api('friends')).items||[];
  const options=friendsCache.filter(f=>!f.hidden).map(f=>`<option value="${Number(f.id)}">${esc(f.alias)}</option>`).join('');
  panel.innerHTML=`<form data-arc-share-form="${id}" data-arc-type="${type}"><strong>Sdílet v komunitě</strong><label>Kam sdílet?<select name="target_id"><option value="0">Na moji zeď (veřejně)</option>${options}</select></label><label>Vlastní poznámka<textarea maxlength="800" rows="2" name="message" placeholder="Proč to doporučuješ?"></textarea></label><small>Sdílet lze jen veřejný originál. Autor zůstane uveden.</small><div class="arc-edit-buttons"><button type="submit">Sdílet</button><button type="button" data-arc-action-close>Zavřít</button></div></form>${ext}`;
 }catch(e){panel.innerHTML=ext+'<p>'+esc(e.message)+'</p>';}
};
app.addEventListener('click',async e=>{
 const video=e.target.closest('[data-arc-start-video]');
 if(video){videoPlay(video.closest('[data-arc-video-id]'),true);return;}
 const sound=e.target.closest('[data-arc-video-sound]');
 if(sound){const box=sound.closest('[data-arc-video-id]');
  if(box!==currentVideo)return;
  const frame=box.querySelector('iframe');
  if(frame?.contentWindow){frame.contentWindow.postMessage(JSON.stringify({event:'command',func:'unMute',args:[]}), 'https://www.youtube-nocookie.com');sound.hidden=true;}
  return;}
 const commentLike=e.target.closest('[data-arc-comment-like]');
 if(commentLike){const card=commentLike.closest('[data-arc-card]');if(!card)return;commentLike.disabled=true;
  try{const r=await api('comments/'+Number(commentLike.dataset.arcCommentLike)+'/like',{method:'POST'});showComments(card.querySelector('[data-arc-comments-panel]'),r.items||[]);const p=cardCache.get(card.dataset.arcCard);if(p)p.comments=r.items||[];}
  catch(err){alert(err.message);}finally{commentLike.disabled=false;}return;}
 const reply=e.target.closest('[data-arc-comment-reply]');
 if(reply){const row=reply.closest('[data-arc-post-comment],[data-arc-comment-id]');if(!row)return;
  const existing=row.querySelector('[data-arc-comment-reply-form]');if(existing){existing.remove();return;}
  const c=reply.closest('[data-arc-card]');if(!c)return;const postId=Number(c.dataset.arcCard.split(':')[1]);
  const form=document.createElement('form');form.dataset.arcCommentReplyForm=String(postId);form.dataset.parentId=String(Number(reply.dataset.arcCommentReply));
  form.className='arc-comment-reply-form';form.innerHTML=`<input name="body" maxlength="800" minlength="2" aria-label="Odpověď uživateli" placeholder="Napiš odpověď…" required><button type="submit">Odpovědět</button>`;row.appendChild(form);form.querySelector('input')?.focus();return;}
 const commentEdit=e.target.closest('[data-arc-comment-edit]');
 if(commentEdit){const row=commentEdit.closest('[data-arc-comment-id]');if(!row)return;
  const oldText=row.querySelector('p')?.textContent||'';
  row.innerHTML=`<form data-arc-comment-edit-form="${Number(commentEdit.dataset.arcCommentEdit)}"><textarea name="body" maxlength="800" minlength="2" rows="2" required>${esc(oldText)}</textarea><div class="arc-comment-edit-actions"><button type="submit">Uložit</button><button type="button" data-arc-comment-cancel>Storno</button></div></form>`;
  row.querySelector('textarea')?.focus();return;
 }
 if(e.target.closest('[data-arc-comment-cancel]')){const c=e.target.closest('[data-arc-card]');if(c){const p=cardCache.get(c.dataset.arcCard);if(p)showComments(c.querySelector('[data-arc-comments-panel]'),p.comments||[]);}return;}
 const commentDelete=e.target.closest('[data-arc-comment-delete]');
 if(commentDelete){if(!confirm('Smazat tento komentář?'))return;
  const c=commentDelete.closest('[data-arc-card]');if(!c)return;
  commentDelete.disabled=true;
  try{const r=await api('comments/'+Number(commentDelete.dataset.arcCommentDelete),{method:'DELETE'});
   showComments(c.querySelector('[data-arc-comments-panel]'),r.items||[]);
   const p=cardCache.get(c.dataset.arcCard);if(p)p.comments=r.items||[];
  }catch(err){alert(err.message);}finally{commentDelete.disabled=false;}return;
 }
 const c=e.target.closest('[data-arc-card]');if(!c)return;
 const like=e.target.closest('[data-arc-react]'),save=e.target.closest('[data-arc-save]'),share=e.target.closest('[data-arc-share]'),edit=e.target.closest('[data-arc-edit]'),del=e.target.closest('[data-arc-delete]');
 const toggle=e.target.closest('[data-arc-view-comments]'),close=e.target.closest('[data-arc-action-close]');
 if(toggle){const panel=c.querySelector('[data-arc-comments-panel]');panel.hidden=!panel.hidden;return;}
 if(close){actionPanel(c).hidden=true;return;}
 if(edit){openEdit(c,Number(edit.dataset.arcEdit));return;}
 if(share){await shareForm(c,Number(share.dataset.arcShare),share.dataset.arcType);return;}
 if(like){like.disabled=true;try{
  const r=await api('items/'+like.dataset.arcType+'/'+like.dataset.arcReact+'/react',{method:'POST'});
  like.setAttribute('aria-pressed',String(r.reacted));like.querySelector('.arc-action-count').textContent='('+r.count+')';like.querySelector('[data-arc-like-label]').textContent=r.reacted?'Líbí se':'To se mi líbí';like.setAttribute('aria-label','To se mi líbí ('+r.count+')');
 }catch(er){alert(er.message);}finally{like.disabled=false;}return;}
 if(save){save.disabled=true;try{const r=await api('items/'+save.dataset.arcType+'/'+save.dataset.arcSave+'/save',{method:'POST'});
  save.setAttribute('aria-pressed',String(r.saved));save.title=r.saved?'Odebrat z uložených':'Uložit';save.setAttribute('aria-label',save.title);loadSavedSidebar();
 }catch(er){alert(er.message);}finally{save.disabled=false;}return;}
 if(del){if(!confirm('Opravdu smazat vlastní příspěvek?'))return;
  try{await api('posts/'+del.dataset.arcDelete,{method:'DELETE'});if(currentVideo&&c.contains(currentVideo))videoStop();c.remove();refreshVideo();}catch(er){alert(er.message);}return;}
});
app.addEventListener('submit',async e=>{
 const f=e.target.closest('[data-arc-comment], [data-arc-comment-reply-form], [data-arc-comment-edit-form], [data-arc-edit-form], [data-arc-share-form]');if(!f)return;e.preventDefault();
 const btn=f.querySelector('[type="submit"]');if(!btn)return;btn.disabled=true;
 const c=f.closest('[data-arc-card]');
 try{
  if(f.hasAttribute('data-arc-comment')||f.hasAttribute('data-arc-comment-reply-form')){
   const postId=Number(f.dataset.arcComment||f.dataset.arcCommentReplyForm);
   const r=await api('posts/'+postId+'/comment',{method:'POST',body:JSON.stringify({body:f.elements.namedItem('body').value,parent_id:Number(f.dataset.parentId||0)})});
   showComments(c?.querySelector('[data-arc-comments-panel]'),r.items||[]);
   const p=c?cardCache.get(c.dataset.arcCard):null;if(p){p.comments=r.items||[];p.comment_count=p.comments.length;}
   const badge=c?.querySelector('[data-arc-view-comments] .arc-action-count');if(badge)badge.textContent='('+(r.items||[]).length+')';
   f.reset();const st=c?.querySelector('.arc-comment-status');if(st)st.textContent='Komentář zveřejněn.';
   return;
  }
  if(f.hasAttribute('data-arc-comment-edit-form')){
   const r=await api('comments/'+f.dataset.arcCommentEditForm+'/edit',{method:'POST',body:JSON.stringify({body:f.elements.namedItem('body').value})});
   showComments(c?.querySelector('[data-arc-comments-panel]'),r.items||[]);
   const p=c?cardCache.get(c.dataset.arcCard):null;if(p)p.comments=r.items||[];
   return;
  }
  if(f.hasAttribute('data-arc-edit-form')){
   const vis=f.elements.visibility.disabled?'friend_wall':f.elements.visibility.value;
   await api('posts/'+f.dataset.arcEditForm+'/edit',{method:'POST',body:JSON.stringify({body:f.elements.body.value,visibility:vis,video:f.elements.video.value,comments_enabled:f.elements.comments_enabled.checked})});
  }
  if(f.hasAttribute('data-arc-share-form'))await api('items/'+f.dataset.arcType+'/'+f.dataset.arcShareForm+'/share',{method:'POST',body:JSON.stringify({target_id:Number(f.elements.target_id.value),message:f.elements.message.value})});
  loadFeed();
 }catch(er){const status=c?.querySelector('.arc-comment-status');if(status)status.textContent=er.message;else alert(er.message);}finally{btn.disabled=false;}
});
const alerts=el('[data-arc-live-alerts]');
const loadAlerts=async()=>{
 if(!alerts)return;
 try{const data=await api('notifications');updateSideNotifications(data);const fresh=(data.items||[]).filter(x=>!x.read);
  alerts.hidden=!fresh.length;if(!fresh.length)return;
  const count=alerts.querySelector('[data-arc-live-alert-count]');count.textContent='('+fresh.length+')';
  alerts.querySelector('[data-arc-live-alert-items]').innerHTML=fresh.slice(0,5).map(item=>`<a href="${esc(item.url)}">${esc(item.message)}</a>`).join('');
 }catch(_){alerts.hidden=true;}
};
alerts?.querySelector('[data-arc-live-mark-read]')?.addEventListener('click',async()=>{try{await api('notifications/read',{method:'POST',body:'{}'});loadAlerts();}catch(e){alert(e.message);}});
const gearTrack=el('[data-arc-gear-products]');
const loadGear=async()=>{
 const box=el('[data-arc-gear]');if(!cfg.showShop||!box||!gearTrack)return;
 try{const data=await api('gear');if(!data.items?.length)return;
  gearTrack.innerHTML=data.items.slice(0,20).map(g=>`<article class="arc-gear-card"><a class="arc-gear-product-link" href="${esc(g.url)}">${g.image?`<img src="${esc(g.image)}" loading="lazy" alt="">`:'<span class="arc-gear-no-image">✦</span>'}<span>${esc(g.name)}</span><small>${esc(g.price)}</small></a><button type="button" class="arc-fav arc-fav-icon arc-gear-fav" data-arc-favorite="${Number(g.id)}" aria-pressed="false" aria-label="Přidat do oblíbených" title="Přidat do oblíbených"><span aria-hidden="true">♡</span></button></article>`).join('')+
  `<a class="arc-gear-card arc-gear-all" href="${esc(cfg.shop)}"><strong>Prohlédnout celý obchod</strong><span>Další nabídka z obchodu</span><span aria-hidden="true">→</span></a>`;
  const title=box.querySelector('[data-arc-gear-heading]');if(title)title.textContent=data.personalized?'Vybavení podle tvých zájmů':'Tipy z obchodu';
  const sub=box.querySelector('[data-arc-gear-subtitle]');if(sub)sub.textContent='Přejdi do obchodu kdykoli · bez reklam ve zdi';
  box.hidden=false;
  window.ARCRefreshFavoriteButtons?.(window.ARC_FAVORITES_IDS||[]);
 }catch(e){box.hidden=true;}
};
app.querySelector('[data-arc-gear-prev]')?.addEventListener('click',()=>gearTrack?.scrollBy({left:-Math.max(220,gearTrack.clientWidth*.76),behavior:'smooth'}));
app.querySelector('[data-arc-gear-next]')?.addEventListener('click',()=>gearTrack?.scrollBy({left:Math.max(220,gearTrack.clientWidth*.76),behavior:'smooth'}));
const side=sel=>el(sel);
const loadSidebar=async()=>{
 if(!cfg.loggedIn)return;
 const profileBox=side('[data-arc-side-profile]'),friendsBox=side('[data-arc-side-friends]');
 if(!profileBox)return;
 try{const me=await api('me');const interests=(me.interests||'').split(/[,;\n]+/).map(t=>t.trim()).filter(Boolean).slice(0,7);
  profileBox.innerHTML=`<div class="arc-side-profile-head">${me.avatar?`<img class="arc-side-avatar" src="${esc(me.avatar)}" alt="Moje profilová fotografie">`:'<span class="arc-side-avatar arc-side-avatar-empty">AR</span>'}<div><strong>${esc(me.alias||'Můj profil')}</strong><a href="${esc(me.url)}">Zobrazit profil →</a></div></div>`+
  `<h4>Oblíbené sporty</h4>${interests.length?`<div class="arc-side-tags">${interests.map(s=>`<span>${esc(s)}</span>`).join('')}</div>`:'<p>Sporty a aktivity si můžeš doplnit v profilu.</p>'}`+
  `<h4>Můj vztah ke sportu</h4><p>${esc(me.sport_relation||'Zatím nevyplněno.')}</p>`+
  `<h4>Viditelnost profilu</h4><p class="arc-side-visibility">${esc(({public:'Veřejný – viditelný všem',network:'Pouze potvrzení přátelé',private:'Soukromý – jen já'}[me.visibility])||'Soukromý')}</p>`+
  `${me.sport_profession||me.sport_experience?`<h4>Sportovní profesní zkušenosti</h4>${me.sport_profession?`<strong class="arc-side-profession">${esc(me.sport_profession)}</strong>`:''}${me.sport_experience?`<p class="arc-side-experience">${esc(me.sport_experience)}</p>`:''}`:''}`+
  `<a class="arc-side-edit" href="${esc(me.commerce_url||cfg.commerce)}">Upravit komunitní profil</a>`;
 }catch(e){profileBox.innerHTML='<p>Profil teď není dostupný.</p>';}
 if(!friendsBox)return;
 try{const data=await api('friends');const friends=(data.items||[]).filter(x=>!x.hidden).slice(0,8);
  friendsBox.innerHTML=`<div class="arc-side-heading"><h3>Přátelé</h3><a href="${esc(cfg.friendsUrl||'/')}">Zobrazit všechny →</a></div>`+
    (friends.length?`<div class="arc-side-people">${friends.map(f=>`<a href="${esc(f.url)}" class="arc-side-friend">${f.avatar?`<img src="${esc(f.avatar)}" loading="lazy" alt="">`:'<span class="arc-side-friend-empty">AR</span>'}<span>${esc(f.alias)}</span></a>`).join('')}</div>`:'<p>Propoj se se sportovci ve své komunitě.</p>');
 }catch(e){friendsBox.innerHTML='<h3>Přátelé</h3><p>Nepodařilo se načíst přátele.</p>';}
};
const loadSavedSidebar=async()=>{
 const box=el('[data-arc-side-saved]');if(!box||!cfg.loggedIn)return;
 try{
  const data=await api('feed?filter=saved&page=1');const items=(data.items||[]).slice(0,5);
  box.innerHTML=`<div class="arc-side-heading"><h3>Uložené příspěvky</h3><a class="arc-side-saved-all-link" href="${esc(cfg.savedUrl||'/ulozene/?tab=posts')}">Zobrazit všechny →</a></div>`+
   (items.length?`<div class="arc-side-saved-items">${items.map(p=>{
     const title=(p.type==='article'?p.title:p.body)||'Příspěvek od '+(p.author_alias||'člena');
     const subtitle=(p.type==='article'?'Magazín':'Komunita')+' · '+(p.author_alias||'AR');
     const inner=`${p.image?`<img loading="lazy" src="${esc(p.image)}" alt="">`:'<span class="arc-side-saved-fallback">★</span>'}<span><strong>${esc(title.slice(0,105))}</strong><small>${esc(subtitle)}</small></span>`;
     const target=p.type==='article'?`<a href="${esc(p.url)}" class="arc-side-saved-item">${inner}</a>`:`<button type="button" class="arc-side-saved-item" data-arc-jump-saved>${inner}</button>`;
     return `<div class="arc-side-saved-row">${target}<button type="button" class="arc-side-unsave" aria-label="Odebrat z uložených: ${esc(title.slice(0,60))}" title="Odebrat z uložených" data-arc-unsave-id="${Number(p.id)}" data-arc-unsave-type="${esc(p.type)}">×</button></div>`;
   }).join('')}</div>`:'<p>Zatím nemáš uložené příspěvky. U zajímavého příspěvku klikni na Uložit.</p>');
 }catch(_){box.innerHTML='<h3>Uložené příspěvky</h3><p>Uložené příspěvky se nepodařilo načíst.</p>';}
};
app.addEventListener('click',async e=>{
 const btn=e.target.closest('[data-arc-unsave-id]');if(!btn)return;
 e.preventDefault();e.stopPropagation();
 btn.disabled=true;
 try{const r=await api('items/'+encodeURIComponent(btn.dataset.arcUnsaveType)+'/'+Number(btn.dataset.arcUnsaveId)+'/save',{method:'POST',body:'{}'});
  if(r.saved)throw Error('Položka nebyla odebrána.');
  btn.closest('.arc-side-saved-row')?.remove();
  if(filter==='saved')loadFeed();
  const key=btn.dataset.arcUnsaveType+':'+btn.dataset.arcUnsaveId;
  const cache=cardCache.get(key);if(cache)cache.saved=false;
  const action=feed.querySelector('[data-arc-card="'+key+'"] [data-arc-save]');if(action){action.textContent='♧ Uložit';action.setAttribute('aria-pressed','false');}
  loadSavedSidebar();
 }catch(err){alert(err.message);btn.disabled=false;}
});
app.addEventListener('click',e=>{if(!e.target.closest('[data-arc-jump-saved]'))return;
 const btn=app.querySelector('[data-arc-filter="saved"]');btn?.click();app.querySelector('.arc-filter-bar')?.scrollIntoView({behavior:'smooth',block:'start'});
});
const updateSideNotifications=data=>{
 const box=side('[data-arc-side-notifications]');if(!box)return;
 const items=(data.items||[]).slice(0,5);
 box.innerHTML=`<div class="arc-side-heading"><h3>Poslední oznámení</h3><a href="${esc(cfg.notificationsUrl||'/')}">Všechna →</a></div>`+(
  items.length?`<div class="arc-side-notices">${items.map(x=>`<a href="${esc(x.url)}" class="${x.read?'':'is-unread'}">${esc(x.message)}<small>${esc(x.date||'')}</small></a>`).join('')}</div>`:'<p>Momentálně nemáš nová oznámení.</p>');
};
const restoreFeed=async()=>{
 let state=null;try{state=JSON.parse(sessionStorage.getItem(feedStateKey)||'null');sessionStorage.removeItem(feedStateKey);}catch(_){}
 if(!state?.fromArticle||Date.now()-Number(state.at)>45*60*1000){await loadFeed();return;}
 restoringFeed=true;filter=['all','following','articles','saved'].includes(state.filter)?state.filter:'all';
 app.querySelectorAll('[data-arc-filter]').forEach(x=>x.setAttribute('aria-pressed',String(x.dataset.arcFilter===filter)));
 await loadFeed();
 const requested=Math.max(1,Math.min(30,Number(state.page)||1));
 while(page<requested&&hasMore){page++;await loadFeed(false);}
 const y=Math.max(0,Number(state.y)||0);
 // Allow media placeholders to receive their dimensions before final restoration.
 requestAnimationFrame(()=>{scrollTo(0,y);setTimeout(()=>{if(Math.abs(scrollY-y)<600)scrollTo(0,y);},400);});
 restoringFeed=false;
};
restoreFeed();showDeepPost();loadAlerts();loadGear();loadSidebar();loadSavedSidebar();
if(alerts)setInterval(()=>{if(!document.hidden)loadAlerts();},60000);
})();
