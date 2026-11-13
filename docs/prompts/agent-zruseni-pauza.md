# Zadání pro session 4 (agent farmy): zrušení a pauza se musí ověřit na tiskárně

Stav k 11. 11. 2026 večer. Zadává Roman přes koordinátora. Větev si založ z `origin/main`, pracuj ve svém worktree
(`C:\matplace-sell-wt`), nic nenasazuj a nesahej na tiskárny; nasazení a nový zip agenta jdou přes koordinátora.

## Co se stalo (F26-000158, tiskárna U2 = Kobra S1 s Rinkhals, Moonraker)

Časy jsou místní (UTC+1), data z produkce a z konzole tiskárny.

| Čas | Událost |
|---|---|
| 20:29:27 | tisk začal (job 230, příkaz start 373 v pořádku) |
| 20:29:38 | Roman na webu zmáčkl pauzu (příkaz 374). Tiskárna byla ve startovním makru (nahřívání, vyrovnání) |
| 20:31:05 | příkaz 374 skončil jako failed: `/printer/print/pause: TimeoutError; the printer's state did not change in 60 s` |
| 20:31:32 | Roman zastavil tisk na displeji tiskárny: konzole `Instruction cancellation`, `action:paused`, `cancel_request:stopping` |
| 20:49:20 | Roman zakázku zrušil jako zákazník; příkaz cancel 375 odeslán 20:49:35 a hned zapsán jako done |
| 20:49:45 | konzole tiskárny: `action:cancel`, `cancel_request:stopping`; `print_stats.state` ale zůstal `paused` |
| 21:04:48 | koordinátor poslal `POST /printer/print/cancel` znovu: odpověď `ok`, stav dál `paused` |
| 21:05:21 | koordinátor poslal G-kód `SDCARD_RESET_FILE`: stav přešel na `standby`, soubor se uvolnil |

Výsledek pro Romana: zakázka na webu „zrušená“, tiskárna přitom 15 minut visela v pauze se souborem zrušené zakázky,
farma ji vedla jako pozastavenou a další tisk by na ni neposlala. Nikde to nebylo vidět.

## Příčiny v kódu

1. **Zrušení se neověřuje.** `agent/farm_agent/agent.py`, `_control()`: když volání ovladače projde bez výjimky,
   vrátí se hned `""`. Stav tiskárny se sleduje jen ve větvi, kdy volání selhalo. Kobra S1 pod Rinkhals na
   `print/cancel` odpoví `ok`, ale z pauzy vzniklé zastavením na displeji nevyjde.
2. **Moonraker ovladač nemá záložní krok.** `agent/farm_agent/drivers/moonraker.py`, `cancel()` posílá jen
   `/printer/print/cancel`. Ověřeno na U2: po neúspěšném cancel pomohl `SDCARD_RESET_FILE` (GoKlipper ho zná,
   komentář u `reset()` to měl za nejisté).
3. **Pauza během startovního makra tiše selže.** Tiskárna ji v té chvíli nepřijme; agent čeká 60 s a pak vrátí
   failed. Zákazník minutu nevidí nic a pak jen technickou hlášku.

## Co udělat

**A. Zrušení potvrzené stavem (agent).**
- `_control("cancel")` po úspěšném volání také sleduje stav (stejná podmínka `wanted` jako dnes) po
  `CONFIRM_WAIT_SECONDS`.
- Když tiskárna zůstane v `PRINTING`/`PAUSED`: zavolat nový krok ovladače (Moonraker: `SDCARD_RESET_FILE` přes
  `/printer/gcode/script`), znovu ověřit stav a do výsledku příkazu napsat, co pomohlo.
- Když nepomůže ani to: příkaz skončí failed se srozumitelným důvodem a server pošle provozovateli upozornění
  (existující `alertAdmin`, druh podle `docs/AL.md §8`); tiskárna nesmí zůstat tiše zablokovaná.
- Ostatní ovladače (`anycubic_lan`, `prusalink`, `mock`): ověření stavu po cancel stejně; záložní krok jen tam, kde
  existuje bezpečný. Do `mock` přidej přepínač „cancel odpoví ok, stav zůstane paused“ pro testy.

**B. Pauza a zrušení během přípravy tisku.**
- Zjisti na U2/U1 (jen čtením stavu a z logu agenta), jak se startovní fáze pozná (`print_duration == 0`,
  `filament_used == 0`, teploty pod cílem) a navrhni chování: pauzu v této fázi buď odmítnout hned s jasnou zprávou
  („Tiskárna se připravuje, pauza půjde za chvíli“), nebo ji odložit a provést po startu. Vyber jednodušší a
  spolehlivější variantu a zdůvodni ji v dokumentaci.
- Zrušení v této fázi musí projít (zákazník si to rozmyslel hned po zaplacení): ověř, že po něm tiskárna skončí
  v klidu, topení vypnuté.

**C. Co vidí zákazník a provozovatel (server).**
- Zakázka zrušená zákazníkem, u které tiskárna zrušení nepotvrdila, se v administraci farmy ukáže jako
  „čeká na potvrzení tiskárny“ / „tiskárna nezrušila“, ne jako hotová věc.
- Text chyby u nepovedené pauzy pro zákazníka česky a anglicky bez technické hlášky (lang přes `lang/src/*.json`
  a `scripts/lang_add.py`, viz gotchas v koordinátorově dokumentu).

## Testy a ověření

- Jednotkové testy agenta (`agent/tests`): cancel ok + stav paused → záložní krok → potvrzeno; záložní krok
  nepomůže → failed s důvodem; pauza ve startovní fázi podle zvolené varianty.
- PHP testy serveru pro stav „nepotvrzeno“ a upozornění provozovateli.
- Na skutečné tiskárně nic nezkoušej sám. Napiš Romanovi krátký postup zkoušky na U2 (spustit malý tisk, zastavit
  na displeji, zrušit na webu) a co má po opravě vidět.

## Výstup

- Větev `fix/agent-cancel-confirm`, commit(y), push na origin; nic neslučuj.
- Nová verze agenta (další číslo po 0.2.12) a zip pro PC obou farem, postup instalace podle `docs/AM.md §5`.
- Doplněná kapitola v `docs/AM.md` s tím, co se změnilo a co zůstává neověřené na skutečných tiskárnách.
- Závěrečná zpráva koordinátorovi: co je hotové, co čeká na Romanovu zkoušku, co se má nasadit na server.

## Související drobnost (nezahrnuj, jen o ní věz)

Při změně voleb zákazníkem se staré řezání nedořezává zbytečně? Dnes u zakázky 341 čekal nový běh 173 s ve frontě
za starým (`timings.queue_s`). To je práce pro session 2, sem nepatří.
