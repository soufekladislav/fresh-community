# AR Community 0.2.0-beta.7.1 – nouzová oprava vykreslování

- Obnoven způsob vykreslování horní členské lišty pomocí `wp_body_open`, použitý v beta.6.
- `[ar_live_topbar]` nyní nevrací HTML v šabloně Elementoru (kompatibilní prázdný shortcode).
- Zachována stránka oznámení, spodní menu, administrace členů a upozornění z beta.7.
- Tato verze nemění datové tabulky, objednávky, provize ani Stripe předplatné.
- **Odstraň widget `[ar_live_topbar]` z globálního Elementor headeru.**
- Jde o nouzový balíček vycházející ze statického auditu, nikoli ověřenou produkční integraci.
