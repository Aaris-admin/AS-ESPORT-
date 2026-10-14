<?php
/**
 * AS ESPORT Admin - closes the shared layout and wires shared JS.
 */
?>
        </main>
    </div>
</div>

<div id="toastHost" class="pointer-events-none fixed inset-x-0 top-4 z-[70] flex flex-col items-center gap-2 px-4"></div>

<script>
const AS = (() => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    async function post(url, data = {}) {
        const body = new FormData();
        Object.entries(data).forEach(([k, v]) => body.append(k, v));
        const res = await fetch(url, { method: 'POST', headers: { 'X-CSRF-Token': csrf }, body, credentials: 'same-origin' });
        try { return await res.json(); } catch (e) { return { success: false, message: 'Unexpected server response.' }; }
    }
    function toast(message, type = 'info') {
        const host = document.getElementById('toastHost');
        if (!host) return;
        const palette = {
            success: 'border-emerald-500/40 bg-emerald-500/15 text-emerald-200',
            error: 'border-rose-500/40 bg-rose-500/15 text-rose-200',
            info: 'border-[#00f2fe]/40 bg-[#00f2fe]/15 text-[#9deffd]',
        }[type] || 'border-white/20 bg-white/10 text-white';
        const el = document.createElement('div');
        el.className = `pointer-events-auto w-full max-w-sm rounded-xl border px-4 py-3 text-sm shadow-lg backdrop-blur fade-in ${palette}`;
        el.textContent = message;
        host.appendChild(el);
        setTimeout(() => el.remove(), 3400);
    }
    function loading(button) {
        if (!button) return () => {};
        button.classList.add('is-loading'); button.disabled = true;
        return () => { button.classList.remove('is-loading'); button.disabled = false; };
    }
    return { csrf, post, toast, loading };
})();

document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-toggle-password]');
    if (!toggle) return;
    const input = document.getElementById(toggle.dataset.togglePassword);
    if (!input) return;
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    toggle.innerHTML = show ? '<i class="fa-solid fa-eye-slash"></i>' : '<i class="fa-solid fa-eye"></i>';
});

const sidebar = document.getElementById('adminSidebar');
const overlay = document.getElementById('adminOverlay');
const toggleBtn = document.getElementById('sidebarToggle');
if (toggleBtn) {
    toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('-translate-x-full');
        overlay.classList.toggle('hidden');
    });
    overlay.addEventListener('click', () => {
        sidebar.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
    });
}

/* Generic confirm-before-submit guard. */
document.querySelectorAll('[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (e) => {
        if (!window.confirm(form.dataset.confirm)) e.preventDefault();
    });
});
</script>
<?= $pageScripts ?>
</body>
</html>
