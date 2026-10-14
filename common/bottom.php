<?php
/**
 * AS ESPORT - Closes the front-end layout: bottom navigation, toast host and
 * shared JavaScript. Pages append markup to $pageScripts for page-specific JS.
 */
?>
</main>

<!-- Toast host -->
<div id="toastHost" class="pointer-events-none fixed inset-x-0 top-4 z-[60] flex flex-col items-center gap-2 px-4"></div>

<!-- Bottom navigation -->
<nav class="fixed inset-x-0 bottom-0 z-40 border-t border-white/10 bg-[#0d0f17]/95 backdrop-blur">
    <div class="mx-auto grid max-w-7xl grid-cols-5 text-xs">
        <a href="index.php" class="flex flex-col items-center gap-1 py-3 <?= nav_class('home', $activeNav) ?>">
            <i class="fa-solid fa-house text-base"></i><span>Home</span>
        </a>
        <a href="my_tournaments.php" class="flex flex-col items-center gap-1 py-3 <?= nav_class('my', $activeNav) ?>">
            <i class="fa-solid fa-trophy text-base"></i><span>My Matches</span>
        </a>
        <a href="promo.php" class="flex flex-col items-center gap-1 py-3 <?= nav_class('promo', $activeNav) ?>">
            <i class="fa-solid fa-gift text-base"></i><span>Promo</span>
        </a>
        <a href="wallet.php" class="flex flex-col items-center gap-1 py-3 <?= nav_class('wallet', $activeNav) ?>">
            <i class="fa-solid fa-wallet text-base"></i><span>Wallet</span>
        </a>
        <a href="profile.php" class="flex flex-col items-center gap-1 py-3 <?= nav_class('profile', $activeNav) ?>">
            <i class="fa-solid fa-user text-base"></i><span>Profile</span>
        </a>
    </div>
</nav>

<script>
/* ---------------------------------------------------------------------------
 * Shared client runtime. All server time formatting happens in PHP; the browser
 * never shifts timestamps.
 * ------------------------------------------------------------------------- */
const AS = (() => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    /** POST JSON to an endpoint and parse the JSON response. */
    async function post(url, data = {}) {
        const body = new FormData();
        Object.entries(data).forEach(([k, v]) => body.append(k, v));
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrf },
            body,
            credentials: 'same-origin',
        });
        let payload;
        try { payload = await res.json(); }
        catch (e) { payload = { success: false, message: 'Unexpected server response.' }; }
        return payload;
    }

    /** Render a transient toast. type: success | error | info */
    function toast(message, type = 'info') {
        const host = document.getElementById('toastHost');
        if (!host) return;
        const palette = {
            success: 'border-emerald-500/40 bg-emerald-500/15 text-emerald-200',
            error:   'border-rose-500/40 bg-rose-500/15 text-rose-200',
            info:    'border-[#00f2fe]/40 bg-[#00f2fe]/15 text-[#9deffd]',
        }[type] || 'border-white/20 bg-white/10 text-white';
        const el = document.createElement('div');
        el.className = `pointer-events-auto w-full max-w-sm rounded-xl border px-4 py-3 text-sm shadow-lg backdrop-blur fade-in ${palette}`;
        el.textContent = message;
        host.appendChild(el);
        setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity .3s'; }, 3200);
        setTimeout(() => el.remove(), 3600);
    }

    /** Put a button into a loading state and return a restore function. */
    function loading(button) {
        if (!button) return () => {};
        button.classList.add('is-loading');
        button.disabled = true;
        return () => { button.classList.remove('is-loading'); button.disabled = false; };
    }

    /** Refresh every wallet balance widget on the page. */
    async function refreshBalance() {
        const payload = await post('ajax/get_balance.php');
        if (payload.success) {
            document.querySelectorAll('[data-balance-value]').forEach((el) => {
                el.textContent = payload.formatted;
            });
        }
        return payload;
    }

    return { csrf, post, toast, loading, refreshBalance };
})();

/* Password visibility toggle - delegated so dynamically rendered fields work. */
document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-toggle-password]');
    if (!toggle) return;
    const input = document.getElementById(toggle.dataset.togglePassword);
    if (!input) return;
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    toggle.innerHTML = show ? '<i class="fa-solid fa-eye-slash"></i>' : '<i class="fa-solid fa-eye"></i>';
});

/* Keep the header balance fresh without a reload. */
document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelector('[data-balance-value]')) AS.refreshBalance();
    const bal = document.getElementById('walletBalance');
    if (bal) bal.classList.remove('hidden');
});
</script>
<?= $pageScripts ?>
</body>
</html>
