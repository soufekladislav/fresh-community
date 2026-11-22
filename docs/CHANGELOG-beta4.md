# AR Community 0.2.0-beta.4 – profil a navigace

- Našeptávač pro jména/přezdívky s fotkami (public + potvrzení přátelé s režimem network). Skryté účty nejsou dohledatelné. Pozvání na přesný e-mail zůstává anonymní.
- Jednoduchý editor **O mně**: tučné písmo, odkazy HTTPS a odstavce; HTML whitelist na serveru.
- Grafický sdílený profil `[ar_community_profile]` s parametrem `?ar_member=WP_ID`; fotografie, bio, dlaždice veřejných příspěvků, ověřené či vlastní články a veřejné balíčky.
- Stránka `/pratele/` používá shortcode `[ar_community_friends]` (bez druhé identity).
- Horní úzká lišta přihlášeného uživatele jen na desktopu; spodní plovoucí menu `[ar_live_navigation]` a automaticky u nové aplikace `[ar_live]`, nikoliv starého jídelníčku.
- Veřejné profilové fotografie v médiích WordPressu mají veřejnou URL i u skrytých profilů; do budoucna chráněná media složka.
- Nesahá na obchodní WooCommerce data, Fresh Affiliate, Eda, Premium a Eventy.

Staging beta, nutné provést end-to-end testy v reálném WP.

**Konfigurace:** Na stránku `/pratele/` vlož `[ar_community_friends]`. Pro sdílený profil nastav v **AR Community → Lidé a profily** stránku s `[ar_community_people]` nebo `[ar_community_profile]`; odkaz bude `?ar_member=WP_ID`. Na stejnou Elementor šablonu můžeš vložit widget sdílení aktuální URL.
