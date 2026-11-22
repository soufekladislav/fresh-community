# AR Community beta 9.4 – kompaktní ikony a menu Uložené

- Srdíčko u produktu a v kartách oblíbených je kompaktní 38px ikonové tlačítko; původní serverové ukládání, guest merge i balíčky zůstávají.
- `[ar_community_favorite_button]` pozná produkt z WooCommerce `$product`, `is_singular('product')` nebo běžného loopu. Na detailu produktu **ID nezadávat**.
- `[ar_community_favorite_button product_id="123"]` slouží pouze pro pevně určený produkt mimo kontext WooCommerce.
- `[ar_community_favorite_button label="1"]` umožní volitelné malé tlačítko s textem.
- Menu obsahuje ikonu Uložené (bookmarks), badge ukazuje počet oblíbených produktů; odkazy vedou na `/ulozene/`.
- Elementorem vytvořený HTML widget **neprovádí shortcody**. Aktualizovaný HTML obsah a importovatelná Elementor JSON šablona jsou oddělené soubory.
- Žádné úpravy databáze, Stripe, WooCommerce ani Elementor Hero.
