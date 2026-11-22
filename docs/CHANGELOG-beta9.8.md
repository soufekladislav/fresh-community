# AR Community 0.2.0-beta.9.8 – přátelé a Moje komunita

- **Moje komunita:** jedna karta Přátelé s vyhledáváním a deseti naposledy registrovanými přáteli. Pozvánky z této karty přesunuty na hlavní stránku Přátelé. Odkaz „Spravovat přátele“.
- **Přátelé:** tři záložky „Moji přátelé“, „Pozvat přátele“, „Nové žádosti“. Moje přátele lze filtrovat podle jména. Pozvání zobrazuje průběžný adresář členů, kteří nemají soukromý profil (24 na stránku), vyhledávání podle zobrazovaného jména a přesného e-mailu, přidání přátel. Pozvání přes WhatsApp/e-mail/reference zůstávají na této stránce.
- **Upozornění v účtu:** pouze počet nepřečtených a odkaz do přehledu; osobní oznámení jsou stále načítána přes autorizované REST API bez cache.
- **WooCommerce Můj účet:** skrytá položka „Nástěnka“ a Moje komunita první. Přihlášená domovská stránka účtu přesměruje na komunitní endpoint; objednávky a ostatní Woo endpointy zůstávají přístupné.
- **Odkazy:** aplikace, spodní menu, profil, oznámení, notifikace, emailové pozvánky i PWA shortcut používají permalink existující stránky z hierarchie `/app/pratele/` nebo `/pratele/` (analogicky oznámení) podle skutečné konfigurace WordPressu.
- **Soukromí:** adresář neposílá profily s nastavením private, suspended účty ani e-mailové adresy. U „network“ profilu poskytuje jen jméno, avatar a stav přátelství; soukromý obsah nepovoluje.
- **Bez migrace dat:** WP účty, objednávky, Fresh Affiliate přiřazení, uložené položky, komunitní příspěvky a relation edges beze změny.

## Testy po nasazení
1. Můj účet → Moje komunita: přátelé (10), vyhledávání, Spravovat přátele a zjednodušené upozornění.
2. Můj účet → původní Nástěnka není v záložkách, root účtu vede do Moje komunita. Ověřit Objednávky/Fakturaci/Změnu hesla.
3. Přátelé: 3 záložky, filtr mých přátel, člen s veřejným profilem, člen s profilem network, soukromý uživatel není v katalogu, pozvánky + oznámení.
4. Ověřit dolní menu a pozvánky přes e-mail v reálných permalincích.
