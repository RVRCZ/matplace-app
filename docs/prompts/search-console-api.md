# Search Console přes API – session 3 (Roman 27. 10. 2026: „natrvalo“)

Cíl: týdenní (a denní) čísla ze Search Console per nástroj a jazyk bez ručního exportu; `/admin/stats/tools` je čte
z databáze místo CSV (CSV zůstává jako záloha pro import). Piš česky, krátce, bez pomlček. Větev `feature/search-console-api`
z `origin/main`, deník AN.md §14, žádné volání API naostro bez připojeného účtu (Roman ho připojí v adminu).

## Jak to zapojit

- Stejný Google Cloud projekt a OAuth klient jako YouTube (`YouTubeClient`: client id/secret v konfiguraci, `authUrl`,
  `connect`, uložený refresh token). Udělej obecnější `GoogleOAuth` (nebo jen druhý klient `SearchConsoleClient` se stejným
  vzorem), rozsah `https://www.googleapis.com/auth/webmasters.readonly`, callback **přesně**
  `https://matplace.com/admin/search-console/callback` (Roman ho registruje v OAuth klientovi, ta adresa je domluvená).
- Admin: `/admin/search-console` s tlačítkem Připojit (jen admin), stav připojení, výběr vlastnosti (domain property
  `sc-domain:matplace.com`, nebo URL prefix `https://matplace.com/`; vypiš `sites.list` a nech Romana vybrat), tlačítko
  „Stáhnout teď“, poslední stažení a chyby.
- Tabulka `search_console_rows` (date, page, query nullable, country nullable, clicks, impressions, ctr, position,
  unikát date+page+query+country; šířky sloupců proti MariaDB) + `search_console_sites` nebo sloupce v účtu.
- Příkaz `searchconsole:pull [--days=3] [--since=YYYY-MM-DD]`: `searchanalytics.query` po dnech s dimensions
  `page,query` (řádky po 25 000, stránkování `startRow`), první běh od `--since` (nabídni 16 měsíců zpět, tolik API drží),
  pak denně `--days=3` (Search Console má 2 až 3 dny zpoždění), idempotentní (upsert). Rozvrh v `routes/console.php`
  denně ráno. Kvóta je volná (1 200 dotazů/min), ale počítej s pauzou při 429.
- `App\Domain\Stats\SearchConsole` ať bere data z DB, když jsou, jinak z CSV jako dnes; stránka `/admin/stats/tools`
  beze změny vzhledu, navíc řádek „data ze Search Console do <datum>“ a filtr země (CZ / ostatní).
- Testy s falešným Googlem jako u YouTube (připojení, pull s dvěma stránkami odpovědi, upsert dvakrát = stejné počty,
  stats z DB i z CSV).

## Co klikne Roman (v odpovědi řídící)

Google Cloud: zapnout „Google Search Console API“ v projektu s YouTube; do OAuth klienta přidat redirect URI
`https://matplace.com/admin/search-console/callback`; na obrazovce souhlasu (pokud je v režimu testování) přidat rozsah
webmasters.readonly a mít svůj účet v testovacích uživatelích. Search Console: účet, kterým se připojí, musí být vlastník
nebo uživatel vlastnosti matplace.com. Pak v `/admin/search-console` klikne Připojit.
