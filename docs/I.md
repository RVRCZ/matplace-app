# Nastavení tisku nad rámec předvoleb

Větev `feature/h-tool-fixes` → `main` (4. 10. 2026). Podnět: výkres z firmy (PETG/ABS, výplň 20 %, vrstva 0,2 mm,
2 perimetry) — výplň 20 % se nedala zadat, perimetry vůbec.

## Co vzniklo

**Zákazník: „Pokročilé nastavení“** v objednávce (sbalená sekce pod podpěrami): výplň v procentech (5–100),
perimetry (1–6), horní a spodní vrstvy (0–10). Prázdné pole = hodnota z předvoleb. Po změně se zakázka naslicuje
a nacení znovu, jako při změně kvality. Hodnoty jsou v `farm_orders.print_settings` a ve `slice_params`
(`infill_percent`, `overrides.process.wall_loops` / `top_shell_layers` / `bottom_shell_layers`).

**Admin: přepisy nastavení sliceru** na stránce zakázky (`/admin/farm/orders/{token}`): JSON objekt s klíči
procesního profilu OrcaSlicer, například `{"wall_loops": "3", "sparse_infill_pattern": "gyroid"}`. Má přednost před
předvolbami i nastavením zákazníka; po uložení se zakázka naslicuje znovu. Jde to u nezaplacené zakázky a u
zaplacené, která čeká na schválení; ve frontě a dál už ne (tiskárna si může brát G‑code). Cena zaplacené zakázky
se nemění. Uloženo v `farm_orders.admin_overrides`.

Pořadí při slicování (`PrepareFarmOrder`, `App\Domain\Farm\PrintSettings`): profil tiskárny → předvolby →
čísla zákazníka → přepisy tiskárny → přepisy admina.

## Rozhodnutí

- Klíče přepisů jsou jen „slovo z malých písmen, číslic a podtržítek“ s textovou nebo číselnou hodnotou; nic
  jiného se neuloží (hláška říká, které klíče neprošly). Platnost klíče pro Orcu se nekontroluje — neznámý klíč
  Orca ignoruje.
- Výplň zákazníka jde přes `SliceParams::infillPercent` (stejná cesta jako předvolba), ostatní jako přepisy.

## Testy

`tests/Feature/PrintSettingsTest.php` (2 testy): čísla zákazníka dojdou do sliceru, cena roste s výplní, meze,
návrat k předvolbám; přepisy admina vyhrají nad zákazníkem, špatný JSON a cizí klíče se odmítnou, zrušení, zámek
po zařazení do fronty, zaplacená zakázka čekající na schválení se naslicuje bez změny ceny.

## Nasazení

```
php artisan migrate --force        # 2026_10_09_100000_farm_order_print_settings
npm ci && npm run build
php artisan optimize && systemctl restart php8.2-fpm matplace-worker
```
