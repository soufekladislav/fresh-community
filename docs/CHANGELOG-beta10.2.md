# 0.2.0-beta.10.2 – komunitní administrace, loader a mazání dat

- CSS komunitní zdi a profilu se načítá v `wp_enqueue_scripts` před vykreslením Elementoru. Loader je jen v obsahu feedu.
- Členové komunity: rozbalovací rychlá editace řádku, vyhledávání jména/e-mailu, správce může přidat a odebrat potvrzená přátelství.
- Mobil: mimo komunitní stránky plovoucí zelené „Do komunity“ (bez zdvojení spodního menu); ne v pokladně.
- Trvalé smazání WordPress uživatele: odstranění vlastních záznamů a komunitních příspěvků/balíčků, interakcí a sociálních vztahů. WooCommerce a Fresh Affiliate se nemažou.
- Po aktualizaci provést testy nejlépe na stagingu; běžné pluginové syntaktické testy nezaručují chování PHP/DB v produkci.
