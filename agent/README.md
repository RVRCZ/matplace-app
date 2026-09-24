# matplace farm-agent

Malá služba, která běží **v síti u tiskáren** a propojuje je s matplace. Tiskárny zůstávají za NATem bez jediného
otevřeného portu: agent se na matplace připojuje sám, jen odchozím HTTPS, a s tiskárnami mluví lokálně.

```
zákazník ──> matplace (beta.matplace.com) <── HTTPS, jen odchozí ── farm-agent ── LAN ──> Moonraker na tiskárně
                  admin: „podložka je volná“                          (mini PC / Raspberry Pi / Windows)
```

- Jeden agent obslouží libovolný počet tiskáren (jeden blok v `config.yaml` na tiskárnu).
- Tisk **nikdy nezačne sám od sebe**. Agent jen plní příkaz `start`, který matplace vydá až poté, co obsluha
  v administraci potvrdí „podložka je volná“. Spuštěním se potvrzení spotřebuje.
- Komunikace s tiskárnou je schovaná za ovladačem (`farm_agent/drivers/`): teď `moonraker` a `mock`,
  později třeba `prusalink` nebo `simplyprint` — jeden nový soubor, zbytek se nemění.

## Ověřené verze

| Co | Verze | Poznámka |
|---|---|---|
| Anycubic Kobra S1 Combo, firmware | **2.7.2.7** | stav k 21. 9. 2026 |
| ACE Pro, firmware | 1.3.863 | Rinkhals na něj nesahá |
| Rinkhals | **20260901_01** (`update-ks1.swu`) | z `github.com/rinkhals-community/Rinkhals`; podporu 2.7.2.7 má od 20260601_03 |
| Python | 3.10 nebo novější | vyzkoušeno na 3.11 |

**Firmware tiskárny neaktualizujte automaticky.** Rinkhals podporuje jen konkrétní verze firmwaru Anycubic; po
aktualizaci firmwaru může přestat fungovat, dokud nevyjde nový Rinkhals. V aplikaci Anycubic i na displeji vypněte
automatické aktualizace a před každou ruční aktualizací si ověřte v poznámkách k vydání Rinkhals, že novou verzi umí.
Na tiskárně nechte zapnutý **LAN mode**.

## Síť (na tomhle se dá zaseknout)

- Stroj s agentem musí na tiskárny **vidět**: stejná síť, vypnutá „AP/Client isolation“, tiskárny ne v síti pro hosty.
  Zkouška: `curl http://IP_TISKARNY:7125/server/info` z počítače, kde agent poběží, musí vrátit JSON.
- Stroj s agentem připojte **kabelem**; tiskárny (Kobra S1 umí jen Wi‑Fi 2,4 GHz) na vlastní router farmy.
- V routeru nastavte tiskárnám **pevné IP adresy** (rezervace DHCP podle MAC) — v konfiguraci jsou IP adresy.
- Moonraker na Rinkhals nemá přihlašování: kdo je v síti farmy, ovládá tiskárny. Síť farmy proto držte oddělenou,
  s vlastním heslem, a **nepřesměrovávejte do ní žádné porty z internetu**. Připojení přes mobilní síť (CGNAT) nevadí.

## Příprava v matplace

1. `/admin/farm/agents` → *Vytvořit a zobrazit token*. Token se ukáže jen jednou.
   (Bez administrace: `php artisan farm:setup --agent="Dílna"`.)
2. `/admin/farm/printers` → u tiskárny nastavte **Režim: Automaticky přes agenta**, vyberte agenta a zapamatujte si
   její **klíč** (např. `kobra-s1-01`). Stejný klíč patří do `config.yaml`.

## Instalace – Raspberry Pi / Linux

```bash
sudo useradd --system --home /opt/farm-agent --shell /usr/sbin/nologin farm
sudo mkdir -p /opt/farm-agent/work
sudo cp -r farm_agent requirements.txt config.example.yaml /opt/farm-agent/
cd /opt/farm-agent
sudo python3 -m venv venv
sudo venv/bin/pip install -r requirements.txt
sudo cp config.example.yaml config.yaml
sudo nano config.yaml                      # server, token, IP tiskáren
sudo chown -R farm:farm /opt/farm-agent && sudo chmod 600 config.yaml

sudo -u farm venv/bin/python -m farm_agent --config config.yaml --check    # zkouška spojení, nic nespouští
```

`--check` vypíše stav každé tiskárny a to, jestli matplace token přijal a které tiskárny má agent přidělené.
Když je vše v pořádku, zapněte službu (spouští se sama po startu a po pádu se restartuje):

```bash
sudo cp farm-agent.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now farm-agent
journalctl -u farm-agent -f                # průběžný výpis
```

## Instalace – Windows (dokud není Raspberry Pi)

```powershell
cd C:\farm-agent                           # sem zkopírujte složku agent
py -m venv venv
venv\Scripts\pip install -r requirements.txt
copy config.example.yaml config.yaml       # vyplňte server, token, IP tiskáren
venv\Scripts\python -m farm_agent --config config.yaml --check
venv\Scripts\python -m farm_agent --config config.yaml
```

Trvalý běh: Plánovač úloh → Nová úloha → spouštět *Při spuštění počítače*, „Spustit bez ohledu na přihlášení“,
akce `C:\farm-agent\venv\Scripts\python.exe`, argumenty `-m farm_agent --config C:\farm-agent\config.yaml`,
„Spustit v“ `C:\farm-agent`, na kartě Nastavení „Při selhání restartovat každou 1 minutu“.
Počítač nesmí usínat.

## Konfigurace

Viz `config.example.yaml`. Token lze místo souboru předat proměnnou prostředí `FARM_AGENT_TOKEN`.

| Položka | Význam |
|---|---|
| `server` | adresa matplace, jen `https://` |
| `token` | token agenta z administrace |
| `poll_seconds` | jak často se agent hlásí a ptá na práci (výchozí 5 s); změnu stavu tiskárny posílá hned (websocket) |
| `snapshot_seconds` | jak často posílá snímek z kamery běžícího tisku (výchozí 15 s; živý náhled, server si nechává jeden za minutu pro časosběr) |
| `printers[].key` | klíč tiskárny v administraci |
| `printers[].driver` | `moonraker` nebo `mock` |
| `printers[].url` | Moonraker, `http://IP:7125` |
| `printers[].snapshot_url` | snímek z kamery, u Rinkhals `http://IP/webcam/?action=snapshot` |
| `printers[].light_device` | světlo komory (Moonraker power device); výchozí `chamber_light` = Rinkhals. Agent ho rozsvítí před startem tisku a zhasne po jeho konci, v adminu jsou tlačítka Rozsvítit/Zhasnout. `""` = tiskárna světlo nemá |

## Zkouška bez tiskárny (mock)

Celý tok od zákazníka po „hotovo“ jde projít se simulovanou tiskárnou:

1. V `config.yaml` dejte tiskárně `driver: mock` (např. `print_seconds: 90`), spusťte agenta.
2. V administraci: tiskárna v režimu *agent*, přiřazený agent, ve slotu 1 barva se zapnutým „nabízet“.
3. Jako zákazník: kalkulace → **Pronajmout tiskárnu** → dobít kredit → vybrat barvu → zaplatit.
4. V `/admin/farm` klikněte **Podložka je volná, může tisknout**. Do pár vteřin agent převezme příkaz, „tiskne“,
   zákazník i admin vidí průběh, teploty a snímek; po doběhnutí je zakázka *Hotovo*, kredit stržený a u zakázky
   je zapsaný skutečný čas a spotřeba.
5. Zkuste i `fail_at: 40` (selhání → kredit se vrátí, adminovi přijde e‑mail), pauzu / pokračování / zrušení
   z administrace a vypnutí agenta na víc než 2 minuty (tiskárna „offline“, e‑mail adminovi).

## Co agent dělá při potížích

| Situace | Chování |
|---|---|
| matplace nedostupný | tisk běží dál, agent zkouší znovu; konečný stav doručí, až spojení naskočí |
| restart agenta / výpadek proudu u agenta | rozpracovaná zakázka je uložená ve `work/state.json`, hlášení pokračuje |
| tiskárna nedostupná | hlásí se jako `offline`; po 2 minutách je v administraci offline a obsluze přijde e‑mail |
| tiskárna není volná při startu | start se odmítne, zakázka zůstane ve frontě, obsluha dostane e‑mail |
| stažený G‑code nesouhlasí s kontrolním součtem | start se odmítne |
| někdo na tiskárně pustí jiný soubor | sledovaná zakázka se po ~40 s ohlásí jako selhaná (nikdy ne jako hotová) |
| příkaz `start` nikdo 10 minut nepřevezme | matplace ho stáhne; je potřeba znovu potvrdit volnou podložku |

## Ověřeno na skutečné Kobra S1 (22. 9. 2026, fw 2.7.2.7, Rinkhals 20260901_01)

G‑code z matplace má stejný začátek a konec jako G‑code z Anycubic Slicer Next 2.0.0.3 (`G9111 bedTemp=… extruderTemp=…`,
`M117`, `T0`, čistící linka na Y 255, stejný end G‑code) — porovnáno se skutečným souborem.

| Co | Výsledek |
|---|---|
| Moonraker `:7125`, Mainsail `:4409`, Fluidd `:4408`, kamera `http://IP/webcam/?action=snapshot` (JPEG) | funguje |
| světlo: `POST /machine/device_power/device?device=chamber_light&action=on|off` | funguje (Rinkhals power device) |
| sušení v ACE: `MMU_DRYER_START UNIT=0 DURATION=<min> TEMP=<°C>` / `MMU_DRYER_STOP UNIT=0` přes `/printer/gcode/script`, stav v objektu `mmu_machine.unit_0.dryer_*` | Rinkhals `mmu_ace`; v adminu tlačítka Sušit / Zastavit sušení, stav v telemetrii jako `dryer` |
| Upload `POST /server/files/upload` + `POST /printer/print/start` | tisk se rozjel a proběhla celá startovní sekvence jako ze Sliceru Next: předehřev 170/55 °C, LeviQ (sondování), zahřátí na 220 °C, čisticí linka, tisk |
| `print_stats` během startu | `progress` 0, po začátku první vrstvy se `print_duration` vynuluje → hlášený čas je čistý čas tisku (to chce kalibrace ceny) |
| Navíc oproti čistému Klipperu | `print_stats.info.current_layer/total_layer`, `virtual_sdcard.remain_time` (agent je posílá v telemetrii) |
| Uložený soubor je o pár desítek bajtů větší než originál | Rinkhals ho při uložení doplňuje; metadata (čas, filament) sedí |

| Volba slotu ACE | `T2` v G‑code (za `M117`) → tiskárna tiskla ze slotu 3. Rinkhals má makra `t0`…`t3`, která ACE ovládají; objekt `ota_filament_hub` hlásí stav výměny (`state`, `progress`), ne aktivní slot. Orca sama žádný `T` řádek nepíše, matplace ho vkládá. |
| Typ podložky | Orca CLI bez `curr_bed_type` slicuje pro „Cool Plate“ (podložka 35 °C!). Farm přepisy nastavují `Textured PEI Plate` → 55 °C jako Slicer Next. |
| Metadata souboru | u G‑code z Orcy Rinkhals nevyplní `estimated_time` v `/server/files/metadata` (u Slicer Next ano); agent metadata nepotřebuje |

Zbývá vyzkoušet: **pauza / pokračování / zrušení** z administrace a chování po zrušení (hlava odjede, topení se vypne).

## Vývoj

```bash
cd agent
python -m unittest discover -s tests        # 13 testů proti simulované tiskárně a falešnému serveru
```

Nový ovladač: třída odvozená z `PrinterDriver` (`drivers/base.py`: `status`, `start`, `pause`, `resume`, `cancel`,
volitelně `snapshot`, `connect`, `close`), zápis do `DRIVERS` v `drivers/__init__.py`. Agent ani matplace se nemění.

### První kalibrační tisk (Benchy ze Sliceru Next, 22. 9. 2026)

| | odhad v hlavičce | tiskárna | poměr |
|---|---|---|---|
| čas tisku | 37:40 | 40:16 (`print_duration`; s přípravou 44:02) | 1,07 |
| filament | 3681,8 mm / 10,98 g | 3722 mm = 11,10 g; váha 11 g | 1,01 |

Druhý tisk (stojánek na telefon 101×105×60 mm, **vyslicováno naší serverovou Orcou** s farm profilem, slot 3 bílé PLA+ 225/220 °C):
odhad 186 min / 17 682 mm → tiskárna **139,8 min** / 18 094 mm (54,0 g). Filament opět do 2 %; čas ale o 25 % **kratší** –
odhad Orcy je pesimistický, zatímco Slicer Next (Benchy) byl přesný. Farma tiskne jen z Orcy, takže skutečná korekce času
bude spíš pod 1,0; zatím zůstává 1,07 (cena raději vyšší) a upřesní se po několika tiscích (admin počítá průměr).

Výchozí korekce Kobra S1 v `FarmSeeder`: čas × 1,07, hmotnost × 1,0. `filament_used` z tiskárny sedí s váhou, takže
kalibrace hmotnosti může stát na hlášení agenta; váha je jen kontrola.
