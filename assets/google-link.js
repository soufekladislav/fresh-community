/* Explicitly link Google ID to the logged-in WordPress account using AJAX + WP nonce. */
(function () {
  'use strict';
  function init() {
    if (!window.google || !google.accounts || !google.accounts.id || !window.ARC_GOOGLE_LINK) return;
    var els = Array.prototype.slice.call(document.querySelectorAll('[data-arc-google-link]'));
    if (!els.length) return;
    var cfg = window.ARC_GOOGLE_LINK;
    google.accounts.id.initialize({client_id: cfg.clientId, auto_select: false, callback: function(resp){
      els.forEach(function(el){var p=el.querySelector('[data-arc-google-link-msg]');if(p)p.textContent='Ověřuji propojení…';});
      var body = new URLSearchParams({action:'arc_google_link', nonce:cfg.nonce, credential:resp.credential});
      fetch(cfg.ajaxUrl, {method:'POST', credentials:'same-origin', cache:'no-store', headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'}, body:body.toString()})
        .then(function(r){ return r.json().then(function(j){if(!r.ok||!j.success)throw new Error((j.data&&j.data.message)||'Propojení selhalo.');return j;});})
        .then(function(){window.location.reload();})
        .catch(function(err){els.forEach(function(el){var p=el.querySelector('[data-arc-google-link-msg]');if(p)p.textContent=err.message;});});
    }});
    els.forEach(function(el){
      var target=el.querySelector('[data-arc-google-target]');
      if (target) google.accounts.id.renderButton(target,{theme:'outline',size:'large',text:'continue_with',locale:'cs'});
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
