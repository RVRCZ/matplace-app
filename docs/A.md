# Krok A: uživatelský účet

Větev `feature/a-account` (z `feature/a0-locale`). Účet je místo zákazníka: tisky, modely, kalkulace. Slovo tiskař
z rozhraní zmizelo. E‑mail se ověřuje, mění přes potvrzení a účet jde smazat tak, aby účetnictví zůstalo.

## Co vzniklo

**Ověření e‑mailu**
- `User` je `MustVerifyEmail` + `HasLocalePreference`. Registrace pošle `VerifyEmailAddress` (podepsaný odkaz, 24 h)
  v jazyce registrační stránky. Odkaz funguje i v prohlížeči, kde uživatel není přihlášený (odkaz sám je důkaz).
- Middleware `verified.email` (`EnsureEmailVerified`) stojí před: `POST /farm/orders`, `POST …/pay`,
  `POST /account/credit`. Stránky zůstávají přístupné a ukazují lištu `partials/verify_banner` s tlačítkem
  „poslat znovu“ (3× za hodinu).
- Google a Facebook: `email_verified_at = now()` jako dosud.

**Přihlášení**
- Stránka přihlášení a registrace si zapamatuje, odkud návštěvník přišel (Referer z našeho webu, kromě
  přihlašovacích stránek), a po přihlášení ho tam vrátí. Stránka, která si přihlášení vynutila, má přednost.
- Smazaný (anonymizovaný) a zablokovaný účet se nepřihlásí heslem ani přes OAuth.
- E‑mail pro nové heslo je náš (`ResetPasswordLink`), v jazyce účtu. Dřív chodil anglický výchozí text frameworku.

**Změna e‑mailu** (`App\Domain\Account\EmailChange`)
- `users.pending_email`, `pending_email_token` (sha256 tokenu), `pending_email_at`. Potvrzení jde na novou adresu,
  informace na starou; po potvrzení ještě jedna zpráva na starou adresu („e‑mail byl změněn“).
- Platí 24 h, jde zrušit, obsazená adresa se odmítne při zadání i při potvrzení.

**Propojená přihlášení**
- Profil ukazuje `oauth_identities`, odpojit jde jen když zůstane heslo nebo jiné propojení.
- „Propojit Google“ → `/auth/google/redirect?link=1`; callback přidá identitu přihlášenému účtu, cizí identitu odmítne.
  OAuth routy už nejsou za `guest`.

**Smazání účtu** (`App\Domain\Account\AccountEraser`)
- S heslem: potvrzení heslem. Bez hesla (jen Google/Facebook): e‑mail s podepsaným odkazem (24 h) → stránka
  s tlačítkem → POST. Samotné otevření odkazu nic nemaže (poštovní skenery odkazy otevírají).
- Maže: jméno („Smazaný uživatel“), e‑mail (`deleted-{id}@invalid`), telefon, adresa, výdejní místo, avatar, heslo,
  OAuth identity, session, kalkulace, modely, které nic nepotřebuje, IP u anonymních session.
- Zůstává: objednávky, platby, řádky kreditu. Zaplacené tisky doběhnou. Nezaplacené objednávky se zruší.
- Kredit propadá po zaškrtnutí; do knihy jde řádek `forfeit`, takže zůstatek 0 má vysvětlení.
- Událost `App\Events\AccountErasing` běží uvnitř transakce: další kroky (designérské karty, `events`) si na ni
  pověsí vlastní úklid.

**Obrazovky**
- `/account`: Moje tisky (5 posledních, kredit, nový tisk) → Moje modely (8 posledních) → Kalkulace (10 posledních),
  každý blok s odkazem na vše. Místo pro kartu designérského profilu je připravené (`account._designer_card`, krok B).
- `/account/orders` (filtr stavu; `/farm/orders` sem vede 301), `/account/models` (filtr původu, 24 na stránku),
  `/account/calculations`.
- `/account/profile`: údaje, doručovací adresa (jméno příjemce, země jménem ze seznamu EU + GB + US), oblíbené
  výdejní místo, jazyk e‑mailů, e‑mail, heslo, propojená přihlášení, smazání účtu.
- Model: otevřít v nástroji / otevřít v kalkulaci, objednat tisk, 3MF pro moji tiskárnu (dialog
  `partials/printer_pick`, stejný zapamatovaný výběr jako v kalkulaci), smazat.

**Mazání modelu** (`App\Domain\Account\ModelLibrary`): nejde, když na model míří objednávka v jiném stavu než
zrušeno/nepodařilo se, poptávka, nabídka nebo karta `designer_models`. Tlačítko je šedé s vysvětlením.

## Rozhodnutí nad rámec zadání

1. **Skutečné náhledy modelů.** Zadání: „existující thumbnail nebo ikona“. Thumbnail ale nikdo neukládal. Prohlížeč
   majitele teď model jednou vykreslí stávajícím 3D viewerem (jeden sdílený, postupně, jen co je vidět) a obrázek
   pošle na `POST /api/files/{uuid}/preview`; server ho překóduje do WebP. Příště je to obyčejný obrázek. Modely
   z nástrojů bez náhledu ukazují obrázek karty nástroje. Náhledy využijí i karty designérů a OG obrázky.
2. **Existující účty se počítají jako ověřené.** Migrace nastaví `email_verified_at` všem dosavadním účtům
   (import ze starého webu, registrace na betě), jinak by se Roman a testovací zákazníci zamkli.
3. **Změna e‑mailu chce současné heslo** (má‑li účet heslo). Ukradená session tak účet nepřestěhuje.
4. **Ochrana proti předregistraci cizí adresy.** Když se přes Google přihlásí majitel adresy, na kterou si někdo
   dřív založil účet heslem a neověřil ji, heslo se zruší. Jinak by útočník znal heslo k účtu oběti.
5. **`users.deleted_at` není SoftDeletes.** Řádek uživatele musí zůstat dohledatelný pro objednávky v adminu;
   obě časová razítka se nastaví při anonymizaci.
6. **Smazaný model s historií.** `farm_orders.model_file_id` má `cascadeOnDelete`, smazání řádku modelu by smazalo
   i zrušené objednávky. Model se zrušenou objednávkou proto zůstane jako prázdná skořápka (`model_files.deleted_at`,
   soubory pryč), v seznamech není.
7. **Doručovací adresa u smazaného účtu:** u vydaných, zrušených a nepovedených objednávek se maže, u běžících
   zůstane do předání.
8. **Dashboard ukazuje 8 modelů**, stránkování po 24 je na `/account/models`.
9. **Texty přihlášení a registrace** jsou přepsané na vykání (cs) a usted (es), stejně jako nové texty nástrojů.
10. **Tiskařské přepínače rolí zmizely i z rout** (`account.roles.enable/disable`), protože `PrinterProfile` se nemá
    nikde zakládat. Tiskařské stránky zůstávají za `feature:marketplace` a rolí `printer`; testy si tiskaře
    zakládají přímo v databázi.

## Co zbývá

- Karta „Designérský profil“ na `/account` (krok B).
- Výběr výdejního místa se v profilu objeví až s `PACKETA_API_KEY` (krok D ho použije i v objednávce).
- Částky jsou zatím „Kč“ natvrdo; `@money` a měna účtu jsou krok D (`users.currency` už existuje).
- `events.user_id = null` při smazání účtu doplní krok E (posluchač `AccountErasing`).
- `feature/farm` má vlastní rozbitý test `TuningAdvisorTest::a clean print changes nothing` (od commitu bafdb28,
  jiná session). Tady se neopravuje.

## Nové `.env` klíče

| klíč | k čemu |
|---|---|
| `PACKETA_API_KEY` | klíč Zásilkovny; otevírá i výběr výdejního místa v prohlížeči |
| `PACKETA_API_PASS` | heslo API (krok D) |
| `PACKETA_ESHOP` | označení e‑shopu v Zásilkovně (krok D) |

## Testy

`tests/Feature/AccountsTest.php` (19 testů): registrace + ověřovací e‑mail, neověřený neobjedná ani nedobije,
odkaz z e‑mailu, Google účet, návrat na nástroj po přihlášení, pořadí bloků a žádné „tiskař“, filtr tisků,
mazání modelu, náhled modelu, profil, změna e‑mailu (včetně obsazeného, vypršelého a zrušeného), odpojení
posledního přihlášení, propojení Googlu, změna hesla, smazání účtu heslem i e‑mailem.

## Nasazení (Roman)

```
php artisan migrate --force      # 2026_10_02_100000_account_email_change_and_deletion
npm ci && npm run build
php artisan optimize
systemctl restart php8.2-fpm matplace-worker
```

Migrace přidá sloupce do `users` a `model_files` a označí dosavadní účty jako ověřené. Po nasazení zkusit:
registrace e‑mailem → lišta „ověřte e‑mail“ na `/farm` → odkaz z e‑mailu (na betě jde pošta přes
`MAIL_ALWAYS_TO` do info@) → objednávka projde.
