# Zadání pro session 1: medaile, kolo 4: zaoblené hrany medaile, zakulacené konce článků, menší mezera řetězu

Od Romana (20. 10. 2026, po prvním dvoubarevném výtisku F26‑000089 na Kobře X: „medaile dotiskla, je to rozhodně
lepší“). Chybí:

1. **Zaoblené hrany po obvodu medaile zespoda i seshora** (líc i rub). Líc leží na podložce: zaoblení dole jako
   u článků (poloměr ~1 mm s malou ploškou, převis do 45°, bez podpěr); nahoře plné zaoblení ~1 mm. Zaoblení se
   týká obrysu placky (kruh, hvězda, štít…), ne lemu a motivu (ty zůstávají ploché v rovině líce). Očko zaoblit
   stejně, pokud to engine umí levně; jinak nechat.
2. **Zakulacené konce článků řetězu**: otevřené konce „C“ zakončit půlkoulí (stejný průměr jako profil ⌀ 4), ne
   seříznutou plochou.
3. **Mezera mezi konci článků o 2 mm menší** (pro tenhle rozměr článku 30 × 18, profil ⌀ 4): dnešní otvor je o 0,4 mm
   užší než příčka; po zakulacení konců zmenšit rozteč konců ještě o 2 mm, aby se řetěz samovolně nerozpadal, a
   přitom šel ručně zacvaknout (PLA pruží; spočítej a napiš, jaký přesah vychází, případně `LINK` jako volbu
   v enginu s výchozí hodnotou pro 30 × 18).

Větev **`feature/medallion-round4`** z aktuálního `main`, `docs/P.md` §8c. Soubory jako dřív (`shape_kinds.py`,
testy enginu, ukázky jen když se změní vzhled). Ověř řezem (bez podpěr, převis ≤ 45°), gramy a čas pro 60 mm + 40
článků do P.md. Pravidla jako dosud; push + zpráva řídící session; po nasazení zkušební kalkulace (výchozí stav).
