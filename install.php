<?php
/**
 * AS ESPORT - Installer.
 *
 * Creates every table, seeds default settings and provisions the first Super
 * Admin. Safe to re-run: all statements are idempotent. Re-running never
 * overwrites an existing Super Admin password.
 *
 * Usage (CLI):  php install.php
 * Usage (web):  http://host/install.php  (delete this file afterwards)
 */

require_once __DIR__ . '/config.php';

$isCli = PHP_SAPI === 'cli';
$log = [];
$errors = [];

/** Execute a statement, recording a friendly line for the install log. */
function run(PDO $pdo, string $label, string $sql, array &$log, array &$errors): void
{
    try {
        $pdo->exec($sql);
        $log[] = "OK   - {$label}";
    } catch (PDOException $e) {
        $errors[] = "FAIL - {$label}: " . $e->getMessage();
    }
}

$schema = [
    'users' => "
        CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            username VARCHAR(50) NOT NULL,
            email VARCHAR(150) NOT NULL,
            phone VARCHAR(20) NOT NULL DEFAULT '',
            password VARCHAR(255) NOT NULL,
            balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            device_id VARCHAR(64) NOT NULL DEFAULT '',
            status TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_users_username (username),
            UNIQUE KEY uq_users_email (email),
            KEY idx_users_device (device_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'admins' => "
        CREATE TABLE IF NOT EXISTS admins (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            username VARCHAR(50) NOT NULL,
            email VARCHAR(150) NOT NULL DEFAULT '',
            password VARCHAR(255) NOT NULL,
            role ENUM('super','sub') NOT NULL DEFAULT 'sub',
            status TINYINT(1) NOT NULL DEFAULT 1,
            created_by INT UNSIGNED DEFAULT NULL,
            last_login DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_admins_username (username),
            KEY idx_admins_role (role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'tournaments' => "
        CREATE TABLE IF NOT EXISTS tournaments (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(150) NOT NULL,
            game VARCHAR(80) NOT NULL DEFAULT 'BGMI',
            map VARCHAR(80) NOT NULL DEFAULT 'Erangel',
            mode VARCHAR(40) NOT NULL DEFAULT 'Squad',
            match_type VARCHAR(40) NOT NULL DEFAULT 'Battle Royale',
            entry_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            prize_pool DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            per_kill DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            total_slots INT UNSIGNED NOT NULL DEFAULT 100,
            filled_slots INT UNSIGNED NOT NULL DEFAULT 0,
            max_slots_per_user TINYINT UNSIGNED NOT NULL DEFAULT 4,
            match_time DATETIME NOT NULL,
            room_id VARCHAR(60) NOT NULL DEFAULT '',
            room_password VARCHAR(60) NOT NULL DEFAULT '',
            rules TEXT,
            banner VARCHAR(255) NOT NULL DEFAULT '',
            status ENUM('upcoming','completed','cancelled') NOT NULL DEFAULT 'upcoming',
            winner_user_id INT UNSIGNED DEFAULT NULL,
            result_note VARCHAR(255) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_tournaments_time (match_time),
            KEY idx_tournaments_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'participants' => "
        CREATE TABLE IF NOT EXISTS participants (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tournament_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            slot_no INT UNSIGNED NOT NULL,
            in_game_uid VARCHAR(64) NOT NULL,
            in_game_name VARCHAR(64) NOT NULL DEFAULT '',
            entry_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            is_winner TINYINT(1) NOT NULL DEFAULT 0,
            joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_participant_slot (tournament_id, slot_no),
            KEY idx_participant_user (user_id),
            CONSTRAINT fk_participant_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments (id) ON DELETE CASCADE,
            CONSTRAINT fk_participant_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'deposits' => "
        CREATE TABLE IF NOT EXISTS deposits (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            txn_id VARCHAR(100) NOT NULL DEFAULT '',
            utr VARCHAR(100) NOT NULL DEFAULT '',
            method VARCHAR(40) NOT NULL DEFAULT 'upi',
            screenshot VARCHAR(255) NOT NULL DEFAULT '',
            status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            admin_note VARCHAR(255) NOT NULL DEFAULT '',
            processed_by INT UNSIGNED DEFAULT NULL,
            processed_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_deposits_user (user_id),
            KEY idx_deposits_status (status),
            CONSTRAINT fk_deposits_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'withdrawals' => "
        CREATE TABLE IF NOT EXISTS withdrawals (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            upi_id VARCHAR(120) NOT NULL,
            status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            admin_note VARCHAR(255) NOT NULL DEFAULT '',
            processed_by INT UNSIGNED DEFAULT NULL,
            processed_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_withdrawals_user (user_id),
            KEY idx_withdrawals_status (status),
            CONSTRAINT fk_withdrawals_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'promo_codes' => "
        CREATE TABLE IF NOT EXISTS promo_codes (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(50) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            max_uses INT UNSIGNED NOT NULL DEFAULT 1,
            used_count INT UNSIGNED NOT NULL DEFAULT 0,
            expires_at DATETIME DEFAULT NULL,
            status TINYINT(1) NOT NULL DEFAULT 1,
            created_by INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_promo_code (code),
            KEY idx_promo_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'promo_claims' => "
        CREATE TABLE IF NOT EXISTS promo_claims (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            promo_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            device_id VARCHAR(64) NOT NULL DEFAULT '',
            amount DECIMAL(10,2) NOT NULL,
            claimed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_claim_user (promo_id, user_id),
            KEY idx_claim_device (device_id),
            CONSTRAINT fk_claim_promo FOREIGN KEY (promo_id) REFERENCES promo_codes (id) ON DELETE CASCADE,
            CONSTRAINT fk_claim_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'transactions' => "
        CREATE TABLE IF NOT EXISTS transactions (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'settings' => "
        CREATE TABLE IF NOT EXISTS settings (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            setting_key VARCHAR(80) NOT NULL,
            setting_value TEXT,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_setting_key (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];

foreach ($schema as $label => $sql) {
    run($pdo, "table:{$label}", $sql, $log, $errors);
}

// ---- Seed default settings ------------------------------------------------
$defaults = [
    'site_name'        => SITE_NAME,
    'upi_id'           => 'asesport@upi',
    'upi_name'         => 'AS ESPORT',
    'qr_image'         => '',
    'min_deposit'      => '50',
    'min_withdrawal'   => '100',
    'whatsapp'         => SUPPORT_WHATSAPP,
    'email'            => SUPPORT_EMAIL,
    'instagram'        => SUPPORT_INSTAGRAM,
    'youtube'          => SUPPORT_YOUTUBE,
    'whatsapp_channel' => PROMO_WHATSAPP_CHANNEL,
    'telegram'         => PROMO_TELEGRAM,
    'deposit_note'     => 'Payments are reviewed within 5-10 minutes.',
];
$stmt = $pdo->prepare('INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)');
foreach ($defaults as $k => $v) {
    $stmt->execute([$k, $v]);
}
$log[] = 'OK   - seed:settings';

// ---- Provision the first Super Admin --------------------------------------
$superUser = getenv('SUPER_ADMIN_USER') ?: 'superadmin';
$superPass = getenv('SUPER_ADMIN_PASS') ?: 'Admin@12345';

$exists = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE role = 'super'");
$exists->execute();
if ((int) $exists->fetchColumn() === 0) {
    $insert = $pdo->prepare("INSERT INTO admins (name, username, email, password, role, status) VALUES (?, ?, ?, ?, 'super', 1)");
    $insert->execute([
        'Super Admin',
        $superUser,
        'help.asesprot@gmail.com',
        password_hash($superPass, PASSWORD_DEFAULT),
    ]);
    $log[] = "OK   - super admin created (username: {$superUser})";
} else {
    $log[] = 'SKIP - super admin already exists';
}

// ---- Report ---------------------------------------------------------------
if ($isCli) {
    echo "AS ESPORT installer\n";
    echo str_repeat('=', 50) . "\n";
    foreach ($log as $line) {
        echo $line . "\n";
    }
    foreach ($errors as $line) {
        echo $line . "\n";
    }
    echo str_repeat('=', 50) . "\n";
    echo count($errors) ? "Finished WITH ERRORS.\n" : "Install complete.\n";
    echo "Super Admin login: admin/login.php\n";
    exit(count($errors) ? 1 : 0);
}

$ok = !$errors;
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Install - <?= e(SITE_NAME) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#0d0f17] text-slate-200 min-h-screen flex items-center justify-center p-4">
<div class="w-full max-w-2xl bg-[#13151f] border border-white/10 rounded-2xl p-6">
    <h1 class="text-2xl font-bold text-white mb-1">AS ESPORT Installer</h1>
    <p class="text-sm text-slate-400 mb-5">Schema, settings and Super Admin provisioning.</p>
    <pre class="bg-black/40 rounded-lg p-4 text-xs leading-6 overflow-auto max-h-80"><?php
        foreach ($log as $line) { echo e($line) . "\n"; }
        foreach ($errors as $line) { echo e($line) . "\n"; }
    ?></pre>
    <?php if ($ok): ?>
        <div class="mt-5 rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm">
            <p class="font-semibold text-emerald-300">Install complete.</p>
            <p class="text-slate-300 mt-1">Super Admin: <code class="text-emerald-300"><?= e($superUser) ?></code>
            &middot; Password: <code class="text-emerald-300"><?= e($superPass) ?></code></p>
            <p class="text-rose-300 mt-2 font-semibold">Delete install.php before going live.</p>
        </div>
        <a href="admin/login.php" class="mt-4 inline-block rounded-lg bg-[#00f2fe] px-5 py-2.5 font-semibold text-[#0d0f17]">Go to Admin Login</a>
    <?php else: ?>
        <div class="mt-5 rounded-lg border border-rose-500/30 bg-rose-500/10 p-4 text-sm text-rose-300">
            Install finished with errors. Review the log above.
        </div>
    <?php endif; ?>
</div>
</body>
</html>
