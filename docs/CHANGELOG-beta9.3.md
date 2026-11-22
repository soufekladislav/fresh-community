# AR Community 0.2.0-beta.9.3 – Uložené a oblíbené produkty

## Cíl
Jedna uživatelská oblast **Uložené**, která spojuje dva již existující mechanismy bez změn databázové struktury:

- **Na později:** komunitní příspěvky a magazínové články, uložené v uživatelském meta `_arc_saved_wall_items`.
- **Oblíbené produkty:** stávající soukromý seznam `arc_collection` druhu `favorites`, sloužící také pro tvorbu balíčků.

## Shortcody

| Shortcode | Použití |
| --- | --- |
| `[ar_community_favorite_button]` | Srdíčko pro aktuální produkt (funguje už v předchozí verzi) |
| `[ar_community_favorite_button product_id="123"]` | Srdíčko pro konkrétní produkt ID 123 |
| `[ar_community_favorite_products]` | Výpis oblíbených produktů uživatele včetně obrázku, ceny a odebrání |
| `[ar_community_favorite_products limit="6"]` | Krátký náhled produktů |
| `[ar_community_saved_posts]` | Stránkovaný úplný seznam uložených příspěvků/článků |
| `[ar_community_saved_posts limit="5"]` | Náhled posledních pěti uložených |
| `[ar_community_saved]` | Celá sjednocená stránka Uložené s přepínáním položek |
| `[ar_community_saved_icon]` | Ikona srdíčka s počtem oblíbených produktů (např. Elementor Shortcode widget) |
| `[ar_community_saved_icon label="1"]` | Ikona s textovým popiskem Uložené |

## Umístění
- Při první aktivní návštěvě po aktualizaci WordPress vytvoří **stránku `/ulozene/`** s `[ar_community_saved]`, pokud ještě neexistuje. NIKDY nepřepisuje existující stránku stejného slugu. Existující stránka musí shortcode obsahovat ručně.
- **Můj účet → Moje komunita**: v bloku produktů nový výpis oblíbených (6 ks), přímo pod ním nový panel **Uložené příspěvky** s náhledem (5 ks) a odkazem na celý seznam.
- **Desktop pravý panel /app/**: odkaz „Zobrazit všechny“ v bloku Uložené příspěvky vede na `/ulozene/?tab=posts`.
- **WP Vzhled → Menu**: nejspolehlivější je vlastní položka Odkaz na `https://adrenalinerace.eu/ulozene/` a název `Uložené ♡`. Standardní WP menu obvykle shortcody v názvu nevykonává. Pro Elementor Shortcode widget použijte `[ar_community_saved_icon]`.

## Chování a zabezpečení
- Oblíbené produkty fungují také hostům v lokálním úložišti prohlížeče; po přihlášení se slučují přes stávající REST API. Prohlížeč nezaručuje synchronizaci hostovských oblíbených mezi zařízeními.
- Uložené příspěvky se zobrazují **jen přihlášenému uživateli**, při výpisu se vždy kontroluje aktuální viditelnost. Umožňují odebrání přes stávající endpoint `POST /ar-community/v1/live/items/{type}/{id}/save`.
- Pro otevření staršího komunitního příspěvku ze seznamu používáme `/app/?arc_post=ID` a nové čtecí REST API `GET /ar-community/v1/live/post/ID` se stejnými přístupovými právy jako zeď.
- Zůstávají nedotčené WooCommerce, objednávky, předplatné a Fresh Affiliate.

## Kontroly
- `php -l` všech PHP souborů a `node --check` všech JS souborů prošly.
- Lokální PHP test: náhled uložených respektuje typ obsahu a nezveřejňuje koncept; zkouška shortcode výpisu, ikony a deep-linku prošla.
- Produkční WordPress / REST / frontend test na konkrétním hostingu zatím neproběhl.
