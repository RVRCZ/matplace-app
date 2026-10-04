# Zadání: počítač v dílně – farma matplace (agent, foto-box, Claude Code)

Datum: 4. 10. 2026. Tohle je první prompt pro Claude Code na **počítači v dílně**, kam se stěhuje dosavadní router
farmy (bez tiskáren – ty tři Kobry zůstávají doma na novém routeru se stejnou sítí a obsluhuje je dál agent na
vývojovém počítači). Dílna bude **druhé stanoviště farmy**: vlastní agent „Dílna“ pro tiskárny, které tam
přibudou, a foto-box. Vývojový počítač (repozitáře, nasazování na server, relay pro import z Printables) zůstává doma;
tenhle počítač jen **provozuje farmu**. Kód se tu nevyvíjí – změny agenta vznikají na vývojovém počítači a sem se jen
zkopírují / stáhnou.

## Co je co

- **matplace.com** – Laravel aplikace na serveru Hetzner (178.104.162.164). Nic na serveru neměň a nepřipojuj se
  k němu; všechno, co dílna potřebuje, jde přes HTTPS a administraci `https://matplace.com/admin/farm`.
- **Farm agent** (`agent/` v repu `RVRCZ/matplace-app`, Python 3.11) – malá služba, která běží v síti u tiskáren,
  polluje matplace (jen odchozí HTTPS), stahuje G‑code a mluví s tiskárnami lokálně přes Moonraker (Rinkhals).
  Tisk nikdy nezačne sám – jen po potvrzení „podložka je volná“ v administraci. Dokumentace: `agent/README.md`.
- **Tiskárny doma** (zůstávají, agent „Agent1 S1“ na vývojovém počítači, síť 192.168.1.x): `kobra-s1-01`
  (.101), `kobra-3-max-01` (.102), `kobra-s1-02` (.103, stock firmware, offline). **Těch se tady nedotýkej** – v adminu
  je nech u původního agenta.
- **Tiskárny v dílně** přibudou: každá dostane v `/admin/farm/printers` vlastní klíč, režim *Automaticky přes agenta*
  a agenta „Dílna“; v routeru dílny pevnou IP podle MAC; Anycubic s Rinkhals = ovladač `moonraker`
  (`http://IP:7125`, kamera `http://IP/webcam/?action=snapshot`), Prusa = ovladač `prusalink` (až vznikne,
  zadání `docs/prompts/prusa.md`). Zkouška, že počítač tiskárnu vidí: `curl http://IP:7125/server/info` vrátí JSON.
  Dokud v dílně žádná tiskárna není, agent se spustí s jedním blokem `driver: mock` (viz README „Zkouška bez
  tiskárny“), aby bylo vidět, že spojení se serverem funguje.
- **Foto-box** – tři USB kamery (shora, zleva, zprava) připojené k tomuto počítači; stránka
  `https://matplace.com/admin/farm/photobox` je obsluhuje přímo z prohlížeče (kamery si prohlížeč pamatuje).
  Fotky hotových kusů končí na konci videí na YouTube/Facebooku.
- **Časosběr** – snímky bere agent z kamer tiskáren, nic k nastavení.
- **Relay pro import z Printables** (`C:\matplace-relay` na vývojovém počítači) – zůstává doma, s dílnou nesouvisí.

## Než začneš, vyžádej si od Romana (jedna zpráva, všechno najednou)

1. **Windows** na tomhle počítači (10/11, 64bit) a že má účet správce; počítač může běžet nepřetržitě (bude se
   mu vypínat usínání).
2. **Internet pro router farmy v dílně**: kabel z dílenského routeru do WAN portu routeru farmy (nejlepší), nebo
   router farmy v režimu klient/repeater na dílenské Wi‑Fi. Tento počítač připoj **kabelem do routeru farmy**
   (LAN port), ne na dílenskou Wi‑Fi – jinak tiskárny neuvidí. Zkontroluj, že router farmy nemá zapnutou
   izolaci klientů a tiskárny nejsou v síti pro hosty.
3. **Nový token agenta**: Roman ho vytvoří v `https://matplace.com/admin/farm/agents` → *Vytvořit a zobrazit token*
   (jméno „Dílna“). Tiskárny dílny v `/admin/farm/printers` dostanou tohoto agenta; domácí Kobry zůstávají u
   „Agent1 S1“. Token se ukáže jen jednou; Roman ti ho **vloží do `C:\farm-agent\config.yaml` sám**, nebo ti ho
   dá a ty ho tam zapíšeš. **Token nikdy nevypisuj** do chatu, logu ani jiného souboru.
4. **Zdroj kódu**: na flashce (D:\matplace-dilna\agent – složka agenta připravená na vývojovém počítači), nebo
   klon repozitáře `git@github.com:RVRCZ/matplace-app.git` (soukromý: Roman musí na GitHub přidat SSH klíč
   tohoto počítače jako *deploy key*, jen čtení). Pro provoz stačí flashka.
5. **Přihlášení**: účet Claude (claude.ai) pro Claude Code a admin účet matplace v prohlížeči.
6. Které **tiskárny v dílně** budou (model, IP, Rinkhals/PrusaLink), a že jsou zapnuté (na displeji Kobry v LAN
   módu svítí IP). Když zatím žádná, jede se s `mock`.

## Postup

### 1. Windows

- Napájení: nikdy neusínat, nehibernovat, displej vypínat klidně (`powercfg /change standby-timeout-ac 0`,
  `hibernate-timeout-ac 0`); v BIOSu/UEFI zapnout „po výpadku proudu zapnout“, pokud to deska umí.
- Windows Update: aktivní hodiny tak, aby restart nepřišel uprostřed tisku (např. 2–6 h ráno). Agent se po
  restartu spustí sám (úloha v Plánovači, bez přihlášení), ale běžící tisk sleduje jen když počítač běží.
- Časové pásmo Europe/Prague, správný čas (jinak nesedí HTTPS a logy).
- Prohlížeč: Chrome nebo Edge, jeden profil, přihlásit admina na matplace.com a nechat přihlášeného.

### 2. Nástroje

`winget install Git.Git Python.Python.3.11 Microsoft.VisualStudioCode OpenJS.NodeJS.LTS` (u Pythonu
zaškrtnout/ověřit „Add to PATH“; `py -3 --version` → 3.11). Claude Code: `npm install -g @anthropic-ai/claude-code`,
pak `claude` a přihlášení. VS Code otevři na složce `C:\farm-agent` (a případně na klonu repa, jen pro čtení).
Na vývojovém počítači běží Python 3.11.9, Node 22, Claude Code 2.1.x – drž se stejných řad.

### 3. Agent

```powershell
# z flashky
robocopy D:\matplace-dilna\agent C:\farm-agent /E
cd C:\farm-agent
copy config.yaml.template config.yaml       # pak doplnit token (viz bod 3 výše)
```

`config.yaml`: `server: https://matplace.com` (ne beta!), `token: ...`, `work_dir: C:/farm-agent/work`, pak
**jen tiskárny dílny** – bloky domácích Kober ze šablony **smaž** (ty obsluhuje domácí agent; stejný klíč u dvou
agentů dělá zmatek). Bez tiskárny nech jeden blok `driver: mock`.
Pak **jako správce**: `powershell -ExecutionPolicy Bypass -File C:\farm-agent\install-farma.ps1` – založí venv,
nainstaluje knihovny, vypne usínání, ověří tiskárny v síti, spustí `--check` (nic nespouští; vypíše stav tiskáren
a zda server token přijal) a založí úlohu Plánovače **matplace-farm-agent** (start s počítačem jako SYSTEM, restart
po pádu). Výpis: `C:\farm-agent\agent.log`. Ověření: v `/admin/farm/agents` má „Dílna“ čerstvý heartbeat a tiskárny
dílny jsou v `/admin/farm/printers` do minuty **online**. Domácí agent běží dál, nic na něm neměň.

### 4. Foto-box

Zapoj tři kamery, otevři `https://matplace.com/admin/farm/photobox`, povol kamery, u každého pohledu vyber
správnou kameru (prohlížeč si volbu pamatuje – používej pořád stejný profil), *Vyfotit a uložit* u zkušební
zakázky. Fotka označená „do videa“ je ta, kterou skončí video zakázky.

### 5. Zkouška provozu

Malý tisk přes celou cestu: v adminu „Podložka je volná“ u zakázky ve frontě → agent do pár vteřin převezme,
v adminu běží průběh, teploty a snímek; po dokončení je zakázka *Hotovo*. Pak zkus pauzu/pokračování
z administrace a vypnutí agenta na 3 minuty (tiskárna „offline“, Romanovi přijde e‑mail; po zapnutí se
sám přihlásí).

### 6. Vzdálená správa (doporučeno, volitelné)

Aby Roman nemusel do dílny kvůli každé drobnosti: Tailscale (zdarma) na tomhle i vývojovém počítači a RDP,
nebo alespoň Chrome Remote Desktop. Žádné porty z internetu do sítě farmy neotvírat – Moonraker na tiskárnách
nemá heslo.

## Pravidla

- Token agenta a hesla **nikdy nevypisuj** a neukládej jinam než do `C:\farm-agent\config.yaml`.
- Firmware tiskáren **neaktualizovat** (Rinkhals podporuje jen konkrétní verze; v aplikaci Anycubic i na displeji
  vypnout automatické aktualizace). Kobra S1 #2 dostane Rinkhals až po domluvě s Romanem.
- Na serveru nic neměň; když něco chybí v administraci nebo v agentu, napiš, co přesně, a Roman to zadá
  vývojové session (`docs/prompts/` v repu).
- Nic z repa tu neupravuj a necommituj; po změně agenta na vývojovém počítači sem jen zkopíruj novou složku
  `farm_agent` a restartuj úlohu (`Restart` = `Stop-ScheduledTask` + `Start-ScheduledTask matplace-farm-agent`).
- Při každém kroku napiš, co jsi udělal a co vidíš v logu/adminu; když tiskárna neodpovídá, nejdřív síť
  (`ping`, `curl …:7125/server/info`), pak až konfigurace.

## Hotovo, když

`/admin/farm/agents`: „Dílna“ online; tiskárny dílny (nebo mock) online a zkušební tisk prošel do *Hotovo* se
snímky; domácí Kobry dál online u „Agent1 S1“; foto-box fotí všemi třemi kamerami; úloha Plánovače přežije
restart počítače (vyzkoušet restartem).
