# Opravy pěti nástrojů a sloučení s farmou

Větev `feature/h-tool-fixes` (z `feature/g-no-pickup-logo`). Všech pět drobností sepsaných v `docs/E.md`.
Větev také slučuje `feature/farm` (QR kód ve dvou barvách filamentu, commity `7194340` a `311d745`).

| nástroj | co bylo špatně | oprava |
|---|---|---|
| Cedulka | popisek „Druhý řádek (menší)“, ale oba řádky byly stejně vysoké | druhý řádek má 70 % výšky prvního (`SIGN_SECOND_LINE` v `creative_kinds.py`, `shape2d.text(..., scales=)`). Destička se dopočítá podle toho. Cedulka s jedním řádkem se nezměnila. |
| Světelná cedule | nápověda: otvor na kabel je v zadním krytu; ve skutečnosti je v těle | opravený text ve třech jazycích („tělo s otvorem na kabel a zadní kryt“) |
| Stojánek na telefon | formulář dovolí úhel 35 až 80°, tvar ho potichu upravil (deska a vlna od 55°, stolní od 45°, klín do 70°) | model se pořád vyrobí v úhlu, ve kterém tvar stojí pevně, ale stránka to řekne: „Tenhle tvar stojánku stojí pevně až od 55°. Model má proto úhel 55°, ne ten zadaný.“ Použitý úhel je i v údajích modelu (`notes.angle`). |
| QR kód | volba „otvor na zavěšení“ nic nedělala (podmínka v generátoru nešla splnit) | otvor je na proužku 9 mm nad kódem, takže volný okraj kódu zůstává prázdný; se stojánkem otvor není (cedulka stojí) |
| Krytka se závitem | ve slovníku byla hláška „závit umíme jen na kulatém víčku“ | hláška se nikdy nezobrazila a nebyla pravdivá (závit jde do kulatého, hranatého i šestihranného víčka). Je smazaná. |

## Rozhodnutí

1. **Cedulka: opravil jsem chování, ne popisek.** Jméno a menší řádek pod ním (číslo domu, datum) je to, co
   formulář slibuje a co lidé u cedulky čekají. Ukázka „Novákovi / 12“ na stránce nástroje je překreslená
   (`public/img/tool-examples/sign-2.png`) a text stránky to zmiňuje.
2. **Stojánek: upozornění, ne jiné meze ve formuláři.** Meze podle tvaru by znamenaly měnit formulář a jeho skript,
   které má právě rozpracované druhá session. Upozornění jde stejnou cestou jako ostatní (`notes.warnings` →
   `param.warn.*`).
3. **Nové texty jsou v `lang/{cs,en,es}/param.php`**, ne ve sdílených `lang/*.json`, aby se nepotkaly s texty
   QR kódu, které druhá session přidává na konec slovníků. Ze slovníků jsem změnil jen dva řádky (nápověda
   světelné cedule, smazaná hláška krytky), daleko od jejích úprav.

## Sloučení s `feature/farm`

Session farmy mezitím dokončila dvoubarevný QR kód (destička a kód každý svou barvou, od nástroje až po objednávku)
a pushnula ho. Je sloučený do téhle větve (`17fb92a`); jediný konflikt byl seznam textů pro skript stránky
nástroje, ponechané jsou obě strany. Test QR kódu z farmy používal adresu `?lang=cs`, kterou web od kroku A0
přesměrovává; upravený na `/tools/qr` (`cdf0d47`).

## Testy

`tests/Feature/ToolFixesTest.php` (5 testů): druhý řádek cedulky má 70 % výšky; stojánek hlásí upravený úhel
u všech čtyř tvarů a mlčí, když úhel sedí; otvor na kabel je v těle světelné cedule a text to říká; závitové víčko
jde vyrobit kulaté, hranaté i šestihranné a hláška o „jen kulatém“ neexistuje; QR kód s otvorem je o 9 mm vyšší a otvor je skutečně vyříznutý, se stojánkem otvor není.

## Nasazení (Roman)

```
npm ci && npm run build
php artisan optimize
systemctl restart php8.2-fpm
```

Bez migrace a bez nových `.env` klíčů. `npm run build` je potřeba kvůli sloučené práci farmy (náhled ve dvou barvách).
