# Fresh Community — produktová a funkční specifikace
**Stav dokumentu:** výchozí inventura podle repozitáře, 22. 11. 2026. **Zdrojová verze:** `0.2.0-beta.10.16`. **Ověření:** čtení kódu a historických dokumentů, nikoli integrační test produkční instalace.

## 1. Co je Fresh Community
Fresh Community je WordPress plugin pro provoz vlastní komunitní/mikrosociální sítě pod značkou provozovatele webu. Referenční implementací je **Adrenaline Live** v rámci Adrenaline Race. Smyslem je přímá komunikace členů, sdílení obsahu, vytváření sociálních vazeb a zpětná vazba, nikoli primárně reklamní feed. Zamýšlený obchodní produkt: opakovaně nasaditelný plugin / realizace na klíč pro jiné WordPress weby. **Univerzální přenositelnost dosud není prokázána.**

## 2. Uživatelé a účel
- Host: vidí povolené veřejné informace, články a registrační výzvy.
- Registrovaný člen: spravuje profil, kontakty, příspěvky, reakce, komentáře, uložený obsah, pozvánky a zpětnou vazbu.
- Komunitní člen s veřejným nebo síťovým profilem: volí publikum a sdílí s přáteli; nastavení soukromí musí respektovat ACL.
- Správce: nastavuje komunitu, členy, moderaci vybraných procesů, oznámení a zpětnou vazbu.
- Provozovatel WordPress webu: v budoucnu nasazuje komunitu pod vlastní značkou bez závislosti na AR.

## 3. Základní architektura
- **WordPress `wp_users`** je jediný zdroj identity; plugin používá WP účet, hesla nezdvojuje. Na AR je WooCommerce zdrojem zákaznických a obchodních procesů.
- **WooCommerce** zůstává vlastníkem produktů, objednávek a plateb. Fresh Community pouze integruje profily, oblíbené, seznamy vybavení a účtové stránky.
- **Fresh Affiliate** vlastní provize, lifetime vazby a referral tracking; komunitní sociální kontakty nejsou affiliate vazby.
- **Původní Adrenaline Live / Stripe**: starší dokumentace uvádí zachování billing/webhooků v odděleném pluginu; před jakoukoli migrací nutný audit skutečné instalace.
- **Fresh AI Engine**: poradce je v původní specifikaci pouze neaktivní adaptér, nikoli hotová AI funkce.
- **Fresh Event, Fresh Sales**: potenciální budoucí volitelné integrace, ne součást základního jádra.
- **Elementor**: stránky mohou obsahovat shortcody, nemá se přepisovat šablona.
- **Datový model**: WP user meta pro profily, `arc_collection` pro oblíbené/sestavy, `arc_social_post` a vlastní tabulky sociálních vazeb, reakcí, komentářů, oznámení a zpětné vazby. Privátní obsah se musí číst přes aplikační ACL.

## 4. Funkční moduly — podle přítomnosti implementace
| Modul | Popis | Stav důkazu |
|---|---|---|
| Registrace a účet | WP/Woo účet, přihlášení, Google login/link, e-mailové ověření | Kód existuje; integrační QA chybí |
| Profil | Alias, avatar, bio, zájmy, viditelnost `private/network/public` | Kód existuje; ACL QA chybí |
| Sociální vazby | Sledování veřejných profilů, žádosti, potvrzené kontakty | Kód existuje; QA chybí |
| Komunitní zeď | Příspěvky, sdílení, editace, reakce, komentáře/vlákna, článek z magazínu | Kód existuje; QA chybí |
| Média | Obrázky, chráněná privátní média, YouTube odkazy | Kód existuje; kontrola soukromí a úložiště nutná |
| Pozvánky | Odkazy, e-mailové pozvánky, přijetí, referral integrace | Kód existuje; end-to-end QA chybí |
| Oznámení | Interní oznámení, čtení/mazání, dávkový e-mail přes WP-Cron | Kód existuje; SMTP/Cron QA chybí |
| Push | Service worker umí přijmout push událost, ale není doložena kompletní serverová Web Push infrastruktura | Nehotové / ověřit |
| PWA | Manifest, instalace, update kontrola, `/app/` service worker | Implementace vázaná na AR; zařízení a cache QA chybí |
| Onboarding | Uvítání, kroky, profil, pozvání, instalace | Kód existuje; QA chybí |
| Zpětná vazba | Přihlášený člen odesílá formulář, admin spravuje podněty | Kód existuje; QA chybí |
| Správa členů | Moderace/správa, odstranění testovacích účtů s ochranami | Kód existuje; bezpečnostní QA nutná |
| Uložené a vybavení | Oblíbené produkty, články, seznamy a sdílení | Kód existuje; QA chybí |
| YouTube kanál | OAuth, schválení, upload přes WP-Cron, veřejné video | Nová implementace; reálné OAuth/upload neověřeny |
| Premium | Katalog výhod, read-only integrace | Nezaměňovat za funkční billing |
| AI poradce | Historicky placeholder/501 bez provideru | Nehotové |
| Samostatný univerzální plugin | Instalace na libovolný WP, vlastní branding a volitelné integrace | Produktový záměr; neověřeno |

## 5. Typický uživatelský tok
1. Návštěvník otevře komunitu, přečte veřejný obsah a rozhodne se registrovat.
2. Použije existující WP/Woo registraci nebo podporovaný Google login.
3. Nový profil začíná soukromě; člen si nastaví alias, avatar a viditelnost.
4. Člen pozve přátele, potvrdí spojení a publikuje příspěvky s vybraným publikem.
5. Další členové reagují a komentují, oznámení se zobrazují v aplikaci; e-mail závisí na nastavení a doručovací infrastruktuře.
6. Člen pošle zpětnou vazbu. Správce ji zpracuje a rozhodne o dalším vývoji.

## 6. Pravidla, která nesmíme porušit
- Nezdvojovat WP identitu, WooCommerce obchod ani affiliate provize.
- Výchozí soukromí; `network` znamená schválené sociální kontakty, nikoli affiliate síť.
- Nepublikovat privátní obsah přes veřejné URL/REST; bezpečně chránit soukromá média.
- Neměnit Stripe/webhooky a aktivní předplatné bez zvláštního ověření.
- Respektovat souhlas s obchodními zprávami a práva uživatelů k datům.
- Žádné automatické mapování historických profilů podle podobnosti jmen.
- Změny nejprve testovat na stagingu; v produkci nepovažovat přítomnost kódu za důkaz funkčnosti.

## 7. Provozní omezení a závislosti
- Hardcoded AR prvky: `/app/`, název Adrenaline Live, některé Woo a affiliate endpointy, AR branding a navigace.
- WP-Cron je kritický pro dávkové e-maily, oznámení a YouTube upload.
- PWA a LiteSpeed/CDN cache vyžadují test na mobilu i desktopu.
- Google OAuth vyžaduje nastavení klienta, originu a callbacku.
- Produkční smoke test: registrace, login, soukromí, přátelé, feed, komentáře, média, pozvánky, oznámení, zpětná vazba, Woo checkout, affiliate, Stripe, PWA, YouTube.

## 8. Produktové hranice a další směr
**Adrenaline Live** = konkrétní komunitní služba; **Fresh Community** = pluginové jádro a plánovaný komerční produkt. Před prodejem jako univerzální plugin je potřeba konfigurace brandingu/URL, oddělení AR integrací, instalace na čistý WordPress, aktualizace, QA, dokumentace a provozní podpora. Vývoj prioritizovat podle pilotních uživatelů a zpětné vazby.

## 9. Evidence a důvěryhodnost stavu
- **Implementováno v kódu** není synonymum **otestováno** ani **nasazeno**.
- Historické README 0.1, README-ADRENALINE-LIVE beta.2, `docs/ARCHITEKTURA-0.2.md`, `docs/ANALYZA-ZDROJU-A-ROADMAP.md`, instalační PWA návod a changelogy beta.3–10.16 jsou historické zdroje, nikoli současný jednoznačný stav.
- Aktuální inventura vychází z `ar-community.php`, `includes/`, `assets/`, `templates/` a posledních changelogů.
- Stav Issues evidujeme jako **implementováno / potřebuje QA / rozpracováno / plán**, nikoli automaticky „hotovo v produkci“.

## 10. Jak dokumentaci udržovat
Tento dokument je hlavní produktová specifikace. Doplňující dokumenty: `docs/ARCHITEKTURA-0.2.md` (historická architektura), nová provozní/testovací dokumentace, rozhodnutí a roadmapa. Každá změna funkce má mít Issue, akceptační kritéria, test a odkaz na PR; README aktualizovat po schválení.