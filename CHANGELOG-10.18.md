# Fresh Community 0.2.0-beta.10.18 — první instalace mikrokomunity
**Datum:** 22. 11. 2026  
**Stav:** návrh v PR #18, nenahrazuje provozní QA.

## Doplněno
- **První instalace:** nástroj v administraci pro bezpečné vytvoření sedmi základních WP stránek; opakované spuštění je nedestruktivní a hlásí konflikty.
- **Nastavení registrace:** veřejná nebo uzavřená pouze na pozvánku, s kontrolami WP/Woo formulářů, Google vytváření účtu a komunitních REST/shortcode výstupů.
- **Správa členství:** administrátor může schvalovat existující WordPress uživatele; pozvánka poskytuje členství až po ověřené vazbě s pozývajícím.
- **Soukromé zprávy:** jednoduchý textový 1:1 chat potvrzených přátel, vlastní tabulka, oznámení, limit odesílání a načítání nových zpráv každých 12 sekund.
- **Navigace:** po instalaci nových stránek přibývají Zprávy, správné odkazy na Moji komunitu a možnost psát z profilu přítele.
- **Dokumentace:** `docs/PRVNI-INSTALACE.md` a `docs/STAV-PROJEKTU.md` a Issues #19–25.

## Zachováno
- Původní složka pluginu `ar-community/`, databázové identity a shortcody kvůli aktualizacím.
- Existující `/app/` Adrenaline Live, účet WooCommerce, Affiliate a Stripe billing se neodstraňují.

## Výslovně nehotové
- Provozní test na čistém WordPressu a regresní test AR, GDPR export a mazání chatových zpráv, blokace obtěžování a plnohodnotné push notifikace.
- Nejde o šifrovaný end-to-end chat ani o „okamžité“ WebSocket zprávy.
- Tato verze nemá být označena jako produkčně ověřená bez výsledků testů a uzavření P0 Issues.
