# AR Community 0.2.0-beta.10.1 — navigace, soukromí, návrat ze zdi, pozvánky

Navazuje přímo na beta.10.0, zachovává data komunity, oblíbených produktů, balíčků, WooCommerce i Fresh Affiliate.

1. Horní účetní lišta a plovoucí Elementor header: sjednocená výška pro přihlášeného běžného uživatele a administrátora včetně mobilních pravidel.
2. Návrat z článku magazínu: krátkodobě se pamatuje filtr, stránka nekonečné zdi a souřadnice posunu. Po návratu se odpovídající stránky **znovu načítají přes soukromé REST API** a obnoví se pozice; nikdy se neukládá HTML příspěvků do úložiště prohlížeče.
3. Rozsah příspěvku je omezen nastavením profilu: soukromý -> jen já; síťový -> síť / já; veřejný -> všechny volby (sdílení přímo na zeď přítele vyžaduje veřejný profil, protože jej mohou vidět i přátelé příjemce). Server kontroluje vytvoření, editaci, zobrazení i přesdílení. Příspěvky starší než změna viditelnosti profilu se při zpřísnění nastavení rovněž skryjí.
4. Veřejný profil a pravý panel zdi nyní ukazují viditelnost profilu. Blok přátel se nabízí také návštěvníkům, avšak zobrazí jen takové profily přátel, jejichž vlastní nastavení viditelnosti návštěvníkovi přístup dovoluje.
5. Krátký celoobrazovkový loader chrání před bliknutím rozpracované Elementor šablony. Je aktivní pouze na aplikaci a má bezpečnostní timeout, aby nezablokoval stránku při chybě API.
6. Pozvánky: nový uživatel se po registraci přes platný odkaz spojí s pozývajícím. Existujícímu uživateli se po přihlášení vytvoří nová žádost; přihlášený uživatel otevře link a spojení potvrdí tlačítkem. Odeslání samotné WhatsApp/e-mailové zprávy bez registrace / přijetí nezaloží přátelství. Fresh Affiliate první přiřazení se nepřepisuje.

Zachováno: Stripe/Woo, stávající PWA service worker bez cache soukromého HTML a REST dat; žádná destruktivní migrace.

### Známé omezení

Přiložené fotografie mají veřejné mediální URL. Proto současný upload fotografií do komunitního příspěvku vyžaduje veřejný příspěvek i veřejný profil; toto omezení je starší a beta10.1 ho vědomě nezrušila. Chce-li komunita neveřejné fotografie, potřebuje řízené doručování chráněných souborů.
