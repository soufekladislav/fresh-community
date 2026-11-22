<?php defined('ABSPATH') || exit; ?>
<div class="arc-live" data-arc-live>
  <h2 class="arc-screenreader-only">Adrenaline Live – komunitní zeď</h2>
  <?php if(is_user_logged_in()): ?>
    <section class="arc-live-alerts" data-arc-live-alerts aria-label="Upozornění komunity" hidden>
      <div class="arc-live-alerts-head"><strong>Upozornění <span data-arc-live-alert-count></span></strong><button type="button" data-arc-live-mark-read>Označit jako přečtené</button></div>
      <div data-arc-live-alert-items></div>
    </section>
  <?php endif; ?>
  <div class="arc-wall-layout">
  <main data-arc-panel="wall" class="arc-wall-panel" aria-label="Komunitní příspěvky">
    <?php if(is_user_logged_in()): ?>
      <div class="arc-composer-shell arc-live-card" data-arc-compose-shell>
        <?php
          $arc_yt_result = sanitize_key((string)($_GET['arc_youtube'] ?? ''));
          $arc_yt_messages = [
              'sent'=>'Video bylo odesláno správci ke schválení. Na YouTube zatím není zveřejněno.',
              'form'=>'Doplň název a souhlas s veřejným zveřejněním videa.',
              'upload'=>'Nahrávání selhalo. Zkontroluj video a limity velikosti souborů.',
              'size'=>'Video je větší než limit hostingu.',
              'format'=>'Nepodporovaný formát videa.',
              'storage'=>'Na serveru není dostupné bezpečné soukromé úložiště.',
              'limit'=>'Za posledních 24 hodin jsi již odeslal(a) 3 videa.',
          ];
          if (isset($arc_yt_messages[$arc_yt_result])):
        ?>
          <p class="arc-yt-status" role="status"><?php echo esc_html($arc_yt_messages[$arc_yt_result]); ?></p>
        <?php endif; ?>
        
        <div class="arc-composer-prompt">
          <span class="arc-avatar-mark" aria-hidden="true">AR</span>
          <button type="button" class="arc-composer-open" data-arc-compose-open aria-expanded="false">Co jsi dnes zažil?</button>
          <button type="button" class="arc-composer-quick" data-arc-compose-photo aria-label="Přidat fotografii">Foto</button>
        </div>
        <div class="arc-composer-shortcuts" role="group" aria-label="Typ příspěvku"><button type="button" data-arc-compose-mode="text" aria-pressed="true">✎ Příspěvek</button><button type="button" data-arc-compose-mode="photo" aria-pressed="false">▧ Fotografie</button><button type="button" data-arc-compose-mode="video" aria-pressed="false">▶ Video</button></div>
        <form data-arc-compose class="arc-composer-details" hidden>
          <h3 class="arc-compose-mode-title" data-arc-compose-title>Textový příspěvek</h3>
          <label for="arc-post-body" data-arc-body-label>Tvůj příběh nebo zážitek</label>
          <textarea id="arc-post-body" name="body" rows="3" maxlength="3000" placeholder="Napiš příběh, přidej tip na trasu nebo vlastní zkušenost…"></textarea>
          <div class="arc-live-grid"><label>Komu se příspěvek zobrazí<select name="visibility"><option value="public">Veřejně</option><option value="network">Jen potvrzeným přátelům</option><option value="private">Jen mně</option></select><small data-arc-audience-help>Viditelnost se řídí nastavením tvého profilu.</small></label></div>
          <div data-arc-compose-video-fields hidden><label>Odkaz na YouTube video<input type="url" name="video" placeholder="https://youtu.be/…" inputmode="url"></label>
          <div class="arc-video-choices"><button type="button" data-arc-video-paste>🔗 Vložit zkopírovaný odkaz</button><button type="button" data-arc-youtube-open>＋ Nahrát na kanál Adrenaline Race</button></div>
          <p class="arc-compose-help">Odkaz z vlastního YouTube můžeš publikovat hned. Nahrání na oficiální kanál vyžaduje předchozí schválení administrátorem; výsledné video bude veřejné na YouTube.</p></div>
          <div data-arc-compose-photo-fields hidden><div class="arc-photo-source-actions"><button type="button" data-arc-compose-source="camera">📷 Vyfotit</button><button type="button" data-arc-compose-source="gallery">▧ Z galerie</button></div><label class="arc-photo-field arc-photo-pick"><input type="file" name="image" accept="image/*"><span class="arc-photo-pick-icon" aria-hidden="true">＋</span><strong data-arc-image-filename>Vybrat fotografii nebo vyfotit mobilem</strong><small>Na mobilu se nabídne fotoaparát nebo galerie</small></label><p class="arc-compose-help">Fotografie je na serveru veřejně přístupná. Prozatím lze přiložit jen k veřejnému příspěvku. Větší fotografie se před odesláním automaticky zmenší.</p></div>
          <div class="arc-compose-submit"><button type="button" class="arc-compose-cancel" data-arc-compose-close>Zavřít</button><button type="submit">Publikovat</button></div>
          <p role="status" data-arc-form-message aria-live="polite"></p>
        </form>
        <div data-arc-youtube-panel hidden class="arc-live-card arc-youtube-upload-panel">
          <button type="button" data-arc-youtube-close class="arc-compose-cancel">Zavřít nahrávání</button>
          <?php ARC_YouTube::form_contents(); ?>
        </div>
      </div>
    <?php else: ?>
      <div class="arc-live-card"><p>Sport, turistika a skutečné zážitky. Přihlas se nebo si vytvoř bezplatný účet, abys mohl/a sdílet vlastní příspěvky.</p><?php echo do_shortcode('[arc_google_login]'); ?><a class="arc-live-button" href="<?php echo esc_url(function_exists('wc_get_page_permalink')?wc_get_page_permalink('myaccount'):wp_login_url()); ?>">Přihlášení a registrace</a></div>
    <?php endif; ?>
    <section class="arc-gear-strip" data-arc-gear hidden aria-label="Produkty Adrenaline Race shopu">
      <div class="arc-gear-title"><div><h3 data-arc-gear-heading>Tipy z AR Shopu</h3><span data-arc-gear-subtitle>Vybavení pro tvé aktivity</span></div>
        <div class="arc-gear-tools"><button type="button" class="arc-gear-arrow" data-arc-gear-prev aria-label="Předchozí produkty">‹</button><button type="button" class="arc-gear-arrow" data-arc-gear-next aria-label="Další produkty">›</button></div>
      </div>
      <div class="arc-gear-products" data-arc-gear-products tabindex="0" role="region" aria-label="Posuvný seznam produktů"></div>
    </section>
    <div class="arc-filter-bar"><span class="arc-filter-caption">Filtrovat v příspěvcích:</span>
      <div class="arc-live-filters" role="group" aria-label="Filtrovat příspěvky"><button data-arc-filter="all" aria-pressed="true">Nejnovější</button><button data-arc-filter="following">Přátelé</button><button data-arc-filter="articles">Magazín</button><button data-arc-filter="saved">Uložené</button></div>
    </div>
    <div data-arc-feed aria-live="polite"><div class="arc-content-loader" role="status" aria-label="Načítání příspěvků"><span class="arc-content-loader-spin" aria-hidden="true"></span><strong>Načítám příspěvky…</strong><small>Komunitní zeď už se připravuje.</small></div></div>
    <div class="arc-load-actions"><button type="button" data-arc-more hidden>Načíst starší příspěvky</button><div data-arc-scroll-sentinel aria-hidden="true"></div></div>
  </main>
  <aside class="arc-wall-sidebar" aria-label="Můj komunitní přehled">
    <?php if(is_user_logged_in()): ?>
      <section class="arc-side-card arc-side-profile" data-arc-side-profile><div class="arc-side-loading">Načítám profil…</div></section>
      <section class="arc-side-card arc-side-friends" data-arc-side-friends><h3>Přátelé</h3><div class="arc-side-loading">Načítám přátele…</div></section>
      <section class="arc-side-card arc-side-notifications" data-arc-side-notifications><h3>Poslední oznámení</h3><div class="arc-side-loading">Načítám oznámení…</div></section>
      <section class="arc-side-card arc-side-saved" data-arc-side-saved><h3>Uložené příspěvky</h3><div class="arc-side-loading">Načítám uložené…</div></section>
    <?php else: ?>
      <section class="arc-side-card arc-side-guest"><h3>Adrenaline Live</h3><p>Přidej se ke skutečným lidem, které baví pohyb a zážitky.</p><a href="<?php echo esc_url(function_exists('wc_get_page_permalink')?wc_get_page_permalink('myaccount'):wp_login_url()); ?>">Přihlásit se</a></section>
    <?php endif; ?>
  </aside>
  </div>
  <footer class="arc-live-footer">Bez reklamního řazení příspěvků · Jeden účet pro komunitu i AR Shop</footer>
</div>
