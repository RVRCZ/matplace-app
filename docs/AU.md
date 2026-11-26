# AU · Hlídač: schránka info@ a zdraví serveru (26. 11. 2026)

Proč: poptávka Saunia (50 ks klíčenek, 29. 10. 2026) ležela ve schránce info@ čtyři týdny nepřečtená pod šesti sty
vlastními hláškami farmy („[farm] Nová zakázka…“), odrazy a DMARC reporty. Roman 26. 11.: „stránka musí být
absolutně bezpečná, nastavená a sledovaná – e-maily jsem nezachytil bez upozornění“. Tahle kapitola popisuje
hlídače v aplikaci a zároveň ruční změny na serveru z téhož dne, aby byly dohledatelné.

## 1. Hlídač schránky (`matplace:watch-inbox`, každých 5 minut)

`App\Domain\Mail\MailboxWatch` projde nepřečtené zprávy ve schránce (`Mailbox::recent(100, unreadOnly)`) a
každou zařadí podle odesílatele a předmětu (`config/watch.php`):

| druh | co | co se s tím stane |
|---|---|---|
| `human` | všechno ostatní | nová zpráva → jeden e-mail majiteli, záznam v `mailbox_alerts`; po 24 h nepřečtená → připomenutí jednou denně |
| `own` | od naší adresy (schránka, `MAIL_FROM_ADDRESS`) nebo předmět `[farm]` | archivuje se (`Mailbox::archive`: pryč z doručené pošty i z nepřečtených, zůstává ve „Všech zprávách“) |
| `report` | mailer-daemon, postmaster, DMARC, abuse@, předmět „Report domain:“, „Delivery Status Notification“… | archivuje se |
| `robot` | noreply, notifications@, newsletter, Instagram, … | zůstává, admin ji ve schránce skryje pod „Automatické“ |

Majitel = farm nastavení **`alert_email`** („Můj e-mail pro hlídače“ v `/admin/farm/settings`, `FARM_ALERT_EMAIL`).
Je záměrně jiné než `admin_email`: tam chodí hlášky farmy a je to ta zahlcená schránka. Bez adresy se zprávy jen
zapisují, nikomu nic nechodí. E-mail `App\Mail\NewMailAlert` (markdown `mail.new_mail_alert`): kdo, kdy, předmět,
prvních 300 znaků, odkaz na zprávu v adminu. Hlídač nic neoznačuje jako přečtené – to dělá člověk.

Cache `watch.mailbox` = `{unread_human, checked_at}`: červený odznak u „E-maily“ v admin navigaci (vedle oranžového
s koncepty) a vstup pro kontrolu `mailbox` níže.

## 2. Hlídač serveru (`matplace:health`, každých 10 minut)

`App\Domain\Ops\HealthChecks::run()` odpoví na každou kontrolu `{key, ok, message}` jednou větou česky:

- `disk` – systémový disk a disk s modely (`storage/app/models`); problém od 85 %, „KRITICKÉ“ od 93 %.
- `models` – složka modelů existuje a není prázdná (prázdná = nepřipojený svazek Hetzneru).
- `failed_jobs` – úloha spadlá za posledních 60 min, se jménem a začátkem výjimky.
- `queue` – nejstarší nerezervovaná úloha čeká déle než 15 min (stojí workery?).
- `log` – řádky ERROR/CRITICAL/ALERT/EMERGENCY v `laravel.log` za posledních 15 min (čte posledních 256 KB; razítka
  logu jsou v `app.timezone`).
- `backup` – stáří souboru `WATCH_BACKUP_MARKER` (zapisuje ho záloha, §4); bez proměnné se kontrola nedělá.
- `mailbox` – hlídač schránky doběhl za posledních 30 min (jen když je schránka připojená).

`App\Domain\Ops\Health::run()` porovná se stavem v `health_checks` a majiteli pošle `App\Mail\HealthAlert`
jen při změně: **Problém** jednou, když nastane; **Trvá** jednou za den, dokud trvá; **Vyřešeno** jednou, když zmizí.
Kontrola, která je v pořádku od začátku, nic neříká. Bez `alert_email` se problém pamatuje a ohlásí, až adresa je.
Testy nahrazují skutečné kontroly přes `HealthChecks::$fake`.

Admin: **Farma → Hlídač** (`/admin/health`): tabulka kontrol se stavem a časem změny, tlačítko „Zkontrolovat teď“
(spustí obě úlohy, výpis pod tabulkou), poslední průchod schránkou a seznam lidí, o kterých majitel dostal zprávu.

Příkazy ručně: `php artisan matplace:health --print` (nic nepamatuje, nic neposílá), `php artisan matplace:watch-inbox`.

## 3. Schránka v adminu

`/admin/emails/inbox` ukazuje jen zprávy od lidí; `?auto=1` („Automatické (n)“) přidá roboty, reporty a vlastní
hlášky, pokud ještě v doručené poště jsou. Rozhraní `Mailbox` dostalo `archive(id)` (Gmail: `messages/{id}/modify`
removeLabelIds INBOX+UNREAD; `FakeMailbox`: zpráva mizí z `recent()`).

## 4. Co se 26. 11. 2026 udělalo ručně na serveru (není v kódu, je v záloze konfigurace)

- **Systém:** `apt full-upgrade` (98 balíčků, PHP 8.2.36, jádro 6.8.0-151), restart v klidu, všech 14 agentů se
  přihlásilo zpět. PPA `ondrej/php` se hlásí jako „deprecated“ – při příštím upgradu PHP počítat s jiným zdrojem.
- **fail2ban** (`/etc/fail2ban/jail.local`): sshd (aggressive, 5 pokusů/10 min → hodina) + recidive (3 bany/den →
  týden); Romanova adresa 212.79.110.132 v `ignoreip`. Před tím 23 286 neúspěšných pokusů o heslo roota.
- **ufw:** deny incoming; 22/tcp `limit`, 80 a 443 allow (IPv4 i IPv6).
- **sshd** (`/etc/ssh/sshd_config.d/60-matplace.conf`): MaxAuthTries 3, LoginGraceTime 30, bez X11. **Heslo zůstává
  povolené** – Roman se hlásí i z jiných zařízení bez klíče; až tam klíče budou, vypnout `PasswordAuthentication`.
- **Záloha nového webu** `/root/backup_matplace_app.sh` (cron `/etc/cron.d/matplace-app-backup`, 2:40): dump
  `matplace_app` (14 dní na `/var/backups/matplace-app`, 30 dní na svazku `/mnt/HC_Volume_107593853/backups/db`),
  konfigurace (`.env`, nginx, fail2ban, sshd, cron, systemd, letsencrypt, `/root/*.json`, soubory z `*_PATH` v .env)
  jako `config_*.tgz`, zrcadlo `storage/app` bez `models` do `backups/storage-app` (jen když je na svazku ≥ 5 GB),
  kopie dumpů staré DB, značka `backups/LATEST` pro kontrolu `backup`. Do té doby se zálohovala **jen stará**
  databáze `matplace`. Svazek je druhý disk téhož serveru – kopie jinam (Storage Box) zatím není.
- **Starý web** (`legacy.matplace.com`): `snippets/legacy-closed.conf` vrací 410 pro `/ucet|tiskar|designer/registrace`
  – roboti tam v listopadu založili 199 účtů a každý dostal „Potvrďte svůj e-mail“ z info@ (61 odrazů). Kopie
  těch účtů v `/root/old-users-bots-2026-11-26.sql`; jejich smazání/zablokování zůstalo na Romanovi.
- **Schránka:** 829 zpráv archivováno (ids v `/root/inbox_archived_2026-11-26.json`), 866 → 37.

## 5. Ověření

`tests/Feature/WatchInboxTest.php` (druhy, jeden e-mail majiteli, archivace vlastních hlášek a odrazů, robot
zůstává, bez adresy jen záznam, připomenutí po dni jen jednou denně, admin skrývá roboty a odznak počítá lidi),
`tests/Feature/HealthWatchTest.php` (problém → trvá → vyřešeno, bez adresy počká, skutečné kontroly vidí spadlou úlohu,
stojící frontu a zastaralý hlídač schránky, `--print` nic nepamatuje, admin stránka a „Zkontrolovat teď“).

## 6. Co zbývá (Roman)

- DKIM pro matplace.cz i .com (Workspace → Gmail → Ověření e-mailů → TXT u Wedosu), potom DMARC `p=quarantine`.
- Kopie záloh mimo server (Hetzner Storage Box) a vypnutí hesla pro SSH, až mají ostatní zařízení klíč.
- Druhé ověření do administrace (přihlášení správců jen přes Google), vnější hlídač dostupnosti (UptimeRobot).
- Přístupový klíč přidaný k info@ 17. 11. – ověřit, že je Romanův.

## 7. Lepší práce se schránkou (26. 11. 2026 večer)

Roman: „potřebuju hlídat došlou poštu, když je tam něco důležitého od člověka, poslat hlášku na Gmail a v adminu
navrhnout odpověď; když otevřu e-mail, nefunguje to dobře“. Ráno přišla stížnost zákaznice (F26-000194, „objednala
jsem fenu a přišel pes, posílám fotky“): admin fotky neukázal, text byl zahlcený citovaným e-mailem a AI napsala,
že se „fotografie k nám nedostaly“.

- **Přílohy:** `GmailMailbox::parse` vrací `attachments` (i fotky vložené do textu, jak je posílají telefony),
  `Mailbox::attachment(id, file)` jejich obsah; admin `GET /admin/emails/inbox/{id}/file/{file}`
  (`admin.emails.inbox.file`): obrázky a PDF se otevřou v prohlížeči, ostatní se stáhne.
- **Citace:** `App\Domain\Mail\MailText::split` odřízne citovaný e-mail (Seznam „Původní e-mail“, Gmail „Dne …
  napsal(a):“, Outlook, řádky „>“). Admin ukazuje velkým písmem jen to, co člověk napsal; citace je sbalená.
  `MailText::locale` odhadne jazyk (cs / es / en) pro koncept.
- **Souvislosti:** `ReplyContext` najde objednávky jmenované ve zprávě (F26-…) a poslední zaplacené objednávky účtu
  s adresou odesílatele; admin je ukazuje s odkazem, AI je dostává jako fakta.
- **AI:** `Outbox::reply` posílá AI zvlášť zprávu, seznam příloh, až 4 přiložené fotky (≤ 4,5 MB, dočasné soubory
  se hned mažou), objednávky a zkrácenou citaci; pravidla: přílohy dorazily, faktům z objednávek neodporovat, při
  chybě na naší straně se jednou omluvit a řešení nechat potvrdit adminovi.
- **Koncept hned:** hlídač ke každé nové lidské zprávě (nejvýš 5 za průchod, `WATCH_AUTO_DRAFT`) nechá napsat koncept
  a v e-mailu majiteli je u zprávy odkaz „Koncept odpovědi ke schválení“; zpráva v adminu má nahoře „Přečíst a
  odeslat“. Odchází pořád jen po schválení, jako odpověď ve vlákně.
- **Rychlost:** `recent()` načítá hlavičky po deseti najednou (`Http::pool`), dřív stránka čekala ~10 s.
- **Opravy hlídače:** připomenutí se počítá od chvíle, kdy majitel dostal zprávu (ne od data e-mailu – staré zprávy
  se připomínaly hned po prvním upozornění); `matplace:health` končí kódem 0 (jinak plánovač psal ERROR do logu a
  kontrola „Chyby v logu“ hlásila sama sebe), log kontrola ignoruje vlastní řádky hlídačů; značka zálohy je
  `/var/lib/matplace/backup-latest` (složka záloh je jen pro roota, web ji nepřečetl).

## 8. Obrázky zakázky v odpovědi, odkaz na zakázku, náhled modelu pro AI (26. 11. 2026)

Roman: „jde do odpovědi vkládat snímek modelu nebo fotky, nebo odkaz na model, podle kterého jsme tiskli?“ – ano,
všechny čtyři body (přiložit, odkaz, nechat náhledy natrvalo, AI vidí náš model).

- **`App\Domain\Farm\OrderPictures`**: ke každé objednávce malé JPEGy (≤ 900 px, desítky kB) ve složce zakázky
  `keep/`: `model` (náhled modelu, jak šel do tisku – `ModelPicture` přes `advise_tool.py preview`), `source` (fotka,
  ze které model vznikl), `snapshot` (poslední snímek kamery), `photo` (foto-box). `OrderFiles::prune` maže soubory
  podle jmen a `keep/` nechává; `matplace:prune` před úklidem zakázky náhledy uloží (nejvýš 40 kreslení za noc).
  Novější zdroj (např. snímek kamery během tisku) kopii obnoví.
- **Koncept odpovědi** (`/admin/emails/{id}`): tlačítka „Vložit odkaz na zakázku“ (odkaz na stránku zakázky s tokenem,
  otevře se bez přihlášení), sekce „Přiložit“ s náhledy obrázků zakázek (zaškrtnout) a nahráním vlastních souborů
  (obrázky, PDF, nejvýš 6 příloh po 8 MB); „Přílohy, které odejdou“ s odebráním. Soubory konceptu jsou na disku
  `local` v `mail-attachments/{id}/`, seznam ve sloupci `outgoing_emails.attachments`.
- **Odeslání:** odpověď ve vlákně jde přes Gmail jako `multipart/mixed` (`GmailMailbox::raw` s `files`), vlastní
  e-mail přes `PlainMessage` s přílohami.
- **AI:** `Outbox::reply` k fotkám zákazníka přidá u nejvýš dvou objednávek náš náhled modelu a fotku-předlohu
  a v textu řekne, který obrázek je který („porovnej, když zákazník píše, že výtisk neodpovídá“). Hlídač kreslí chybějící
  náhled modelu (`draw: true`), tlačítko v adminu bere jen to, co už je uložené (web nečeká 90 s).
