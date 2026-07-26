# Checkpoint — Entrada slice (Login + Registro tokenizados + migração de tema)

Date: 2026-07-22

## Task
Vertical slice: 15-theme tokenized design (Claude Design "NovixFin Entrada.html" v4)
applied to login/register, users.theme migrated to the 15-slug system, and the
localStorage -> DB theme bridge at signup. No financial core touched.

## Pre-edit grep report (theme writers/validators)
- `actions/profile/update-theme.php` — ONLY writer of users.theme; had the old
  `['dark','black','light']` whitelist. Updated.
- `includes/auth.php createUser()` — did not write theme (column default). Now
  writes the validated bridge value.
- Readers with old 'dark' fallback (NOT writers): `includes/auth.php` session
  build (updated to 'ametista'), `includes/layout/header.php`, `public/profile.php`
  selector, `public/scripts/dashboard.js` (App slice — intentionally untouched).

## Files changed
- NEW `includes/themes.php` — canonical whitelist (15 slugs) + `novix_valid_theme()`,
  `NOVIX_THEME_DEFAULT = 'ametista'`.
- NEW `migrations/20240101000007_theme_15_slugs_v3.php` — guarded, idempotent,
  non-destructive. users.theme -> VARCHAR(20) NOT NULL DEFAULT 'ametista'.
  Backfill mapping (documented decision): light -> ametista; dark/black -> tinta;
  NULL/unknown -> ametista.
- `actions/profile/update-theme.php` — new whitelist, fallback 'ametista';
  CSRF + requireAuth preserved.
- `includes/auth.php` — `createUser()` gains optional `$theme` (validated,
  default 'ametista') and inserts users.theme; session theme fallback now
  validated against the whitelist.
- NEW `public/styles/entrada.css` — 15 theme token blocks + entrada components
  (ported from the approved design; corners via --cut/--r).
- NEW `includes/layout/entrada.php` — shared head snippet (pre-paint theme from
  localStorage['novixfin-theme'] + per-theme Google Fonts), crystal brand panel,
  parallax script (reduced-motion aware).
- `public/login.php` — HTML reskinned only; auth logic/CSRF/throttle untouched.
  Error banners: throttle -> gold clock, credentials/others -> negative; success -> positive.
- `public/register.php` — HTML reskinned; hidden `theme` input filled by JS from
  localStorage (on load + on submit) and passed to `createUser()`.

## Decisions
- "Esqueci minha senha": link REMOVED (no reset backend exists; honest UI, no
  partial reset flow). Revisit as its own slice.
- Legacy dark/black -> 'tinta' (dark ink theme), light -> 'ametista'.
- Demo-only state chips from the design mock were not ported (design artifacts).
- `marmore-trim.png` texture strip omitted (asset not in repo; subtle 0.22-opacity
  layer — can be added later with the real asset).
- Internal app pages still hardcode dark Tailwind + themes.css (dark/black/light);
  unknown new slugs simply render the default dark look until the App slice.
- `public/profile.php` still shows the old 3-option selector (App slice). Choosing
  there now safely falls back to 'ametista' via the new whitelist.

## Validation performed
- Local PHP unavailable — static checks only:
  - PHP 8-only syntax scan on all changed files: clean (JS `catch (e)` false positives only).
  - Brace/paren balance per file: balanced.
- Server-side (run on Hostinger before/at deploy):
  - `php -l public/login.php public/register.php includes/auth.php includes/themes.php includes/layout/entrada.php actions/profile/update-theme.php migrations/20240101000007_theme_15_slugs_v3.php`
  - `php scripts/migrate.php up` (backup DB first)
  - `php tests/smoke.php`
  - Manual browser: login states (erro credenciais, throttle, sucesso), registro
    (validações, email duplicado, throttle, sucesso -> login), theme inheritance
    pre-paint from landing, signup theme persisted in users.theme.

## Risks
- Registration now INSERTs a `theme` column: deploy the migration BEFORE (or with)
  the code. On legacy DBs missing the column the migration adds it.
- CSS uses `color-mix()`/`clip-path` per the approved design — modern browsers only.

## Remaining work
- App + Landing slices (do not start without review).
- Confidence: 85% (pending server lint/smoke + browser pass).
