#!/bin/bash
# End-to-end smoke test for AS ESPORT. Exercises real HTTP flows against the
# local PHP dev server: registration, login, AJAX join, promo claim, admin login.
set -u
BASE="http://127.0.0.1:12000"
USERJ=/tmp/cj_user.txt
ADMINJ=/tmp/cj_admin.txt
rm -f "$USERJ" "$ADMINJ"

pass=0; fail=0
check() { # label expected actual
  if [ "$2" = "$3" ]; then echo "PASS  $1"; pass=$((pass+1));
  else echo "FAIL  $1 (expected '$2' got '$3')"; fail=$((fail+1)); fi
}

token_of() { grep -o 'name="csrf_token" value="[a-f0-9]*"\|name="csrf-token" content="[a-f0-9]*"' "$1" | head -1 | sed 's/.*="//;s/"//'; }

echo "=== 1. Register a user ==="
curl -s -c "$USERJ" "$BASE/login.php" -o /tmp/t_login.html
TOKEN=$(token_of /tmp/t_login.html)
check "csrf token present" "yes" "$([ -n "$TOKEN" ] && echo yes || echo no)"
curl -s -b "$USERJ" -c "$USERJ" -o /dev/null -w "%{http_code}" \
  -d "csrf_token=$TOKEN&action=register&name=Test+Player&username=testplayer&email=test@example.com&phone=9876543210&password=secret123&confirm_password=secret123" \
  "$BASE/login.php" > /tmp/code_reg
check "register redirects" "302" "$(cat /tmp/code_reg)"

echo "=== 2. Confirm logged-in session ==="
curl -s -b "$USERJ" "$BASE/index.php" -o /tmp/t_index.html
grep -q "testplayer\|walletBalance" /tmp/t_index.html && check "index served for user" "yes" "yes" || check "index served for user" "yes" "no"

echo "=== 3. Create a tournament as admin ==="
curl -s -c "$ADMINJ" "$BASE/admin/login.php" -o /tmp/t_alogin.html
ATOKEN=$(token_of /tmp/t_alogin.html)
curl -s -b "$ADMINJ" -c "$ADMINJ" -o /dev/null -w "%{http_code}" \
  -d "csrf_token=$ATOKEN&username=superadmin&password=Admin@12345" "$BASE/admin/login.php" > /tmp/code_alogin
check "admin login redirects" "302" "$(cat /tmp/code_alogin)"

curl -s -b "$ADMINJ" "$BASE/admin/tournament.php" -o /tmp/t_tour.html
ATOKEN=$(token_of /tmp/t_tour.html)
MATCH_TIME=$(date -d "+2 hours" '+%Y-%m-%dT%H:%M')
curl -s -b "$ADMINJ" -c "$ADMINJ" -o /dev/null -w "%{http_code}" \
  --data-urlencode "csrf_token=$ATOKEN" --data-urlencode "action=save" --data-urlencode "id=0" \
  --data-urlencode "title=Test Squad Cup" --data-urlencode "game=BGMI" --data-urlencode "map=Erangle" \
  --data-urlencode "mode=Squad" --data-urlencode "match_type=Battle Royale" \
  --data-urlencode "entry_fee=20" --data-urlencode "prize_pool=500" --data-urlencode "per_kill=10" \
  --data-urlencode "total_slots=50" --data-urlencode "max_slots_per_user=4" \
  --data-urlencode "match_time=$MATCH_TIME" --data-urlencode "status=upcoming" --data-urlencode "rules=Be on time" \
  "$BASE/admin/tournament.php" > /tmp/code_tour
check "tournament create redirects" "302" "$(cat /tmp/code_tour)"

echo "=== 4. Verify tournament row ==="
ROW=$(mariadb -h 127.0.0.1 -u asesport -pasesport_pass asesport -N -e "SELECT title,entry_fee,total_slots,status,match_time FROM tournaments ORDER BY id DESC LIMIT 1;")
echo "      DB row: $ROW"
check "tournament stored" "yes" "$(echo "$ROW" | grep -q 'Test Squad Cup' && echo yes || echo no)"

echo "=== 5. Seed wallet balance ==="
mariadb -h 127.0.0.1 -u asesport -pasesport_pass asesport -e "UPDATE users SET balance=100 WHERE username='testplayer';" 2>/dev/null
BAL=$(mariadb -h 127.0.0.1 -u asesport -pasesport_pass asesport -N -e "SELECT balance FROM users WHERE username='testplayer';")
check "balance seeded" "100.00" "$BAL"

echo "=== 6. AJAX get_balance ==="
UTOKEN=$(token_of /tmp/t_index.html)
RESP=$(curl -s -b "$USERJ" -H "X-CSRF-Token: $UTOKEN" -d "" "$BASE/ajax/get_balance.php")
echo "      $RESP"
check "get_balance success" "yes" "$(echo "$RESP" | grep -q '"success":true' && echo yes || echo no)"

echo "=== 7. AJAX join tournament (2 slots) ==="
TID=$(mariadb -h 127.0.0.1 -u asesport -pasesport_pass asesport -N -e "SELECT id FROM tournaments ORDER BY id DESC LIMIT 1;")
RESP=$(curl -s -b "$USERJ" -H "X-CSRF-Token: $UTOKEN" \
  --data-urlencode "tournament_id=$TID" --data-urlencode "slots=2" \
  --data-urlencode 'uids=["1111111111","2222222222"]' "$BASE/ajax/join_tournament.php")
echo "      $RESP"
check "join success" "yes" "$(echo "$RESP" | grep -q '"success":true' && echo yes || echo no)"
check "balance deducted to 60" "yes" "$(echo "$RESP" | grep -q '60.00' && echo yes || echo no)"

SLOTS=$(mariadb -h 127.0.0.1 -u asesport -pasesport_pass asesport -N -e "SELECT COUNT(*) FROM participants WHERE tournament_id=$TID;")
check "2 participant rows" "2" "$SLOTS"
FILLED=$(mariadb -h 127.0.0.1 -u asesport -pasesport_pass asesport -N -e "SELECT filled_slots FROM tournaments WHERE id=$TID;")
check "filled_slots updated" "2" "$FILLED"
LEDGER=$(mariadb -h 127.0.0.1 -u asesport -pasesport_pass asesport -N -e "SELECT COUNT(*) FROM transactions WHERE type='entry_fee';")
check "entry_fee ledger row" "1" "$LEDGER"

echo "=== 8. Per-user slot cap enforcement ==="
RESP=$(curl -s -b "$USERJ" -H "X-CSRF-Token: $UTOKEN" \
  --data-urlencode "tournament_id=$TID" --data-urlencode "slots=3" \
  --data-urlencode 'uids=["3333333333","4444444444","5555555555"]' "$BASE/ajax/join_tournament.php")
echo "      $RESP"
check "cap blocks 3 more slots" "yes" "$(echo "$RESP" | grep -q 'Max 4 per user' && echo yes || echo no)"

echo "=== 9. Promo code create + claim ==="
curl -s -b "$ADMINJ" "$BASE/admin/promo_codes.php" -o /tmp/t_promo.html
PTOKEN=$(token_of /tmp/t_promo.html)
curl -s -b "$ADMINJ" -c "$ADMINJ" -o /dev/null -w "%{http_code}" \
  --data-urlencode "csrf_token=$PTOKEN" --data-urlencode "action=create" \
  --data-urlencode "code=WELCOME50" --data-urlencode "amount=50" --data-urlencode "max_uses=10" \
  "$BASE/admin/promo_codes.php" > /tmp/code_promo
check "promo create redirects" "302" "$(cat /tmp/code_promo)"

RESP=$(curl -s -b "$USERJ" -H "X-CSRF-Token: $UTOKEN" -d "code=WELCOME50" "$BASE/ajax/claim_promo.php")
echo "      claim1: $RESP"
check "promo claim success" "yes" "$(echo "$RESP" | grep -q '"success":true' && echo yes || echo no)"

RESP=$(curl -s -b "$USERJ" -H "X-CSRF-Token: $UTOKEN" -d "code=WELCOME50" "$BASE/ajax/claim_promo.php")
echo "      claim2: $RESP"
check "promo single-use blocked" "yes" "$(echo "$RESP" | grep -q 'already used' && echo yes || echo no)"

echo "=== 10. Sub-admin scoping ==="
curl -s -b "$ADMINJ" "$BASE/admin/sub_admins.php" -o /tmp/t_sub.html
STOKEN=$(token_of /tmp/t_sub.html)
curl -s -b "$ADMINJ" -c "$ADMINJ" -o /dev/null -w "%{http_code}" \
  --data-urlencode "csrf_token=$STOKEN" --data-urlencode "action=create" \
  --data-urlencode "name=Sub Mod" --data-urlencode "username=submod" \
  --data-urlencode "email=sub@example.com" --data-urlencode "password=subpass123" \
  "$BASE/admin/sub_admins.php" > /tmp/code_sub
check "sub-admin create redirects" "302" "$(cat /tmp/code_sub)"

SUBJ=/tmp/cj_sub.txt; rm -f "$SUBJ"
curl -s -c "$SUBJ" "$BASE/admin/login.php" -o /tmp/t_slogin.html
STOK=$(token_of /tmp/t_slogin.html)
curl -s -b "$SUBJ" -c "$SUBJ" -o /dev/null -w "%{http_code}" \
  -d "csrf_token=$STOK&username=submod&password=subpass123" "$BASE/admin/login.php" > /tmp/code_slogin
check "sub-admin login redirects" "302" "$(cat /tmp/code_slogin)"

CODE=$(curl -s -b "$SUBJ" -o /tmp/t_settings.html -w "%{http_code}" "$BASE/admin/setting.php")
check "sub-admin blocked from settings" "403" "$CODE"
CODE=$(curl -s -b "$SUBJ" -o /tmp/t_promoadmin.html -w "%{http_code}" "$BASE/admin/promo_codes.php")
check "sub-admin blocked from promo admin" "403" "$CODE"
CODE=$(curl -s -b "$SUBJ" -o /dev/null -w "%{http_code}" "$BASE/admin/manage_tournament.php?id=$TID")
check "sub-admin allowed manage matches" "200" "$CODE"

echo "=== 11. Revoke sub-admin -> instant session destruction ==="
SUBI=$(mariadb -h 127.0.0.1 -u asesport -pasesport_pass asesport -N -e "SELECT id FROM admins WHERE username='submod';")
curl -s -b "$ADMINJ" -c "$ADMINJ" -o /dev/null \
  --data-urlencode "csrf_token=$STOKEN" --data-urlencode "action=toggle" --data-urlencode "id=$SUBI" \
  "$BASE/admin/sub_admins.php"
BODY=$(curl -s -b "$SUBJ" -L "$BASE/admin/index.php")
check "revoked sub-admin forced to login" "yes" "$(echo "$BODY" | grep -q 'Admin Panel' && echo yes || echo no)"

echo "=== 12. Room credentials + winner declaration ==="
curl -s -b "$ADMINJ" -c "$ADMINJ" -o /dev/null \
  --data-urlencode "csrf_token=$PTOKEN" --data-urlencode "action=room" \
  --data-urlencode "room_id=99887766" --data-urlencode "room_password=aspass" \
  "$BASE/admin/manage_tournament.php?id=$TID"
ROOM=$(mariadb -h 127.0.0.1 -u asesport -pasesport_pass asesport -N -e "SELECT room_id,room_password FROM tournaments WHERE id=$TID;")
echo "      room: $ROOM"
check "room stored" "99887766	aspass" "$ROOM"

PID=$(mariadb -h 127.0.0.1 -u asesport -pasesport_pass asesport -N -e "SELECT id FROM participants WHERE tournament_id=$TID ORDER BY slot_no LIMIT 1;")
curl -s -b "$ADMINJ" -c "$ADMINJ" -o /dev/null \
  --data-urlencode "csrf_token=$PTOKEN" --data-urlencode "action=winner" \
  --data-urlencode "participant_id=$PID" --data-urlencode "prize=500" \
  "$BASE/admin/manage_tournament.php?id=$TID"
PRIZE=$(mariadb -h 127.0.0.1 -u asesport -pasesport_pass asesport -N -e "SELECT COUNT(*) FROM transactions WHERE type='prize';")
check "prize ledger row" "1" "$PRIZE"

echo "=== 13. Deposit submit + approve ==="
curl -s -b "$USERJ" "$BASE/wallet.php" -o /tmp/t_wallet.html
WTOKEN=$(token_of /tmp/t_wallet.html)
curl -s -b "$USERJ" -c "$USERJ" -o /dev/null -w "%{http_code}" \
  --data-urlencode "csrf_token=$WTOKEN" --data-urlencode "action=deposit" \
  --data-urlencode "amount=200" --data-urlencode "utr=UTR123456" \
  "$BASE/wallet.php" > /tmp/code_dep
# A valid deposit renders the in-page "under review" popup rather than redirecting.
check "deposit submit renders popup" "200" "$(cat /tmp/code_dep)"
grep -q "Deposit Under Review" /tmp/t_wallet.html 2>/dev/null || true
DEP_DONE=$(curl -s -b "$USERJ" --data-urlencode "csrf_token=$WTOKEN" --data-urlencode "action=deposit" \
  --data-urlencode "amount=200" --data-urlencode "utr=UTR123456" "$BASE/wallet.php")
check "deposit popup shown" "yes" "$(echo "$DEP_DONE" | grep -q 'Deposit Under Review' && echo yes || echo no)"

DID=$(mariadb -h 127.0.0.1 -u asesport -pasesport_pass asesport -N -e "SELECT id FROM deposits ORDER BY id DESC LIMIT 1;")
curl -s -b "$ADMINJ" "$BASE/admin/deposits.php" -o /tmp/t_deps.html
DTOKEN=$(token_of /tmp/t_deps.html)
curl -s -b "$ADMINJ" -c "$ADMINJ" -o /dev/null \
  --data-urlencode "csrf_token=$DTOKEN" --data-urlencode "id=$DID" --data-urlencode "decision=approve" \
  "$BASE/admin/deposits.php"
DSTATUS=$(mariadb -h 127.0.0.1 -u asesport -pasesport_pass asesport -N -e "SELECT status FROM deposits WHERE id=$DID;")
check "deposit approved" "approved" "$DSTATUS"
DBAL=$(mariadb -h 127.0.0.1 -u asesport -pasesport_pass asesport -N -e "SELECT balance FROM users WHERE username='testplayer';")
echo "      balance after deposit+promo+prize: $DBAL"

echo "=== 14. Withdrawal request + approve ==="
curl -s -b "$USERJ" -c "$USERJ" -o /dev/null \
  --data-urlencode "csrf_token=$WTOKEN" --data-urlencode "action=withdraw" \
  --data-urlencode "amount=200" --data-urlencode "upi_id=test@upi" \
  "$BASE/wallet.php"
WID=$(mariadb -h 127.0.0.1 -u asesport -pasesport_pass asesport -N -e "SELECT id FROM withdrawals ORDER BY id DESC LIMIT 1;")
curl -s -b "$ADMINJ" "$BASE/admin/withdrawals.php" -o /tmp/t_wds.html
WTOKEN2=$(token_of /tmp/t_wds.html)
curl -s -b "$ADMINJ" -c "$ADMINJ" -o /dev/null \
  --data-urlencode "csrf_token=$WTOKEN2" --data-urlencode "id=$WID" --data-urlencode "decision=approve" \
  "$BASE/admin/withdrawals.php"
WSTATUS=$(mariadb -h 127.0.0.1 -u asesport -pasesport_pass asesport -N -e "SELECT status FROM withdrawals WHERE id=$WID;")
check "withdrawal approved" "approved" "$WSTATUS"

echo
echo "=================================="
echo "PASS: $pass   FAIL: $fail"
echo "=================================="
exit $fail
