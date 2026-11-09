# Shorts: názvy, výběr, formát, UTM a Analytics (TODO #81)

Pro session 2, 9. 11. 2026. Roman: „první videa měla stovky zhlédnutí a teď nic“. Čísla z `farm_videos` (YouTube API,
9. 11. večer): 44 videí, 10 115 zhlédnutí od 26. 9. Shorts 28. 9. až 22. 10.: 23 videí, průměr 412, medián 344 (busta
z fotky 1144, stojánek na telefon 1107, socha bohyně 942, medaile 934). Shorts 26. 10. až 7. 11.: 8 videí, průměr 31,
medián 31. Ostatní druhy (wide 5–186, howto 109/21, monthly 15, price 19, weekly 0) jsou vedlejší.

Co se změnilo kolem 26. 10. (tři věci najednou):
1. **Názvy ze jmen souborů.** Hity měly české popisné názvy (nástroj nebo model z katalogu: „Stojánek na telefon
   s rybinovým vzorem v měděném silku“). Od 28. 10. jdou ven syrové názvy nahraných souborů: „Snail planter2“ (40),
   „Turtlescutieone2“ (8), „MECCHA+CHAMELEON“ (2), „Hogwartstrain“ (16), „Phone Stand“ (55, 33), s příponou „za 17 s“.
   Zdroj: `FarmVideos.php` ~848 `pathinfo($file->original_name, PATHINFO_FILENAME)` jako název, když zakázka nemá
   název z nástroje ani z katalogu.
2. **Druh obsahu a opakování.** Hity = dárkové věci z nástrojů a modely z katalogu; poslední tři týdny náhodné
   zkušební tisky a opakované náměty (druhý drak 30, druhá vázа). YouTube opakovaný námět dál nepouští.
3. **Nepravidelnost.** 10. až 22. 10. denně, pak mezery 4, 2, 3, 3 dny. Po sérii slabých videí se testovací vzorek
   kanálu zmenšil, propadly i ty s českým názvem (cedulka 62).

Worktree session 2 `C:\matplace-gcode-wt` (čistý, větve #72/#77 sloučené), nová větev `feature/shorts-titles-format`
z `origin/main`. Stejná pravidla jako vždy (nic nenasazovat, žádný `migrate*` ve worktree, git add jen jménem, žádný
stash, `Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>`, texty přes `lang/src/<soubor>.json` + `lang_add.py`,
JSON přes `json.load` s kontrolou duplicit, PHPUnit po jedné sadě, pint, build). YouTube API v testech nevolat
(`Http::fake`); klíče a token neměnit; skutečné přejmenování na kanále dělá řídící na serveru po Romanově souhlasu.

## Co udělat

### A. Pravidlo pro název (žádný syrový název souboru ven)

- Název Shortu vzniká v tomto pořadí: název z nástroje (šablona nástroje + materiál/barva, jak dnes), název modelu
  z katalogu, **jinak český popisný název od modelu s viděním** (`ClaudeAssistant`, druh `video_title`, model podle
  `config/ai.php`, vstup: snímek hotového výtisku z časosběru nebo fotka výtisku + jméno souboru + materiál a barva
  cívky + doba tisku; výstup JSON: `title_cs` do 70 znaků, `title_en`, `description_cs` dvě věty, `subject` jedno slovo
  pro kontrolu opakování, `confidence`; hlídej limit 16 polí s typem null/union). Když model nedá jistotu ≥ 0.6, video
  zůstane `pending` s poznámkou „bez názvu“ a čeká na Romana v `/admin/youtube`, nezveřejní se.
- Zrušit příponu „za 17 s“ v názvu (patří nanejvýš do popisu). Formát názvu: „Co to je, pro koho nebo z čeho – doba
  tisku“ bez interních čísel; doba tisku jen zaokrouhlená („4,5 hodiny tisku“).
- Jména souborů nikdy v názvu ani v popisu.

### B. Výběr a pravidelnost

- Fronta ke zveřejnění: přednostně výstupy nástrojů a modely z katalogu; zkušební tisky Romana (`isTest()`) ven vůbec
  ne, pokud je Roman v administraci výslovně nepustí.
- Opakování námětu: stejný `subject` (nebo stejný nástroj + stejný model) do 30 dnů od posledního zveřejnění se
  odloží na konec fronty; v `/admin/youtube` to u videa napsat.
- Pravidelnost: jeden Short denně ve stejný čas (nastavení farmy `shorts_publish_hour`, výchozí 18:00 Europe/Prague),
  přes YouTube `publishAt` (stav `scheduled` už existuje). Když ve frontě nic není, nic se nevynucuje; když je víc,
  rozloží se po dnech. Weekly a monthly videa drží své termíny z `routes/console.php`.

### C. Formát Shortu (řezač `TimelapseCutter` / `SeriesCutter`)

- **První vteřina = hotová věc**: do střihu na začátek vložit 1,0 s posledního snímku (hotový výtisk) před časosběr,
  u nástrojů z fotky vedle ní předlohu (fotka → výtisk), pak časosběr, na konci opět hotová věc tak, aby smyčka
  navazovala (poslední snímek = první).
- **Text na obraze** z názvu videa (první řádek) a ceny (druhý řádek, když je `video_prices` zapnuté), velikost
  a umístění jako u cenového Shortu (`SeriesCutter::priceShort` `$line`), bez vnitřních čísel.
- **Hudba** vždy (knihovna skladeb bez práv, jak ji používá cenový Short), tiché Shorts ven nejdou.
- Délka 8 až 15 s (časosběr zrychlit podle délky tisku), svisle 1080×1920.
- Popis: první řádek = název, pak dvě věty, pak výzva s odkazem **s UTM** (`?utm_source=youtube&utm_medium=shorts&
  utm_campaign=<kind>&utm_content=<youtube_id|order>`) na rozcestník (`/`) u dárkových věcí a na stránku nástroje
  u výstupů nástrojů; `#Shorts` a tři až pět relevantních hashtagů česky. UTM musí projít do auditu (zdroj návštěvy)
  a do trychtýře `/admin/stats` jako řádek „z YouTube“ → odesláno → „Vytvořit“ → zaplaceno.

### D. Analytics

- `YouTubeClient::SCOPE` rozšířit o `https://www.googleapis.com/auth/yt-analytics.readonly`; připojení kanálu
  v `/admin/youtube` ukáže „Znovu připojit s rozsahem Analytics“ (nový souhlas, token se přepíše; nic nemazat, viz
  poučení o sdíleném grantu v docs). Nový příkaz `youtube:analytics` (denně po `youtube:stats`): pro každé video
  posledních 28 dní `views`, `averageViewPercentage`, `averageViewDuration`, u Shorts i poměr zhlédnuto/přeskočeno,
  když ho API dá (`engagedViews`), a zdroje návštěv (`insightTrafficSourceType`). Uložit do `farm_videos`
  (nové sloupce nebo json `analytics`), ukázat v `/admin/youtube` vedle zhlédnutí a v tabulce `youtube:titles`.

### E. Přejmenování zpětně

- Řídící připravil návrhy názvů pro 10 posledních videí (níže); Roman je schválí nebo upraví. Session udělá
  `docs/data/youtube-titles-2026-11.csv` ve formátu `youtube:titles` s těmi názvy (a popisy bez jmen souborů),
  **ne** `--apply`; `youtube:retitle … --apply` spustí řídící na serveru po souhlasu. Naplánované video „Ball3“
  (`i6kDnHotrQI`, stav scheduled) musí dostat název dřív, než se zveřejní; když to nestihneme, posuň publishAt.

| youtube_id | dnes | návrh |
|---|---|---|
| cmzE-TdkGbY | Čínský drak v modro-červeném silku – 10 h 59 min | Čínský drak ve dvou barvách silku – 11 hodin tisku v jednom kuse |
| _rVkRxsIIes | Snail planter2 – 4 h 27 min tisku za 17 s | Květináč ve tvaru šneka – 4,5 hodiny tisku |
| Z4SRiEu4dRI | Phone Stand – 1 h 27 min tisku za 17 s | Stojánek na telefon vytištěný za hodinu a půl |
| oJMTGJEU3ps | Turtlescutieone2 – 10 h 4 min tisku | Želví rodinka z 3D tiskárny – 10 hodin tisku |
| sxBdxwG3JPI | Kolik stojí 3D tisk? 5 věcí s cenou | beze změny |
| jEOWBQYorAA | MECCHA+CHAMELEON – 3 h 36 min tisku | Chameleon z 3D tiskárny – 3,5 hodiny tisku |
| u-yf-DWTQxk | Hogwartstrain – 2 h 30 min tisku | Kouzelnický parní vlak z 3D tiskárny – 2,5 hodiny tisku |
| Kgh0p0G2AUk | Phone Stand – 2 h 26 min tisku | Stojánek na telefon na míru – 2,5 hodiny tisku |
| awzB8VvLslQ | Cedulka se jménem – 2 h 43 min tisku | Cedulka se jménem na dveře – 3D tisk za necelé 3 hodiny |
| i6kDnHotrQI | Ball3 – 17 h 27 min tisku (naplánováno) | Dekorační koule z 3D tiskárny – 17 hodin tisku (Roman upřesní, co to je) |

### F. Testy a zpráva

- Testy: název z nástroje / katalogu / modelu (fake) / bez jistoty → pending; zákaz jména souboru; opakování námětu
  odloží; plánování na `shorts_publish_hour`; střih s první vteřinou hotové věci (kontrola pořadí vstupů ffmpeg,
  ne skutečný render) a s hudbou; UTM v popisu a jeho zachycení v auditu; `youtube:analytics` s `Http::fake`;
  CSV pro retitle. Stávající YouTube testy zelené (`YouTubeSeriesTest` je známý jako občas náhodný).
- Zpráva řídící: větev a SHA, co je hotové, CSV s názvy, co má rozhodnout Roman (hodina zveřejnění, zda zkušební
  tisky ven vůbec, názvy z tabulky), a jak znovu připojit kanál s Analytics. Dokumentace do `docs/AL.md` §13 (nová
  podsekce).
