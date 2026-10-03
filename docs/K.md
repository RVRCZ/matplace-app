# Pronájem tiskárny: texty a licence

`main` (4. 10. 2026). Roman: produkt je pronájem tiskárny (soubor nahraje zákazník, tiskárna ho pro něj vytiskne,
kopii si tím pořizuje on), ale texty mluvily o „tisku na zakázku“ a tvrdily, že práva posuzujeme my. Tlačítko
„Pronajmout tiskárnu“ znělo jako smlouva s kaucí.

## Co se změnilo

- **Tlačítko**: „Tisknout na naší tiskárně“ (en *Print on our printer*, es *Imprimir en nuestra impresora*),
  nápověda pod ním říká, že si tiskárnu na dobu tisku pronajímáte. Stejně nadpis stránky objednávky, nápověda
  kalkulačky (`tools_seo/calc.php`), popisky v adminu farmy, README.
- **Souhlas v objednávce**: „tiskárnu si pronajímám, za model a právo ho vytisknout odpovídám já; model neobsahuje
  zbraně ani jejich části“ (dřív „nejde o cizí chráněné dílo“, což by u modelu z internetu odklikl každý nepravdivě).
- **Podmínky farmy** (body 1–2) a **obchodní podmínky § 3** („Pronájem tiskárny: co na ní tisknout nelze“): tiskne
  se váš soubor a kopii pořizujete vy; za právo tisknout odpovídáte vy, my nic neposuzujeme; tiskárnu nepronajmeme
  na zbraně, díly k obcházení zákona a na výtisky cizích modelů na prodej bez souhlasu autora; na oprávněnou výzvu
  nositele práv tisk zastavíme a model odstraníme (navazuje na § 8 nahlášení). § 5 „Co je zakázáno“: místo „nahrávat
  soubory porušující autorská práva“ je „tisknout cizí modely na prodej bez souhlasu autora nebo jinak porušovat
  cizí práva“. § 7 inspirační katalog: tisk pro vlastní potřebu je možný u všech modelů, na prodej jen kde to licence
  dovoluje. Verze podmínek `2026-10` (výchozí v `config/farm.php`; uloženou hodnotu v `/admin/farm/settings` je
  třeba přepsat ručně, souhlas se bere u každé zakázky znovu).
- **Inspirační katalog**: licence už nebrání tisku. Karta s nekomerční/placenou/neznámou licencí
  (`license_restricted`) ukáže „Vytiskněte si ho pro sebe“ s vysvětlením „jen pro vlastní potřebu, ne na prodej“
  a stejné tlačítko „Nahrát soubor a vytisknout“; komerční licence má text „dovoluje i komerční použití“ a u CC BY /
  CC BY-SA uvedení autora. `OrderController` přijme `source`/`catalog_model` u každé zobrazené karty, poznámka
  zakázky nese název, autora, adresu a licenci jako dřív. Admin katalogu: „jen pro sebe“ / „i na prodej“.
  `license_restricted` zůstává (řídí text a pořadí překladů), význam je teď „výtisky se nesmějí prodávat“.

Právní opora, kterou má Roman ukázat právníkovi: § 30 autorského zákona (rozmnoženina pro osobní potřebu) a to,
že u pronájmu je pořizovatelem kopie zákazník. Zbraně zůstávají vyloučené bez ohledu na licenci.

## Tlačítko a kredit (doplněno)

- Tlačítko v kalkulaci je krátké a výrazné: **„Vytisknout u nás“** (en *Print it with us*, es *Imprimirlo con
  nosotros*), oranžové jako hlavní akce, větší; text o pronájmu zůstává pod ním (`farm.cta_hint`). Nadpis stránky
  objednávky zůstal „Tisknout na naší tiskárně“.
- Stránka zakázky: když je cena vyšší než kredit, tlačítko „Zaplatit kreditem a spustit tisk“ se změní na
  **„Chybí 85 Kč kreditu – dobít kartou“** (`farm.order.pay_short`), je aktivní bez ohledu na barvu a souhlas
  a vede na `/account/credit?back=<token>&need=<chybí, celé>`; po dobití se vrátí na zakázku. Platba, která by
  stejně selhala (402), se neposílá; odpověď 402 se dál ošetřuje pro případ, že se kredit změní mezitím.

## Testy

`CatalogTest::test_a_model_page_calls_to_print_and_the_licence_only_changes_the_words`: komerční karta
(`data-use="commercial"`, uvedení autora), nekomerční karta (`data-use="personal"`, bez uvedení autora, tlačítko
zůstává), objednávka z obou s poznámkou včetně licence.

## Nasazení

Bez migrace a bez buildu: `php artisan optimize && systemctl restart php8.2-fpm`, pak v `/admin/farm/settings`
přepsat verzi podmínek na `2026-10`.
