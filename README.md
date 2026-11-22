# Fresh Community
**WordPress plugin pro vlastní mikrokomunitu** · vývojová verze `0.2.0-beta.10.17`.

Fresh Community přidává na stávající WordPress web komunitní zeď, členské profily, přátele, pozvánky, příspěvky, reakce, komentáře, oznámení, zpětnou vazbu a možnost PWA instalace. Referenční implementace se jmenuje **Adrenaline Live** na Adrenaline Race. Cílem je použitelný základ také pro jiné komunity, např. Fresh Develop nebo podnikatelskou komunitu Fresh Crackers.

**Stav:** beta. Jednotlivé funkce existují ve zdrojovém kódu, ale univerzální nasazení mimo Adrenaline Race ani produkční end-to-end test nejsou potvrzené. Neprezentujte plugin jako otestovaný komerční produkt.

## Co plugin dělá
- Sdílený účet `wp_users` (nezakládá vlastní uživatelskou databázi), volitelná integrace WooCommerce a přihlášení Google.
- Profily s viditelností `private`, `network` (schválení přátelé), `public`; sociální vztahy oddělené od affiliate provizí.
- Komunitní zeď, fotografie, komentáře, reakce, oznámení, pozvání přátel, zpětná vazba.
- PWA (manifest, instalace, aktualizace). Kompletní serverové Web Push doručování zatím není hotové.
- Volitelné obchodní části: produkty WooCommerce, Premium, Affiliate, YouTube kanál a sportovní profily.

## Co je nastavitelné
V **Fresh Community → Nastavení** lze upravit:
- název a krátký název komunity, popis a hlavní stránku s `[ar_live]`;
- URL obchodu, zobrazení produktů, Premium, Affiliate a YouTube;
- zobrazení sportovních údajů v profilu;
- komunitní úvodní nástěnku ve WooCommerce a globální spodní navigaci.

Stávající AR instalace se rozpoznává podle publikované stránky `/app/` se shortcode; původní nastavení zůstává výchozím stavem. Při vytváření jiné komunity zvolte vlastní stránku a název. Pro WordPress bez WooCommerce je třeba zvlášť zajistit povolenou registraci uživatelů a otestovat přihlašování.

## Instalace a aktualizace
Technický plugin adresář **zůstává `ar-community/`**, hlavní soubor `ar-community.php`; stejná ID zachovává aktualizační kompatibilitu s Adrenaline Live. Plugin se **neodinstalovává** mezi verzemi.

Aktualizační ZIP vytvoříte:
```bash
bash scripts/build-update.sh
```
Výstup do `dist/fresh-community-0.2.0-beta.10.17-update.zip`. Soubory můžete nahrát jako **náhradu stávajícího pluginu** přes administraci WordPressu. Automatický update server prozatím není součástí projektu.

Podrobný postup: [Aktualizace a nastavení](docs/AKTUALIZACE-A-NASTAVENI.md).

## Struktura
- `ar-community.php` — hlavička pluginu, bootstrap a verze
- `includes/class-arc-config.php` — nastavení jednotlivých instalací
- `includes/` — profily, zeď, API, pozvánky, oznámení, integrace, PWA
- `assets/` — JS a CSS
- `templates/` — zobrazení zdi
- `scripts/build-update.sh` — reprodukovatelný aktualizační ZIP
- `.github/workflows/fresh-community-update.yml` — kontrola PHP/JS a sestavení balíčku

## Kompatibilita a hranice
Technické prefixy `ARC_*`, shortcody `[ar_live]`, `[ar_community_account]` a databázové klíče se **nepřejmenovávají**. WooCommerce vlastní objednávky/platby, Fresh Affiliate vlastní provize a stávající Stripe billing starého Adrenaline Live zůstává oddělený. Nenasazujte přímo na produkci bez zálohy a staging testu.

Historické dokumenty `README-ADRENALINE-LIVE.md`, `docs/ARCHITEKTURA-0.2.md` a dřívější changelogy popisují tehdejší vydání, nikoli automaticky současný stav. Stav implementace a známé úkoly evidujeme v [GitHub Issues](https://github.com/soufekladislav/fresh-community/issues).

## Vývoj
PHP 8.0+, WordPress 6.3+. Změny vyvíjejte přes samostatnou větev a PR, po syntaktické kontrole proveďte testy na stagingu s dvěma uživateli (privátní obsah, přátelé, komentáře, registrace, e-mail, PWA; v případě AR také Woo, affiliate, Stripe a YouTube). Žádná změna v GitHubu se sama nenasazuje na produkční WordPress.
