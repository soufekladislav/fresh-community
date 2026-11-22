# AR Community 0.2.0-beta.10.5-rc1 — kontrolní verze

Změny oproti 10.4: funkční `[ar_live_topbar]` bez automatického wp_body_open vložení; Přátelé na profilu jen vlastníkovi; individuální čtení a smazání oznámení; označení Oblíbené sporty; rozšířený výstup referenčního statusu u potvrzených přátel a jednorázová affiliate atribuce při přijetí; dřívější načtení CSS pro vnořené /app/pratele a /app/oznameni.

**Upozornění:** Verze určená pro staging a testování, nikoli automaticky schválený produkční build. Není ověřeno fungování Google SSO, uploadu velkých fotek, externích pozvánek, Stripe ani live migrace. To vše vyžaduje dvouuživatelské integrační testy. Smazání oznámení je trvalé. Účty, objednávky a existující provizní vazby se nemigrují.

## Omezení před ostrým nasazením

- Fotografie se nyní odesílají po kompresi; veřejné příspěvky lze nahrát i z velké mobilní fotografie. Neveřejné fotografie stále záměrně nejsou povolené, protože WP Media URL jsou veřejné.
- Loader už má předem načtené CSS i na podstránkách `/app/pratele/` a `/app/oznameni/`, ale odezva serveru a renderování Elementor/LiteSpeed se musí ověřit na produkci.
- Při přijetí pozvánky od aktivního referenčního partnera se vyplní pouze dosud neobsazená affiliate lifetime vazba. Existující přiřazení se nepřepíší.
- Instalační tlačítko se do horního shortcodu nepřidává.
