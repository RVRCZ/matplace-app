# Zadání pro session 4: Kobra X s měničem (ACE), 3 + 4 pozice v ovladači `anycubic_lan`

Od Romana (18. 10. 2026, po nasazení agenta 0.2.0): „KX má 4 barvy normálně, pokud je měnič, tak 3 + 4.“ Měnič **má
připojený**: Rack 3 pozice + ACE 4 pozice = 7 cívek. Dnes ovladač posílá cívku jen do Racku (`ams_box_mapping`
s `ams_index` = index v Racku, box `id −1`), takže pozice v měniči zákazníkům nabídnout nejde.

## 0. Kde pracuješ a pravidla

- Větev **`feature/agent-kobra-x-ace`** z aktuálního `main` (964e31b nese tvůj ovladač), dokument **`docs/AM.md` §6**
  (pokračuj v tomtéž souboru), agent verze **0.2.1**.
- Jen `agent/` (ovladač, `anycubic_pkg` pokud je třeba, testy, README, config.example). Server neměň: pozice 5–7 (sloty
  4–6) C1 založí řídící na produkci (řádky `farm_printer_slots`, 18. 10.), cívky do nich dává Roman
  v `/admin/farm/printers`; server posílá do `start(gcode, filename, slot)` číslo slotu 0–6 jako dosud.
- Pravidla jako minule: `git add` jmenovitě, žádný stash, `python -m pytest agent/tests`; nemergovat, nenasazovat;
  hotovo = push + zpráva řídící session (conceprt19092026-8c). Zip agenta staví řídící.
- **Skutečná tiskárna** (192.168.8.44): čtení kdykoli; každý příkaz jen na kostce (`C:\Users\roman\Downloads\kx-cube10.gcode`)
  a jen po Romanově svolení **v tvém okně**; nikdy nemazat soubory v úložišti tiskárny. C1 je teď v režimu agent
  na PC farmy: po dobu tvé zkoušky ji musí Roman v adminu přepnout na ruční (nebo zastavit agenta), ať se o ni neperete.

## 1. Co zjistit (čtením, hned)

1. `multiColorBox getInfo` s připojeným ACE: kolik slotů hlásí box `id −1` (Rack) s měničem (3? nebo 4 s jedním
   nepoužitelným?), jak je očíslovaný ACE (`id 0`, `slots[].index` 0–3?), které pole říká, že je cívka zavedená
   (`status 5`), a jak se to ukazuje na displeji (Rack 1–3, ACE 1–4?). Zapiš skutečný výpis do AM.md.
2. Jak start řekne tiskárně „cívka z ACE“: pole v `ams_box_mapping` (`ams_index` přes boxy, nebo zvlášť `ams_id` /
   `box_id` / `ams_index` = box × 4 + slot?). Zdroje v tomhle pořadí: kód `kobra_connect` a projekty na GitHubu
   (kobra-unleashed, anycubic LAN protocol), log Slicer Next na Romanově PC (`%APPDATA%\AnycubicSlicerNext\log` nebo
   podobně: když Slicer po startu tisku zapisuje odeslaný JSON, je to vidět bez sítě; MQTT je TLS, pktmon tu nepomůže),
   nakonec zkouška na tiskárně (bod 3).

## 2. Co ovladač udělá

- Mapování **slot matplace → (box, index)**: čti živě z `getInfo`, boxy v pořadí Rack (`id −1`) pak ACE (`id 0`);
  slot n = n‑tá pozice napříč boxy v tomhle pořadí (Rack 0–2 → sloty 0–2, ACE 0–3 → sloty 3–6). Když je ACE odpojený,
  Rack má 4 pozice → sloty 0–3 jako dnes; nic se v configu nenastavuje. Zapiš do README, co kde je.
- `start`: `ams_box_mapping` s údaji pro zvolený box; `paint_info`/`statistics` v balíčku beze změny.
- `status()`: zavedená cívka (`channel_no`, `loaded_slot`) přeložená zpět na slot matplace, ať nástěnka ukazuje
  správnou pozici; stavy `ace_filament_feeding` a `extfilbox_filament_feeding` oba jako „zavádí“.
- `spoolAt(slot)` (barva, materiál pro kontrolu) napříč boxy.
- Testy: falešný `getInfo` se dvěma boxy (3 + 4), s jedním (4), start ze slotu 0, 2, 3, 6; odpojený ACE → slot 4+ dá
  srozumitelnou chybu, ne pád.

## 3. Zkouška na tiskárně (po Romanově „volná“ v tvém okně)

Kostka ze slotu v ACE (např. slot 3 = ACE pozice 1, cívka tam musí být zavedená: řekni Romanovi, kterou pozici chceš):
start, `ace_filament_feeding`, první vrstvy, zrušení → free. Pak totéž ze slotu 0 (Rack 1), ať je vidět, že Rack dál
funguje. Výpis `print/report` (channel_no, loaded_slot) do AM.md.

**Hotovo =** push `feature/agent-kobra-x-ace`, AM.md §6 (výpis boxů, tvar mapování, co je ověřeno), testy, zpráva
řídící session; co se mění v instalaci na PC farmy (zřejmě jen soubory, žádný nový balíček).
