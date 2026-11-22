# Fresh Community — instalace a aktualizační balíčky
**Vývojová verze:** 0.2.0-beta.10.18 (k revizi na stagingu).

## Zásady kompatibility
- Plugin se uživateli zobrazuje jako **Fresh Community**, ale technický adresář zůstává `ar-community/`.
- Hlavní soubor zůstává `ar-community/ar-community.php`, PHP prefixy `ARC_*`, `ar-community` shortcode a databázové klíče se **nepřejmenovávají**. Jen tak WordPress aktualizaci rozpozná jako nahrazení téhož pluginu.
- Header `Version` a konstanta `ARC_VERSION` musí být stejné; další balíčky mají mít vyšší verzi.
- `Update URI` odděluje plugin od náhodné shody na WordPress.org. **Neznamená automatický updater ani automatické stahování aktualizací.** Distribuce je zatím přes instalační ZIP.
- Aktualizace **neprovádí uninstall**, nemaže uživatele, příspěvky, pluginové tabulky, Woo objednávky ani Stripe předplatné.
- Staré shortcody a URL na Adrenaline Race zůstávají kompatibilní. Před změnou PWA adresy/scope musí být zvláštní test na instalovaných zařízeních.

## Vytvoření aktualizačního ZIP
Na počítači s `bash`, `zip`, `unzip`:
```bash
bash scripts/build-update.sh
```
Výstup: `dist/fresh-community-0.2.0-beta.10.17-update.zip`. Obsah ZIP začíná složkou `ar-community/`, nikoli samostatným souborem `ar-community.php` v kořeni.

## Ruční aktualizace ve WordPressu
1. Zálohujte databázi i existující složku pluginu. Ověřte aktuální stav aktivních pluginů a Stripe/Woo.
2. Nahrajte ZIP přes **Pluginy → Instalace pluginů → Nahrát plugin**.
3. Pokud WordPress nabídne přepsat stávající plugin, potvrďte **nahrazení aktuální verze**, nikoli smazání pluginu a novou instalaci.
4. V administraci otevřete **Fresh Community → Nastavení**. U stávajícího Adrenaline Live nechte zatím původní stránku `/app/`, volby WooCommerce, Affiliate a PWA bez změn.
5. Ověřte přihlášení/registraci, profily a oprávnění, feed, pozvánky, Woo objednávky, Fresh Affiliate, Stripe, e-maily, PWA a YouTube.
6. Teprve po testu na stagingu schvalte ostré nasazení. Při problému obnovte předchozí ZIP a zálohu databáze.

## Nastavení dalších mikrokomunit
1. Na čistém WordPress webu nahrajte ZIP a aktivujte plugin. Dále použijte **Fresh Community → První instalace** pro vytvoření chybějících stránek, viz [podrobný návod](PRVNI-INSTALACE.md).
2. Vytvořte stránku pro komunitu, např. `/komunita/`, s `[ar_live]`.
3. Ve **Fresh Community → Nastavení** vyberte tuto stránku a vyplňte **název, krátký název PWA a popis**.
4. Ponechte vypnuté nepotřebné moduly: produkty/obchod, Premium, Affiliate, YouTube, sportovní pole. Zvlášť rozhodněte, zda chcete spodní menu na celém webu či komunitní Woo dashboard.
5. Vytvořte/navázejte stránky profilu a přátel podle aktuálních shortcode možností. Pro běžnou registraci bez Woo musí WordPress umožňovat registrace, nebo musí existovat vlastní registrační řešení.
6. Před veřejným spuštěním otestujte dvěma různými účty práva a soukromý obsah. **Nasazení na čistém WP se v této revizi ještě integračně neověřilo.**

## Co není obsaženo
Tento balíček neřeší automatický update server, licencování, úplnou migraci Stripe, Web Push infrastrukturu ani ověření funkčnosti na libovolné šabloně. Kód je potřeba projít v CI a testovat na stagingu.

## CI a evidence
Workflow `fresh-community-update.yml` kontroluje syntaxi PHP/JS a vytvoří kontrolní ZIP. Žádný build automaticky nenasazuje na produkci.
