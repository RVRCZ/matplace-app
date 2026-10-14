# Kobra X na farmě: profily (session F) a připojení (řídící)

Od Romana (14. 10. 2026): „máme v síti novou tiskárnu Kobra X“.

## Co víme (14. 10.)

- Anycubic Kobra X (2026): 260 × 260 × 260 mm, přímý extrudér, kalená tryska do 300 °C, deska do 100 °C, až 600 mm/s,
  4 barvy přes ACE (rozšiřitelné na 19). Nový software „K4“ (C++ port Klipperu), podepsaný firmware – **Rinkhals
  na ni nejde**, Moonraker tedy nemá; náš agent (ovladač Moonraker) ji neumí řídit.
- Má **LAN režim** Anycubicu: proprietární MQTT přes TLS (port 9883) s HTTP handshake na 18910; po zapnutí se odpojí
  cloud i mobilní aplikace, Anycubic Slicer Next posílá tisky, sleduje průběh a video. Komunitní klienti
  (kobra-connect, Python, Apache-2.0; integrace do Home Assistantu; Printago, SimplyPrint) umí stav, start/pauza/
  zrušení, soubory, kameru – ale jsou psané pro Kobra 3/S1 (K3); zda K4 mluví stejně, nikdo nepotvrdil.
- OrcaSlicer 2.4.0-beta na serveru profil Kobra X nemá (končí u S1 Max); Anycubic Slicer Next ho má.

## 1. Session F – profily (hned)

- `engines/orca/profiles/machine_kobrax.json` (+ případně `*_n04` varianty) odvozený z profilu Kobra X v Anycubic
  Slicer Next (F má CLI místně): tisková plocha 260 × 260, výška 260, start/end G-code, limity rychlostí, retrakce;
  proces a filamenty: spárovat s našimi `process_*` a `filament_*` jako u Kobra 3 Max (`compatible_printers`).
- **Start G-code**: zjistit z ukázkového G-code Anycubic Slicer Next pro Kobra X, jak začíná tisk (K4 už nemusí
  používat `G9111 bedTemp= extruderTemp=`, na který spoléhá `GcodeSlot::retarget` pro T-řádek a zahřátí před výměnou
  cívky) – napsat do dokumentu a říct řídící session, jestli je třeba `GcodeSlot` upravit.
- Zkušební řez naší kostkou a stojánkem přes CLI, porovnat čas/hmotnost s Anycubic Slicer Next; `FarmTuningTest`/
  `OrcaProfileTest` zelené; dokument docs/V.md (nový oddíl).
- Řádek tiskárny v katalogu zakládá Roman (`/admin/farm/printers/new`: model Kobra X, klíč `kobra-x-01`, 260/260/260,
  tryska 0,4, profil `machine_kobrax.json`, **režim ruční**), cívky přes „Založit cívky“.

## 2. Řídící session – připojení (po Romanových odpovědích)

- Roman: IP adresa, zapnout LAN režim na displeji (Nastavení → síť → LAN), verze firmwaru, tryska, kolik cívek v ACE,
  kamera ano/ne, a souhlas s tím, že z PC farmy U pustíme zkušební handshake (kobra-connect, Python) – jen čtení stavu.
- Když K4 odpoví na protokol K3: nový ovladač agenta `anycubic_lan` (stav, start s nahráním souboru, pauza/zrušení,
  kamera přes proxy) vedle `moonraker`; verze agenta 0.2.0; test proti falešnému brokeru.
- Když neodpoví: tiskárna zůstane v ručním režimu (obsluha nahraje G-code přes Anycubic Slicer Next nebo USB a stav
  přepíná ručně) do doby, než někdo protokol K4 popíše (sledovat kobra-connect, hass-anycubic, Rinkhals).
