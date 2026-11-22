# AR Community 0.2.0-beta.10.3 – instalační tlačítko a kontrola aktualizací

## Co tato verze MĚNÍ
- Přidává `[ar_instalovat_aplikaci]` (alias `[ar_install_app]`) s tlačítkem **Instalovat aplikaci**, design AR a průvodcem pro nepodporované prohlížeče.
- Pokud prohlížeč podporuje `beforeinstallprompt`, po kliknutí nabídne instalaci. iOS/Safari a nekompatibilní prohlížeče dostanou postup přidání na plochu.
- Na stránkách s shortcodem připojí existující manifest Adrenaline Live; nemění manifest ID, `start_url` ani root/scope service workeru.
- Každé spuštění /app/ po prvním vykreslení zkontroluje aktualizaci *existujícího* service workeru. Když se jeho ovládání změní, nabídne **Aktualizovat / Později**; nikdy neobnoví rozpracovaný formulář bez uživatelského kliknutí.
- Přidává `ARC_BUILD` do JS odpovědi existujícího service workeru, aby změna verze pluginu byla rozpoznatelná i v PWA.

## Co verze NEMĚNÍ
- Profily, přátele, pozvánky, příspěvky, datové tabulky, obchod, affiliate, WooCommerce a login. Opravuje jediný výraz pro odkaz v postranním přehledu oznámení.
- Při aktualizaci z beta.10.2 neprovádí nové `dbDelta` schéma, pouze nastaví verzi stejného schématu.
- Nevytváří nový service worker, nemění jeho scope ani URL.
- Nezapíná systémové push notifikace. AR Community dosud nemá serverovou Web Push infrastrukturu pro registraci a VAPID odeslání; tato práce musí navázat na revizi původního pluginu `adrenaline-live`.
- Nepřepisuje Elementor, stávající loaders ani PWA cache strategii.

## Shortcode (Elementor – Shortcode widget)
`[ar_instalovat_aplikaci]`

Volitelný text:
`[ar_instalovat_aplikaci text="Instalovat Adrenaline Live" popis="Sociální síť sportovců vždy po ruce"]`

## Bezpečný postup
1. Zazálohovat aktuální `ar-community` a databázi; vyzkoušet na stagingu.
2. Na stagingu nahrát ZIP jako aktualizaci existujícího `ar-community` (není to druhá paralelní instance).
3. Prověřit /app/ pro přihlášeného i anonymního návštěvníka, přátele, oznámení, uložené, affiliate a checkout.
4. Přidat shortcode na libovolnou Elementor stránku a ověřit instalaci na Android/Chrome, iOS/Safari a desktop/Edge či Chrome.
5. Stávající instalované PWA neztrácejí účet ani data. Při spuštění obnoví nejnovější HTML/JS/CSS; změna service workeru se ověří přes `registration.update()`.
6. Volitelně nasadit produkčně. Při problému obnovit zálohu původního ZIPu bez mazání DB.

## Technické omezení
PWA nelze z webového kódu bez souhlasu uživatele automaticky nainstalovat a na iOS není dostupný `beforeinstallprompt`. Změny JS/CSS z WordPressu se běžně stahují při běžné navigaci; service worker musí být zvlášť aktualizován. Celé PWA se ze serveru nestahuje jako nativní binární instalační balíček.
