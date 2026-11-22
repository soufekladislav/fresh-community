# AR Community beta.9.1 – komentáře, uložené, produkty a YouTube UX

## Změny
- Opravena chyba odeslání komunitního komentáře: v beta.9.0 chyběl `type="submit"` a klientský JavaScript kvůli tomu skončil ještě před REST voláním.
- Komentář se po zveřejnění zobrazí přímo pod příspěvkem, formulář zůstane otevřený. Případná chyba se vypíše přímo u něj.
- Administrátor (`manage_options`) může jednotlivé komentáře komunitních příspěvků upravovat i mazat. REST kontroluje oprávnění a dostupnost nadřazeného příspěvku. Magazínové články si zachovávají původní komentářový systém WordPressu.
- Menší, méně výrazné tlačítko Odeslat; mobilní komentářový řádek se již neskládá do velkého bloku pod sebe.
- V desktopovém pravém panelu přibyl blok uložených příspěvků (5 posledních, s možností filtrovat všechny). Stejný filtr je k dispozici i na mobilu. Výběr respektuje práva ke čtení obsahu.
- Produktový slider používá širší karty, větší fotografii, jednoznačný název a cenu; font Poppins; odkaz do e-shopu je `/obchod/`.
- Vložení YouTube odkazu a tlačítko „＋ Nahrát na YouTube“ (nyní otevírá oficiální YouTube Studio). Neprobíhá automatické nahrání do AR kanálu, dokud se nepřidá admin OAuth autorizace a bezpečný upload workflow.

## Video – další etapa
Přímý upload z AR na YouTube kanál potřebuje: OAuth 2.0 s `youtube.upload` udělený správcem AR kanálu, resumable upload, omezení velikosti a formátů, frontu a logování stavu, moderaci s explicitním souhlasem autora, správu autorských práv a dvě nezávislá pole viditelnosti (AR příspěvek / YouTube video). YouTube umí `public`, `unlisted`, `private`; omezení na „přátele v AR“ přímo YouTube neumí. Neveřejné (`unlisted`) video lze otevřít komukoli s URL. Náklady na přenos a kvóty musíme ověřit u projektu v Google Cloud. V AR komunitě se pak uloží pouze YouTube video ID a stav. Pro budoucí AR TV lze připravit schválená veřejná videa.

## Poznámka k nasazení
Před výměnou zálohuj nynější plugin. Aktualizace nemění WooCommerce, Elementor Hero, uživatelské účty, obrázky ani původní Stripe billing. Ověř na stagingu komentáře jako běžný uživatel a jejich správu jako admin. PHP+JS prošly statickou syntaktickou kontrolou, ale nebyly provedeny živé REST testy v tvé instalaci WP.
