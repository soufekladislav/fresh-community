(()=>{'use strict';
 if(typeof ARCBookmark==='undefined')return;
 document.addEventListener('click',async event=>{
  const button=event.target.closest('[data-arc-article-save]');if(!button)return;
  if(!ARCBookmark.loggedIn){window.location.href=ARCBookmark.login;return;}
  const id=Number(button.dataset.arcArticleSave);if(!id)return;
  button.disabled=true;
  try{
   const res=await fetch(ARCBookmark.root+'live/items/article/'+id+'/save',{
    method:'POST',credentials:'same-origin',cache:'no-store',headers:{'X-WP-Nonce':ARCBookmark.nonce}
   });const data=await res.json();if(!res.ok)throw Error(data.message||'Uložení se nezdařilo.');
   button.classList.toggle('is-saved',!!data.saved);button.setAttribute('aria-pressed',String(!!data.saved));
   const text=data.saved?'Odebrat článek z uložených':'Uložit článek na později';button.title=text;button.setAttribute('aria-label',text);
   const label=button.querySelector('[data-arc-article-label]');if(label)label.textContent=data.saved?'Uloženo':'Uložit';
  }catch(error){alert(error.message);}finally{button.disabled=false;}
 });
})();
