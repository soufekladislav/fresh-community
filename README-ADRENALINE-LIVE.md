# AR Community 0.2.0-beta.2 – první testovací jádro Adrenaline Live

**Verze:** 0.2.0-beta.2 | **Typ:** aktualizace původního WP pluginu `ar-community` (ne nový plugin)

## Co zůstává zachováno

- **Jedno `wp_users.ID`** – žádné duplicitní přihlašování. WooCommerce zůstává účet i obchod.
- **Fresh Affiliate** – žádné přepočítávání provizí, změny doživotního přiřazení ani obchodních sítí.
- **Původní Adrenaline Live / Stripe** – žádné vypínání, změny webhooků, refundace nebo migrace předplatných. Placené funkce jsou budoucí rozšíření.
- **Původní AR Community 0.1.0** – existující oblíbené, seznamy vybavení, REST API a Woo endpoint.
- **Články magazínu** – žádné kopie, importy či úpravy URL; živý veřejný feed používá původní publikace.
- **Fresh Event** – v0.2 nepřipojovat ani neaktivovat; po otestování možné samostatné rozšíření.

## Co přidává tato beta

- [x] Jednotná prezentace profilu založená na WP účtu. Výchozí viditelnost nového zákazníka `private`, pro poprvé inicializovaného aktivního affiliate partnera `network` (pouze potvrzené sociální kontakty). Staré explicitní nastavení soukromí se zachová.
- [x] Alias místo automatického zveřejnění Woo zobrazovaného jména / e-mailu.
- [x] Viditelnost profilu `private | network | public`; `network` = **oboustranně schválené komunitní kontakty**.
- [x] Oddělená sociální vazba `follow` (pro veřejné profily) / `connection` (žádost → schválení). NIKDY se nedotýká Fresh Affiliate.
- [x] Samostatné příspěvky zdi se serverovou ACL, komentáře, reakce, text a odkazy na YouTube.
- [x] Veřejné fotografie do příspěvků; zatím bez chráněného úložiště pro privátní fotografie.
- [x] Přehled magazínu v komunitní zdi bez kopírování článků.
- [x] Nadstavbový katalog prémiových nástrojů připraven pro budoucí entitlement adapter, zatím **bez aktivního paywallu**.
- [x] Admin potvrzuje roli profesionálního obchodního partnera; zveřejnění vyžaduje explicitní potvrzení.
- [x] Dávková inicializace existujících WP účtů: max. 100 za krok, opakovatelná a idempotentní.
- [x] Nový shortcode `[ar_live]` pro samostatnou testovací stránku. Původní funkce mohou dál běžet.

## Není součástí této beta

- Automatický přesun / přesměrování původní URL `/app/`, změna service workeru nebo nová instalovatelná PWA.
- Přesun existujícího Stripe Billing z původního pluginu. **Starý plugin neodinstalovávat ani nevypínat.**
- Automatický placený přístup k novým aplikacím – chybí ověřený napojovací adaptér na stav předplatného.
- Migrace rubriky „Lidé“ na konkrétní osobní profily. Budoucí párování vyžaduje ověření identity.
- Funkční eventy, registrace, startovné a vstupenky.
- Prodej produktů třetích stran a provize autorů obsahu mimo současný e-shop.
- Notifikace, zprávy, skupiny, vlastní video hosting, soukromé fotografie.
- Produkční QA v reálné WP/Woo instalaci (vyžaduje staging a testovací uživatele).

## Bezpečný instalační postup

1. **Zálohuj** soubory původního `ar-community` a celou databázi. Ověř aktivní předplatné, Stripe webhooky a stávající root-scope service worker z původního Live.
2. Nasazuj **nejdřív pouze na staging**. ZIP má přesně stejný kořenový adresář `ar-community/`, aby šlo o aktualizaci téhož pluginu.
3. Aktualizuj AR Community na 0.2.0-beta.2. **Nevypínej ani neodinstalovávej `adrenaline-live`.** Nenahrávej Ultimate Member ani Fresh Event, pokud k tomu nemáš samostatný důvod.
4. Vytvoř neveřejně propagovanou stránku, např. `/komunita-test/`, a vlož do ní **`[ar_live]`**. Nezasahuj zatím do současné adresy `/app/`.
5. V administraci **AR Community** uvidíš postupný nástroj „Inicializovat další dávku (max. 100 účtů)“. Ověř prvních 100 a případně pokračuj. Bez kliknutí funguje i lazy inicializace při přihlášení.
6. Ověř dvěma až třemi různými testovacími WP účty chování dle testů níže. V anonymním prohlížeči vyzkoušej veřejnou zeď, profil a přímý REST přístup k privátním postům.
7. Pokud původní Live registroval service worker ve scope `/`, při QA ověř i návštěvu v novém anonymním profilu prohlížeče bez cache. **Žádný takeover PWA nyní neprobíhá.**
8. Teprve po úspěšné kontrole uvažovat o produkční aktualizaci pluginu. Instalace ZIPu **není provedena tímto balíkem**.

## Konkrétní smoke testy

| # | Test | Očekávané chování |
|---|---|---|
| 1 | Nový Woo zákazník / WP registrace | Jeden stávající WP user ID, AR profil private, bez trialu |
| 2 | Starý WP zákazník a admin batch | Jeden profil, žádné nové účty, žádná změna affiliate |
| 3 | Host otevře `/live/me` | HTTP 401/403; nezíská soukromé údaje |
| 4 | Host otevře `GET /live/feed` | Jen veřejné community posty + veřejné články |
| 5 | Host otevře `GET /live/profile/{privateUserId}` | HTTP 404 |
| 6 | Spojení A → B, zatím pending | Síťový obsah B je pro A nepřístupný |
| 7 | B schválí A | Síťový obsah B dostupný A; affiliate bez změny |
| 8 | A sleduje veřejného B bez propojení | Dostane veřejné příspěvky, ne síťové |
| 9 | A publikuje private post | Nečtou jej ani kontakty; pouze A/admin |
| 10 | A publikuje public post s YouTube URL | Veřejná karta + odkaz na YouTube |
| 11 | A se pokusí přidat fotografii k network postu | Odmítnuto; ochrana před nechtěným zveřejněním veřejné URL |
| 12 | Člen B zkusí smazat A post | HTTP 403 |
| 13 | Komentář, reakce, rate-limit | Pouze přihlášení, ACL, limity |
| 14 | Feed článků | Zobrazuje původní permalink, neobsahuje kopie článků |
| 15 | AR Shop objednávka s affiliate | Stejná provize jako před aktualizací |
| 16 | Stripe měsíční obnova předplatného | Původní webhook/obnova zůstávají funkční |
| 17 | Fresh Event plugin není aktivní | Žádná nová eventová registrace |

## Ověření před výrobním přechodem

- Možnost registrace v **WooCommerce > Nastavení > Účty a soukromí** – pokud Woo registraci zakazuje, komunitní registrační CTA vede na login stránku, ale nový účet ještě nevytvoří.
- Nastavení cache pro personalizované REST endpointy a stránky profilu. Plugin vrací `nocache_headers()`; navíc je třeba prověřit CDN/server cache.
- Reálná taxonomie „Lidé“ a konkrétní vlastníci cca 400 článků.
- Stripe, produkt předplatného a související Woo mirror objednávky před jakoukoliv změnou původního Live.
- Počet uživatelů a objem obsahu – první feed je určen pro pilot, ne pro statisíce příspěvků.
- Moderace a obsahové podmínky před pozváním veřejnosti.

## Architektura

```text
WP user ID (jediný účet)
├─ AR Shop / WooCommerce           objednávky, košík, platby
├─ Fresh Affiliate                komerční přiřazení a provize (odděleně)
├─ AR Community                   profil + sociální vazby + zeď + magazín
│  └─ Adrenaline Live             shortcode [ar_live], zatím pilotní webové UI
├─ staré Adrenaline Live          Stripe subscription webhooks, plánovač (dočasně zachovat)
└─ Premium aplikace (budoucí)     AR Jídelníček, mapy, fitness, eventové výhody
```

## Inspirace z dodaných ZIPů

- **Ultimate Member 2.14.1:** orientace na profil, registraci a adresář. Nepřebíráme UM role, datové modely, CSS/JS ani jeho rozšíření; není závislostí.
- **Fresh Event 0.2.0:** organizátoři, WooCommerce event produkt, startovní listiny, vstupenky, provize. Do komunitního pluginu se teď nepřenášejí ani neaktivují. Pozdější integrace jen přes bezpečné read-only API / odkazy, až plugin projde QA.
- **Původní Adrenaline Live 0.9.1:** zachován odděleně kvůli aktivním předplatným, webhooks a datům plánovače. Po důkladném auditu lze oddělit modul AR Jídelníček a billing adapter.

## Nejbližší krok po testu

1. Opravit problémy z reálného WP stagingu a doplnit schvalování obsahu / reportovací frontu.
2. Ověřit billing adapter, aby existující předplatitelé měli kontinuitu výhod.
3. Připravit čisté PWA takeover bez root-scope service workeru a bez zásahu do checkoutu.
4. Audit a mapování rubriky „Lidé“ na potvrzené členské profily (preview → ruční potvrzení → apply, rollback).
5. Fresh Event připojit až po samostatném otestování.

## Beta.2: Google přihlášení

- Přidána volitelná integrace Google Identity Services přes oficiální ID token a serverovou kontrolu podpisu.
- Google Cloud OAuth 2.0 **Web client ID** vlož do administrace **AR Community** (nebo `ARC_GOOGLE_CLIENT_ID` ve wp-config.php).
- Authorized JavaScript origins: odpovídající HTTPS origin webu. Authorized redirect URI: adresa z administrace pluginu (např. `https://adrenalinerace.eu/wp-admin/admin-post.php?action=arc_google_login`). Pozor na rozdíl www / bez www a staging.
- `g_csrf_token` musí souhlasit mezi cookie a POST, ID token musí mít platný Google RS256 podpis, příjemce, vydavatele a čas platnosti.
- Nový účet se vytvoří pouze tehdy, když WooCommerce nebo WP umožňuje registrace. Výchozí soukromý profil; žádný trial, žádná affiliate změna.
- Pokud e-mail již patří existujícímu WP účtu, **nedojde k automatickému propojení**. Přihlas se původním způsobem, navštiv stránku Můj účet či Můj profil a použij Google tlačítko pro propojení (ověřený e-mail musí být totožný).
- `[arc_google_login]` samostatný shortcode; tlačítko se také zobrazuje v login/registration formuláři WooCommerce, dashboardu účtu a Adrenaline Live.
- Apple je plánován jako samostatný provider, není v beta.2 implementován.
- BETA.2 vyžaduje integrační a bezpečnostní otestování na stagingu, **není automaticky nasazena**.
