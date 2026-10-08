<?php

namespace Database\Seeders;

use App\Models\MarketEvent;
use Illuminate\Database\Seeder;

/**
 * The first markets, fairs and conventions of the Czech and Slovak Republics for /tools/vendors, from public
 * websites as remembered on 8 October 2026, every one with status `verify`: the admin checks the dates and the
 * stall fees at /admin/events before they count as verified. Run again safely: an event is matched by name and city.
 *
 *   php artisan db:seed --class=MarketEventsSeeder
 */
class MarketEventsSeeder extends Seeder
{
    /** city → [lat, lng] (town centres) */
    private const PLACES = [
        'Praha' => [50.0875, 14.4213], 'Brno' => [49.1951, 16.6068], 'Ostrava' => [49.8209, 18.2625], 'Plzeň' => [49.7475, 13.3776], 'Olomouc' => [49.5938, 17.2509],
        'Liberec' => [50.7671, 15.0562], 'Hradec Králové' => [50.2092, 15.8328], 'České Budějovice' => [48.9745, 14.4743], 'Pardubice' => [50.0343, 15.7812], 'Zlín' => [49.2265, 17.6665],
        'Jihlava' => [49.3961, 15.5912], 'Karlovy Vary' => [50.2306, 12.8712], 'Ústí nad Labem' => [50.6607, 14.0323], 'Kutná Hora' => [49.9484, 15.2682], 'Český Krumlov' => [48.8107, 14.3150],
        'Tábor' => [49.4144, 14.6578], 'Kroměříž' => [49.2979, 17.3931], 'Telč' => [49.1843, 15.4527], 'Litomyšl' => [49.8720, 16.3105], 'Opava' => [49.9387, 17.9026],
        'Bratislava' => [48.1486, 17.1077], 'Košice' => [48.7164, 21.2611], 'Žilina' => [49.2231, 18.7394], 'Banská Bystrica' => [48.7363, 19.1462], 'Trnava' => [48.3774, 17.5883],
        'Nitra' => [48.3069, 18.0921], 'Prešov' => [48.9986, 21.2394], 'Trenčín' => [48.8945, 18.0444],
    ];

    public function run(): void
    {
        foreach ($this->events() as $e) {
            [$lat, $lng] = self::PLACES[$e['city']];
            MarketEvent::updateOrCreate(['name' => $e['name'], 'city' => $e['city']], $e + ['lat' => $lat, 'lng' => $lng, 'status' => 'verify', 'country' => $e['country'] ?? 'CZ']);
        }
    }

    /** @return list<array<string, mixed>> */
    private function events(): array
    {
        $x = fn (string $name, string $type, string $city, string $from, string $to, ?string $url, ?string $fee, string $note, ?string $address = null, string $country = 'CZ') => compact('name', 'type', 'city', 'address', 'country', 'url', 'note') + ['starts_on' => $from, 'ends_on' => $to, 'stall_fee' => $fee, 'source' => $url];

        return [
            // design and craft markets
            $x('Dyzajn market zima', 'design', 'Praha', '2026-12-05', '2026-12-06', 'https://www.dyzajnmarket.com/', 'od 2 500 Kč / víkend', 'Designový trh na Vltavské (Praha 7), čtyřikrát do roka; výběr prodejců přihláškou předem.', 'Bubenské nábřeží, Praha 7'),
            $x('Dyzajn market jaro', 'design', 'Praha', '2027-04-24', '2027-04-25', 'https://www.dyzajnmarket.com/', 'od 2 500 Kč / víkend', 'Jarní vydání designového trhu; přihlášky několik měsíců předem.', 'Bubenské nábřeží, Praha 7'),
            $x('MINT market Praha', 'design', 'Praha', '2026-12-12', '2026-12-13', 'https://www.mintmarket.cz/', 'od 2 000 Kč / den', 'Trh místní tvorby a designu; prodejci přihláškou s výběrem.', 'Praha'),
            $x('MINT market Brno', 'design', 'Brno', '2026-11-28', '2026-11-29', 'https://www.mintmarket.cz/', 'od 1 800 Kč / den', 'Brněnské vydání MINT marketu před Vánocemi.', 'Brno'),
            $x('Designblok', 'design', 'Praha', '2027-10-06', '2027-10-10', 'https://www.designblok.cz/', 'podle sekce', 'Festival designu a módy; pro drobné prodejce sekce Diploma Selection a market.', 'Praha'),
            $x('Prague Design Week', 'design', 'Praha', '2027-05-18', '2027-05-23', 'https://www.praguedesignweek.cz/', 'podle balíčku', 'Přehlídka designu s prodejní částí; přihlášky vystavovatelů.', 'Praha'),
            $x('Kreativní trh Brno', 'craft', 'Brno', '2027-03-20', '2027-03-20', null, 'neuvedeno', 'Řemeslný trh ručně dělaných věcí; termíny po sezónách.', 'Brno'),
            $x('Hradecký řemeslný jarmark', 'craft', 'Hradec Králové', '2027-06-12', '2027-06-13', null, 'neuvedeno', 'Jarmark řemesel na Velkém náměstí.', 'Velké náměstí, Hradec Králové'),
            $x('Jarmark v Kutné Hoře', 'craft', 'Kutná Hora', '2027-05-29', '2027-05-29', null, 'neuvedeno', 'Řemeslný jarmark v historickém centru.', 'Palackého náměstí, Kutná Hora'),
            $x('Telčské řemeslné trhy', 'craft', 'Telč', '2027-07-31', '2027-08-01', null, 'neuvedeno', 'Prázdninové řemeslné trhy na náměstí Zachariáše z Hradce.', 'nám. Zachariáše z Hradce, Telč'),
            $x('Řemeslný trh Litomyšl', 'craft', 'Litomyšl', '2027-06-26', '2027-06-26', null, 'neuvedeno', 'Trh řemesel na Smetanově náměstí.', 'Smetanovo náměstí, Litomyšl'),
            // Christmas markets of the cities
            $x('Vánoční trhy Staroměstské náměstí', 'christmas', 'Praha', '2026-11-28', '2027-01-06', 'https://www.trhypraha.cz/', 'podle stánku a délky', 'Největší vánoční trhy v Česku; stánky se přidělují pořadatelem, uzávěrka na jaře.', 'Staroměstské náměstí, Praha 1'),
            $x('Vánoční trhy náměstí Míru', 'christmas', 'Praha', '2026-11-25', '2026-12-24', 'https://www.trhypraha.cz/', 'podle stánku', 'Vánoční trhy před kostelem sv. Ludmily, Praha 2.', 'náměstí Míru, Praha 2'),
            $x('Vánoční trhy Brno', 'christmas', 'Brno', '2026-11-27', '2026-12-23', 'https://www.vanocebrno.cz/', 'podle stánku', 'Trhy na náměstí Svobody, Zelném trhu a Moravském náměstí; prodejci se hlásí přes TIC Brno.', 'náměstí Svobody, Brno'),
            $x('Vánoční trhy Olomouc', 'christmas', 'Olomouc', '2026-11-20', '2026-12-23', 'https://www.vanocnitrhy.eu/', 'podle stánku', 'Trhy na Horním a Dolním náměstí.', 'Horní náměstí, Olomouc'),
            $x('Vánoční trhy Plzeň', 'christmas', 'Plzeň', '2026-11-27', '2026-12-23', null, 'podle stánku', 'Trhy na náměstí Republiky.', 'náměstí Republiky, Plzeň'),
            $x('Vánoční trhy Ostrava', 'christmas', 'Ostrava', '2026-11-27', '2026-12-23', null, 'podle stánku', 'Trhy na Masarykově náměstí.', 'Masarykovo náměstí, Ostrava'),
            $x('Vánoční trhy Liberec', 'christmas', 'Liberec', '2026-11-27', '2026-12-22', null, 'podle stánku', 'Trhy na náměstí Dr. E. Beneše.', 'náměstí Dr. E. Beneše, Liberec'),
            $x('Vánoční trhy České Budějovice', 'christmas', 'České Budějovice', '2026-11-27', '2026-12-23', null, 'podle stánku', 'Trhy na náměstí Přemysla Otakara II.', 'náměstí Přemysla Otakara II., České Budějovice'),
            $x('Vánoční trhy Pardubice', 'christmas', 'Pardubice', '2026-11-27', '2026-12-23', null, 'podle stánku', 'Trhy na Pernštýnském náměstí.', 'Pernštýnské náměstí, Pardubice'),
            $x('Vánoční trhy Zlín', 'christmas', 'Zlín', '2026-11-27', '2026-12-23', null, 'podle stánku', 'Trhy na náměstí Míru.', 'náměstí Míru, Zlín'),
            $x('Vánoční trhy Karlovy Vary', 'christmas', 'Karlovy Vary', '2026-11-27', '2026-12-23', null, 'podle stánku', 'Trhy před Hlavní poštou.', 'T. G. Masaryka, Karlovy Vary'),
            $x('Vánoční trhy Český Krumlov', 'christmas', 'Český Krumlov', '2026-11-27', '2026-12-23', null, 'podle stánku', 'Trhy na náměstí Svornosti.', 'náměstí Svornosti, Český Krumlov'),
            $x('Vianočné trhy Bratislava', 'christmas', 'Bratislava', '2026-11-20', '2026-12-22', 'https://www.bratislava.sk/', 'podľa stánku', 'Vianočné trhy na Hlavnom a Františkánskom námestí; predajcovia cez mesto.', 'Hlavné námestie, Bratislava', 'SK'),
            $x('Vianočné trhy Košice', 'christmas', 'Košice', '2026-11-27', '2026-12-23', null, 'podľa stánku', 'Trhy na Hlavnej ulici.', 'Hlavná, Košice', 'SK'),
            $x('Vianočné trhy Žilina', 'christmas', 'Žilina', '2026-11-27', '2026-12-23', null, 'podľa stánku', 'Trhy na Mariánskom námestí.', 'Mariánske námestie, Žilina', 'SK'),
            $x('Vianočné trhy Banská Bystrica', 'christmas', 'Banská Bystrica', '2026-11-27', '2026-12-23', null, 'podľa stánku', 'Trhy na Námestí SNP.', 'Námestie SNP, Banská Bystrica', 'SK'),
            $x('Vianočné trhy Trnava', 'christmas', 'Trnava', '2026-11-27', '2026-12-23', null, 'podľa stánku', 'Trhy na Trojičnom námestí.', 'Trojičné námestie, Trnava', 'SK'),
            $x('Vianočné trhy Nitra', 'christmas', 'Nitra', '2026-11-27', '2026-12-23', null, 'podľa stánku', 'Trhy na Svätoplukovom námestí.', 'Svätoplukovo námestie, Nitra', 'SK'),
            // makers, comics, fairs
            $x('Maker Faire Prague', 'maker', 'Praha', '2027-06-12', '2027-06-13', 'https://makerfaire.cz/', 'makeři zdarma, komerční podle ceníku', 'Festival kutilů, tiskaři a elektronika; přihláška projektu.', 'Praha'),
            $x('Maker Faire Brno', 'maker', 'Brno', '2026-10-17', '2026-10-18', 'https://makerfaire.cz/', 'makeři zdarma, komerční podle ceníku', 'Podzimní Maker Faire v Brně.', 'Brno'),
            $x('Maker Faire Plzeň', 'maker', 'Plzeň', '2027-05-15', '2027-05-16', 'https://makerfaire.cz/', 'makeři zdarma, komerční podle ceníku', 'Maker Faire v DEPO2015.', 'DEPO2015, Plzeň'),
            $x('Maker Faire Ostrava', 'maker', 'Ostrava', '2027-04-17', '2027-04-18', 'https://makerfaire.cz/', 'makeři zdarma, komerční podle ceníku', 'Maker Faire v Dolní oblasti Vítkovice.', 'Dolní Vítkovice, Ostrava'),
            $x('Maker Faire Liberec', 'maker', 'Liberec', '2027-09-25', '2027-09-26', 'https://makerfaire.cz/', 'makeři zdarma, komerční podle ceníku', 'Maker Faire v iQLANDII.', 'Liberec'),
            $x('Comic-Con Prague', 'comic', 'Praha', '2027-04-09', '2027-04-11', 'https://www.comiccon.cz/', 'podle velikosti stánku', 'Největší popkulturní festival v Česku; artist alley a prodejní zóna.', 'O2 universum, Praha'),
            $x('Comic-Con Junior', 'comic', 'Brno', '2026-11-14', '2026-11-15', 'https://www.comiccon.cz/', 'podle velikosti stánku', 'Comic-Con na brněnském výstavišti.', 'Výstaviště Brno'),
            $x('Comic Salon', 'comic', 'Bratislava', '2027-03-26', '2027-03-28', 'https://www.comicsalon.sk/', 'podľa stánku', 'Popkultúrny festival v Bratislave; artist alley.', 'Bratislava', 'SK'),
            $x('3D tisk a výrobní technologie – Model Hobby', 'fair', 'Praha', '2026-10-22', '2026-10-25', 'https://www.model-hobby.cz/', 'podle m²', 'Veletrh modelářství a hobby na výstavišti v Letňanech; vhodné pro figurky a doplňky.', 'PVA EXPO Praha, Letňany'),
            $x('Hobby Brno', 'fair', 'Brno', '2027-02-26', '2027-02-28', 'https://www.bvv.cz/', 'podle m²', 'Veletrh hobby a volného času na brněnském výstavišti.', 'Výstaviště Brno'),
            $x('Bleší trh Tylovo náměstí', 'swap', 'Praha', '2026-10-18', '2026-10-18', null, 'od 400 Kč / místo', 'Bleší trh každou neděli; místo bez stánku, vlastní stůl.', 'Tylovo náměstí, Praha 2'),
            $x('Farmářské trhy Náplavka', 'farmers', 'Praha', '2026-10-10', '2026-12-19', 'https://www.farmarsketrziste.cz/', 'podle druhu zboží', 'Sobotní farmářské trhy na Rašínově nábřeží; řemeslné zboží jen vybrané.', 'Rašínovo nábřeží, Praha 2'),
            $x('Farmářské trhy Zelný trh', 'farmers', 'Brno', '2026-10-09', '2026-12-23', null, 'podle druhu zboží', 'Trhy na Zelném trhu po celý rok.', 'Zelný trh, Brno'),
            $x('Trh na Jiřáku', 'farmers', 'Praha', '2026-10-10', '2026-12-19', 'https://www.farmarsketrziste.cz/', 'podle druhu zboží', 'Farmářské trhy na náměstí Jiřího z Poděbrad, středa až sobota.', 'náměstí Jiřího z Poděbrad, Praha 3'),
        ];
    }
}
