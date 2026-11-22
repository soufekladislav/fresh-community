# Fresh Community 0.2.0-beta.10.16 – YouTube kanál

Základ: ZIP aktivní verze `0.2.0-beta.10.15` dodaný uživatelem 3. 11. 2026. Zachován adresář `ar-community/`, shortcody, WooCommerce účet, Google přihlášení, affiliate vazby, zeď, profil a Premium.

Nové:
- Fresh Community → YouTube: samostatný OAuth klient s `youtube.upload` a vlastním callbackem.
- Client ID a šifrované uložení Client Secret / OAuth tokenů (AES-256-GCM s klíčem odvozeným od WP salts).
- OAuth offline (`refresh_token`) a obnova access tokenu, možnost odpojit.
- Členský formulář v původním editoru videa na `/app/` a shortcode `[arc_youtube_submit]`.
- Explicitní souhlas s veřejným zveřejněním a potvrzení práv k videu.
- Před schválením soubory mimo veřejný webroot; administrační náhled chráněný oprávněním a nonce.
- Fronta čekajících videí, administrátorské schválení či odmítnutí, ruční pokračování.
- YouTube resumable upload po 2 MiB přes WP-Cron, bezpečné obnovení po přerušení, chybové stavy.
- Po potvrzeném uploadu uložení YouTube ID, smazání místní kopie a vytvoření příspěvku s odkazem na komunitní zeď, pokud to umožňuje viditelnost profilu člena.
- Při smazání člena čištěné nezveřejněné videonávrhy (veřejně publikovaná YouTube videa se automaticky nemažou).

Poznámky:
- Upload po schválení je **veřejný na YouTube**. Soukromý komunitní profil nedovoluje automatický příspěvek na komunitní zeď.
- Potřebné limity `upload_max_filesize` a `post_max_size`; soukromý adresář musí jít zapsat mimo webroot.
- WP-Cron musí být aktivní, jinak video může čekat na další dávku; administrátor může proces ručně obnovit.
- V režimu Testing může refresh token za 7 dní ztratit platnost.
- Neproběhlo reálné propojení s Google účtem uživatele ani upload na ostrý kanál.
