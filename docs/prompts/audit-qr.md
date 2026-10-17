# Zadání pro session 2: Wi-Fi QR bez technického zápisu (audit bod 9)

Podklad: `docs/AUDIT.md` bod 9. Dnes nástroj `/tools/qr` bere obsah jako jeden text (`url`) a pro Wi-Fi radí ruční
zápis `WIFI:T:WPA;S:…;P:…;;` (ukázka v `config/tools.php`, `qr` → `seo.examples`). Audit: nahradit běžným
formulářem.

## 0. Kde a pravidla

- Tvůj worktree, větev **`feature/qr-wifi`** z aktuálního `main`, dokument **`docs/AI.md`**.
- Soubory: engine `engines/python/creative_kinds.py` (`def qr`, řádek ~745), definice polí nástroje (kde `qr` má
  `url`, `label`, `size`, `stand`, `hole`: `config/tools.php`, `ParametricGenerator`/`param_tool.py` pro `qr`),
  stránka nástroje přes společnou šablonu (`param.ts` je sdílený: změny jen pro druh `qr`, co nejmenší), texty
  přes `lang/src/qr_wifi.json` + `python scripts/lang_add.py`. Nesahat: `tool_page.ts` a `tools/page.blade.php`
  (session 3 v nich pracuje), kalkulace, farma.
- Pravidla: `git add` jmenovitě, žádný stash, `PYTHON_BIN` v `.env`, `pint --dirty`, `tsc`, `build`, testy
  (`ParametricToolsTest`, `ToolPageTest` s filtrem); nemergovat, nenasazovat; hotovo = push + zpráva řídící session
  (conceprt19092026-8c).

## 1. Typy obsahu: Web, Wi-Fi, Text

- Přepínač typu nahoře v kroku zadání. **Web:** adresa; doplnit `https://`, když chybí, a ukázat výslednou adresu
  pod polem; neplatná adresa = chyba u pole. **Wi-Fi:** název sítě (SSID), heslo (pole typu password s možností
  ukázat), zabezpečení WPA/WPA2 (výchozí), WEP, bez hesla, přepínač „skrytá síť“. **Text:** libovolný text
  s limitem délky podle verze QR, který ještě projde kontrolou čitelnosti.
- Řetězec `WIFI:T:<WPA|WEP|nopass>;S:<ssid>;P:<heslo>;H:<true>;;` skládá server (engine nebo PHP), ne prohlížeč;
  escapování `\`, `;`, `,`, `:`, `"` zpětným lomítkem podle zvyklostí ZXing; při „bez hesla“ `P:` vynechat.
- Přepnutí typu nemění rozměry, podstavec, otvor, barvy ani popisek cedulky; mění jen obsah.
- Kontrola čitelnosti (dnešní varování podle hustoty modulů) a vysvětlení dvoubarevného tisku zůstávají.

## 2. Soukromí

- Heslo a celý obsah Wi-Fi QR nesmí skončit v `events` (meta nástroje), v logu aplikace ani v názvech souborů; do
  `seo.examples` a do sdílených odkazů jde jen ukázka. Ověř, co dnes nástroj posílá do měření a do logu, a doplň
  test, že v zápisu události ani v logu heslo není.
- Sdílený odkaz na návrh (pokud nástroj umí) heslo nese jen v parametrech generování; napiš do AI.md, jak to je.

## 3. Testy a ověření

- `ParametricToolsTest` (nebo kde jsou testy QR): Wi-Fi s názvem `Kavárna "U;Dvou:Koček"` a heslem s `\ ; , : "`
  vytvoří platný řetězec; dekódování vzorového QR z vyrenderovaného modelu nebo ze stejné knihovny potvrdí obsah;
  web bez `https://` se doplní; neplatná adresa je 422 s textem u pole; žádný surový klíč v cs/en/es.
- Roman načte zkušební QR na Androidu a iOS (vytiskni nebo mu dej PNG náhledu do `docs/img/qr-wifi/`); napiš mu,
  co má zkusit (připojení k síti z QR).
- **Hotovo =** push `feature/qr-wifi`, AI.md (formát řetězce, co se escapuje, co jde do měření), testy, `pint`, `tsc`,
  `build`, zpráva řídící session.
