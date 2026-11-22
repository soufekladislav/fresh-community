# AR Community 0.2.0-beta.5 — navigace, profil a oznámení

## Rozsah

- Oprava plovoucího menu: pevná vodorovná sazba, ikony SVG, font Poppins, izolace od Elementor/šablonových stylů. Centrální vložení na hlavní stránce Adrenaline Race a na stránkách komunitního profilu a Přátel. Nová `[ar_live]` může menu aktivovat i z Elementor shortcode widgetu. Starou jídelníčkovou aplikaci to nepřepisuje.
- Horní přihlášená lišta na desktopu a dodatečný offset Elementor sticky headeru. U atypických custom/absolute headerů může být nutná úprava v Elementor Theme Builderu.
- Veřejný profil: větší kruhová fotografie, zvýrazněné zájmy, společné a veřejné profily přátel, aktivní odkaz `/pratele/`, grafické kartičky původních příspěvků a článků.
- Vlastní účet: kopírování sdíleného URL, informační text u balíčků, potvrzení uložení vedle formulářů.
- In-app upozornění: nová DB tabulka `{prefix}arc_notifications`, seznam posledních 35 oznámení s rolováním, označit přečtené, nové žádosti o přátelství, potvrzení, nové viditelné příspěvky a komunitní články. Pouze přihlášení mohou číst svá upozornění. Zobrazení běžně otevřené stránky se obnoví jednou za 60 sekund; e-maily zatím ne.

## Ochrana soukromí a limity

- Nový příspěvek posílá upozornění pouze potvrzeným přátelům, a pokud je veřejný, také sledujícím. Soukromé příspěvky nemají distribuovaná upozornění.
- Články notifikujeme jen při **novém publikování** článků z AR Community nebo přiřazených ověřenému komunitnímu autorovi; nedochází ke starému hromadnému oznámení ~400 existujících článků.
- Nezveřejňujeme soukromé profily v seznamu veřejných přátel; „společní přátelé“ se zobrazí jen tam, kde má návštěvník právo profil vidět.
- Změny přátelství nemění žádná Fresh Affiliate přiřazení.
- Jednorázový limit rozesílání na 150 účtů na obsah. Pro větší komunitu přenést doručování do fronty WP-Cron/workerů.
- Do žádných současných Stripe webhooků, WooCommerce objednávek, Eda ani Fresh Event se nezasahovalo.

## Nasazení a testy

1. Zálohovat databázi a aktuální ZIP beta.4. Aktualizovat beta.5 přednostně na stagingu; zachovat existující adresář `ar-community`.
2. Otevřít `/muj-ucet/ar-community/`, potvrdit profil, fotografii, editaci a kopírování odkazu. Po uložení má být text **✓ Uloženo** vedle tlačítka.
3. Na `/pratele/` otestovat odeslání pozvánky z A na B (dvě rozdílné WP identity). B obdrží upozornění a může žádost potvrdit. A uvidí oznámení o potvrzení.
4. Jako A publikovat veřejný a síťový příspěvek; kontrolovat oznámení u přátel, případně sledujících. Soukromý příspěvek nesmí způsobit nové oznámení ostatním.
5. Publikovat **nový** komunitní článek; přítel obdrží upozornění s odkazem na konkrétní článek. Staré články nesmějí začít generovat zpětná upozornění.
6. Otestovat sdílenou stránku `[ar_community_profile]` s `?ar_member=ID` v anonymním okně, při přátelství a bez něj; ověřit kruhový avatar, veřejné přátele, společné přátele a dělení příspěvky/články.
7. Otestovat plovoucí menu na 360px mobilu a desktopu a nezasahování do staré app `/app/`. U nové komunitní stránky `[ar_live]` bude menu automatické.
8. Otevřít stránku s Elementor sticky headerem jako přihlášený, zkontrolovat, že se horní lišta s menu nepřekrývá. Pokud se používá atypický absolutní header, upravit jeho `top`/sticky offset v konkrétní šabloně.
9. Otestovat standardní WooCommerce přihlášení/registraci přes Google, faktury a trvalé Fresh Affiliate přiřazení — žádná regresní změna nesmí nastat.

## Systémová poznámka

PHP a JS syntaxe byly kontrolovány staticky. Funkční integrační test skutečného WordPressu/WooCommerce, databáze a Elementor layoutu nebyl v tomto sestavení proveden.
