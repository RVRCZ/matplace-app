# Zadání pro session 4: Kobra X přes LAN: časosběr po vrstvách, světlo, sušení (agent 0.2.2)

Od Romana (18. 10. 2026, po nasazení měniče): „Časosběr by se hodil, světlo a sušení taky.“ Kobra X je připojená bez
Moonrakeru (ovladač `anycubic_lan`, agent 0.2.1, docs/AM.md §1–§6), a tím zatím chybí tři věci, které U1–U3 přes
Rinkhals mají: obrázek po každé vrstvě (časosběr farmy), světlo komory pro kameru, sušení cívek v měniči.

## 0. Kde pracuješ a pravidla

- Větev **`feature/agent-kobra-x-timelapse`** z aktuálního `main` (fa1a008 nese 0.2.1), **docs/AM.md §7**, agent **0.2.2**.
- Jen `agent/` (ovladač, testy, README, config.example). Server (`TimelapseGcode`, `AgentController`, `BuildFarmTimelapse`)
  jen po domluvě s řídící session, když bez toho časosběr nejde; nejdřív navrhni, co a proč.
- Pravidla jako dosud: `git add` jmenovitě, žádný stash, `python -m pytest agent/tests`; nemergovat, nenasazovat;
  hotovo = push + zpráva řídící session (conceprt19092026-8c). Zip agenta staví řídící.
- **Skutečná tiskárna** (192.168.8.44): čtení kdykoli; každý příkaz (světlo, sušení, start kostky) jen po Romanově
  svolení **v tvém okně**, C1 na dobu zkoušky v ručním režimu (řekni řídící session, přepne). Sušení zkoušej krátce
  (10 minut, 45 °C) a vždy zastav. Nikdy nemazat soubory v úložišti tiskárny.

## 1. Světlo (`light(on)`)

Agent zapíná světlo při startu tisku a zhasíná po něm (`agent.py` 342/351); u Kobry X dnes no‑op. Zjisti v K3 LAN
protokolu povel pro světlo (v cloudovém protokolu Anycubic je téma `light`, `action onoff`, `data {type, status}`;
`kobra_connect`, kobra-unleashed, hass-anycubic_cloud) a ověř na tiskárně: zapnout, zhasnout, co hlásí `print/report`
nebo `info` (stav světla, ať nástěnka ví). Když Kobra X světlo nemá nebo protokol nedovolí, napiš to a nech no‑op.

## 2. Sušení (`dry(on, temp, minutes)`)

ACE (box `id 0`) umí sušit. V cloudovém protokolu je to `multiColorBox` `drying` (`data {id, temp, duration}`) a
`drying_stop`; ověř v LAN: start 45 °C / 10 min, hlášení (`multiColorBox getInfo`: teplota, zbývající čas, stav),
stop. Mapuj na `dry()` z `drivers/base.py` jako u Moonrakeru (`MMU_DRYER_START/STOP`); v `status()` dej teplotu
sušení do telemetrie stejným klíčem jako moonraker (`filament_hub`/`dryer`, podívej se, co nástěnka čte).
Rozsahy z displeje tiskárny (PLA 45 °C, PETG 55 °C, max. minuty) zapiš do AM.md.

## 3. Časosběr po vrstvách

Dnes: `head()` vrací `None` → agent fotí po minutách. Farma dělá časosběr tak, že server vloží do G‑kódu po každé
vrstvě odjezd hlavy na `park_x/park_y`, `dwell_ms` a návrat (`app/Domain/Farm/TimelapseGcode.php`; parametry
u tiskárny v `farm_printers.timelapse`, C1 má 250/250, 1000 ms, zdvih 0,6), a agent vyfotí, když `head()` stojí
v parkovací poloze (`agent.py` `layer_frame`, tolerance, 4× za sekundu). Zjisti, co jde, v tomhle pořadí:

1. **Vlastní časosběr tiskárny**: tiskárna hlásí `camera_timelapse_support` a start ve tvaru Sliceru nese
   `"timelapse": {"status": 0, "count": 32765, "type": 32, "mode": 0}` (`anycubic_lan.py` 468). Co dělá `status 1`
   (a `mode`, `type`, `count`): natočí si tiskárna video sama, kam ho uloží, jak ho stáhnout v LAN (HTTP na 18910,
   téma `video`, `file` akce)? Když video jde stáhnout, agent ho po dotištění pošle serveru jako hotový časosběr
   (dohodni s řídící, jak: dnes server skládá video ze snímků, `BuildFarmTimelapse`; nový koncový bod „hotové
   video“ by byla změna serveru).
2. **Vrstva z hlášení**: `print/report` nese číslo vrstvy (telemetrie `layer 375/375`). Změř, s jakým zpožděním
   přijde změna vrstvy proti skutečnému odjezdu hlavy na park (G‑kód farmy ji parkuje hned po `;LAYER_CHANGE`):
   když zpoždění < `dwell`, vyfoť při změně vrstvy (`head()` může vracet park, dokud dwell běží, a agent se nemusí
   měnit). Když je zpoždění větší, navrhni delší `dwell` pro C1 (parametr tiskárny, bez změny kódu) a změř, kolik
   stačí; nebo zjisti, zda `print/report` hlásí i souřadnice (`position`, `coord`), které by `head()` dalo přímo.
3. **Když nic**: zůstane po minutách; napiš proč.

Ověření na tiskárně: kostka 10 mm (`C:\Users\roman\Downloads\kx-cube10.gcode`) s parkováním farmy (řídící ti dá
G‑kód z `TimelapseGcode`, když řekneš), 50 vrstev → kolik snímků má hlavu v parku. Snímky a čísla do AM.md §7.

## 4. Testy a hotovo

`agent/tests/test_anycubic_lan.py`: světlo, sušení start/stop a hlášení, časosběr podle zvolené cesty (falešná
hlášení s vrstvami a časy). **Hotovo =** push, AM.md §7 (co funguje, co ne a proč, čísla), testy, zpráva řídící
session s tím, co se mění v instalaci (soubory, případně config) a zda je potřeba změna na serveru.
