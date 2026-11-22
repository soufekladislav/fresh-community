# AR Community 0.1.0 – instalační kostra

Samostatný plugin pro **Adrenaline Race**. Stav: **technický základ / staging MVP**, nikoli hotová komunita ani hotový AI poradce.

## Základní hranice

- **WooCommerce**: jediný zdroj přihlašovacích účtů, produktů, cen, skladů a objednávek. Žádná vlastní registrace ani objednávkový systém.
- **Elementor**: běžná editace stránek, použij widget *Shortcode*. Žádné přepisování šablon.
- **AR Shop Organizer**: beze změny, v této fázi se nerozšiřuje.
- **Fresh AI Engine**: nezávislý vykonavatel AI požadavků. AR Community není jeho projekt ani klientská identita. Chatové připojení je ve verzi 0.1 pouze připravené rozhraní a **není aktivní**.
- **Fresh Affiliate**: volitelná integrace jen pro referenční URL. Nezakládáme provize ani vlastní tracking.
- **Fresh Sales**: pouze volitelná budoucí obchodní vazba; kód, kontakty ani rozesílky se do AR Community nepřebírají.

## Co už v této kostře skutečně funguje

1. Nová položka WooCommerce **Můj účet → Moje komunita** a samostatné nastavení v administraci.
2. Soukromý/veřejný komunitní profil nad existujícím `wp_users`; veřejnost je ve výchozím stavu **vypnutá**. Bio a zájmy jsou v user_meta.
3. Jeden typ **seznamu výbavy** (`arc_collection`) pro soukromé oblíbené a veřejné/soukromé balíčky. Produkty jsou odkazy na WooCommerce, nikoli jejich kopie.
4. Vyhledání produktu a přidání/odebrání v přihlášeném účtu.
5. Vytvoření balíčku ze základního seznamu oblíbených.
6. Favoritní tlačítko pro produkt. Pro nepřihlášené dočasné ID v `localStorage`, po přihlášení merge do účtu.
7. Veřejné profily, adresář lidí a veřejná knihovna balíčků; soukromé záznamy nejsou dostupné přes veřejnou API. Veřejný balíček vyžaduje veřejný profil.
8. Je-li na **stejném AR webu** skutečně aktivní kompatibilní Fresh Affiliate, odkazy z veřejných balíčků se mohou generovat přes jeho vlastní `Fresh_Affiliate_Tracker::referral_url()`. Bez pluginu jsou odkazy běžné.
9. Frontendový formulář pro článek: příspěvek ve WordPress se ukládá pouze jako `pending` pro redakční schválení (bez automatické publikace).
10. Admin **náhled historických Lidé**: volitelný výběr typu příspěvku a čtecí přehled. **Žádná automatická migrace.**

## Co zatím nefunguje / není součástí v0.1

- AI chat či skutečné doporučování vybavení: `[ar_community_advisor]` je **označená ukázková stránka**, REST `/advisor` bez připojeného provideru vrací 501.
- Hromadné vložení celého balíčku do košíku, vytvoření Woo objednávky; nyní veřejný balíček směruje na skutečné produkty.
- Ukládání vlastnictví produktu/poznámek ve vizuálním editoru (datový model i API tato pole připravují; UI v0.1 pouze přidává/odebírá).
- Bezpečné párování historických profilů „Lidé“ na účty: vyžaduje poznání skutečného post type a konkrétních vazeb v AR databázi.
- Newslettery, CRM, placené kampaně, automatické publikování článků, přímé napojení Fresh Sales.
- Moderace veřejných balíčků nad rámec běžné administrace WP a možností přepnout viditelnost. Před širším veřejným spuštěním doplnit reportování/zneužití a schvalování.
- Tvůrčí obrázky, kategorizace balíčků, SEO šablony a velká galerie; nákupní CTA a optimalizace vyhledávání jsou další etapy.

## Instalace na staging

1. Zálohuj databázi a instaluj ZIP klasicky přes **Pluginy → Instalace pluginů → Nahrát**.
2. Aktivuj **AR Community**. WooCommerce musí být aktivní pro produkty a přihlášené účty.
3. V **AR Community** v administraci nastav tři již existující WP stránky (nebo vytvoř nové):
   - „Lidé a profily“ s `[ar_community_people]` (na stejné stránce se přes `?ar_member=ID` zobrazí konkrétní profil),
   - „Výbava na cesty“ s `[ar_community_library]` (na stejné stránce `?ar_kit=ID` zobrazí sestavu),
   - „Osobní poradce“ s `[ar_community_advisor]` (**zatím připravujeme**).
4. Volitelně stránka „Přispět do magazínu“ s `[ar_community_submit_article]`.
5. Na kartě produktu lze přidat `[ar_community_favorite_button]` jako shortcode widget; v Woo účtu je nová záložka „Moje komunita“.
6. Testuj pomocí dvou rozdílných účtů, včetně veřejného/soukromého profilu a veřejné/soukromé sestavy.
7. Historické profily **neimportuj automaticky**. V admin vyber pravý typ příspěvku a nejdřív zkontroluj jeho strukturu.

Plugin **nevytváří stránky, nepřepisuje stávající obsah, neupravuje WordPress role, nemaže data při deaktivaci**, nepřebírá WooCommerce účty, nepřidává žádné API klíče.

## Shortcody

| Shortcode | Účel |
| --- | --- |
| `[ar_community_account]` | Profil a seznamy v Můj účet (zobrazí se i na standardním WC endpointu) |
| `[ar_community_people]` | Seznam veřejných profilů a detail podle `ar_member` |
| `[ar_community_profile]` | Detail vybraného profilu |
| `[ar_community_library]` | Seznam veřejných balíčků a detail podle `ar_kit` |
| `[ar_community_favorite_button]` | Přidat/odebrat aktuální produkt do oblíbených |
| `[ar_community_favorite_button product_id="123"]` | Tlačítko konkrétního produktu |
| `[ar_community_submit_article]` | Článek od člena ke schválení |
| `[ar_community_advisor]` | Označené místo pro budoucího poradce |

## REST API `ar-community/v1`

- `GET /me`, `POST /me` – vlastní profil (přihlášený uživatel)
- `GET /collections`, `POST /collections` – vlastní seznamy (`copy_from` lze předat při vytváření)
- `GET /collections/{id}` – veřejný seznam, nebo vlastní seznam autora
- `POST /collections/{id}` – vlastník upraví název/viditelnost
- `DELETE /collections/{id}` – vlastník uloží do koše
- `POST /collections/{id}/items` – přidání/upravení položky (`product_id`, `qty`, `owned`, `note`)
- `DELETE /collections/{id}/items/{product_id}` – odebrání položky
- `GET /favorites`, `POST /favorites/toggle`, `POST /favorites/merge` – oblíbené + sloučení hosta
- `GET /people`, `GET /library`, `GET /products` – veřejné katalogové výpisy, pouze povolené záznamy
- `POST /advisor` – neaktivní integrační místo (501), minimální omezení 8 požadavků / 10 min po připojení provideru
- `GET /migration-preview` – administrátor, pouze čtení

Pro změnové REST operace WordPress používá přihlášení a `X-WP-Nonce` jako součást standardní WP REST cookie autentizace. U všech seznamů se kontroluje vlastník. U hosta se ukládají jen ID produktů do jeho prohlížeče.

## Datový model

- `wp_users` + `user_meta`: `_arc_public_profile` (výchozí 0), `_arc_profile_bio`, `_arc_profile_interests`.
- `wp_posts` typu `arc_collection` (`post_author=WP user`; `post_status=private|publish`).
- `wp_postmeta`: `_arc_kind=favorites|kit`, `_arc_items=[{product_id,qty,owned,note},...]`.
- Favorite je jeden soukromý automaticky vytvářený seznam. Balíček je jiný seznam stejného typu a lze jej kopírovat z oblíbených.
- Staré příspěvky a další WP data se **nemění**.

## Kód a bezpečnost

- PHP 8+, WP 6.3+; zdrojový kód používá WordPress sanitizaci/escapování, REST nonce a kontroly vlastníka.
- Veřejná stránka nevrací seznamy soukromých profilů, i kdyby příspěvek dříve byl publikovaný.
- AI credentials, Chat DB, Chat SSO a user účty Fresh systému nejsou součástí pluginu.
- V případě nárůstu objemu seznamů připravit dedikovanou tabulku položek a stránkování; v0.1 je záměrně malý rozsah.

Více informací je v `docs/ANALYZA-ZDROJU-A-ROADMAP.md`.
