<?php
/**
 * AS ESPORT - Schema updater / migration runner.
 *
 * Brings an existing installation up to date without dropping data. Every
 * migration is guarded so it is safe to run repeatedly. Run this after
 * deploying new code:
 *
 *   CLI: php update_database.php
 *   Web: http://host/update_database.php
 */

require_once __DIR__ . '/config.php';

$isCli = PHP_SAPI === 'cli';
$log = [];

/** True when a table exists in the current database. */
function table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

/** True when a column exists on a table. */
function column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

/** Add a column only when it is missing. */
function add_column(PDO $pdo, string $table, string $column, string $definition, array &$log): void
{
    if (!table_exists($pdo, $table)) {
        $log[] = "SKIP - {$table}.{$column} (table missing)";
        return;
    }
    if (column_exists($pdo, $table, $column)) {
        $log[] = "SKIP - {$table}.{$column} already present";
        return;
    }
    $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
    $log[] = "OK   - added {$table}.{$column}";
}

$migrations = [
    // 1.1 - in-game identity and per-user slot limit on tournaments
    function (PDO $pdo, array &$log) {
        add_column($pdo, 'tournaments', 'max_slots_per_user', 'TINYINT UNSIGNED NOT NULL DEFAULT 4 AFTER filled_slots', $log);
        add_column($pdo, 'tournaments', 'result_note', "VARCHAR(255) NOT NULL DEFAULT '' AFTER winner_user_id", $log);
    },
    // 1.2 - device binding for single-use promo enforcement
    function (PDO $pdo, array &$log) {
        add_column($pdo, 'users', 'device_id', "VARCHAR(64) NOT NULL DEFAULT '' AFTER balance", $log);
        add_column($pdo, 'promo_claims', 'device_id', "VARCHAR(64) NOT NULL DEFAULT '' AFTER user_id", $log);
    },
    // 1.3 - deposit screenshot + UTR columns on legacy installs
    function (PDO $pdo, array &$log) {
        add_column($pdo, 'deposits', 'screenshot', "VARCHAR(255) NOT NULL DEFAULT '' AFTER method", $log);
        add_column($pdo, 'deposits', 'utr', "VARCHAR(100) NOT NULL DEFAULT '' AFTER txn_id", $log);
    },
    // 1.4 - transaction audit trail
    function (PDO $pdo, array &$log) {
        if (table_exists($pdo, 'transactions')) {
            $log[] = 'SKIP - transactions table already present';
            return;
        }
        $pdo->exec("CREATE TABLE transactions (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            type ENUM('deposit','withdrawal','entry_fee','prize','promo','refund','admin_credit','admin_debit') NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            balance_after DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            reference VARCHAR(120) NOT NULL DEFAULT '',
            note VARCHAR(255) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_tx_user (user_id),
            KEY idx_tx_type (type),
            CONSTRAINT fk_tx_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $log[] = 'OK   - created transactions table';
    },
    // 1.5 - default payment settings for installs created before the wallet UI
    function (PDO $pdo, array &$log) {
        $stmt = $pdo->prepare('INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)');
        $defaults = [
            'upi_id'         => 'asesport@upi',
            'upi_name'       => 'AS ESPORT',
            'min_deposit'    => '50',
            'min_withdrawal' => '100',
            'deposit_note'   => 'Payments are reviewed within 5-10 minutes.',
        ];
        foreach ($defaults as $k => $v) {
            $stmt->execute([$k, $v]);
        }
        $log[] = 'OK   - ensured default settings';
    },
];

foreach ($migrations as $i => $migration) {
    try {
        $migration($pdo, $log);
    } catch (PDOException $e) {
        $log[] = 'FAIL - migration #' . ($i + 1) . ': ' . $e->getMessage();
    }
}

// Report the clock contract so the operator can confirm IST sync.
$dbNow = $pdo->query('SELECT NOW()')->fetchColumn();
$log[] = 'INFO - MySQL session timezone: ' . $pdo->query('SELECT @@session.time_zone')->fetchColumn();
$log[] = 'INFO - MySQL NOW() (IST): ' . $dbNow;
$log[] = 'INFO - PHP date() (IST): ' . date('Y-m-d H:i:s');

if ($isCli) {
    foreach ($log as $line) {
        echo $line . "\n";
    }
    exit(0);
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Update Database - <?= e(SITE_NAME) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#0d0f17] text-slate-200 min-h-screen flex items-center justify-center p-4">
<div class="w-full max-w-2xl bg-[#13151f] border border-white/10 rounded-2xl p-6">
    <h1 class="text-2xl font-bold text-white mb-4">Database Update</h1>
    <pre class="bg-black/40 rounded-lg p-4 text-xs leading-6 overflow-auto max-h-96"><?php
        foreach ($log as $line) { echo e($line) . "\n"; }
    ?></pre>
    <a href="admin/login.php" class="mt-4 inline-block rounded-lg bg-[#00f2fe] px-5 py-2.5 font-semibold text-[#0d0f17]">Admin Login</a>
</div>
</body>
</html>
