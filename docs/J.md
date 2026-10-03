# Správa uživatelů v adminu

`main` (4. 10. 2026). Starý admin měl seznam uživatelů; nový měl jen kredit podle e‑mailu. Přibylo `/admin/users`.

## Co vzniklo

- **Seznam** (`/admin/users`): hledání podle jména, e‑mailu nebo telefonu, filtr podle role, přepínač na smazané
  (anonymizované) účty; u každého země a měna účtu, role, kredit, počet zaplacených tisků a modelů, datum
  registrace. Nahoře počty: účtů, ověřených, designérů, nových za 30 dní.
- **Detail** (`/admin/users/{id}`): kontakt, adresa, jazyk, způsob přihlášení (heslo / Google / Facebook), modely
  a kalkulace, designérský profil (odkaz na `/d/…` a na karty v katalogu), kredit s posledními 15 pohyby a odkazem
  na úpravu kreditu/měny (stávající stránka farmy), posledních 20 tisků s odkazy do adminu farmy, souhrn událostí.
- **Role**: `admin` a `designer` zapnout/vypnout. Designér se zapíná stejnou cestou jako z účtu (vznikne profil);
  vypnutí roli odebere a profil skryje. Vlastní roli admin si nikdo nevezme; neověřený e‑mail designérem být nemůže.
- **Odkaz na nové heslo**: stejný e‑mail jako „Zapomenuté heslo“.
- **Smazání účtu**: stejné jako když si ho smaže člověk sám (`AccountEraser`: anonymizace, propadnutí kreditu,
  zakázky a kniha zůstanou); jen po opsání e‑mailu, ne vlastní účet, ne admin (nejdřív odebrat roli).

Zakládání účtů z adminu (starý web to měl) jsem nepřenesl: lidé se registrují sami a admin by jim musel vymýšlet
heslo; místo toho je tu odkaz na nové heslo.

## Testy

`tests/Feature/AdminUsersTest.php` (2 testy): přístup jen pro adminy, hledání, detail s kreditem; role včetně
profilu designéra a zákazů, odkaz na heslo, smazání s potvrzením a výpis smazaných.

## Nasazení

`php artisan optimize && systemctl restart php8.2-fpm` — bez migrace, bez buildu.
