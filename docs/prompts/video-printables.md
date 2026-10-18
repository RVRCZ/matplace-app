# Instruktážní video: model z Printables → matplace → výtisk z farmy (pro Romana a session, která video sestaví)

Cíl: 2 až 3 minuty. Divák vidí, že si vybere hotový model na Printables, stáhne ho, na matplace.com za minutu zná cenu a
dobu tisku, objedná a dostane výtisk. Natočíš průchod na počítači (OBS) a hotový kus telefonem; časosběr z farmy
vznikne sám (16:9 i Short). Sestavení, titulky cs/en, hudba, YouTube, FB/IG a vložení na web uděláme my.

## 0. Co si připravit

- **Model na Printables s licencí, která dovoluje ukázat ho ve videu a vytisknout:** CC0, CC BY nebo CC BY-SA (u CC BY
  jméno autora a odkaz do popisu videa). Vyhni se „Non-commercial“ a modelům s lidskými tvářemi. Ideální: jeden kus
  do 80 mm bez podpěr, který se tiskne do 2 hodin (držák, organizér, klíčenka, figurka zvířete), ať je časosběr
  krátký a hezký. Stáhni jen STL (ne celý zip s více díly), název souboru krátký.
- **Dvě okna prohlížeče** v anonymním režimu (bez rozšíření), 1920 × 1080, F11, přiblížení 125 %: jedno s Printables,
  druhé s matplace.com (cs). Nic osobního na obrazovce. Na matplace budeš přihlášený, protože „Vytisknout u nás“
  vidí dnes jen přihlášený (farma není otevřená hostům); divák to v `/c/<kód>` uvidí po přihlášení stejně.
- **Účet se zkušebním kreditem**, nebo zaplať kartou v testu a klik na zaplacení nenatáčej (střihneme na „ve frontě“).
- OBS: Nastavení → Výstup mp4, kvalita vysoká, 30 fps; Video 1920 × 1080; zdroj „Záznam okna“ (prohlížeč), kurzor
  viditelný. Zvuk nenahrávej, hudba a titulky přijdou při sestřihu.

## 1. Natáčení (jeden zátah, pomalu, chyby nevadí)

1. **Printables (20 s):** stránka modelu, 2 s klid, posun na fotky, klik „Download“ → STL (ukaž licenci, 1 s).
2. **matplace.com (10 s):** hlavní stránka 2 s klid, přetáhni STL do pole „Přetáhněte sem soubor“ (nebo klepni
   „Nahrát soubor“ a vyber).
3. **Kalkulace (30 s):** nech načíst model, otoč ho myší (3 s), přečti cenu a dobu tisku (nech 3 s), změň kusy na 2
   a zpátky na 1 (ukáže se, že cena jde za kusem), velikost nech. Klik **„Vytisknout u nás“**.
4. **Zakázka (40 s):** druh plastu nech PLA+, zvol barvu z karty (ukaž, že barvy mají kódy cívek), kvalitu nech,
   zvol dopravu (Zásilkovna nebo vyzvednutí), zaškrtni podmínky (zmizí červený rámeček), klik na zaplacení kreditem
   → stránka zakázky „ve frontě“ / „tiskne se“. 3 s klid.
5. **Stránka zakázky později (10 s):** až bude vytištěno, otevři ji znovu: stav „hotovo“, časosběr se přehraje
   (klikni na něj, 5 s), tlačítko stažení videa.
6. Telefonem: hotový kus v ruce 10 až 20 s na šířku u okna, pomalu otočit; případně odlepení z desky.

Soubory: `Desktop\video\printables\pruchod.mp4`, `vec-1.mp4`, `poznamka.txt` (číslo zakázky, odkaz na model
a autora na Printables, licence).

## 2. Sestavení (session)

- Střih 1,5 až 2×, statické pauzy ven, titulky cs/en v obraze ke každému kroku („Model z Printables“, „Cena za minutu“,
  „Barva a doprava“, „Tiskneme“, „Hotovo“), úvod s názvem, konec „matplace.com“ 3 s, hudba z knihovny farmy.
- Časosběr té zakázky z pipeline (16:9 `timelapse_wide_path`) mezi krok 4 a 5, záběr telefonu na konec.
- Výstup 1080p mp4 + Short 9:16 (nejlepších 20 s: nahrání → cena → výtisk v ruce). YouTube přes `FarmVideos` po
  Romanově schválení, popis: odkaz na model a autora (licence), odkaz `matplace.com/?utm_source=youtube&utm_medium=social&utm_campaign=video_howto`;
  do `config/tools.php` nepatří (není to nástroj), odkaz na video přijde na hlavní stránku do sekce „Jak to jde“.
- Příspěvky FB/IG přes `matplace:social-videos`.

## 3. Co se ve videu nesmí objevit

Cizí modely s licencí NC nebo bez licence, cizí fotky, osobní údaje v zakázce (adresa doručení: použij vyzvednutí),
kredit účtu a administrace.
