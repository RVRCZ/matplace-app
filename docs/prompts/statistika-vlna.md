# Zadání pro session 3: vlna „lidí“, kteří nejsou lidé (23. až 24. 10. 2026)

Od řídící session (24. 10. 2026, Roman se ptal na návštěvnost). Přehled (`App\Domain\Stats\Overview::compute`) ukazuje:

| den | lidé | roboti |
|---|---|---|
| 17. 10. | 16 | 11 315 |
| 18. 10. | 69 | 24 396 |
| 19. 10. | 19 | 18 986 |
| 20. 10. | 38 | 24 941 |
| 21. 10. | 16 | 25 450 |
| 22. 10. | 33 | 20 195 |
| 23. 10. | **323** | 23 228 |
| 24. 10. (do 09:45 UTC) | **177** | 9 188 |

Posledních 24 h: 495 „lidí“, z toho 495 `direct`, 492 `en`, 3 `cs`; stránky: `/model` 68× (68 lidí), `/login` 29× (29 lidí), pak jednotlivé `/model/<slug>` po jedné návštěvě na „člověka“ (katalog, abecedně působící výběr: 2‑in‑1‑bambulab…, 3d‑scanned‑buddha…, 4‑tealight…, 7inch‑screen…, 9mm‑bullet…), trychtýř: 1 kalkulace, 1 nahrání, 0 zakázek. Kampaně prázdné. Před týdnem 16–69 lidí denně s trychtýřem ~10 % kalkulací.

To je jedno „auto“ (headless prohlížeč, který projde JS a tedy potvrzení člověka), ne návštěvníci. Číslo „lidé“ je tím pro Romana nepoužitelné.

## Úkol

1. **Zjistit v datech**, čím se vlna liší od lidí: stejný/úzký rozsah IP nebo ASN, stejný user agent (verze Chrome, headless, chybějící `Accept-Language` pro `en`?), rozlišení/okno, čas mezi otevřením stránky a potvrzením, žádná druhá stránka v sezení, žádný pohyb/scroll, pořadí slugů podle abecedy, cadence (1 za N sekund). Výsledek do `docs/O.md` (nová část „Vlny“), s čísly.
2. **Pravidlo**, které takovou vlnu zařadí mezi roboty, aniž by sebralo skutečné lidi: např. sezení s jedinou stránkou katalogu (`/model/...`) bez druhé akce + shodný otisk (UA + rozlišení + jazyk) sdílený s ≥ N dalšími sezeními v hodině + žádný referer. Práh zdůvodnit na datech z 17. až 22. 10. (lidé tam zůstat musí: ověř, že denní počty lidí v těch dnech se nezmění o víc než ~1 člověka).
3. **Zpětně přepočítat** 23. a 24. 10. (příkaz nebo přepočet při čtení, podle toho, jak se dnes „lidé“ počítají), aby týdenní tabulka a mail s přehledem ukazovaly skutečné lidi; vlnu ukázat zvlášť jedním číslem („vlny: 480“) jako u skenerů (TODO #36).
4. `/login` 29× od „lidí“ bez dalšího kroku: ověřit, že to není hádání hesel (`Auth` log, neúspěšné pokusy, IP); pokud ano, limit na přihlášení podle IP (Laravel throttle) a zápis do O.md.

Větev **`feature/stats-wave`** z aktuálního `main` (cf222c9+), `docs/O.md`, testy pro pravidlo (syntetická vlna + lidé z fixture), pint, push + zpráva řídící session s čísly před → po. Netknout: farma, agent, nástroje. Texty jen přes `lang/src/<soubor>.json` + `scripts/lang_add.py`, JSON po úpravě validovat.
