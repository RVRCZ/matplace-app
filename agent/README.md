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
- Komunikace s tiskárnou je schovaná za ovladačem (`farm_agent/drivers/`): teď `moonraker` (Kobra S1 s Rinkhals),
  `anycubic_lan` (Kobra X s původním firmwarem v LAN režimu) a `mock`, později třeba `prusalink` nebo `simplyprint` —
  jeden nový soubor, zbytek se nemění.

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
| `printers[].driver` | `moonraker`, `anycubic_lan` nebo `mock` |
| `printers[].ip` | jen `anycubic_lan`: IP tiskárny (rezervovat v routeru) |
| `printers[].camera` | jen `anycubic_lan`: snímek z vlastní kamery tiskárny (výchozí `true`, potřebuje balíček `av`) |
| `printers[].light` | jen `anycubic_lan`: rozsvěcet světlo kamery před tiskem a zhasnout po něm (výchozí `true`) |
| `printers[].park_lead_min`, `park_lead` | jen `anycubic_lan`: za kolik sekund po ohlášené vrstvě hlava nejdřív / nejpozději dojede do parku. Nenastavené = podle hlavičky G‑kódu: bez značky 2,2 / 4,0 s (Kobra X hlásí vrstvu při načtení G‑kódu, napřed před pohybem; u dlouhých drah i přes 5 s), se `sync=m400` (server vloží před značku vrstvy `M400`, značka se čte se stojící hlavou; přejezd do parku 1–1,5 s, hlášení 0–2 s po čtení) −1,0 / 1,5 s. Jisté okno je `[park_lead, park_lead_min + dwell]`: s `dwell` 3000 ms tedy 4,0–5,2 s, resp. 1,5–2,0 s po hlášení, agent v něm fotí jeden snímek; bez jistého okna sérii |
| `printers[].park_window_min` | jen `anycubic_lan`: nejkratší jisté okno, které se ještě použije (výchozí 0,5 s) |
| `printers[].layer_window` | jen `anycubic_lan`: ruční přepsání – kolik sekund po ohlášené vrstvě hlásit park jako plánovanou polohu (výchozí `0` = podle změřeného předstihu) |
| `printers[].auto_leveling` | jen `anycubic_lan`: tiskárna si před každým tiskem vyrovná podložku, jako po odeslání ze Sliceru Next (výchozí `true`) |
| `printers[].package` | jen `anycubic_lan`: soubor poslat jako balíček `.gcode.3mf` s náhledem (jako Slicer Next) místo holého G-code s doplněnými řádky; obě cesty ověřené na Kobra X 18. 10. 2026 (`docs/AM.md`) |
| `printers[].url` | Moonraker, `http://IP:7125` |
| `printers[].snapshot_url` | snímek z kamery, u Rinkhals `http://IP/webcam/?action=snapshot` |
| `printers[].light_device` | světlo komory (Moonraker power device); výchozí `chamber_light` = Rinkhals. Agent ho rozsvítí před startem tisku a zhasne po jeho konci, v adminu jsou tlačítka Rozsvítit/Zhasnout. `""` = tiskárna světlo nemá |

## Kobra X a další Anycubic s původním firmwarem (`anycubic_lan`)

Kobra X nemá Rinkhals ani Moonraker. Agent s ní mluví stejným protokolem jako Anycubic Slicer Next v LAN režimu
(knihovna `kobra-connect`, podrobnosti a ověření v `docs/AM.md`).

1. **Na tiskárně zapnout LAN režim** (Nastavení → Síť → LAN mode). Tiskárna se tím odpojí od cloudu Anycubic
   (aplikace v telefonu ji pak nevidí); Slicer Next ji najde v síti. Dokud je v cloudovém režimu, nástěnka ji ukazuje
   jako nedostupnou s důvodem „není v LAN režimu“.
2. IP tiskárny rezervovat v routeru a zapsat do `config.yaml` (`driver: anycubic_lan`, `ip: …`).
3. Balíčky: `requirements.txt` je už obsahuje (`kobra-connect` přišpendlený na commit, `av` pro kameru). Stará instalace
   agenta (0.1.x) je dostane příkazem `venv\Scripts\python.exe -m pip install -r requirements.txt`.

**Pozice cívek (sloty v matplace):** ovladač je čte z tiskárny (`multiColorBox`), nic se nenastavuje. Rack tiskárny
je první, měnič ACE za ním: s měničem Rack 1–3 = sloty 0–2 a ACE 1–4 = sloty 3–6 (čtvrtý vstup Racku bere měnič),
bez měniče Rack 1–4 = sloty 0–3. Slot, který tiskárna nemá, start odmítne s výpisem pozic. Na nástěnce je při tisku
vidět, která pozice je zavedená (`spool`, `slot`).

**Více filamentů na jedné podložce:** když G‑kód nese řádek farmy `; matplace filaments T0=<slot> T1=<slot> …`,
ovladač nechá `T<n>` v souboru (jsou to barvy 0..n‑1 projektu) a ve startu pošle `ams_box_mapping` s položkou pro
každou barvu – cívku na výměnu si tiskárna bere z měniče sama. Barva, materiál a spotřeba každé cívky jdou do
balíčku `.gcode.3mf`. Mezera v barvách, `T<n>` bez položky nebo slot mimo pozice = start odmítnutý před nahráním. Při
výměně uprostřed tisku ukazuje nástěnka zaváděnou pozici (`spool`, `slot`) a stav výměny (`spool_change`). Ověření na
tiskárně viz `docs/AM.md` §8.

Co ovladač umí: stav (volná / tiskne / pauza / chyba), teploty, průběh, vrstvy, zbývající minuty, název souboru;
nahrání G-code do tiskárny a start; pauza, pokračování, zrušení; snímek z kamery tiskárny (HTTP-FLV, jeden snímek
dekóduje `av`); světlo kamery (zapne před tiskem, zhasne po něm, stav na nástěnce); sušičku měniče ACE (start
s teplotou a minutami, stop, teplota a vlhkost na nástěnce); časosběr po vrstvách: tiskárna polohu hlavy nehlásí,
ale hlásí číslo vrstvy, a tak ovladač po každé ohlášené změně vrstvy po dobu parkování říká agentovi „hlava je
v parku“ a agent vybere nejklidnější snímek (viz `docs/AM.md` §7; dwell tiskárny v adminu nastavit podle měření).
Neumí: reset chyby (chybu potvrdí obsluha na displeji), vlastní časosběr tiskárny (video si tiskárna ukládá, ale
v LAN ho nejde stáhnout). Soubory v úložišti tiskárny nikdy nemaže.

## Zkouška bez tiskárny (mock)

Celý tok od zákazníka po „hotovo“ jde projít se simulovanou tiskárnou:

1. V `config.yaml` dejte tiskárně `driver: mock` (např. `print_seconds: 90`), spusťte agenta.
2. V administraci: tiskárna v režimu *agent*, přiřazený agent, ve slotu 1 barva se zapnutým „nabízet“.
3. Jako zákazník: kalkulace → **Vytisknout u nás** → dobít kredit → vybrat barvu → zaplatit.
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
| vyrovnání podložky před tiskem | Slicer Next ho posílá jako parametr úlohy (`task_settings.auto_leveling`), ne v G‑code. Start přes Moonraker překládá Rinkhals (`kobra.py`) na stejný MQTT příkaz, ale příznak bere z vlastnosti aplikace Moonraker „Auto leveling when starting prints“ (výchozí False!) – na tiskárně: Rinkhals → Settings → Apps → Moonraker. Pro farmu zapnout; z agenta se to per tisk ovlivnit nedá. |
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
