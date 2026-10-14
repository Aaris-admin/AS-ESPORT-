# AS ESPORT

Dark modern esports tournament platform. Core PHP (no framework), MySQL via
PDO prepared statements, Tailwind CSS, Font Awesome, vanilla JS with the Fetch
API.

## Requirements

- PHP 8.1+ with `pdo_mysql`, `mbstring`, `fileinfo` (and `curl` for the CDN assets)
- MySQL 5.7+ / MariaDB 10.3+

## Install

1. Point your web server document root at this directory.
2. Set database credentials as environment variables (or edit the defaults in
   `config.php`): `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`.
3. Create the database, then run the installer:

   ```bash
   php install.php
   # or open http://your-host/install.php
   ```

   This creates all tables, seeds default settings and provisions the first
   Super Admin. Override the seeded credentials with `SUPER_ADMIN_USER` and
   `SUPER_ADMIN_PASS` before running.

4. Delete `install.php` from the production host.
5. Log in at `/admin/login.php` and set your UPI ID / QR code under Settings.

For an existing installation, run `php update_database.php` after deploying new
code. Every migration is idempotent and never drops data.

Set `APP_ENV=production` in production so verbose errors are suppressed.

## Timezone contract

The whole application runs on **Asia/Kolkata (IST, UTC+5:30)**:

- `config.php` calls `date_default_timezone_set('Asia/Kolkata')`.
- Every PDO connection issues `SET time_zone = '+05:30'`.
- `match_time` is a plain `DATETIME` holding IST wall-clock time.
- Times are rendered with `date('d M Y, h:i A', strtotime($match_time))`.
- The browser never shifts timestamps.

## Directory layout

```
config.php                     bootstrap: timezone, session, PDO, constants
install.php                    first-run installer
update_database.php            idempotent migrations
login.php                      login + registration
index.php                      tournament listing + join modal
promo.php                      promo redemption + channel links
my_tournaments.php             entries, room credentials, results
wallet.php                     deposits, withdrawals, ledger
profile.php                    account, password, support links
common/header.php              front-end layout + theme
common/bottom.php              bottom nav + shared JS runtime
common/functions.php           helpers: escaping, CSRF, auth guards, formatting
ajax/get_balance.php           live balance
ajax/join_tournament.php       transactional 1-4 slot join
ajax/claim_promo.php           single-use promo claim
admin/login.php                admin authentication
admin/index.php                dashboard
admin/tournament.php           create / edit / delete tournaments
admin/manage_tournament.php    room credentials, winners, prize credit
admin/deposits.php             deposit approvals
admin/withdrawals.php          withdrawal approvals
admin/promo_codes.php          promo management (Super Admin)
admin/sub_admins.php           sub-admin management (Super Admin)
admin/user.php                 user list, drill-down, wallet adjustment
admin/setting.php              site settings (Super Admin)
admin/partials/                shared admin layout
tests/e2e.sh                   end-to-end HTTP smoke test
tests/render_check.sh          page render + error check
```

## Security notes

- All queries use PDO prepared statements; user input is validated and escaped
  with `htmlspecialchars` on output.
- Every state-changing POST is CSRF-protected via a per-session token.
- Passwords are stored with `password_hash()` (bcrypt).
- Sessions are `HttpOnly`, `SameSite=Lax`, and `Secure` when served over HTTPS.
- Money movement (joins, deposits, withdrawals, prizes, promo claims) runs in
  transactions with `SELECT ... FOR UPDATE` row locks.
- Sub-admins are restricted to room credentials and winner declaration;
  revocation takes effect on their next request.
- Promo codes are single-use per user and per device.
- `assets/uploads/` denies script execution.

## Tests

With the app served on `http://127.0.0.1:12000` and a reachable MySQL:

```bash
bash tests/e2e.sh          # full HTTP flow, 29 assertions
bash tests/render_check.sh # renders every page, asserts no PHP errors
```
