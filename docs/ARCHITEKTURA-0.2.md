# Technická struktura AR Community 0.2

## Zdroje pravdy

| Doména | Zůstává autoritou | AR Community smí |
|---|---|---|
| Identita / hesla | WordPress `wp_users` | Používat WP ID, neinicializovat druhý účet |
| Obchod / platby | WooCommerce | Odkázat na stávající účet a produkty |
| Provize / lifetime zákazníci | Fresh Affiliate | Číst stav, generovat existující referral URL |
| Předplatné / Stripe webhook | Starý plugin Live do migračního plánu | Pouze read-only entitlement adaptér (budoucí) |
| Historické články | WP `post` | Číst publikované články, bez duplicit |
| Eventy / startovné | Fresh Event | Zatím nic; později volitelný adaptér |
| Sociální vazby | AR Community | Vytvářet `follow` a `connection` bez ovlivnění affiliate |

## Struktura

```text
ar-community/
  ar-community.php
  includes/
    class-ar-community.php           bootstrap / upgrade / DB schema
    class-arc-data.php               původní seznamy, beze změny obchodní logiky
    class-arc-api.php                původní REST API (zpětně kompatibilní)
    class-arc-integrations.php       původní read-only Fresh Affiliate
    class-arc-shortcodes.php         původní Woo shortcode a články
    class-arc-admin.php              nastavení + dávková inicializace profilů
    class-arc-profile.php            NOVÉ user_meta, alias, 3 stupně viditelnosti
    class-arc-relations.php          NOVÉ sociální vazby, požadavky na propojení
    class-arc-wall.php               NOVÉ post ACL, reakce, komentáře, agregovaný feed
    class-arc-live-api.php           NOVÉ kontrolované REST rozhraní zdi / profilů
    class-arc-live-shortcodes.php    NOVÉ [ar_live]
    class-arc-premium.php            NOVÉ read-only entitlement registry
  templates/
    live.php                         pilotní aplikace / webový wrapper
  assets/
    community.css / community.js     původní seznamy a Woo účet
    live.css / live.js               nová komunitní zeď
  docs/
    ARCHITEKTURA-0.2.md
  README-ADRENALINE-LIVE.md
```

## Nová databáze

- User meta `_arc_profile_initialized`, `_arc_profile_visibility`, `_arc_profile_alias`, `_arc_profile_type`, `_arc_profile_bio`, `_arc_profile_interests`.
- `{$prefix}arc_relations`: `user_id`, `target_id`, `kind`, `status`, `created_at`, unique edge. Typ `follow` = jednostranné sledování bez přístupu k síťovému obsahu; `connection` + `accepted` = potvrzený sociální kontakt.
- CPT `arc_social_post`: **vždy** `post_status=private` + `_arc_audience` enum; `publicly_queryable=false`, `show_in_rest=false`. Veřejné/network texty dostupné pouze přes vlastní ACL REST rozhraní.
- `{$prefix}arc_wall_comments`: vlastní komentáře, aby WP REST comments neobešel ACL private CPT.
- `{$prefix}arc_wall_reactions`: osobní reakce s unikátním `(post_id,user_id)`.
- WP `post` zůstávají publikované redakční články. Volitelný budoucí ověřený odkaz `_arc_verified_author_user_id`, nyní žádný automatický import.

## REST rozhraní (namespace `ar-community/v1`)

- `GET/POST /live/me` – vlastní profil (přihlášení).
- `GET /live/profile/{id}` – ACL viditelnost profilu.
- `GET /live/people` – pouze veřejné profily.
- `POST /live/relations` – `follow|unfollow|request|accept|decline|disconnect`.
- `GET /live/requests` – čekající žádosti o propojení.
- `GET /live/feed?filter=all|following|articles&page=1` – veřejný/ACL feed + originální WP články.
- `POST /live/posts` – nový příspěvek (`body`, `visibility`, `video`, `image_id`).
- `DELETE /live/posts/{id}` – jen autor/admin.
- `POST /live/posts/{id}/comment` / `react` – jen přihlášení s přístupem.
- `POST /live/photo` – validovaný upload maximálně 5 MB, **jen pro veřejný post**.
- `GET /live/premium` – registry potenciálních výhod (bez automatického odemykání).

## Role vs. viditelnost

- Zákazník: default `private`. Může ručně změnit na `network` / `public`.
- Aktivní affiliate partner: status se zjišťuje **čtením Fresh Affiliate**, nevytváří se jiný WP účet a nemění se odměňování. Při první inicializaci nového affiliate profilu se nastaví `network` (viditelnost jen potvrzeným sociálním kontaktům). Pokud starý účet již měl explicitně uložené nastavení soukromí, respektujeme je a netlačíme retroaktivní zveřejnění.
- Obchodní partner: admin nastaví ověřený profesionální typ; profil může zveřejnit až po potvrzení dohody se členem.
- `network` v tomto vydání = pouze potvrzené kontakty AR Community; *nejde o downstream affiliate zákazníky*. Obchodní vztah nesmí sám o sobě zpřístupňovat soukromý obsah.

## Rizika a další integrační brány

1. Stávající předplatné 199 Kč / jakékoli aktivní Stripe subscription běží ve starém pluginu. Neodinstalovat; `ARC_Premium` je zatím katalog, nikoli migrace billing.
2. Dnešní PWA root-scope SW `/` má vlastní cache. Testovat na oddělené URL, nenasazovat nový SW v této verzi.
3. Fotografie mohou obsahovat EXIF, nelze je bezpečně sdílet „jen pro kontakty“ běžnou veřejnou WP media URL. V0.2 private/network pouze text / odkazy, chráněné soubory později.
4. Skutečné počty WP článků a taxonomie „Lidé“ nejsou v archivech. Automatické mapování článku k člověku podle `post_author` může být chybné.
5. Komunitní obchodní příspěvky a provizní odkazy musí být označené; pozdější QA moderace / reportování před veřejným pilotem.
6. Před rozsáhlým provozem je potřeba optimalizovat stránkování feedu a cache podle skutečné produkční velikosti DB.
