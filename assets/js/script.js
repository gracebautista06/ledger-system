/* ============================================================
   script.js — Egg Ledger System Global Scripts
   IMPROVEMENTS:
   - Auto-dismiss alerts after 5 seconds
   - Active nav link highlighting
   - Form double-submit prevention
   - Numeric input sanitizer (no negatives)
   - Global confirmAction() helper for delete buttons
   - show/hide password toggle (togglePassword) shared globally
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {

    /* ----------------------------------------------------------
       1. AUTO-DISMISS ALERTS
       Alerts fade out after 5 seconds automatically.
    ---------------------------------------------------------- */
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            alert.style.opacity    = '0';
            alert.style.transform  = 'translateY(-8px)';
            setTimeout(() => alert.remove(), 600);
        }, 5000);
    });

    /* ----------------------------------------------------------
       2. ACTIVE NAV LINK HIGHLIGHTING
    ---------------------------------------------------------- */
    const currentPath = window.location.pathname;
    document.querySelectorAll('.nav-links a').forEach(function (link) {
        const href = link.getAttribute('href');
        if (href && currentPath.endsWith(href.replace('../', ''))) {
            link.style.color          = 'var(--gold)';
            link.style.fontWeight     = '800';
            link.style.borderBottom   = '2px solid var(--gold)';
        }
    });

    /* ----------------------------------------------------------
       3. FORM DOUBLE-SUBMIT PREVENTION
    ---------------------------------------------------------- */
    document.querySelectorAll('form[method="POST"]').forEach(function (form) {
        form.addEventListener('submit', function () {
            const btn = form.querySelector('button[type="submit"]');
            if (btn) {
                setTimeout(function () {
                    btn.disabled     = true;
                    btn.textContent  = '⏳ Saving...';
                }, 50);
            }
        });
    });

    /* ----------------------------------------------------------
       4. NUMERIC INPUT SANITIZER — no negatives on min="0" inputs
    ---------------------------------------------------------- */
    document.querySelectorAll('input[type="number"][min="0"]').forEach(function (input) {
        input.addEventListener('blur', function () {
            if (parseInt(this.value) < 0 || isNaN(parseInt(this.value))) {
                this.value = 0;
            }
        });
    });

});

/* ----------------------------------------------------------
   5. GLOBAL CONFIRM HELPER
   Usage: onclick="return confirmAction('Delete this record?')"
---------------------------------------------------------- */
function confirmAction(message) {
    return confirm(message || 'Are you sure you want to proceed?');
}

/* ----------------------------------------------------------
   6. TOGGLE PASSWORD VISIBILITY (shared across pages)
   Usage: togglePassword('input-id', 'icon-span-id')
---------------------------------------------------------- */
function togglePassword(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);
    if (!input || !icon) return;
    input.type       = input.type === 'password' ? 'text' : 'password';
    icon.textContent = input.type === 'password' ? '👁️' : '🙈';
}