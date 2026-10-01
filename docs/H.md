# Opravy čtyř nástrojů

Větev `feature/h-tool-fixes` (z `feature/g-no-pickup-logo`). Čtyři z pěti drobností sepsaných v `docs/E.md`;
pátou (QR kód, otvor na zavěšení) opravuje jiná session ve `feature/farm`.

| nástroj | co bylo špatně | oprava |
|---|---|---|
| Cedulka | popisek „Druhý řádek (menší)“, ale oba řádky byly stejně vysoké | druhý řádek má 70 % výšky prvního (`SIGN_SECOND_LINE` v `creative_kinds.py`, `shape2d.text(..., scales=)`). Destička se dopočítá podle toho. Cedulka s jedním řádkem se nezměnila. |
| Světelná cedule | nápověda: otvor na kabel je v zadním krytu; ve skutečnosti je v těle | opravený text ve třech jazycích („tělo s otvorem na kabel a zadní kryt“) |
| Stojánek na telefon | formulář dovolí úhel 35 až 80°, tvar ho potichu upravil (deska a vlna od 55°, stolní od 45°, klín do 70°) | model se pořád vyrobí v úhlu, ve kterém tvar stojí pevně, ale stránka to řekne: „Tenhle tvar stojánku stojí pevně až od 55°. Model má proto úhel 55°, ne ten zadaný.“ Použitý úhel je i v údajích modelu (`notes.angle`). |
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

## Sloučení s opravou QR kódu

Druhá session má rozpracované `engines/python/creative_kinds.py`, `resources/views/tools/param.blade.php`,
`lang/*.json`, `ParametricGenerator.php`, `param.ts`, `farm.ts` a `OrderController.php`. Moje úpravy v prvních
třech jsou na jiných řádcích, takže se sloučí samy. **`farm.ts` a `OrderController.php` jsem ale hodně měnil
v krocích D a G (doručení, měna, bez osobního odběru)**; tam je při slučování větve farmy s touhle potřeba
počítat s ručním řešením konfliktů.

## Testy

`tests/Feature/ToolFixesTest.php` (4 testy): druhý řádek cedulky má 70 % výšky; stojánek hlásí upravený úhel
u všech čtyř tvarů a mlčí, když úhel sedí; otvor na kabel je v těle světelné cedule a text to říká; závitové víčko
jde vyrobit kulaté, hranaté i šestihranné a hláška o „jen kulatém“ neexistuje.

## Nasazení (Roman)

```
php artisan optimize
systemctl restart php8.2-fpm
```

Bez migrace, bez nových `.env` klíčů, bez nového buildu (JavaScript se neměnil).
