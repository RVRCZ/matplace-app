# AI – Wi‑Fi QR bez technického zápisu (audit bod 9)

Session 2, větev `feature/qr-wifi` (z `main` 10245aa), 17. 10. 2026. Zadání: `docs/prompts/audit-qr.md`,
podklad `docs/AUDIT.md` bod 9. Nemergováno, nenasazeno.

## 1. Co se změnilo

Nástroj `/tools/qr` měl jedno pole „Odkaz nebo text“ a pro Wi‑Fi radil ruční zápis `WIFI:T:WPA;S:…;P:…;;`.
Teď má nahoře v kroku zadání přepínač **Druh obsahu: Web / Wi‑Fi / Text** a každý druh svá pole:

| druh | pole | co se stane |
|---|---|---|
| Web | Adresa webu | chybí‑li schéma, doplní se `https://`; pod polem věta „Kód otevře https://…“; neplatná adresa = chyba u pole |
| Wi‑Fi | Název sítě (SSID), Heslo sítě (skryté, tlačítko Ukázat/Skrýt), Zabezpečení WPA/WPA2/WPA3 (výchozí) · WEP · Bez hesla, Skrytá síť | server složí řetězec pro telefony |
| Text | Text | libovolný text; limit délky podle velikosti kódu |

Přepnutí druhu mění jen obsah kódu: rozměry, destička, stojánek, otvor, barvy i popisek zůstávají (test to drží).
Kontrola čitelnosti (hustota modulů `qr_too_dense`, zpětné čtení matice `qr_unreadable`) a vysvětlení dvoubarevného
tisku jsou beze změny.

## 2. Řetězec, který kód nese (`ParametricGenerator::qrPayload`)

Skládá ho server v `forTool()` → `payload`, engine (`creative_kinds.py qr`) ho jen vykreslí (`p["payload"]`,
pro staré návrhy `p["url"]`). Prohlížeč nic neskládá.

- **Web** (`webAddress`): `trim`, bez mezer; bez schématu → `https://` před; jen `http`/`https`; host musí být
  tečkované jméno, IP, nebo `localhost`; host s diakritikou → punycode (`idn_to_ascii`, bez intl = chyba).
  `mailto:`, holé slovo, „a b.cz“ → `qr_bad_url` u pole `url`.
- **Wi‑Fi** (`wifiString`): `WIFI:T:<WPA|WEP|nopass>;S:<ssid>;P:<heslo>;H:true;;` podle zvyklostí ZXing:
  - escapují se `\` `;` `,` `:` `"` zpětným lomítkem (`addcslashes`);
  - název nebo heslo složené jen z hex číslic (`ABCDEF12`, `deadbeef`) jde do uvozovek `"…"`, jinak si je čtečka vyloží
    jako hex (ZXing tak zapisuje i čte);
  - „Bez hesla“ → `T:nopass`, část `P:` se vynechá (heslo v poli se ignoruje); skrytá síť → `H:true;`;
  - WPA/WPA2/WPA3 je jeden typ `WPA` (telefony WPA3 z kódu nerozlišují); prázdný název → `qr_no_ssid` u `ssid`,
    WPA/WEP bez hesla → `qr_no_password` u `password`.
  Příklad z testu: síť `Kavárna "U;Dvou:Koček"`, heslo `a\b;c,d:e"f` →
  `WIFI:T:WPA;S:Kavárna \"U\;Dvou\:Koček\";P:a\\b\;c\,d\:e\"f;;`.
- **Text**: `trim`; prázdný → `qr_no_text` u `text`.
- **Limit délky** (`qrCapacity(size)`): moduly musí mít aspoň 0,9 mm (hranice tiskárny, stejná jako `qr_too_dense`),
  tedy `cells = floor(size / 0.9)`, verze `v = (cells − 25) / 4` (17 + 4v modulů + 2 × 4 tichá zóna), kapacita
  byte‑módu při úrovni M (ISO 18004, tab. 7), nejvýš 300 znaků polí. 30 mm → 26 B, 40 mm → 62 B, 70 mm → 300.
  Delší obsah → `qr_too_long` s číslem u pole druhu obsahu. Je to dolní odhad (segno pro čísla a velká písmena umí
  hustší mód), konečným soudcem zůstává kontrola hustoty v enginu.
- Chyby jsou `ValidationException` s klíčem `params.<pole>`; `param.ts` (`markNamed`) označí pole, které server
  jmenuje, červeným rámečkem a text ukáže v obvyklém místě; po úspěšném náhledu se značka sundá.

**Staré návrhy** (uložené před tímto dnem, `tool_params` bez `content`): `clean()` dosadí `content = web`, když
`url` je platná adresa, jinak `content = text` a `text = url` – ručně napsaný `WIFI:…` tak dál funguje beze změny
(test čte z vyrenderovaného modelu totéž, co bylo zadáno). Ukázka v `config/tools.php` (`seo.examples`) je
přepsaná na nová pole (`content: wifi, ssid: Kavarna, password: dobrakava`); obrázek ukázky se nemění (stejný kód).

## 3. Soukromí hesla

Ověřeno, co nástroj posílá do měření a logu:
- **Události** (`events`): generování nástroje žádnou událost nezakládá; `calculation` nese jen `tool: qr`,
  `download` jen druh souboru a nástroj (`Track::SHARED_META`: kind, tool, value…). Parametry nikam nejdou.
- **Log**: `ParametricGenerator::build` vrací chybu enginu jako 422 (`explain` → jen text klíče), nic se neloguje;
  `ValidationException` Laravel nehlásí. Test zapíná vlastní log do souboru a hledá v něm heslo – není tam.
- **Názvy souborů**: `qr-<šířka>x<výška>x<tloušťka>.stl` – jen rozměry (test).
- **Meta náhledu** (`X-Model-Meta` → `notes`): obsah kódu v nich není (test hledá heslo i `WIFI:`).
- **Kde heslo je**: v samotném kódu (to je účel) a v `tool_params` uloženého návrhu (`ModelFile`), protože z nich se
  návrh znovu otevírá a farma z nich staví díly. Sdílený odkaz na kalkulaci (`/c/<token>`) vede na soubor, jehož
  `tool_params` dostane jen to, co `UploadController::describe` vrací (otevření nástroje s parametry); heslo tedy
  nese jen v parametrech generování, nikde v textu stránky. Pomocné JSON pro engine (`storage/app/tmp/param/*.json`
  při > 12 kB) se mažou hned po běhu; argumenty příkazové řádky Pythonu jsou po dobu běhu vidět v seznamu procesů
  (jako u všech nástrojů).
- Formulář: `type="password"`, `autocomplete="off"` u názvu i hesla, tlačítko Ukázat/Skrýt (`aria-pressed`).
  Náhled se posílá při každém psaní (jako u všech polí) – heslo tedy putuje na server už při psaní; to je
  nevyhnutelné, kód se z něj kreslí.

## 4. Soubory

`app/Domain/Tools/ParametricGenerator.php` (CHOICES `content`, `security`; TEXTS `url` nepovinné, `text`, `ssid`,
`password`; FLAGS `hidden`; WHEN pro qr; FIRST `content`; PLACE `hidden → input`; `clean()` zpětná kompatibilita;
`forTool()` payload; `qrPayload`, `wifiString`, `webAddress`, `qrCapacity`, `QR_BYTES_M`),
`engines/python/creative_kinds.py` (`payload`, min. délka 1), `resources/views/tools/param.blade.php` (textová pole
s `data-when`, heslo jako `password` + tlačítko, nápověda a věta s adresou pod polem, příznaky kroku zadání pro qr,
`$i18n` qr), `resources/js/calc/param.ts` (`markNamed`, blok `cfg.kind === 'qr'`: povinné pole podle druhu, věta
s adresou, Ukázat/Skrýt), `config/tools.php` (ukázka), `lang/src/qr_wifi.json` → cs/en/es (27 klíčů),
`lang/{cs,en,es}/tools_seo/qr.php` (odpověď FAQ na Wi‑Fi), `engines/python/qr_decode.py` (čtečka pro testy, OpenCV),
`tests/Support/QrRaster.php` (moduly z vrchních ploch STL → PNG), `tests/Feature/QrWifiTest.php`,
`scripts/qr_wifi_png.php` (PNG kódů pro telefonní zkoušku).

## 5. Testy a ověření

`QrWifiTest` (5 testů): escapování a uvozovky u hex hodnot, `nopass`, skrytá síť, doplnění `https://`, chyby
u správného pole (ssid/password/url/text, `qr_too_long` s 62 znaky pro 40 mm); **čtení z vyrenderovaného modelu**:
vrchní plochy STL → mřížka modulů → PNG → `qr_decode.py` (OpenCV) dá přesně složený řetězec pro Wi‑Fi se všemi
zvláštními znaky, pro web (`https://matplace.com`) i pro starý návrh s ručně napsaným `WIFI:…`; stejné rozměry,
barvy a díly pro web i Wi‑Fi; heslo není v událostech, logu, názvu souboru ani v meta; stránka cs/en/es má tři
druhy, pole hesla typu password, `data-when`, bez `WIFI:T:` a bez surových klíčů. Dál `CreativeToolsTest` (staré QR
testy se starým voláním jen s `url`), `ColorsPayloadTest`, `ToolPageTest`, `ParametricToolsTest`; `pint`, `tsc`,
`build`.

**Pro Romana – zkouška telefonem** (`docs/img/qr-wifi/`): `wifi-sample.png` je síť `matplace-test`, heslo
`Tisk3D-2026`, WPA – telefon má po načtení nabídnout připojení k síti toho jména (síť neexistuje, stačí, že
nabídku ukáže se správným názvem; na Androidu „Připojit k síti“, na iOS lišta „Připojit k Wi‑Fi matplace-test“).
`wifi-hidden-wep.png` je síť s uvozovkami, středníky a dvojtečkami v názvu – čtečka má ukázat název
`Kavárna "U;Dvou:Koček"` beze zkomolení. `web-sample.png` otevře `https://matplace.com/tools/qr`. Vlastní síť:
`php scripts/qr_wifi_png.php "název" "heslo" wpa` (PNG je jediná stopa) a pak vyzkoušet skutečné připojení.
Neověřeno: tisk; čtení z displeje stačí pro obsah, čitelnost vytištěné destičky je beze změny.

## 6. Nasazení

Bez migrace a bez balíčku (OpenCV jen pro testy; bez něj se test čtení přeskočí). `npm run build`,
`php artisan view:clear`, `optimize`. Po nasazení otevřít `/tools/qr`, přepnout na Wi‑Fi, zadat síť a heslo,
načíst náhled telefonem z obrazovky (kód je v náhledu čitelný), a jednu cedulku vytisknout.
