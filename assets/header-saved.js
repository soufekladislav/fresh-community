(()=>{'use strict';
const config=window.ARCMenuSaved;
const targets=[...document.querySelectorAll('[data-arc-fav-count]')];
if(!targets.length)return;
const validIds=a=>[...new Set((Array.isArray(a)?a:[]).map(Number).filter(n=>Number.isSafeInteger(n)&&n>0))];
const local=()=>{try{return validIds(JSON.parse(localStorage.getItem('arc_guest_favorites_v1')||'[]'));}catch{return []}};
const paint=ids=>{const count=validIds(ids).length;targets.forEach(el=>{el.textContent=String(count);el.hidden=!count;});};
window.addEventListener('arc:favorites-change',e=>{if(e.detail&&Array.isArray(e.detail.ids))paint(e.detail.ids);});
if(!config||!config.loggedIn){paint(local());window.addEventListener('storage',e=>{if(e.key==='arc_guest_favorites_v1')paint(local());});return;}
fetch(config.root+'favorites',{credentials:'same-origin',cache:'no-store',headers:{'X-WP-Nonce':config.nonce}})
 .then(r=>{if(!r.ok)throw Error('favorites');return r.json();})
 .then(d=>paint((d.items||[]).map(item=>item.product_id)))
 .catch(()=>{ /* Soukromé údaje nevykreslovat při chybě připojení. */ });
})();
