(()=>{
 'use strict';
 const init=()=>{
  document.querySelectorAll('[data-arc-live]').forEach(wall=>{
   const trigger=wall.querySelector('[data-arc-youtube-open]');
   const panel=wall.querySelector('[data-arc-youtube-panel]');
   if(!trigger||!panel)return;
   trigger.addEventListener('click',()=>{
    panel.hidden=false;
    const regularComposer=wall.querySelector('[data-arc-compose]');
    if(regularComposer)regularComposer.hidden=true;
    panel.scrollIntoView({behavior:'smooth',block:'nearest'});
   });
   panel.querySelector('[data-arc-youtube-close]')?.addEventListener('click',()=>{panel.hidden=true;});
  });
 };
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
