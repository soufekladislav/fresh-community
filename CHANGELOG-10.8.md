# Fresh Community 0.2.0-beta.10.8 – 31. 10. 2026

Aktualizace navazuje na **Fresh Community 0.2.0-beta.10.7** (`ar-community` adresář). Fresh Affiliate se neaktualizuje.

## Změny

1. **Moje komunita:** kompaktní představení Adrenaline Live („Nejsi na to sám“), přidaný průvodce pro první vstup, profily, přátelé, sdílení, PWA instalace a referenční program. Průvodce lze přeskočit / označit dokončený. Redakční text lze upravit ve **Fresh Community → Uvítání členů**. Přehled zůstává při dalších přihlášeních.
2. **Profilová fotografie:** robustnější klientské zmenšení velkých fotografií (i velký obraz v relativně malém souboru), ověřování trvalého přiřazení WordPress attachment ID a fallback na původní fotografii, když se nepodaří vygenerovat zmenšeninu. Základní upozornění na veřejnou adresu fotografie zůstává; vzhledem k WordPress uploads nelze garantovat utajení URL soukromého avatara.
3. **Sdílení:** na vlastní zeď/příteli jako dosud; navíc Facebook, LinkedIn, WhatsApp pro **veřejné** příspěvky a články. Soukromé příspěvky a příspěvky jen pro přátele nelze sdílet externě.
4. **Akce pod příspěvky:** kompaktní SVG ikony; počet lajků a komentářů v závorkách. Magazínové komentáře stále směrují pod redakční článek.
5. **Komunitní komentáře:** odpovědi ve třech úrovních (hlavní komentář + dvě úrovně odpovědí), palec/lajky ke komentářům a profilové miniatury. Nový sloupec `parent_id` v `arc_wall_comments`, nová tabulka `arc_wall_comment_likes`. Původní komentáře mají nadále `parent_id=0`.
6. **E-mailová oznámení:** u každého člena ve výchozím stavu zapnuta; přepínač ve **Moje komunita → Můj profil**. Nové in-app události jdou přes omezenou WP-Cron frontu. Historická oznámení se při aktualizaci nerozesílají. Obchodní sdělení navíc stále vyžadují marketingový souhlas.
7. **Zpětná vazba:** více osobní a vstřícné texty.
8. **Správcovská oznámení:** využívají stejnou e-mailovou preferenci a dávkovou frontu; zapnutí po aktualizaci nevytváří rozesílku ze starých notifikací.
9. **Hosté:** běžná veřejná zeď zobrazí magazín a výzvu k registraci, nikoli proud členských příspěvků. Jednotlivý výslovně veřejný příspěvek zůstává přístupný přes přímou URL pro sdílení.

## Důležité

- **Nemění se** WordPress účet, WooCommerce registrace, Google přihlášení, přátelství, referral tokeny, affiliate atribuce, lifetime provize, objednávky ani platební nastavení.
- Neprovádí se žádná destruktivní migrace existujících komentářů. WordPress `dbDelta()` rozšíří schéma při aktualizaci pluginu.
- Ověřeno staticky (PHP + JS syntaxe), nikoli v přihlášené relaci na produkčním webu. Zkontroluj výsledek po aktualizaci a před širším náborem.
- Doručení e-mailů vyžaduje funkční `wp_mail`/SMTP a pravidelné spouštění WP-Cron.
- Samotný upload veřejně přístupných souborů není nástroj pro soukromé fotografie. Soukromé přílohy na zdi stále nejsou povoleny.
