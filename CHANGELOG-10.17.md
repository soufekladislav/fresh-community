# Fresh Community 0.2.0-beta.10.17 — zobecnění a aktualizační ZIP
Datum: 22. 11. 2026. Stav: **vývojová větev / PR, ne produkčně schválená verze**.

## Změny
- Hlavička pluginu: obecný popis, synchronizované `Version` a `ARC_VERSION`, oddělené `Update URI` pro ruční distribuci mimo WordPress.org.
- Nový `ARC_Config`: identita komunity, popis, hlavní stránka, URL obchodu, volitelné moduly WooCommerce/Premium/Affiliate/YouTube, sportovní pole, globální navigace a úpravy Woo nástěnky.
- Frontend a e-mail: názvy, pozvánky, oznámení a většina hardcoded `/app/` odkazů se odvozují z nastavení.
- PWA: dynamický název, manifest, start URL, editovatelné ikony a barvy a scope podle vybrané komunitní stránky. **Po změně stránky PWA je nutný ruční test již nainstalovaných zařízení.**
- Nové mikrokomunity mohou provozovat základní zeď i bez WooCommerce; obchodní části jsou volitelné.
- Stávající Adrenaline Live používá výchozí nastavení odvozené od `/app/` s existujícím shortcodem, dokud ho správce nepřepíše.
- `scripts/build-update.sh` vytváří ZIP s přesným kořenem `ar-community/`, připravený pro nahrazení pluginu bez odinstalace.
- Workflow kontroluje PHP/JS syntaxi, shodu verze a strukturu ZIP.
- README aktualizováno na aktuální beta verzi.

## Neměnit
- Slug `ar-community`, WP user IDs, databázi, stávající shortcody, affiliate atribuce a Stripe webhooky.
- Žádný automatický update server, licencování, nové sociální funkce ani automatické nasazení.

## Neověřeno
- Komplexní test na AR produkci nebo stagingu, nezávislé instalace Fresh Develop/Fresh Crackers, PWA upgrade na všech zařízeních, skutečné YouTube uploady a billing kontinuita. Ověřit před merge a vydáním.
