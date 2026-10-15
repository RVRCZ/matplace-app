# Zadání pro session D: částicová animace loga Matplace při generování (figurka mazlíčka, busta, reliéf)

Od Romana (16. 10. 2026): „u generování mazlíčka v prostoru, kde bude model, udělej animaci jako má Tripo, jak se
pohybují tečky, ale v symbolu loga Matplace“. Tripo ukazuje tmavou plochu, na které tisíce světlých teček víří a
skládají se do jeho značky, dole „Generating…“.

## Výchozí odpovědi (platí, dokud Roman neřekne jinak)

- **Symbol** = celé logo: tryska s oranžovým vláknem a nápis „Matplace“ (`public/img/logo.png`, 1200 × 712, průhledné
  pozadí). Vektor nemáme – body se vzorkují z alfa kanálu PNG (offscreen canvas), teal tečky pro trysku a písmo,
  oranžové pro vlákno (barva z pixelu). Kdyby Roman dodal SVG (má zdroj v CorelDRAW), použít ho místo rastru.
- **Pozadí**: naše světlé pozadí prohlížeče, ne tmavé jako Tripo; tečky v barvách loga, jemný stín.
- **Kde**: figurka mazlíčka (`tools/pet.blade.php`, `pet.ts`), busta z fotky (`figure.ts`) a reliéf během zpracování –
  všude, kde se čeká na model déle než pár sekund; jedna komponenta (`resources/js/calc/particles.ts`), stejné chování.
- **Text pod animací**: stávající fáze („Překreslujeme fotku…“, „Stavíme model, asi minutu…“) a procenta, když je
  server posílá; placeholder „Figurka se ukáže tady…“ zůstane jen bez JavaScriptu.

## Co postavit

1. `particles.ts`: 2D canvas přes plochu prohlížeče (devicePixelRatio), 1 500–3 000 částic podle velikosti plochy;
   cíle = vzorek bodů loga (Poissonovsky řídce, hustěji v obrysech), logo vepsané na ~60 % šířky plochy, vycentrované.
   Smyčka ~7 s: (a) částice přiletí z okraje a víří (šum, lehká rotace kolem středu), (b) během ~2 s se usadí do loga
   (easing, mírný dech ±1 px), (c) vlákno z trysky „protéká“ oranžovými částicemi směrem do písma, (d) rozpad a znovu.
   `requestAnimationFrame`, pauza při skryté kartě, `prefers-reduced-motion` → statické logo bez pohybu, tečky bez
   blur (výkon na telefonu: cíl 60 fps na střední třídě, měřit na 390 px).
2. Spuštění: při odeslání generování (`pet.ts`, `figure.ts`, reliéf) místo prázdné plochy; ukončení: jakmile dorazí
   model (plynulé rozplynutí 400 ms, pak viewer). Při chybě: částice se rozpadnou, zůstane text chyby.
3. Nic v enginu, nic v API. Texty se nemění (fáze už existují).
4. Testy: jednotkový test vzorkování bodů z malého PNG (počet, barvy, meze); `tsc`, `build`; v headless Chrome
   snímky tří fází (vír, logo, rozpad) na 1366 i 390 px do `docs/img/particles/`; měření fps (performance.now) v
   konzoli; `check_preview_colors.mjs` beze změny.

## Otázky pro Romana (odpoví kdykoli, zatím platí výchozí)

1. Celé logo, nebo jen tryska s vláknem? (výchozí: celé)
2. I u busty a reliéfu? (výchozí: ano)
3. Pošle SVG loga z Corelu? (výchozí: rastr stačí)

**Hotovo =** na `/tools/pet-figurine` běží po odeslání animace s logem, končí příchodem modelu; totéž u busty a reliéfu;
snímky, fps, `tsc`, `build`, Y.md. Větev `feature/logo-particles` z main.
