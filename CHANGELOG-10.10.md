# Fresh Community 0.2.0-beta.10.10

- Po registraci přes starou adresu `/muj-ucet/` směřuje přihlášeného člena na existující stránku `/app/muj-ucet/`, pokud je publikovaná. Hostitelské WooCommerce endpointy ani reset hesla nepřesměrovává.
- WooCommerce registrace používá cílovou stránku komunity i při registraci na jiné než hlavní Woo Account stránce. Checkout je vyňat.
- Pro WooCommerce účty s generovaným heslem se zobrazí zpráva o e-mailu a nastavení hesla.
- Průvodce získal přepínatelné kroky 1–4, ukazatel postupu a uložení posledního otevřeného kroku do uživatelských metadat.
- Spodní menu se zobrazuje každému přihlášenému členu na celém frontendu, včetně produktů a magazínu.
- Bez změn v Fresh Affiliate, registraci referenčních tokenů, výpočtu provizí a databázových schématech.
