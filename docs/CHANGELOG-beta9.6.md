# AR Community 0.2.0-beta.9.6 – Přátelé, živá oznámení a zobrazované jméno

- Vlastní decentní tlačítka v přátelích/žádostech: specifická CSS pravidla nezávislá na globálním Elementoru.
- Upozornění v pravém horním rohu při otevřené stránce, privátní REST polling cca každých 20 sekund; nepřečtené v plovoucím menu. Nejedná se o push notifikace při zavřeném webu.
- Na stránce „Můj účet → Moje komunita“ lze měnit zobrazované jméno. Synchronizuje se pouze `wp_users.display_name`, `nickname` v user_meta a komunitní alias. Nemění se login ani e-mail.
- Komunitní účet včetně alternativní cesty `/app/muj-ucet/ar-community/` získává spodní plovoucí navigaci.
- Stránka Přátelé zobrazuje aktivního referenčního partnera na základě `Fresh_Affiliate_DB::get_affiliate_by_user()` a `is_partner` (read-only, bez změny provizí).
- V přátelích i oznámeních se zobrazuje WordPress `display_name` jako kanonické jméno.
- Nic se nemaže, žádné nové tabulky ani data zákazníků, žádné změny přihlašovacích jmen.
- Cache: původní export již měl zakázanou REST cache a privátní cache; bezpečný doplněný export přidává pouze `/ulozene/` a extra vyloučení komunitních REST endpointů.

## Chování notifikací

Polling probíhá jen v otevřené a viditelné kartě. Při prvním načtení se zobrazí čítač; nové oznámení vyvolá malou přechodnou kartu. Pokud stránku zavřeš, při další návštěvě jsou oznámení stále v Inboxu. Při přechodu mezi stránkami se ID posledního zaznamenaného oznámení drží jen v rámci prohlížečové karty (sessionStorage, žádný text oznámení). Na vyšší provoz bude vhodný lehčí API endpoint nebo serverové push.
