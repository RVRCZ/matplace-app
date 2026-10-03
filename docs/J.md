# Správa uživatelů a příchozí e-maily v adminu

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

## Příchozí e-maily (`/admin/emails/inbox`)

Starý web četl schránku `info@matplace.com` přes Gmail API a nabízel odpověď od AI. Přeneseno:

- `App\Engines\Mail\Mailbox` (`GmailMailbox` = Gmail REST API s OAuth tokenem majitele schránky, `FakeMailbox` pro
  testy; `ENGINE_MAILBOX=gmail|fake`). Token je soubor se `refresh_token` (převzatý ze starého webu, scopes
  `gmail.modify` + `gmail.send`), přístupový token se obnovuje a zapisuje zpět.
- Záložka **Příchozí**: nepřečtené zprávy (nebo všechny poslední), u každé stav odpovědi. Detail zprávy s textem
  (HTML se převede na text). **Navrhnout odpověď** (volitelně s pokynem a jazykem): asistent napíše koncept; zpráva
  jde modelu jako podklad, ne jako pokyn, a jako příklady tónu dostane posledních 5 schválených odpovědí
  (náhrada „učení z úprav“ starého webu). Koncept je běžný `outgoing_emails` záznam s `inbox_message_id`,
  `inbox_thread_id`, `in_reply_to`; schválením odejde **jako odpověď ve vlákně z info@** (ne přes náš SMTP) a zpráva
  se označí jako přečtená. Nad konceptem je vidět původní zpráva.
- „Označit jako přečtené bez odpovědi“ pro spam a oznámení.

Nepřeneseno: výběr odesílacího aliasu (vždy info@), Messenger/Instagram.

## `.env` (server)

| klíč | hodnota |
|---|---|
| `ENGINE_MAILBOX` | `gmail` |
| `GMAIL_TOKEN_PATH` | `storage/app/private/gmail_token.json` (kopie tokenu starého webu, vlastník www-data, 600) |
| `GMAIL_CLIENT_ID`, `GMAIL_CLIENT_SECRET` | OAuth klient, kterým token vznikl (starý web: `GOOGLE_CLIENT_ID/SECRET`) |
| `GMAIL_INBOX_EMAIL` | `info@matplace.com` |

## Testy

`tests/Feature/AdminInboxTest.php` (2 testy): koncept odpovědi ze zprávy (zpráva jako podklad, pokyn admina, příklady), odeslání jen po schválení a jen ve vlákně, označení jako přečtené, schránka odpojená; čtení zprávy Gmailu (text i HTML, adresa se jménem) a stavba odpovědi (Re:, In-Reply-To, UTF‑8).
`tests/Feature/AdminUsersTest.php` (2 testy): přístup jen pro adminy, hledání, detail s kreditem; role včetně
profilu designéra a zákazů, odkaz na heslo, smazání s potvrzením a výpis smazaných.

## Nasazení

`php artisan migrate --force` (2026_10_10 outgoing_emails_replies), `.env` výše, `php artisan optimize && systemctl restart php8.2-fpm` — bez buildu.
