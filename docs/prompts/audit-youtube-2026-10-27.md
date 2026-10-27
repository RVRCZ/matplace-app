# Audit YouTube kanálu Matplace – 3D (@Matplace3D) — 27. 10. 2026

Roman 27. 10. k tomu dodává: fotky z fotoboxu doplnit vhodným pozadím automaticky nástrojem, který už máme v portfoliu
(odstranění pozadí rembg ze studia session 4, větev `feature/tools-sell`). Rozdělení řídící: `docs/prompts/youtube-kanal.md`
(session 2: šablona Shorts, tituly, popisy, UTM, playlisty, komentáře, přejmenování přes API), `docs/prompts/fotobox-pozadi.md`
(nová session N3: fotky výtisků bez pozadí a na pozadí, pro konec Shorts, web a blog). Stav `docs/TODO.md` #48. Níže původní znění.

---

## Stav kanálu (snímek)
- 5 odběratelů, 33 videí, všechna za ~1–2 měsíce (nejstarší „1mo ago“).
- 25 Shorts + 8 videí v záložce Videa (z toho 7 jsou ve skutečnosti stejné časosběry v horizontální/ delší verzi 18–38 s, jen 1 je skutečné „dlouhé“ video: *Model z Printables vytištěný na matplace – od stažení po výtisk*, 1:53, 107 zhl.).
- Popis kanálu je anglicky, videa a tituly česky. Odkaz na web v popisu videa má UTM (dobře).
- Popis videa: materiál, tiskárna (Anycubic Kobra S1), doba tisku, CTA na matplace.com, hudba z Audio Library. Dobrá šablona.
- Hashtagy: #3Dprinting #timelapse #Shorts — obecné, žádný český.
- Žádné playlisty, žádné připnuté/featured video, žádné komentáře, žádná „Komunita“.

## Výkon Shorts (zhlédnutí)
| Video | Zhl. |
|---|---|
| Busta nebo figurka z fotky – 1 h 33 min | 1 100 |
| Časosběr – PLA Silk měděná (nejstarší) | 1 100 |
| Greek godess statue – 3 h 28 min | 941 |
| Medaile s řetězem – 1 h 18 min | 934 |
| TILT PHONE STAND V3 – 6 h 28 min | 773 |
| Stojánek na telefon – 2 h 54 min | 709 |
| Mystical Chine Dragon – 10 h 41 min | 563 |
| Busta nebo figurka – 7 h 33 min | 538 |
| Phone Stand Base Only – 3 h 0 min | 484 |
| QR cedulka a stojánek – 5 h 22 min | 408 |
| Spooked ghost – 7 h 45 min | 391 |
| Cat – 6 h 33 min | 344 |
| Váza a obal na květináč – 5 h 14 min | 265 |
| ostatních 12 Shorts | 20–185 |

Celkem cca 9 500 zhlédnutí za ~2 měsíce, medián ~180. 5 odběratelů z 9 500 zhlédnutí = konverze ~0,05 % (běžné u Shorts bývá 0,5–1 %). Zhlédnutí tedy přicházejí z náhodného Shorts feedu, ale nic diváka nedrží ani nepřevede.

## Co funguje
- Pravidelnost: 33 videí za 2 měsíce, několik týdně.
- Konzistentní titulní šablona „Název – doba tisku za X s“ a vodoznak loga.
- Popisek s parametry a CTA s UTM — ideální základ, stačí rozšířit.
- Figurky/busty, sochy, medaile a stojánky na telefon dostávají 3–5× víc než vázy a cedulky → algoritmus říká, co točit.
- Časosběry mají čistý obraz, svítí na nich logo.

## Nálezy a doporučení (podle priority)

### A. Titulky a náhledy (největší páka, nulové náklady)
1. **Tituly jsou technické, ne klikací.** „Phone Stand Base Only – 3 h 0 min tisku za 35 s“ nikomu nic neřekne. Čtyři tituly jsou surové názvy souborů z Printables („TILT+PHONE+STAND+V3 0+(Wider)“, „Spooked+ghost“, „Cat“, „Greek godess statue“ s překlepem). → Formát *[výsledek/emoce] + [háček] + čas*, česky: „Busta z jedné fotky za 1,5 hodiny tisku 😮“, „Co se stane, když necháte tiskárnu 11 hodin v kuse“. Doba tisku do titulu patří, „za 29 s“ ne (to divák vidí).
2. **Duplicitní tituly** („Busta nebo figurka z fotky“ 2×, „QR cedulka a stojánek“ 2×, „Váza a obal“ 2×, „PLA Silk měděná“ 4×) → YouTube je nerozliší, divák také. Každé video unikátní název s tím, CO se tiskne.
3. **Shorts nemají vlastní náhled** – u Shorts to nevadí, ale 8 videí v záložce Videa ano: nahrát náhled s hotovým výtiskem a 2–3 slovy.
4. Stejné video nahrané 2× (Shorts + horizontální verze) dělí zhlédnutí a vypadá jako spam. Nechat jen Shorts; horizontální verzi používat pouze pro delší formát.

### B. Co točit (obsahová strategie)
5. **Shorts = časosběr tisku bez konce.** Divák nevidí finální výrobek v ruce, v měřítku, u zákazníka. Přidat poslední 3 s: hotový díl v ruce / na stole / na telefonu + text „Cena: 99 Kč“. Právě ta cena je to, co nikdo jiný v CZ 3D-tisk feedu neukazuje.
6. **Hook v první sekundě**: textová plocha „Z téhle fotky → busta“ (ukázat fotku, pak tisk), „Zákazník poslal rozměr šuplíku → …“. Teď prvních 1–2 s je prázdná podložka.
7. **Formáty, které kanálu chybí a sedí k produktu:**
   - *Fotka → model → výtisk* (30 s): nejsilnější USP matplace, zatím jen v 1:53 videu.
   - *Kolik to stojí?* seriál: 5 věcí, které lidé zadávají, s cenou z kalkulace.
   - *Před/po*: rozbitý díl z domácnosti → náhrada z nástroje „Víčko, zátka, krytka“.
   - *Z Printables na stůl za 3 kroky* (už existuje, 107 zhl. – udělat Shorts verzi).
   - Dlouhé 3–6 min video 1× měsíčně: „Jak funguje naše farma“, „Proč stojí 3D tisk tolik“ – to je obsah, který sbírá odběratele a objevuje se ve vyhledávání.
8. **Sezóna**: Vánoce jsou za 8 týdnů. Perníčky, ozdoby, vykrajovátka, jmenovky – nástroje už existují, točit teď, aby videa měla čas naběhnout.
9. Sledovat, co jede: figurky z fotky, sochy, medaile, stojánky na telefon. Vázy/cedulky/QR 3–5× hůř → dělat jich méně, nebo jim dát příběh (restaurace, Wi-Fi hostům).

### C. Kanál jako celek
10. **Popis kanálu anglicky, obsah česky** – rozhodnout jazyk. Pro CZ trh česky + klíčová slova („3D tisk na zakázku“, „cena 3D tisku“, „3D tisk Plzeň“); angličtinu nechat na druhý řádek.
11. **Chybí odkazy** v záložce O kanálu: web, Instagram/TikTok/Facebook, e-mail pro firmy. Chybí banner s CTA („Nahrajte model, uvidíte cenu“) a upoutávka kanálu (trailer pro neodběratele – použít to 1:53 video, zkrácené).
12. **Playlisty**: „Figurky z fotky“, „Dárky se jménem“, „Pro domácnost“, „Jak to funguje“ – zrcadlí kategorie nástrojů na webu a prodlouží dobu sledování.
13. **Připnutý komentář** pod každým videem s odkazem na konkrétní nástroj (ne jen home) + cena. Popis má jen home URL.
14. **Hashtagy**: přidat české #3dtisk #3dtiskarna #tiskárna #dárek, u Shorts max 3–5.
15. **Komunita / komentáře**: 0 komentářů za 9 500 zhlédnutí – zakončit Shorts otázkou („Co bych měl vytisknout příště?“), na komentáře odpovídat do hodiny.
16. **Hudba**: Audio Library je bezpečná, ale u Shorts funguje trending zvuk z YouTube knihovny lépe pro dosah.

### D. Propojení s webem
17. Na webu je „33 videí na YouTube“ a 1 vložené video – vložit do každé stránky nástroje jeho Shorts (jsou k tomu už natočené: busta, QR, váza, stojánek, medaile, víčko).
18. Do každé sdílené kalkulace (/c/…) po dokončení tisku poslat zákazníkovi odkaz na jeho video – zákazník ho sdílí sám (to je nejlevnější distribuce) a zároveň tím máte souhlas se zveřejněním písemně.
19. UTM: `utm_source=youtube` je, přidat `utm_content=<videoId>` ať jde v GA vidět, které video prodává.

### E. Měření (YouTube Studio – nemám přístup, doporučuji zkontrolovat)
- U Shorts: „Swiped away“ vs. „Viewed“ – pokud < 70 % viewed, problém je první sekunda (bod 6).
- Průměrná doba zhlédnutí vs. délka: 25–35 s Shorts; pokud divák odpadá v 10 s, zkrátit na 15 s s koncem na hotovém výrobku.
- Zdroj návštěvnosti: kolik je Shorts feed vs. vyhledávání (teď nejspíš 95 % feed).
- Kliknutí na odkaz v popisu / odběratelé z videa – kolik Shorts reálně vede na web.

## Doporučené pořadí
1. Přejmenovat 33 videí (2 h) · 2. Popis kanálu česky + odkazy + banner + trailer (1 h) · 3. 4 playlisty (20 min) · 4. Nová šablona Shorts: hook 1 s + hotový díl + cena na konci (od příštího videa) · 5. Vánoční série 6–8 Shorts do poloviny listopadu · 6. Vložit Shorts na stránky nástrojů · 7. 1 dlouhé video měsíčně.
