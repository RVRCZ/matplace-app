# Krok D: doprava a měna

Větev `feature/d-shipping` (z `feature/c-catalog`). Zákazník v Česku platí v korunách, kdokoliv jiný v eurech,
a hotový tisk dostane Packetou na výdejní místo nebo domů. Osobní odběr zůstává zdarma.

## Co vzniklo

**Peníze** (`App\Support\Money`, neměnný objekt částka + měna): `Money::czk(x)`, `->to('EUR')` kurzem
`farm.eur_rate` se zaokrouhlením nahoru na 0,10 €, `->format($locale)` („1 250 Kč“, „49,90 €“ česky a španělsky,
„1,250 Kč“, „€49.90“ anglicky), `plus`, `minus`, `covers`. Blade `@money($m)`: `Money` vypíše, jak je; holé
číslo bere jako cenu v korunách a ukáže ji v měně návštěvníka; `@money(12.5, 'EUR')` částku ve jmenované měně.
V šablonách ani ve skriptech už není „Kč“ natvrdo (skripty: `resources/js/site/money.ts`, stejné formáty).

**Měna návštěvníka** (`App\Support\Currency::current()`), v tomto pořadí:
1. měna účtu, jakmile se na něm pohnuly peníze (zamčená);
2. přepínač Kč / € v hlavičce vedle jazyka (cookie `currency`, `POST /currency`);
3. země doručení z profilu, pokud ji účet zná;
4. jazyk adresy: čeština = Kč, jinak €.

**Zamknutí měny účtu**: první řádek v knize kreditu (dobití, první odměna designéra) zapíše `users.currency`.
Řádek v jiné měně kniha odmítne (`CurrencyMismatch`, hlídá to `CreditTransaction`). Po zamknutí přepínač
zmizí. Změnit měnu umí jen admin v `/admin/farm/credit`, a jen účtu s nulovým kreditem bez běžících zakázek.
`Wallet::balance()` vrací `Money`.

**Dobití**: Stripe Checkout v měně účtu. Kč podle nastavení adminu (100 až 20 000), EUR podle `farm.topup_eur`
(10 / 20 / 50, nejméně 5, nejvýše 800). Webhook beze změny.

**Cena zakázky v měně zákazníka** (`OrderService::priceFor`): tisk se počítá v Kč (tam jsou sazby), co zákazník
platí (tisk, odměna autorovi, celkem) se převede a zaokrouhlí nahoru na 0,10 €; doprava se bere z tabulky v dané
měně. Řádky rozpisu (čas, materiál, poplatek, základ) jsou prostý převod a DPH je dopočet, takže rozpis vždy
sedí na cenu tisku. Generování nad denní limit (15 Kč) stojí účet v eurech 0,60 €.

**Dopravné** (`config/farm.php` → `shipping`, data, ne kód): zóna × typ × hmotnostní pásmo, v obou měnách.

| zóna | země | výdejní místo | domů |
|---|---|---|---|
| CZ | CZ | 99 Kč / 4,00 € | 149 Kč / 6,00 € |
| SK | SK | 149 Kč / 5,90 € | 175 Kč / 6,90 € |
| EU1 | PL, HU, RO, SI, HR, BG, GR, LT | 199 Kč / 7,90 € | 225 Kč / 8,90 € |
| EU2 | DE, AT, ES, PT, FR, IT, LV | 249 Kč / 9,90 € | 299 Kč / 11,90 € |
| EU3 | NL, BE, LU, IE, DK, SE, FI, EE, CY | 399 Kč / 15,90 € | 575 Kč / 22,90 € |

Do 2 kg základ, 2 až 5 kg + 50 Kč / 2 €, 5 až 15 kg + 100 Kč / 4 € jen po Česku. AT, LU, IE jen domů, CY jen
výdejní místo. Kus delší než 70 cm nebo se součtem stran nad 120 cm se neposílá (zbývá osobní odběr). Mimo EU
se neposílá. `farm.vat_rate` na zónu (nebo zemi) je připravené pro OSS, zatím všude null = česká DPH.

**Packeta** (`App\Engines\Shipping\{ShippingCarrier, PacketaClient, FakeCarrier, Parcel, Packet}`,
`ENGINE_SHIPPING=fake` v testech): `createPacket(order)`, `labelPdf(packetId)`, `trackingUrl(barcode)`,
`carriersForCountry(cc)`, navíc `carriers()` a `refusePoint()` (ověření vybraného místa). Přeneseno ze starého
`PacketaService` a doplněno o zahraničí: `addressId` je ID výdejního místa Packety, nebo ID partnerského
dopravce, u partnerského výdejního místa navíc `carrierPickupPoint`; hmotnost a měna se posílají vždy, dobírka
nikdy. Štítek partnerského dopravce přes `packetCourierNumberV2` + `packetCourierLabelPdf`, jinak štítek Packety.

**Dopravci** (`App\Domain\Farm\CarrierBook`): `php artisan matplace:packeta-carriers` stáhne feed do
`storage/app/packeta_carriers.json` (jen země, kam posíláme), scheduler každé pondělí 4:20. Dopravce pro zemi:
nejdřív `farm.packeta_home_carriers` / `farm.packeta_point_carriers`, jinak ze staženého seznamu podle
`farm.packeta_prefer` (ES: MRW výdejní místa, Correos domů; DE Hermes; FR Mondial Relay a Colis Privé;
IT Punto Poste a HR Parcel; PL DPD domů). `--suggest` vypíše mapy k vložení do configu.

**Doručení v objednávce** (před platbou, mění cenu): osobní odběr, výdejní místo (widget Packety v jazyce
stránky, jen místa zvolené země a dopravce, kterým posíláme; oblíbené místo z profilu je předvyplněné), domů
(adresa z profilu, telefon povinný). Cenu s dopravou vždy počítá server (`POST /farm/orders/{token}/quote`),
stránka nic nesčítá. Při platbě se kontroluje: způsob je zapnutý, země je v tabulce a někdo do ní vozí,
výdejní místo leží ve zvolené zemi a patří dopravci, který tam jezdí, a místo zná i Packeta (ověřovací
endpoint widgetu); adresa je úplná. `farm_orders`: `delivery` (pickup / packeta_point / packeta_home),
`shipping_address` (+ `pickup_point_id`, `pickup_point_name`, `carrier_id`), `shipping_price`.

**Zásilka v adminu**: u hotové zakázky se zásilkou tlačítko „Vytvořit zásilku“ → `createPacket`, uloží se
`packeta_packet_id`, `packeta_barcode`, `tracking_url`, `shipped_at`, stav → `handed_over`, zákazník dostane
e‑mail se sledovacím odkazem v jazyce svého účtu. Odkaz „štítek PDF“. Když Packeta odmítne, admin vidí její
odpověď a stav se nemění. Druhé kliknutí druhou zásilku nevytvoří. Osobní odběr má dál ruční „Vydáno“.

## Rozhodnutí nad rámec zadání

1. **Ruční přepínač má přednost před zemí i jazykem.** Kapitola 11 uvádí pořadí „země → jazyk → přepínač“;
   čtu to jako výchozí hodnoty, které výslovná volba návštěvníka přebije. Jinak by přepínač u účtu se zemí
   nic nedělal.
2. **Měna se zamyká připsáním peněz, ne otevřením Checkoutu.** Kdo platbu nedokončí, zůstane nezamčený.
   Vzácný případ dvou otevřených Checkoutů v různých měnách: druhá platba se nepřipíše, dostane stav „paid“
   a adminovi přijde e‑mail (řeší se vrácením ve Stripe nebo ruční úpravou).
3. **Nezaplacená zakázka sleduje měnu zákazníka, zaplacená si drží svou.** Zákazník může před platbou přepnout
   Kč / € nebo dobít v druhé měně; zakázka se přepočítá. Po platbě se už nemění.
4. **Odměna designéra zůstává v korunách** (`royalty_czk`, jak ji nastavil). Zákazník v eurech ji platí
   zaokrouhlenou nahoru na 0,10 €; designérovi v eurech se připisuje přesný převod na cent.
5. **Hmotnost zásilky = `est_grams` + 60 g**, ne `est_grams × copies`. V této aplikaci je `est_grams` hmotnost
   celé zakázky (všech kusů na všech podložkách), násobení by ji započítalo dvakrát. Je-li známá skutečná
   hmotnost (`actual_grams`), použije se ta.
6. **Tabulka dopravného má obě měny pro každou zónu.** Zadání dává Kč pro Česko a € pro ostatní; účet v korunách
   ale může poslat balík do Španělska a naopak. Dopočítané hodnoty: CZ 4,00 / 6,00 €; SK 149 / 175 Kč;
   EU1 199 / 225 Kč; EU2 249 / 299 Kč; EU3 399 / 575 Kč. Pásmo 5 až 15 kg po Česku (+ 100 Kč) je můj odhad.
   Roman zkontroluje.
7. **Země bez známého dopravce se nenabízí.** Jistá ID mám jen pro CZ 106, SK 131, DE 111, AT 80 (domů) a pro
   vlastní síť Packety (CZ, SK, HU, RO, PL výdejní místa). Ostatní země se otevřou, až na serveru proběhne
   `matplace:packeta-carriers`. Vymyšlené ID v configu by poslalo balík špatnou službou.
8. **Feed dopravců jsem nemohl ověřit naostro.** Lokálně nejsou `PACKETA_*` klíče. Adresa
   `https://pickup-point.api.packeta.com/v5/{key}/carrier/json?lang=en` odpovídá dokumentaci (docs.packeta.com,
   přečteno v prohlížeči 1. 10. 2026) a jde změnit přes `PACKETA_CARRIERS_URL`. Když feed neodpoví, příkaz
   skončí chybou a platí seznam z minula.
9. **Vybrané výdejní místo se ověřuje i na serveru** u Packety (`…/widget/v1/validate`). Když ověření
   neodpovídá, zakázka se nezdrží; zemi a dopravce kontrolujeme sami vždy.
10. **Štítek je A6.** Starý web tiskl „A6 on A4“; pro jednu zásilku na termotiskárně je A6 praktičtější. Jde
    změnit v `PacketaClient::labelPdf`.
11. **Hodnota zásilky** (pojištění) = cena tisku bez dopravy, v měně zakázky.
12. **DPH podle země doručení** je zapojená do výpočtu ceny, ale vypnutá (`farm.vat_rate` = null). Po překročení
    10 000 € stačí doplnit sazby; cena se pak změní při volbě země, proto ji stránka bere ze serveru.
13. **Tržiště tiskařů** (vypnuté) zůstává v korunách; jeho šablony teď jen tisknou částky přes `@money`.

## Co zbývá

- Vratky a reklamace zásilek, sledování stavu zásilky u nás (dnes jen odkaz na Packetu).
- Faktury v eurech a režim OSS (účetnictví; kód má jen `farm.vat_rate`).
- Sitemap, strukturovaná data a měření (krok E), admin přehledy (krok F).

## Nové `.env` klíče

| klíč | výchozí | k čemu |
|---|---|---|
| `PACKETA_API_KEY` | – | veřejný klíč: widget výdejních míst, feed dopravců, ověření místa |
| `PACKETA_API_PASS` | – | heslo API: vytvoření zásilky, štítek (jen server) |
| `PACKETA_ESHOP` | – | označení odesílatele v Packetě, musí sedět s klientskou sekcí |
| `ENGINE_SHIPPING` | `packeta` | `fake` = bez volání Packety (testy, lokálně; v produkci se ignoruje) |
| `PACKETA_CARRIERS_URL` | viz `config/services.php` | adresa feedu dopravců, `{key}` = API klíč |
| `PACKETA_CARRIERS_FILE` | `storage/app/packeta_carriers.json` | kam se seznam ukládá |
| `FARM_EUR_RATE` | `25` | kurz Kč za 1 € (už z kroku C) |

## Testy

`tests/Feature/ShippingCurrencyTest.php` (10 testů): převody a zaokrouhlení `Money`; formáty cs / en / es
a direktiva `@money`; měna podle účtu, přepínače, země a jazyka; zamknutí měny první platbou, nejmenší dobití
5 €, odmítnutí řádku v jiné měně, změna měny adminem jen při nule; cena dopravy podle země, typu a hmotnosti,
výběr dopravce, DPH podle zóny; adresa zásilky z objednávky; volba doručení před platbou a všechny důvody
odmítnutí (místo v jiné zemi, cizí dopravce, neznámé místo, neúplná adresa, země mimo EU, změněná cena);
španělský účet od objednávky v eurech po e‑mail se sledováním, chyba Packety nemění stav; doručení domů po
Česku a osobní odběr bez zásilky; nezaplacená zakázka sleduje přepnutou měnu.

Upravené starší testy: zůstatek je `Money`, částky v textech mají měnu.

## Nasazení (Roman)

```
php artisan migrate --force
npm ci && npm run build
php artisan optimize
systemctl restart php8.2-fpm matplace-worker

php artisan matplace:packeta-carriers --suggest   # stáhne dopravce a vypíše mapy země → dopravce
```

- `.env`: `PACKETA_API_KEY`, `PACKETA_API_PASS`, `PACKETA_ESHOP` (hodnoty z produkčního `.env` starého webu).
- Výpis `--suggest` zkontrolovat proti ceníku a jistá ID opsat do `config/farm.php`
  (`packeta_home_carriers`, `packeta_point_carriers`). U zemí s více dopravci bez preference příkaz napíše,
  že ID chybí; taková země se domů nenabízí, dokud ho nedoplníš.
- V klientské sekci Packety musí být povolené země a dopravci, kterými posíláme, jinak je widget neukáže.
- Migrace převede staré `delivery = shipping` na `packeta_home`, uložené `delivery_modes` na nové názvy
  a účtům s pohybem v knize zamkne měnu CZK.
- Ceník dopravného a pásma: `config/farm.php` → `shipping`. Po změně stačí `php artisan optimize`.
- První zásilku do zahraničí doporučuji vytvořit na zkoušku a zkontrolovat štítek: volání partnerských
  dopravců (`carrierPickupPoint`, štítek dopravce) je podle dokumentace, naostro nevyzkoušené.
