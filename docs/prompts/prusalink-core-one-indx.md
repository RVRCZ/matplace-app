# Ovladač PrusaLink pro Prusa Core One + INDX, 8 nástrojů (TODO #67)

Pro session 4 (agent), 4. 11. 2026. Worktree `C:\matplace-sell-wt`, větev `feature/agent-prusalink` z `origin/main`
(ne z `fix/s1-start-heat`, ta zůstává nesloučená). Po pushi napiš řídící session. Nic nenasazuj, žádný `migrate*` v
worktree, na tiskárně nic nemaž a netiskni bez Romanova svolení.

## Stav

- Roman má v domácí síti Prusa Core One+ s INDX (8 nástrojů), zapnutou a dnes tiskne přes PrusaSlicer + Prusa Connect.
  IP adresu a klíč API PrusaLinku dá Roman; klíč zapíše do `config.yaml` agenta sám, do repozitáře ani do docs nepatří.
- Agent: rozhraní `agent/farm_agent/drivers/base.py` `PrinterDriver` (connect, close, status, start(gcode_path, filename,
  slot), slot_status, files, fetch, delete, plate_confirmed, pause, resume, cancel, snapshot, head, light, dry, reset);
  vzory `moonraker.py` (HTTP) a `anycubic_lan.py`; `config.example.yaml`, `README.md`, testy `agent/tests` s falešnými
  servery. Server zná ovladače jménem (`/admin/farm/printers`), pozice tiskárny `FarmPrinterSlot`.
- Řádek volby cívky `T<n>` dnes vkládá `app/Domain/Farm/GcodeSlot.php` za startovní makro Anycubicu `G9111`; pro Prusu
  nemá smysl (soubor bez `G9111` nechává beze změny, ověř). Session 2 zároveň dělá limit barev podle stroje (TODO #66) a
  `GcodeSlot.php` nemění.
- Slicer: OrcaSlicer 2.5.0 alpha má profily „Prusa CORE One INDX 4T / 8T“ (PR OrcaSlicer/OrcaSlicer#15903, nastavení výměn
  podle PrusaSliceru 3.0 v #16209). Farma dnes řeže Orcou 2.4 (server), lokálně portable `orca24` v scratchpadu
  session 2; povýšení serveru na 2.5 řeší řídící zvlášť, až bude co nasadit.

## Co udělat

A. **API PrusaLinku ověř proti specifikaci** (github.com/prusa3d/Prusa-Link-Web, `spec/openapi.yaml`; firmware Buddy
   Core One): ověření hlavičkou `X-Api-Key` (klíč z displeje, Nastavení → Síť → PrusaLink), případně digest `maker`;
   `GET /api/v1/status`, `/api/v1/info`, `/api/v1/job`; nahrání `PUT /api/v1/files/usb/<jméno>` (hlavičky
   `Content-Length`, `Overwrite`, `Print-After-Upload`); start existujícího souboru `POST /api/v1/files/usb/<jméno>`;
   `PUT /api/v1/job/<id>/pause|resume`, `DELETE /api/v1/job/<id>` (stop); seznam a mazání `/api/v1/files/usb`; chybové
   kódy a limity velikosti. Kamera v PrusaLinku není (`snapshot` vrací None, do docs napiš, co by šlo později).
B. **Ovladač `prusalink`** v `agent/farm_agent/drivers/prusalink.py`: volby `url`, `api_key` (jen z config.yaml),
   `timeout`, `start_wait`; stav z `/status` + `/job` (teploty, průběh, vrstva pokud je, stav `IDLE | PRINTING | PAUSED |
   ERROR | OFFLINE`, hlášky tiskárny pokud API nějaké dává), `start` = nahrání na USB + start, čekání, až tisk opravdu
   běží (API vrací úspěch dřív, než tiskárna nahřeje), `files/fetch/delete` nad `usb`, `plate_confirmed` podle toho, co
   firmware po dotištění hlásí (zjisti; když nic, stejná náhrada jako u Rinkhalsu). `slot` u INDX = nástroj T0 až T7
   zvolený už při řezu, ovladač s ním nic nedělá, jen ho zaloguje a do `slot_status` dá, co API o nástrojích říká
   (zjisti, jestli PrusaLink vrací stav naložených nástrojů; když ne, None).
C. **Mapování nástrojů při startu:** firmware Buddy při neshodě filamentů ukáže dialog mapování na displeji. Zjisti
   (dokumentace, zkouška s Romanem), zda tisk přes PrusaLink čeká na potvrzení a jestli to jde obejít (shodné názvy
   filamentů ve slicích a na tiskárně, nastavení tiskárny). Bez potvrzení na displeji farma bez obsluhy nestartuje;
   výsledek zapiš jako podmínku provozu.
D. **Profily Orca pro INDX 8T** do `engines/orca/profiles` (machine, process, filamenty, které Roman v INDX má) podle
   Orca 2.5.0 alpha; portable 2.5.0 alpha stáhni do scratchpadu (ne do repozitáře). Lokálně nařež dvoubarevný zkušební
   objekt a zkontroluj G-code: výměny nástrojů T0/T1, start bez `G9111`, bez proplachu (INDX je měnič nástrojů).
   Zjisti, jestli 2.4 profily 2.5 vůbec načte; výsledek je vstup pro povýšení serveru.
E. **Web:** ovladač `prusalink` v seznamu ovladačů tiskárny v administraci, 8 pozic s názvy „Nástroj 1 až 8“, druh
   stroje „měnič nástrojů“ (bez odpadu z proplachu, pokud s tím kalkulace počítá; zjisti a popiš, neměň cenu bez
   Romana). Texty přes `lang/src` + `lang_add.py`.
F. **Testy:** pytest s falešným PrusaLinkem (aiohttp test server: status, job, upload, start, pause, stop, chyby,
   pomalý start), PHP testy administrace tiskáren zelené, pint. Agent 0.2.11 (0.2.10 je na nesloučené větvi).
G. **Živá zkouška jen s Romanem:** až tiskárna netiskne, Roman zadá IP a klíč do config.yaml agenta na farmovém PC (nebo
   zkušební agent na jeho PC), malý jednobarevný objekt z farmy, pak dvoubarevný. Řídící předá výsledky.
H. **Docs:** `docs/AM.md` nový oddíl (API, ovladač, mapování nástrojů, profily, co je podmínkou provozu), `agent/README.md`
   a `config.example.yaml`, řádek TODO 67 (řídící ho založila, doplň stav), odchylky sem dolů.

## Pravidla

- Jen tvoje větev a worktree (tři změněné PNG `public/img/tool-examples/map-*.png` ve worktree nech být, nejsou tvoje).
- `GcodeSlot.php` a soubory session 2 neměň; když je to nutné, napiš řídící. Klíče a adresy jen od Romana a jen v jeho
  config.yaml. Git add jmenovitě, žádný stash. Commit končí `Co-Authored-By: Claude <model session> <noreply@anthropic.com>`.
- Produkční databázi ani server nečti. Tiskárna: čtení přes API až s klíčem od Romana, tisky jen s ním.

## Odchylky session 4 (4. 11. 2026)

- Profily nejsou z portable 2.5.0-alpha, ale ze souborů tagu `v2.5.0-alpha` na GitHubu: portable balí dodavatele do `.opc`
  (magic `ZCRO`, není to zip). Portable posloužil k řezu. Rozbalení řetězce dělá `scripts/orca_flatten_profile.py`, chybějící
  klíče doplněny výchozími hodnotami 2.5 (bez nich 2.5 i 2.4 filamenty odmítají).
- Procesů jsou čtyři (0,25 Draft, 0,20 Balanced, 0,15 Detail, 0,10 Fine), filamenty Generic PLA, Generic PETG, Prusament PLA,
  Prusament PETG. ASA ani TPU pro INDX Orca 2.5 nemá; doplní se, až Roman řekne, co v INDX má.
- Orca 2.4.0-beta s profily 2.5 padá (segfault), 2.4 je tedy nezkouší ani nenačte. Orca 2.5 navíc odmítá farmě filamenty
  s neprázdným `inherits` (`filament_pla.json`, `filament_petg.json`, `filament_tpu.json` a varianty); to je k vyřešení při
  povýšení serveru (AM.md §12e).
- Navíc proti zadání: dvouřádková pojistka v `AgentController::gcode` a `FarmOrderController::gcode`: pro
  `FarmPrinter::isToolChanger()` jde agentovi i adminovi originál řezu, protože `GcodeSlot` INDX soubor nepřemapuje (žádný
  holý `T<n>`), ale s teplotami cívky přepíše 6 řádků `M104/M109` ve startu Prusy. `GcodeSlot.php` beze změny. Volbu nástroje
  musí dělat řez (AM.md §12d), to je na session 2 a řídící.
- Ověření `X-Api-Key` doplněno o `auth: digest` (uživatel `maker`, `aiohttp.DigestAuthMiddleware`, aiohttp 3.12+ v
  `requirements.txt`), protože fóra hlásí firmwary, které klíč v hlavičce odmítají; na Romanově 8.0.0 klíč funguje.
- `plate_confirmed` podle přechodu FINISHED/STOPPED → IDLE/READY (obrazovka dotištění opuštěna, „Set ready“), ne podle
  náhrady Rinkhalsu; po konci firmware soubor nejmenuje, proto si ovladač poslední úlohu pamatuje v `work/<key>.prusalink.json`.
- Druh stroje „měnič nástrojů“ je bez nového sloupce (`isToolChanger()` podle jména profilu stroje). V kalkulaci na
  proplachu závisí jen `PrepareFarmOrder::ONE_PLATE_PROCESS` (věž) a odhad `engines.colors` u malovaných 3MF; popsáno
  v AM.md §12d, ceny beze změny.
- Mapování nástrojů je ze zdrojů firmwaru a dokumentace, ne ze zkoušky s Romanem (tiskárna tiskla). Časosběr po vrstvách
  na Core One nejde (API nedává polohu při pohybu), u tiskárny má být vypnutý.
- Agent 0.2.11 obsahuje jen tento ovladač; 0.2.10 (`fix/s1-start-heat`) zůstává nesloučený, čísla verzí na sebe navazují.
