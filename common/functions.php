<?php
/**
 * Shared helpers: escaping, CSRF, flash messages, auth guards, time formatting.
 */

if (!function_exists('e')) {
    /** Escape untrusted text for HTML output. */
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('csrftoken')) {
    /** Lazily create and return the per-session CSRF token. */
    function csrftoken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . e(csrftoken()) . '">';
    }
}

if (!function_exists('verify_csrf')) {
    /**
     * Validate a submitted token. Aborts the request when it does not match.
     * Accepts the token from $_POST or the X-CSRF-Token header (for AJAX).
     */
    function verify_csrf(): void
    {
        $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!is_string($token) || $token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(419);
            header('Content-Type: application/json');
            exit(json_encode(['success' => false, 'message' => 'Security token expired. Please refresh the page.']));
        }
    }
}

if (!function_exists('is_post')) {
    function is_post(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }
}

if (!function_exists('json_response')) {
    function json_response(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
        exit;
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path): void
    {
        header('Location: ' . $path);
        exit;
    }
}

if (!function_exists('flash')) {
    /** Queue or read a one-shot flash message. */
    function flash(?string $type = null, ?string $message = null)
    {
        if ($type !== null && $message !== null) {
            $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
            return null;
        }
        $messages = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $messages;
    }
}

if (!function_exists('current_user')) {
    function current_user(PDO $pdo): ?array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if (!$user || (int) $user['status'] !== 1) {
            unset($_SESSION['user_id']);
            return null;
        }
        $cache = $user;
        return $cache;
    }
}

if (!function_exists('require_user')) {
    /** Page guard: bounce anonymous visitors to login.php. */
    function require_user(PDO $pdo): array
    {
        $user = current_user($pdo);
        if (!$user) {
            redirect('login.php');
        }
        return $user;
    }
}

if (!function_exists('require_admin')) {
    /** Admin guard: bounce to admin/login.php unless a valid admin session exists. */
    function require_admin(PDO $pdo): array
    {
        if (empty($_SESSION['admin_id'])) {
            redirect('login.php');
        }
        $stmt = $pdo->prepare('SELECT * FROM admins WHERE id = ? LIMIT 1');
        $stmt->execute([$_SESSION['admin_id']]);
        $admin = $stmt->fetch();

        // Revoked or disabled sub-admins lose access on the very next request.
        if (!$admin || (int) $admin['status'] !== 1) {
            admin_logout();
            redirect('login.php');
        }
        return $admin;
    }
}

if (!function_exists('admin_logout')) {
    function admin_logout(): void
    {
        unset($_SESSION['admin_id'], $_SESSION['admin_role'], $_SESSION['admin_name']);
    }
}

if (!function_exists('is_super_admin')) {
    function is_super_admin(): bool
    {
        return ($_SESSION['admin_role'] ?? '') === 'super';
    }
}

if (!function_exists('require_super_admin')) {
    /** Feature guard for settings, promo creation and sub-admin management. */
    function require_super_admin(PDO $pdo): array
    {
        $admin = require_admin($pdo);
        if ($admin['role'] !== 'super') {
            http_response_code(403);
            exit('Access denied. Super Admin privileges are required.');
        }
        return $admin;
    }
}

if (!function_exists('format_match_time')) {
    /** Render a stored DATETIME as IST. No JS-side shifting is permitted. */
    function format_match_time(?string $datetime): string
    {
        if (!$datetime) {
            return '-';
        }
        $ts = strtotime($datetime);
        return $ts ? date('d M Y, h:i A', $ts) : '-';
    }
}

if (!function_exists('money')) {
    function money($amount): string
    {
        return '₹' . number_format((float) $amount, 2);
    }
}

if (!function_exists('setting')) {
    /** Read a site setting, falling back to the supplied default. */
    function setting(PDO $pdo, string $key, $default = null)
    {
        static $cache = null;
        if ($cache === null) {
            $cache = [];
            try {
                foreach ($pdo->query('SELECT setting_key, setting_value FROM settings') as $row) {
                    $cache[$row['setting_key']] = $row['setting_value'];
                }
            } catch (PDOException $e) {
                $cache = [];
            }
        }
        return array_key_exists($key, $cache) && $cache[$key] !== '' ? $cache[$key] : $default;
    }
}

if (!function_exists('device_fingerprint')) {
    /** Stable per-browser fingerprint used for single-use promo enforcement. */
    function device_fingerprint(): string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        $ip = client_ip();
        return hash('sha256', $ua . '|' . $lang . '|' . $ip);
    }
}

if (!function_exists('client_ip')) {
    function client_ip(): string
    {
        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($forwarded !== '') {
            $parts = explode(',', $forwarded);
            return trim($parts[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

if (!function_exists('tournament_status')) {
    /** Derive a display status from stored state plus the live IST clock. */
    function tournament_status(array $t): string
    {
        if ($t['status'] === 'cancelled') {
            return 'cancelled';
        }
        if ($t['status'] === 'completed') {
            return 'completed';
        }
        if (!empty($t['room_id']) && $t['status'] !== 'completed') {
            return 'room_released';
        }
        return 'upcoming';
    }
}

if (!function_exists('status_badge')) {
    function status_badge(string $status): string
    {
        $map = [
            'upcoming'      => ['Upcoming', 'bg-sky-500/15 text-sky-300 border-sky-500/30'],
            'room_released' => ['Room Ready', 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30'],
            'completed'     => ['Completed', 'bg-slate-500/15 text-slate-300 border-slate-500/30'],
            'cancelled'     => ['Cancelled', 'bg-rose-500/15 text-rose-300 border-rose-500/30'],
        ];
        [$label, $classes] = $map[$status] ?? ['Unknown', 'bg-slate-500/15 text-slate-300 border-slate-500/30'];
        return '<span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold ' . $classes . '">' . e($label) . '</span>';
    }
}
