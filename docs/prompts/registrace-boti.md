# Zadání pro session 3: registrace proti robotům (58 falešných účtů za den)

Nález 18. 10. 2026 (řídící, z produkce): za 24 hodin přibylo 57 účtů, všechny jméno „Test“, neověřený e‑mail, 55×
@gmail.com, jeden každých 20–30 minut, ze šesti adres (31.56.204.42, 31.57.107.185, 95.182.89.76, 95.182.114.251,
31.56.196.42, 2a13:7c00:5:29:…), stejný prohlížeč „Chrome/124 Windows“. Robot vyplní skutečný formulář (honeypot
`website` ho nezastaví), `throttle:6,1` na `POST /register` mu nevadí (2 za hodinu). Každá registrace odešle
ověřovací e‑mail přes Gmail SMTP (info@matplace.cz) na cizí adresu: 58 mailů na neexistující/cizí schránky kazí
pověst odesílatele. Účty nic jiného nedělaly (0 zakázek, jen událost registrace); řídící je po Romanově slově smaže.

## 0. Kde pracuješ a pravidla

- Větev **`feature/register-guard`** z aktuálního `main`, dokument **`docs/AH.md` §10**.
- Soubory: `routes/web.php` (řádky `register`, `register.store`), `app/Http/Controllers/Auth/AuthController.php`
  (`register`, `showRegister`), `resources/views/auth/register.blade.php`, `app/Providers/AppServiceProvider.php`
  (RateLimiter), `config/services.php` + `.env.example` (Turnstile), `lang/src/*.json` + `python scripts/lang_add.py`,
  testy `tests/Feature/AccountsTest.php` (+ nový `RegisterGuardTest`). Nesahat: přihlášení přes Google/Facebook,
  účet, kalkulace, farma.
- Pravidla: `git add` jmenovitě, žádný stash, `pint --dirty`, testy, `build` jen při změně JS; nemergovat, nenasazovat;
  hotovo = push + zpráva řídící session (conceprt19092026-8c).

## 1. Co udělat (v tomhle pořadí, každé zvlášť v commitu)

1. **Limit podle IP za delší dobu**: pojmenovaný limiter `register` (`RateLimiter::for`): 3 registrace za hodinu
   a 5 za den z jedné IP (u IPv6 podle /64), odpověď 429 s lidskou hláškou na stránce registrace (ne JSON). Ponechat
   i `6,1`.
2. **Čas vyplnění**: `showRegister` dá do formuláře podepsaný čas (`Crypt`/`hash_hmac` s `APP_KEY`, pole `t`);
   `register` odmítne odeslání dřív než 4 s po zobrazení a později než 2 h, a chybějící/pozměněné pole. Hláška
   „Zkuste to prosím znovu“ a formulář zůstane vyplněný.
3. **Ověřovací e‑mail až po prvním kroku**: na `Registered` neposílat mail hned, když registrace přijde z IP, která
   dnes už jednu registraci udělala (druhá a další z téže IP dostanou mail až po kliknutí „Poslat ověření znovu“
   na stránce účtu). Běžný člověk nic nepozná (první registrace z IP mail dostane).
4. **Turnstile (Cloudflare) připravený, vypnutý**: když je `TURNSTILE_SITE_KEY` a `TURNSTILE_SECRET` v `.env`,
   formulář registrace má widget a `register` ověří token přes `https://challenges.cloudflare.com/turnstile/v0/siteverify`
   (`Http::`), jinak se nic nemění. Klíče dodá Roman (účet Cloudflare zdarma, bez přesunu DNS). V AH.md popiš, co
   Roman zapne.
5. **Admin**: v `/admin/users` (nebo kde je seznam uživatelů) sloupec/odznak „neověřený“ a hromadná akce
   „smazat neověřené starší než 7 dní bez zakázek“ s potvrzením; plus příkaz `php artisan users:prune-unverified
   --days=7 --dry-run` (totéž z příkazové řádky, výpis, nic nemaže bez `--force`). Nikdy nemazat účty se zakázkou,
   kreditem, modelem nebo přihlášením přes Google/Facebook.

## 2. Testy a hotovo

`RegisterGuardTest`: 4. registrace z jedné IP za hodinu = 429 se stránkou; odeslání do 4 s = chyba a vyplněný
formulář; po 2 h = chyba; druhá registrace z téže IP týž den = účet vznikne, mail se nepošle (`Notification::fake`),
první ano; Turnstile vypnutý = formulář bez widgetu, zapnutý s falešnou odpovědí API = odmítnuto/přijato; `prune`
`--dry-run` nic nemaže, `--force` maže jen neověřené bez zakázky/kreditu/modelu. `AccountsTest` zelený, tři jazyky.
**Hotovo =** push `feature/register-guard`, AH.md §10, zpráva řídící session.
