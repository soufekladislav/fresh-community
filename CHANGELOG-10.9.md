# Fresh Community 0.2.0-beta.10.9 — Woo nástěnka po registraci

- **Příčina:** `woo_default_community()` přesměroval jen čistou URL `/muj-ucet/`; pozvánky přidávají `?arc_invite=…&ar_ref=…`, takže se vykreslila standardní WooCommerce nástěnka a ne `ARC_Onboarding::panel()`.
- **Oprava:** `/muj-ucet/` je hlavní stránka *Moje komunita*. Pro přihlášené se na přesné kořenové adrese Woo My Account vyrenderuje existující `[ar_community_account]` včetně průvodce, profilu a všech komunitních bloků. `woocommerce_account_dashboard` se zavolá pro Fresh Affiliate a další integrace.
- Zachována WooCommerce navigace a veškeré její endpointy (`fresh-affiliate`, `orders`, `edit-address`, `edit-account`, `payment-methods` atd.).
- `ar-community` endpoint zůstává přístupný pod původní URL, ačkoli položka „Moje komunita“ odkazuje nyní na kanonický Woo dashboard.
- Woo registrace na účtové stránce nyní používá čistou URL účtu bez parametru pozvánky; identita a affiliate přiřazení se zpracují už při registraci.
- Google **nové** účty po přihlášení směřují na stejnou stránku, stávající účty zachovávají původní návratovou adresu.
- Při nestandardním Woo handleru zůstává bezpečný fallback přes `woocommerce_account_dashboard`.

## Minimální test
1. Nová registrace s `?arc_invite=…&ar_ref=…` → po registraci `/muj-ucet/` se zobrazí „Nejsi na to sám“ a první kroky.
2. Klik „Rozumím“ → u nového uživatele zůstane dashboard, první kroky se skryjí, ostatní obsah funguje.
3. `/muj-ucet/fresh-affiliate/` ukáže affiliate program; objednávky/adresy/platby zůstanou na vlastních endpointech.
4. Starý odkaz `/muj-ucet/ar-community/` pořád vykreslí komunitní stránku.
5. Na přihlášení se stávajícím Google účtem se nemění návratová adresa, nový Google účet míří na `/muj-ucet/`.
6. Test po vymazání LiteSpeed Cache a v anonymním okně.

Poznámka: Nezasahuje do interních databázových ani provizních tabulek. Fresh Affiliate se nemění.
