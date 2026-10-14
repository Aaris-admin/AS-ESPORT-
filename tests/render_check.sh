#!/bin/bash
# Renders every page with a live session and reports HTTP status + PHP error count.
set -u
BASE="http://127.0.0.1:12000"
rm -f /tmp/cj_u /tmp/cj_a

curl -s -c /tmp/cj_u "$BASE/login.php" -o /tmp/p.html
T=$(grep -o 'name="csrf_token" value="[a-f0-9]*"' /tmp/p.html | head -1 | sed 's/.*="//;s/"//')
curl -s -b /tmp/cj_u -c /tmp/cj_u -o /dev/null -d "csrf_token=$T&identity=testplayer&password=secret123" "$BASE/login.php"

curl -s -c /tmp/cj_a "$BASE/admin/login.php" -o /tmp/pa.html
A=$(grep -o 'name="csrf_token" value="[a-f0-9]*"' /tmp/pa.html | head -1 | sed 's/.*="//;s/"//')
curl -s -b /tmp/cj_a -c /tmp/cj_a -o /dev/null -d "csrf_token=$A&username=superadmin&password=Admin@12345" "$BASE/admin/login.php"

TID=$(mariadb -h 127.0.0.1 -u asesport -pasesport_pass asesport -N -e "SELECT id FROM tournaments ORDER BY id DESC LIMIT 1;" 2>/dev/null)
UID_=$(mariadb -h 127.0.0.1 -u asesport -pasesport_pass asesport -N -e "SELECT id FROM users ORDER BY id DESC LIMIT 1;" 2>/dev/null)

echo "=== USER PAGES ==="
for p in index.php my_tournaments.php wallet.php promo.php profile.php "index.php?filter=upcoming" "my_tournaments.php?id=$TID"; do
  code=$(curl -s -b /tmp/cj_u -o /tmp/pg.html -w "%{http_code}" "$BASE/$p")
  err=$(grep -ciE "Fatal error|Parse error|Warning:|Notice:|Deprecated:" /tmp/pg.html)
  echo "  $code  err=$err  $p"
done

echo "=== ADMIN PAGES ==="
for p in index.php tournament.php "tournament.php?edit=$TID" "manage_tournament.php?id=$TID" deposits.php withdrawals.php promo_codes.php sub_admins.php user.php "user.php?id=$UID_" setting.php; do
  code=$(curl -s -b /tmp/cj_a -o /tmp/pg.html -w "%{http_code}" "$BASE/admin/$p")
  err=$(grep -ciE "Fatal error|Parse error|Warning:|Notice:|Deprecated:" /tmp/pg.html)
  echo "  $code  err=$err  admin/$p"
done

echo "=== SERVER WARNINGS (fresh log) ==="
grep -icE "Warning:|Fatal|Notice:|Deprecated:" /tmp/php_server2.log
