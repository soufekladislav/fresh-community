# Fresh Community — první instalace / testovací scénář
**Verze určená k testování:** `0.2.0-beta.10.18`. **Stav:** návrh ve větvi `feature/first-install-community-foundation`. Nestačí PHP/JS lint; je nutný staging WordPress test.

## 1. Nejprve záloha a ZIP
1. Neinstalujte nedokončený PR přímo do produkčního Adrenaline Live. Na čistém testovacím WordPressu stačí připravený ZIP z úspěšného GitHub Actions běhu.
2. ZIP obsahuje jedinou složku `ar-community/` a soubor `ar-community/ar-community.php`. Tato identita je stejná pro první instalaci i aktualizace.
3. Aktivujte plugin. Nespoléhejte na automatickou tvorbu stránek při aktivaci.
4. Otevřete **Fresh Community → Nastavení**: zadejte název, krátký název, popis, logo/ikony/barvy a přepněte nepotřebné moduly.

## 2. Založení stránek
Otevřete **Fresh Community → První instalace** a klikněte **Vytvořit chybějící komunitní stránky**. Průvodce vytvoří:
| Stránka | Výchozí slug | Shortcode |
|---|---|---|
| Zeď | `/komunita/` | `[ar_live]` |
| Moje komunita (editace účtu) | `/moje-komunita/` | `[ar_community_account]` |
| Profil člena | `/profil/` | `[ar_community_profile]` |
| Členové | `/clenove/` | `[ar_community_people]` |
| Přátelé | `/pratele/` | `[ar_community_friends]` |
| Zprávy | `/zpravy/` | `[ar_community_messages]` |
| Oznámení | `/oznameni/` | `[ar_live_notifications]` |

U existující stránky se správným shortcodem používá její ID. Stránku, která na stejné adrese obsahuje jiné informace, **nepřepisuje** — zobrazí konflikt pro ruční řešení. Opakované spuštění nevytváří duplicitní stránky. U Adrenaline Live zůstává existující `/app/` a vlastní Elementor rozložení.

## 3. Volby registrace
V **Fresh Community → Nastavení**:
- **Veřejná registrace**: WordPress registrace se řídí standardním nastavením webu, každý přihlášený platný WP uživatel má komunitní přístup.
- **Pouze na pozvánku** (výchozí pro nové weby): komunitní přístup získá správce nebo uživatel zaregistrovaný z platné pozvánky. Existující WP uživatele lze schválit v jejich administrativním profilu nebo přes přijetí pozvánky. Plugin blokuje nepozvané registrace přes standardní WordPress a Woo registrační formuláře, chrání komunitní REST endpointy i shortcody. Woo checkout může založit běžný zákaznický účet, ale neuděluje automaticky přístup do komunity. Google může přihlásit již existující účet, ale při uzavřeném režimu nevytváří nový účet.
- Povolte v **WordPress → Nastavení → Obecné → Členství** registraci, pokud má fungovat WP registrační formulář. Plugin toto **globální** nastavení automaticky nemění.
- V průvodci založte **Testovací pozvánku správce**; pozvánka se otevře na komunitní stránce, kde se uloží token do zabezpečené cookie a uživatel pokračuje na standardní WP/Woo registraci. Token má platnost 30 dní. Testujte v anonymním okně, s odlišným e-mailem.
- Pokud chcete komunitu bez registrací, ponechte registrace WordPressu vypnuté; přístup budou mít jen ručně zřízené a schválené účty. Toto zatím není samostatný režim UI.

## 4. Chat mezi členy (beta)
- `/zpravy/`: soukromé textové zprávy pouze mezi potvrzenými přáteli, bez HTML a bez veřejných URL.
- Zprávy jsou ve vlastní tabulce `arc_direct_messages`. Každá operace ověřuje přihlášení, členství, přátelství a příjemce.
- Limit 1–1500 znaků, max. 40 zpráv za hodinu na účet; posledních 50 zpráv v konverzaci; automatické obnovení každých 12 sekund.
- Zpráva vyvolá komunitní oznámení bez zveřejnění textu. E-mail podle uživatelského nastavení.
- **Zatím chybí:** mazání/export pro GDPR, blokace uživatelů, přílohy, skupinový chat, WebSocket, push, předchozí historie v UI. Nenasazovat jako veřejně dostupnou komunikační službu bez dokončení QA a retenční politiky.

## 5. Povinné testy před produkcí
- [ ] Aktivace na čistém WP bez Woo → žádná PHP chyba, hlavička a správná verze
- [ ] Kliknutí na průvodce vytvoří sedm stránek, opětovné kliknutí nic nezdvojí
- [ ] Konflikt s existující stránkou nevykoná přepis
- [ ] Název/ikony/barvy správně zobrazí PWA i pozvánky
- [ ] Veřejná registrace umožní bezplatný vstup a vlastní profil
- [ ] Uzavřená registrace bez pozvánky je skutečně odmítnuta přes WP formulář
- [ ] Pozvánka na čistý e-mail → založení účtu → propojení s pozývajícím → chat
- [ ] Google nová registrace v uzavřeném režimu odmítnuta, přihlášení existujícího účtu funguje
- [ ] Existující WP účet přijme pozvánku z přátel a získá členství
- [ ] Nečlen nesmí číst wall feed, profily, privátní zprávy přes REST ani shortcode
- [ ] Dva propojení přátelé si vymění zprávy a oznámení, třetí člověk nic neuvidí
- [ ] Rozpojení přátel ihned zabrání přístupu do staré konverzace
- [ ] Mobilní stránky, PWA a navigace jsou použitelné
- [ ] Adrenaline Live upgrade zachová `/app/`, Woo, Fresh Affiliate, Stripe a existující PWA
- [ ] Záloha a návrat k předchozí verzi je možný bez ztráty dat

## 6. Hranice první verze
- Neslibovat plně otestované první nasazení, dokud nejsou splněné výše uvedené kroky.
- Jedna instalace WordPressu = jedna samostatná komunita. Jednotlivé weby nesdílejí uživatelská data.
- Staré technické prefixy `ARC_`, shortcody a databázové tabulky zůstávají kvůli zpětné kompatibilitě.
- Google OAuth, SMTP a PWA mohou vyžadovat samostatnou konfiguraci provozovatele.
