# Fresh Community 0.2.0-beta.10.6

- Oprava přesměrování WooCommerce: pouze skutečná výchozí adresa účtu smí přesměrovat na komunitní dashboard. Endpoint Fresh Affiliate se nesmí zachytávat.
- Odkaz „Referenční program“ používá `wc_get_account_endpoint_url("fresh-affiliate")`, ne pevné `/muj-ucet/fresh-affiliate/`.
- Přidaná plovoucí pravá záložka Zpětná vazba pro přihlášené členy, formulář přes AJAX, ochrana nonce + limit odesílání.
- Záznamy `wp_arc_feedback` (skutečný WP prefix), snapshot e-mailu/jména a stav nové/vyřešené; admin Fresh Community → Zpětná vazba; volitelný admin e-mail.
- Zachované WooCommerce objednávky, affiliate vazby, komunitní shortcody, databázové tabulky a adresář `ar-community`.
- Tato verze je chirurgická oprava přesně z dodaného `0.2.0-beta.10.5-rc1`, nikoli rebase na dříve připravený `0.3.0-beta.1`.
