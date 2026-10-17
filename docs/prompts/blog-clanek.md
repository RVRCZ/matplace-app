# Zadání: první článek blogu nahradit vlastním (Z.md N12, Roman 17. 10. 2026)

Dnes je první karta na `/blog` článek „PrintPal.io a 4 další AI nástroje…“ (post id 6, html, obálka screenshot cizího
webu). Roman: nahradit naším. Až bude nový článek zveřejněný, PrintPal se stáhne (`published_at = null`, řídící session).

## 0. Kde a pravidla

- Kterákoli volná session, ve svém worktree, větev **`feature/blog-bust`** z `main`, dokument `docs/AG.md`.
- Článek jako **markdown** do `docs/blog/jak-vznika-busta-z-fotky.md` s hlavičkou (title, excerpt, slug) **ve třech
  jazycích** (cs, en, es; každý jako samostatný blok `## cs`, `## en`, `## es` v jednom souboru), obrázky do
  `public/img/blog/busta/` jako webp ≤ 200 kB (obálka 1600 × 800). Do produkce ho vloží řídící session přes
  administraci (`/admin/content/posts`, formát markdown, `title`/`excerpt`/`body` jsou pole podle jazyka).
- Pravidla jako jinde: `git add` jmenovitě, žádný stash, nic nemergovat a nenasazovat; hotovo = push + zpráva řídící
  session (conceprt19092026-8c).

## 1. Obsah: „Jak vzniká busta z fotky“ (600 až 900 slov v každém jazyce)

1. Co člověk pošle: jedna fotka zpředu (a volitelně z boku), co na ní má být (světlo, pozadí, výška očí); co
   neposílat (cizí lidi bez souhlasu, děti bez rodiče). Odkaz na `/tools/figure`.
2. Co se děje za minutu: generátor udělá 3D model (Tripo), my ho srovnáme, uzavřeme a dáme na podstavec; soukromí
   (fotka u dodavatele, mazání po vytvoření, model po 30 dnech), limit 1 model denně bez účtu, 3 s účtem.
3. Výběr stylu a podstavce, jméno na podstavci; co jde upravit bez nového generování.
4. Tisk na farmě: materiál (PLA+, matný vs. lesklý), velikost 80 až 150 mm, doba tisku a orientační cena (vezmi
   čísla z kalkulace na produkci, ne odhadem), barvy s kódy cívek, převzetí a doprava.
5. Co od toho čekat a co ne: detail obličeje podle fotky, drobné stopy po podpěrách, srovnání dvou výtisků.
6. Konec: odkaz na nástroj s `?utm_source=blog&utm_medium=article&utm_campaign=busta`, dvě věty o farmě.

Fotky: `docs/img/bust-fixes-after-front.png`, `-side.png`, `-antique.png`, `bust-fixes-sample2-after.png`
(výtisky a náhledy po opravě bust), fotka karty nástroje z `config/tools.php` (`figure`), případně snímek stránky
nástroje na telefonu. Žádné cizí obrázky, žádné odkazy na konkurenci.

## 2. Hotovo

Markdown ve třech jazycích, obrázky, AG.md s tím, odkud jsou čísla (cena, čas) a která fotka je co, push větve,
zpráva řídící session.
