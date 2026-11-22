# 0.2.0-beta.7 – Navigace, oznámení, administrace

- Přidána viditelná položka AR Community → Nastavení (`admin.php?page=ar-community`).
- Nový shortcode `[ar_live_notifications]` pro `/oznameni/`, používá stávající bezpečné REST API a stejné WP tabulky.
- Plovoucí navigace: Zeď, Přátelé, Oznámení, Profil, Premium; v oznámeních indikátor nepřečtených zpráv.
- Ze zdi odstraněna horní interní navigace včetně obchodu (webové hlavní menu zůstává).
- Nový shortcode `[ar_live_topbar]` pro samostatný Elementor Shortcode widget v globální šabloně headeru. Odstraněn automatický výstup do `wp_body_open`, který působil nekontrolovatelné překryvy. Při odhlášení shortcode nevrací nic.
- Úpravy CSS odsazení fixní Elementor hlavičky `.ar-glass-header` pro přihlášené.
- Původní Adrenaline Live 0.9.1 **není součástí balíčku ani nebylo změněno**. Stripe/Fresh Affiliate/WooCommerce logika nedotčena.
- PWA notifikace na zařízení nadále nejsou zapnuté; jde o oznámení v rámci aplikace.
