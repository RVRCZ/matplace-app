# Texty pro YouTube Studio – kanál Matplace 3D (@Matplace3D)

Roman klikne sám (Studio → Přizpůsobení → Základní informace, Branding). Připravila session 2, 27. 10. 2026, podle
auditu `docs/prompts/audit-youtube-2026-10-27.md` bodů 10, 11 a trailer. Nic z toho nejde přes API.

## 1. Název a handle

- Název kanálu: **Matplace 3D** (beze změny)
- Handle: @Matplace3D (beze změny)

## 2. Popis kanálu (česky, klíčová slova v prvních dvou řádcích, které YouTube ukazuje bez rozkliknutí)

```
3D tisk na zakázku z Plzně: nahrajete model nebo fotku, hned vidíte cenu, my tiskneme a posíláme.
Na kanálu ukazujeme, co se u nás tiskne: busty z jedné fotky, dárky se jménem, krabičky a držáky na míru – a na konci každého videa cenu.

Každý výtisk vzniká v nástrojích na matplace.com: z fotky figurka nebo reliéf, ze jména cedulka či medaile, z rozměru krabička nebo organizér. Cena 3D tisku je známá před objednáním, platíte kartou až na konci.

Nástroje zdarma: matplace.com/tools
Nahrát model a zjistit cenu: matplace.com

Časosběry zveřejňujeme se souhlasem zákazníků. Hudba z YouTube Audio Library.

Custom 3D printing from the Czech Republic: upload a model or a photo, see the price at once, we print and ship. matplace.com
```

(Klíčová slova, na která má kanál být k nalezení: 3D tisk na zakázku, cena 3D tisku, 3D tisk Plzeň, busta z fotky,
dárek se jménem, 3D tiskárna, časosběr 3D tisku.)

## 3. Odkazy v záložce „O kanálu“ (Studio → Přizpůsobení → Základní informace → Odkazy; první se zobrazuje i na banneru)

| Text odkazu | Adresa |
|---|---|
| Nahrát model, zjistit cenu | https://matplace.com/?utm_source=youtube&utm_medium=social&utm_campaign=channel |
| Nástroje zdarma (z fotky, se jménem, na míru) | https://matplace.com/tools?utm_source=youtube&utm_medium=social&utm_campaign=channel |
| Instagram | https://www.instagram.com/matplace3d (ověřit účet) |
| Facebook | https://www.facebook.com/matplace (ověřit stránku) |
| Pro firmy a školy (e-mail) | mailto:info@matplace.com (ověřit adresu) |

Kontaktní e-mail pro firmy v sekci „Kontaktní informace“: tentýž.

## 4. Banner (2048 × 1152 px, bezpečná oblast 1235 × 338 uprostřed)

Text do bezpečné oblasti: **„Nahrajte model, uvidíte cenu. Tiskneme a posíláme.“**, pod ním menším písmem
„matplace.com · busty z fotky · dárky se jménem · díly na míru“. Obrázek: tři složené fotky výtisků z fotoboxu vedle sebe
(článek řetězu medaile, busta, krabička) na studiovém podkladu (`farm:photo`, varianta web), logo vlevo. Až budou fotky,
banner složím skriptem (ffmpeg/PIL) – řekněte, které tři kusy.

## 5. Upoutávka kanálu (trailer pro neodběratele)

Zkrátit stávající video „Model z Printables vytištěný na matplace – od stažení po výtisk“ (1:53, 107 zhl.) na
30 až 40 s: 0–3 s háček „Z téhle fotky za hodinu busta – a víte cenu předem“ (textová karta), 3–20 s nástroj z fotky
(obrazovka: nahrání fotky → model → cena), 20–32 s časosběr tisku, 32–38 s hotový kus v ruce s cenou a adresou
matplace.com/tools/figure. Priorita návštěvnosti bod 6: trailer má ukázat nástroj z fotky, ne časosběr. Po schválení
sestříhám z existujících záběrů (`HowtoCutter`, `farm:howto-video` zvládne Short i 16:9 z JSON předpisu) a nahraju
přes frontu videí jako „howto“ ke schválení.

## 6. Doporučené video pro odběratele (Studio → Přizpůsobení → Rozložení)

Nejsledovanější Short „Busta z jedné fotky – 1 h 33 min tisku“ (1 100 zhl.), po přejmenování.

## 7. Sekce domovské stránky kanálu (Rozložení)

1. Shorts (nejnovější)
2. Playlist „Figurky z fotky“
3. Playlist „Dárky se jménem“
4. Playlist „Pro domácnost“
5. Playlist „Jak to funguje“

Playlisty vytvoří `php artisan youtube:playlists --apply` po schválení (dry run bez `--apply`).

## 8. Komunita a komentáře

- Pod každým Shortem je připnutý komentář s odkazem na nástroj a cenou (zapíná `YOUTUBE_PIN_COMMENT=true`, připnutí
  samo je jedno kliknutí v Studiu).
- Na komentáře odpovídat do hodiny; první příspěvek v Komunitě: anketa „Co vytisknout příště?“ se čtyřmi možnostmi
  (busta z fotky, medaile se jménem, organizér do šuplíku, vánoční ozdoba).
