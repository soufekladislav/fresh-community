# AR Community – audit dodaných komponent a doporučená implementace

Datum: 24. 10. 2026. Vstupní archivy: `cajnacars.zip`, `chat.zip`, `fresh affiliate+sales.zip`.
Závěry o zdrojovém kódu platí pro **konkrétně dodané balíky**, nikoliv automaticky pro přesné verze aktivní na Adrenaline Race.

## 1. Audit po komponentách

| Původní řešení | Co je v dodaném kódu skutečně připravené | Jak použít v AR Community | Co NEkopírovat |
|---|---|---|---|
| ČajnaCars Core, `payload/cajnacars-core.php` | REST `/favorites`, `/favorites/merge`, `/favorite-items`, shortcode `cc_favorites`, `cc_favorite_button`, hostovské `localStorage` s klíčem `cajnacars_guest_favorites`, JS přenos oblíbených do účtu | Převzít datový tok a UX pro AR produkty; v AR odlišné REST routy a `arc_collection` s typem favorites/kit | Typy `cc_model`, `cc_vehicle_offer`, názvy CSS, komplexní katalog, vlastní účty |
| ČajnaCars Dealer Registration | Přihlášení/registrace přes WP, profil jako CPT, vazby `_cc_owner_id` a `_cc_dealer_ids`, profil nejprve `draft`, administrátorské schválení | Inspirace pro **explicitní vazbu historický profil → ověřený WP účet** a schvalovací workflow | Roles `cc_dealer`, firmy, IČO, prodejci aut |
| ČajnaCars Partners | Schvalování partnerů, vlastní WooCommerce endpoint, onboarding | Vzor pro rozšíření Můj účet a partnerský onboarding | Paralelní AR affiliate účet, kredity partnera ČajnaCars |
| ČajnaCars Leads 0.3.2 | Formuláře, ochrana proti spamu, CRM, CSV, ruční routing; README upozorňuje na odstraněný rekurzivní `MutationObserver` | V budoucnu připustit „požádat o radu od partnera“, ovšem odděleně | Kompletní dealer lead systém; globální MutationObserver |
| ČajnaCars Ads 0.4.2 + Credits | Kampaně, creative studio, placená reklama a peněženka | Nejvýše návrhová inspirace pro případné propagované balíčky **později** | Reklamní účetnictví, kreditové tabulky, jiná ekonomika |
| Fresh Chat 0.3.2 | Python Flask + MySQL, vlastní účet/SSO, konverzační API, ukládání zpráv, CSRF, individuální historii, frontend `static/chat.js` / `chat.css`, serverovou komunikaci s `/v1/engine/run` | Vzor chatu: rychlé otázky, historii krátkých tahů, zobrazování stavů, „typing“, server-side AI request. Design/chat widget bude samostatný WordPress frontend | Flask server, tabulky `chat_users`, `CHAT_SESSION_SECRET`, SSO do Fresh účtů, PWA worker, soukromé konverzace |
| Fresh Affiliate 0.5.5 | `Fresh_Affiliate_DB::get_affiliate_by_user()`, `Fresh_Affiliate_Tracker::referral_url()`, tokeny, tracking, provize, lifetime vazby, WooCommerce hooky | Při aktivním partnerovi na AR použít existující metodu generování partnerského odkazu na balíček či produkt. Účetnictví/referral řeší originální plugin. | Vlastní referral cookie, vlastní provize, vlastní platební tabulky |
| Fresh Sales 0.10.0 | Obchodní dashboard, CRM, follow-up, SMTP/IMAP, Woo objednávky, návaznost Fresh Affiliate, oprávnění ke kontaktování, evidenci souhlasu | Potenciální externí nástroj pro aktivní partnerství a B2B. AR Community může ukázat proklik pro oprávněného partnera, pokud později vznikne kompatibilní rozhraní. | Automatické rozesílky, přístup k cizím kontaktům, CRM tabulky, odesílání bez dokladovaného oprávnění |

### Poznámky ke skutečnému kódu

- ČajnaCars oblíbené jsou dnes převážně **plochý seznam ID** v `_cc_favorites`. Pro AR nestačí takový seznam, protože má obsahovat více tematických balíčků; proto AR používá jednotný typ `arc_collection` pro `favorites` a `kit`.
- U ČajnaCars registrace prodejce se profil explicitně propojuje s uživatelem: `_cc_owner_id`. To je vhodnější vzor než domněnka, že `post_author` starého článku o člověku je skutečně daný člověk.
- Fresh Chat má **samostatnou Flask autentizaci** a databázi. U Adrenaline Race je přihlášení už vyřešené WordPressem/WooCommerce; druhou identitu nepřenášet.
- Fresh Chat volá Engine serverově s Bearer klíčem a payloadem `source`, `quality`, `prompt`, `context`, `max_output_tokens`. U AR může libovolná oddělená implementace AI služby toto schéma využít, ale AR Community musí pracovat s katalogem skutečných WooCommerce produktů, nikoliv s AI vymyšlenými SKU.
- Fresh Sales explicitně kontroluje oprávnění před oslovením (např. `Fresh_Sales_Consent::can_send_offer`). Ani veřejně dostupný e-mail nemá automaticky vytvořit souhlas k rozesílání.

## 2. Architektura

```
WordPress / WooCommerce (jediná uživatelská a obchodní vrstva)
 ├── WooCommerce: účet, produkty, košík, objednávky
 ├── Elementor: pouze šablony a stránky; AR shortcode widgety
 ├── AR Shop Organizer: katalog a kategorizace, STOP dalších úprav
 └── AR Community (samostatný WP plugin)
      ├── WP profil: veřejný / soukromý, zájmy, vlastní články
      ├── ARC collections: Oblíbené + tematické balíčky
      ├── veřejná knihovna + profily + magazín (články pending)
      ├── affiliate adapter: jen URL, pokud je aktivní Fresh Affiliate
      ├── advisor adapter: připraven, neaktivní
      └── budoucí Sales adapter: pouze odkazy / práva, ne kopie CRM

      externí služba pro AI = volitelný vykonavatel AI požadavků
      (samostatná provozní služba, nikoliv systém AR nebo identita AR)
```

## 3. Způsob fungování

**Návštěvník bez registrace**: prohlédne produkty, přidá oblíbené do dočasného prohlížečového seznamu. Později vytvoří účet přes standardní WooCommerce a seznam se bezpečně sloučí. AI chat později nabídne návrh vybavení bez nucené registrace.

**Zákazník**: vlastní soukromý profil, seznam oblíbených a libovolné tematické balíčky. Může zkopírovat oblíbené do nové sestavy. Ze seznamu se nebere identita produktu ani skladové informace – každá položka stále odkazuje na WooCommerce.

**Aktivní člen komunity**: dobrovolně přepne veřejný profil a jednotlivé sestavy na veřejné. Publikuje texty do magazínu na schválení, sdílí odkazy, přivádí čtenáře k produktům.

**Affiliate partner**: získá své partnerské URL přes existující Fresh Affiliate. Na profilu může později mít i prodejní vstup; procesy CRM ze Sales se neduplikují.

## 4. Historické „Lidé“ – co se zatím ví a co ne

Zadání říká, že existují historické ručně psané příspěvky o lidech a u většiny existuje WordPress účet. V dodaných archivech **není export databáze Adrenaline Race, taxonomie této sekce ani ověřený seznam vztahů příspěvek ↔ účet**. Proto nelze ještě napsat spolehlivou automatickou migraci.

Doporučený bezpečný postup:

1. Identifikovat konkrétní `post_type`, taxonomie a meta klíče příspěvků „Lidé“ v AR.
2. Získat vzorek 20 příspěvků, s existujícím WP `user_id`, údaji o autorovi a veřejném zobrazení.
3. Návrhy vazeb vytvořit podle **ověřeného identifikátoru**, ne podle shody jmen; nejednoznačné dát ke schválení.
4. Nezakládat účty a nepublikovat další osobní údaje automaticky.
5. Původní URL zachovat; historický článek může být navázaný na aktivní profil. Před publikací nebo oslovením historických spolupracovníků ověřit právní základ.

V pluginu je pro bod 1 jednoduchá read-only diagnostika. Migrace není součástí v0.1.

## 5. Roadmap a akceptační kritéria

**V0.1, dodaný plugin (staging)**
- Plugin lze samostatně aktivovat vedle WooCommerce, Fresh Affiliate a Organizeru.
- Vytvořit veřejný profil musí být **výslovná volba**; nový účet zůstane soukromý.
- Oblíbené + balíčky ukládat do jediného systému seznamů, ne duplicitně.
- Veřejný balíček nesmí být dostupný, pokud autor vypne veřejný profil.
- Nepřihlášené ID oblíbených se po přihlášení sloučí.
- Přes REST nelze přidat produkt do cizího seznamu ani číst cizí soukromý seznam.
- Článek od člena končí jako `pending`, nepřímo nepublikuje spam.
- AI adaptér vrací 501; nesmí generovat náklady, dokud není výslovně připojen.

**V0.2 – před produkčním spuštěním**
- Projít skutečný WP staging a upravit šablony Elementor.
- Moderace/report veřejných balíčků a přehled vlastních příspěvků, uživatelské notifikace.
- Všechny položky seznamu: mám/chci koupit, množství, poznámka, nastavení osobních informací.
- Vložit **jen chybějící a dostupné produkty** do jednoho WooCommerce košíku; kontrola variant, skladovosti a cen.
- Volitelné oblíbené přímo v kartě produktu napříč šablonami.
- Zvážit rychlé recenzní balíčky, kategorizaci sestav a parametrické vyhledávání.
- Ověřit mechanismus atribuce Fresh Affiliate v případě, kdy klient přidá více produktů z různých veřejných seznamů.

**V0.3 – AI osobní poradce**
- Kompaktní popup/whole-screen mobilní chat, bez jiné AR identity.
- Max. 3–5 cílených dotazů: typ aktivity, trvání, počasí/sezóna, pro koho, co už mám, rozpočet.
- Model vrací **strukturovaný seznam potřebných typů výbavy**, nikoliv přímo vymyšlené SKU.
- Produktové hledání a finální ceny/sklady bere výhradně z WooCommerce; doporučuje ověřené položky.
- Vytvoří soukromý balíček výbavy, možnost uložit/publikovat a vložit do košíku.
- Limity spotřeby, měření konverzí a explicitní zpracování osobních údajů v konverzaci.

**V0.4 – komunita a partnerství**
- Ověřená migrace „Lidé“ a přiřazení profilů historickým spolupracovníkům.
- Veřejné autorské profily s publikovanými články a balíčky.
- Embeddovatelný blok „Petr doporučuje“ do magazínu pomocí shortcodu.
- Vlastní odkaz pro partnera na balíček se správnou atribucí, bez nových provizních tabulek.
- Případné propojení na Fresh Sales pouze pro oprávněné obchodní workflow a se zohledněním souhlasu.

## 6. Rizika a provozní hranice

- Žádný nový vyhledávací/importní systém v Shop Organizeru.
- Žádné přebírání OAuth/SSO z Fresh Chatu.
- Žádný kreditní nebo reklamní systém z ČajnaCars.
- Bez automatického sloučení historických uživatelů podle autora redakčního článku.
- Nespoléhat na AI při posuzování vhodnosti výrobků bez strukturované validace.
- Veřejné profily nesmějí zobrazovat objednávky, e-maily a jiné soukromé údaje zákazníků.
- Nekompatibilní nebo neaktivní Fresh Affiliate = obyčejné URL, nikoli zdánlivě fungující provize.
- WooCommerce variantní produkty a hromadný košík: explicitní logika až ve V0.2.

## 7. Provozní kontrola

Ve složce ZIP není žádný cizí kód z dodaných archivů kopírovaný 1:1, žádné soubory s hesly ani credentials. Jde o **nový, samostatný plugin** založený na ověřených vzorech z auditovaných komponent.

Ověření při vývoji: `php -l` všech PHP souborů, `node --check` JS souboru, izolovaný Chromium test frontendových interakcí s mock REST rozhraním. **Neproveden integrační test na skutečné databázi Adrenaline Race ani s reálným WooCommerce/WP provozem.**
