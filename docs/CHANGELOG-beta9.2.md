# Adrenaline Live 2.0 / AR Community 0.2.0-beta.9.2

Navazuje na beta 9.1. Kompletní plugin (ne pouze CSS).

## Opravy

- WooCommerce produktový slider: cena se převádí ze značkovaného WooCommerce HTML na dekódovaný text v UTF-8 včetně nedělitelných mezer a měny (například `2 399 Kč`). Zamezuje zobrazení doslovných entit typu `&nbsp;`.
- Uložené příspěvky: desktopové postranní položky obsahují tlačítko × „Odebrat z uložených“. Používá stávající autentizovaný REST toggle a nemění ani nemaže originál. Aktualizuje uložený filtr.
- Vkládání: samostatné režimy **Textový příspěvek**, **Fotografie s popisem** a **Video s popisem**. Přepínání skrývá nepříslušná pole a neposílá omylem fotografii/video z jiného režimu. Neutrální šedá barva vstupního tlačítka i při hoveru.
- Video: rychlé vložení YouTube URL včetně volitelného načtení ze schránky (vyžaduje svolení prohlížeče); zachováno otevření YouTube Studio. Nejde o upload do AR kanálu.
- Komunitní profil: nepovinné položky sportovní profese / služby a sportovní profesní znalosti / zkušenosti. Data jsou uložena pouze v user_meta a zobrazena jen návštěvníkům, kteří již mají oprávnění číst profil. Zobrazují se i v desktopové profilové kartě zdi. Připraveno pro budoucí vyhledávání odborníků.

## YouTube – navazující integrace (zatím není součástí beta 9.2)

Pro vlastní nahrávání souboru z mobilu přímo do kanálu Adrenaline Race je nutné:
1. Propojit vlastníka kanálu pomocí OAuth 2.0 a Google Cloud projektu se schválenými YouTube API oprávněními.
2. Nabídnout v AR aplikaci mobilní výběr videa, odolný přenos po částech a staging *mimo veřejný webroot*, limity a antivirovou/kontentovou kontrolu.
3. Každý upload od uživatele nechat nejprve schválit administrátorem AR; automatizovaný přenos přes YouTube Data API `videos.insert` (resumable upload) až poté. Publikování dočasně jako YouTube **private** do potvrzení moderátorem.
4. Viditelnost na AR nezaměňovat s `public|unlisted|private` na YouTube. YouTube neumí „pouze moji přátelé v AR“.
5. Autorské/licenční prohlášení a souhlas s použitím videa pro AR TV; zásady obsahu a GDPR uchování.

YouTube Studio nepodporuje volné veřejné nahrávání do cizího kanálu bez delegovaných oprávnění. Neposkytovat uživatelům hesla nebo OAuth tokeny kanálu. Současné tlačítko Studio přesměruje uživatele na účet, na kterém je přihlášen, nikoliv automaticky na AR kanál.

## Kontroly

- PHP syntax check všech PHP souborů: OK (PHP 8.4).
- JavaScript syntax check: OK.
- Lokální simulace skutečného Elementor-independent live.php v Chromium: 3 režimy composeru na mobilu, odstranění uloženého článku na desktopu, cena produktu, bez JS pageerrors: OK.
- REST API a Youtube účet v produkci nejsou v této fázi otestované. Nasazovat nejprve na staging nebo se zálohou beta 9.1.
