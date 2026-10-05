# Zadání: počítač v dílně – farma matplace (8× Kobra S1, agent, foto-box)

Datum: 5. 10. 2026. Tohle je první prompt pro Claude Code na **počítači v dílně** (čerstvá Windows 11). V dílně je
**8 tiskáren Anycubic Kobra S1** a 5G box (router s vlastním internetem přes SIM). Tenhle počítač **provozuje
farmu**: běží na něm agent tiskáren (trvale, jako služba Windows) a slouží k foto-boxu. Kód se tu nevyvíjí – změny
agenta vznikají na vývojovém počítači doma a sem se jen zkopírují. Domácí tiskárny (3 Kobry na jiné síti) obsluhuje
domácí agent; těch se tady nedotýkáš.

Na flashce `D:\` (FAT32) je všechno potřebné:

| cesta | co to je |
|---|---|
| `D:\aGVscF9zb3Nf\update.swu` | Rinkhals 20260901_01 pro Kobra S1 – vkládá se do USB tiskárny, viz `NAVOD-kobra-s1.md` |
| `D:\matplace-dilna\NAVOD-kobra-s1.md` | postup pro každou tiskárnu (firmware, Wi‑Fi, instalace, rezervace IP, kontrola) |
| `D:\matplace-dilna\agent\` | agent farmy: `farm_agent\`, `requirements.txt`, `install-farma.ps1`, `run-agent.cmd`, `config.yaml.template` (8 tiskáren) |
| `D:\matplace-dilna\PROMPT-dilna.md` | tento text |

## Co je co

- **matplace.com** – Laravel aplikace na serveru Hetzner. Nic na serveru neměň a nepřipojuj se k němu; všechno
  jde přes HTTPS a administraci `https://matplace.com/admin/farm`.
- **Farm agent** (Python 3.11, `agent/README.md` na flashce) – polluje matplace (jen odchozí HTTPS), stahuje
  G‑code a mluví s tiskárnami lokálně přes Moonraker (Rinkhals, port 7125). Tisk nikdy nezačne sám – jen po
  „Podložka je volná“ v administraci. Jeden agent obslouží všech 8 tiskáren.
- **Tiskárny**: v adminu jsou předpřipravené **Dílna S1 #1 … #8** (klíče `kobra-s1-d01` … `kobra-s1-d08`,
  vypnuté, bez agenta) – kopie nastavení domácí S1 #1 (podložka 250×250×250, tryska 0,4, texturovaná PEI, časosběr
  „consent“). Síť: 5G box `192.168.1.1`, SSID 2,4 GHz **`MujO2Internet_2.4G_B47CB1`**; tiskárna N dostane
  rezervací podle MAC adresu **192.168.1.10N**. Kamera Rinkhals `http://IP/webcam/?action=snapshot`, Mainsail `:4409`.
- **Foto-box** – tři USB kamery (shora, zleva, zprava) u tohoto počítače; `https://matplace.com/admin/farm/photobox`
  je obsluhuje z prohlížeče. Fotky hotových kusů končí na konci videí.
- **Dva agenti souběžně** (domácí „Agent1 S1“ a tenhle „Dílna“) jsou v pořádku – každý má své tiskárny.

## Než začneš, vyžádej si od Romana (jedna zpráva)

1. Že je počítač **kabelem v LAN portu 5G boxu** (ne na jiné Wi‑Fi) a má internet (`curl https://matplace.com`).
2. **Token agenta „Dílna“**: Roman v `https://matplace.com/admin/farm/agents` → *Vytvořit a zobrazit token*
   (jméno „Dílna“), token se ukáže jen jednou. Vloží ho **sám** do `C:\farm-agent\config.yaml`, nebo ti ho dá a ty ho
   tam zapíšeš. **Token nikdy nevypisuj** do chatu, logu ani jiného souboru.
3. Heslo do rozhraní 5G boxu (`http://192.168.1.1`) – kvůli rezervacím IP; zadává ho Roman, ty nepotřebuješ.
4. Přihlášení **Claude** (claude.ai) a **admin matplace** v prohlížeči tohoto počítače.
5. Kolik tiskáren už má Rinkhals (instaluje je Roman podle `NAVOD-kobra-s1.md`, od 1 do 8) – agent spustíš
   klidně dřív, tiskárny bez Rinkhals jsou prostě offline.

## Postup

### 1. Windows 11 (aby počítač běžel pořád)

- Napájení: nikdy neusínat ani nehibernovat (`powercfg /change standby-timeout-ac 0`, `hibernate-timeout-ac 0`,
  `powercfg /h off`); displej se může vypínat. V BIOS/UEFI „po výpadku napájení zapnout“ (AC Power Recovery = On),
  pokud to deska umí – Roman nastaví při restartu.
- **Rychlé spuštění vypnout** (Ovládací panely → Možnosti napájení → Nastavení tlačítek → *Zapnout rychlé spuštění* odškrtnout),
  jinak se po „vypnutí“ služby nechovají předvídatelně.
- Windows Update: aktivní hodiny tak, aby restart nepřišel uprostřed tisku (např. 02–06 h); po restartu agent
  naběhne sám (úloha jako SYSTEM, bez přihlášení).
- Časové pásmo Europe/Prague, čas automaticky; název počítače např. `matplace-dilna`.
- Automatické přihlášení uživatele **není nutné** (agent jede jako SYSTEM); foto-box ale potřebuje otevřený
  prohlížeč, takže je pohodlné ho zapnout (`netplwiz` → odškrtnout „Uživatelé musí zadat jméno a heslo“).
- Prohlížeč: Chrome nebo Edge, jeden profil, přihlásit admina matplace a nechat přihlášeného.
- Antivir/Defender: nechat; **Řízený přístup ke složkám** (Controlled Folder Access) nezapínat – blokuje Python a Git.

### 2. Nástroje

```powershell
winget install -e --id Git.Git
winget install -e --id Python.Python.3.11 --scope machine     # "pro všechny uživatele" – nutné, agent poběží jako SYSTEM
winget install -e --id Microsoft.VisualStudioCode
winget install -e --id OpenJS.NodeJS.LTS
npm install -g @anthropic-ai/claude-code
```
Ověř: `py -3 --version` → 3.11.x a `(Get-Command python).Source` ukazuje do `C:\Program Files\Python311\`
(ne do `WindowsApps` – Python z Microsoft Storu je jen pro uživatele a služba SYSTEM ho nespustí; kdyby tam byl,
odinstalovat a nechat jen ten z python.org/wingetu). Claude Code: `claude` a přihlášení. VS Code otevři na `C:\farm-agent`.

### 3. Agent jako služba (SYSTEM, startuje s počítačem, restart po pádu)

```powershell
robocopy D:\matplace-dilna\agent C:\farm-agent /E
cd C:\farm-agent
copy config.yaml.template config.yaml        # doplnit token (bod 2 výše); zbytek sedí (8 tiskáren, 192.168.1.101–108)
```
Pak **PowerShell jako správce**: `powershell -ExecutionPolicy Bypass -File C:\farm-agent\install-farma.ps1`.
Skript založí `venv`, nainstaluje knihovny, vypne usínání, ověří tiskárny v síti (ty bez Rinkhals zatím
„NEODPOVÍDÁ“ – v pořádku), spustí `--check` (nic nespouští; musí vypsat, že server token přijal a které tiskárny
agentovi patří) a založí úlohu Plánovače **matplace-farm-agent**: spouští se při startu počítače **jako SYSTEM**
(bez přihlášení), při pádu restart každou minutu, bez časového limitu. Výpis: `C:\farm-agent\agent.log`.

Ověření: `Get-ScheduledTask matplace-farm-agent` → Running; v `/admin/farm/agents` má „Dílna“ čerstvý heartbeat;
tiskárny s Rinkhals jsou v `/admin/farm/printers` do minuty online (po tom, co jim Roman v adminu zapne „povolena“
a přiřadí agenta „Dílna“). **Restartuj počítač a ověř, že agent naběhl sám** – to je smysl celé úlohy.

Ovládání: `Stop-ScheduledTask matplace-farm-agent` / `Start-ScheduledTask matplace-farm-agent` (jako správce).
Nová verze agenta z domova: zkopírovat složku `farm_agent` a úlohu restartovat.

### 4. Tiskárny (Roman podle `NAVOD-kobra-s1.md`, ty kontroluješ)

Pro každou: `curl http://192.168.1.10N:7125/server/info` (JSON), snímek z kamery, v adminu online, na displeji
Rinkhals → Settings → Apps → Moonraker → *Auto leveling when starting prints* = ON. Zkušební tisk `quick` z
`/admin/farm/tuning` na první hotové tiskárně, až pak další.

### 5. Foto-box

Zapoj tři kamery, otevři `https://matplace.com/admin/farm/photobox`, povol kamery, u každého pohledu vyber
správnou kameru (prohlížeč si volbu pamatuje), *Vyfotit a uložit* u zkušební zakázky.

### 6. Vzdálená správa (doporučeno)

Tailscale (zdarma) na tomto i vývojovém počítači + vzdálená plocha, nebo Chrome Remote Desktop, aby Roman
nemusel kvůli každé drobnosti do dílny. **Žádné porty z internetu do sítě farmy** – Moonraker na tiskárnách nemá heslo.

## Pravidla

- Token agenta a hesla **nikdy nevypisuj** a neukládej jinam než do `C:\farm-agent\config.yaml`.
- Firmware tiskáren **neaktualizovat**; Rinkhals jen verze z flashky (podporuje firmware 2.7.0.9 a 2.7.2.7).
- Na serveru nic neměň; co chybí v administraci nebo v agentu, napiš přesně a Roman to zadá vývojové session.
- Nic z repa tu neupravuj a necommituj (repozitář tu ani být nemusí; agent je na flashce).
- Při každém kroku napiš, co jsi udělal a co vidíš v logu/adminu; když tiskárna neodpovídá, nejdřív síť
  (`ping`, `curl …:7125/server/info`, rezervace v boxu), pak až konfigurace.

## Hotovo, když

Úloha `matplace-farm-agent` běží jako SYSTEM a přežije restart počítače; „Dílna“ v `/admin/farm/agents` má
heartbeat; každá Kobra s Rinkhals je online pod svým klíčem; první zkušební tisk prošel do *Hotovo* se snímky;
foto-box fotí všemi třemi kamerami; domácí tiskárny zůstaly online u domácího agenta.
