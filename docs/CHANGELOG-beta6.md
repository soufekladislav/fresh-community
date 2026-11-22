# AR Community 0.2.0-beta.6 – Adrenaline Live 2.0

- Odstraněny skryté duplicitní interní části Profil/Lidé/Výhody z aplikace `/app/`; jde pouze o komunitní zeď. Samostatné stránky `/profil/`, `/pratele/`, `/premium/`.
- Profil používá unikátní URL s `ar_member` ze stránky `arc_page_profile`.
- Opraveno desktop/mobilní uspořádání horní lišty i pevná plovoucí navigace; styly Poppins a značkové barvy.
- Nový komunitní PWA manifest a `/app/`-scoped service worker bez cache soukromého obsahu.
- Zabránění dvojímu načtení starého ALF rozhraní na `/app/` po nahrazení shortcode; bezpečný ruční přesun ALF stránky do `/premium/jidelnicek/` s kontrolou shortcodů, zachováním Stripe a dat.
- Premium hub `[ar_live_premium]`; původní ALF předplatné se ověřuje pouze pro čtení, bez trial/platby či změny dat.
- Admin Členové komunity: prezentace, avatar, soukromí, komunitní pozastavení. Odděleně od WooCommerce a Fresh Affiliate.
- Admin Oznámení: provozní a opt-in propagační in-app zprávy cílené na členy či partnery. WP-Cron dávkování, promo jen s opt-in.
- Upozornění na `/app/` vedle přehledu na My Account a Přátelé.
- Dlouhodobá WP remember-cookie alespoň 30 dní (nikoliv trvalé přihlášení).
- CSS zamezuje horizontálnímu přetečení základních prvků v úzkých zobrazeních.

**Limity:** Žádné push doručování na pozadí (jen příjem v service workeru), zatím bez plné integrace WP / WooCommerce / Stripe na stagingu, bez úprav Elementor šablony hlavičky – druhý ZIP obsahoval pouze nastavení serveru.
