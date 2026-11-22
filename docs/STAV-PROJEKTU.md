# Fresh Community — skutečný stav a priority
Aktualizace: 22. 11. 2026. Větev pro první instalaci navazuje na PR #17 (zobecnění). Obě PR jsou **návrhy bez produkčního merge**.

## Jak rozumět stavům
- **Implementováno v kódu:** soubor/hook/REST endpoint existuje, nemusí fungovat v reálné instalaci.
- **Čeká na QA:** technická syntax prošla, ale WordPress test není zaznamenán.
- **Rozpracováno:** funkce nebo dokumentace se připravuje v otevřeném PR.
- **Chybí / plán:** dosud nemá hotové funkční řešení.

| Priorita | Funkce | Stav | Vztah na Issue / PR |
|---|---|---|---|
| P0 | Zobecnění značky/URL, volitelné moduly | Kód v PR #17, čeká na QA | #11, #17 |
| P0 | První instalace: idempotentní založení stránek | Kód v PR #18, čeká na QA | #19, #20 |
| P0 | Veřejná vs. pozvánková registrace a přístup | Kód v PR #18, čeká na bezpečnostní QA | #21 |
| P0 | 1:1 zprávy přátel, oznámení | Beta kód v PR #18, čeká na QA | #22 |
| P0 | Test na čistém WP + regresní test Adrenaline Live | Neprovedeno | #12, #23 |
| P1 | Základ: profily, zeď, reakce, přátelé | Implementováno dříve, QA chybí | #1–4 |
| P1 | Uložené produkty/obsah, Woo | Implementováno dříve, QA chybí | #5 |
| P1 | E-maily, správce, oznámení | Implementováno dříve, QA chybí | #6 |
| P1 | PWA instalace / aktualizace | Částečně, QA chybí | #7 |
| P0 | GDPR: retenční pravidla, export a mazání soukromých zpráv | Chybí před veřejným provozem | #24 |
| P1 | První zapojení členů a správní nastavení | Plán po QA | #25 |
| P1 | Moderace ostatního komunitního obsahu | Nedokončeno | #14 |
| P2 | Prohlížečové push notifikace | Chybí celé doručování | #8 |
| P2 | YouTube kanál a schvalování | Implementováno, QA chybí | #9 |
| P2 | Premium/Stripe kompatibilita | Nutná regresní kontrola | #10 |
| P2 | Komerční licence/automatické aktualizace | Plán | #11 |
| P3 | AI poradce, Eventy a historická migrace | Odloženo | #15 |

## Vývojové pravidlo
Každé Issue drží jedinou odpovědnost, akceptační kritéria a odkaz na PR. Neoznačovat „hotovo“, dokud existuje důkaz testu v příslušném prostředí. Dokumentace a README se synchronizují s vydáním. Priorita do prvního testovacího nasazení je P0; další nové integrace a rozšíření nyní nezačínat.
