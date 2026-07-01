<?php
/**
 * includes/order_toast.php
 * Include in every page's <body> (before </body>).
 * Polls track-status.php every 20s, shows a toast when order status changes.
 * Uses localStorage to track last-known status so it works across page navigations.
 * No session_start() — config.php already handles it.
 */
$_toastToken = $_SESSION['active_order_token'] ?? null;
if (!$_toastToken || strlen($_toastToken) !== 64 || !ctype_xdigit($_toastToken)) return;
?>

<!-- Order Toast Container -->
<div id="orderToastContainer" aria-live="polite" aria-atomic="true"></div>

<style>
/* ── Order Toast ── */
#orderToastContainer {
    position: fixed;
    bottom: 24px;
    left: 24px;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    gap: 12px;
    pointer-events: none;
}
.o-toast {
    pointer-events: all;
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 16px 18px;
    background: #0f0f0f;
    border: 1px solid rgba(201,168,76,0.2);
    border-left: 3px solid #c9a84c;
    border-radius: 12px;
    box-shadow: 0 12px 40px rgba(0,0,0,0.7);
    max-width: 340px;
    cursor: pointer;
    animation: toastIn 0.38s cubic-bezier(0.34,1.56,0.64,1) both;
    text-decoration: none;
    color: #f5f0e8;
    font-family: 'Jost', sans-serif;
}
.o-toast.toast-out {
    animation: toastOut 0.3s ease forwards;
}
@keyframes toastIn  { from {opacity:0; transform:translateX(-30px) scale(0.95);} to {opacity:1; transform:translateX(0) scale(1);} }
@keyframes toastOut { from {opacity:1; transform:translateX(0);} to {opacity:0; transform:translateX(-24px);} }

.toast-icon {
    width: 36px; height: 36px;
    border-radius: 50%;
    background: rgba(201,168,76,0.12);
    border: 1px solid rgba(201,168,76,0.25);
    display: flex; align-items: center; justify-content: center;
    color: #c9a84c;
    font-size: 0.9rem;
    flex-shrink: 0;
}
.toast-body { flex: 1; min-width: 0; }
.toast-title {
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-size: 0.98rem;
    font-weight: 600;
    color: #c9a84c;
    letter-spacing: 0.5px;
    margin-bottom: 3px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.toast-msg { font-size: 0.8rem; color: rgba(245,240,232,0.6); line-height: 1.4; }
.toast-close {
    background: none; border: none; cursor: pointer;
    color: rgba(245,240,232,0.3); font-size: 0.85rem;
    padding: 2px; flex-shrink: 0; transition: color 0.2s;
    align-self: flex-start;
}
.toast-close:hover { color: rgba(245,240,232,0.85); }

@media (max-width: 600px) {
    #orderToastContainer { left: 12px; right: 12px; bottom: 72px; }
    .o-toast { max-width: 100%; }
}
</style>

<script>
(function() {
    const TOKEN   = <?php echo json_encode($_toastToken); ?>;
    const TRACK_URL = 'track.php?token=' + TOKEN;
    const LS_KEY  = 'medusa_order_status_' + TOKEN.slice(0, 8);
    const POLL_MS = 20000;

    const TOAST_CONFIG = {
        placed:           { icon: 'fa-receipt',      title: 'Order Received',       msg: 'We have received your order and are confirming it.' },
        confirmed:        { icon: 'fa-circle-check',  title: 'Order Confirmed',      msg: 'Your order has been confirmed by our team!' },
        preparing:        { icon: 'fa-fire-burner',   title: 'Being Prepared',       msg: 'Our chefs are crafting your order right now. 🍽️' },
        out_for_delivery: { icon: 'fa-motorcycle',    title: 'Out for Delivery!',    msg: 'Your order is on its way to you! 🛵' },
        delivered:        { icon: 'fa-house',         title: 'Delivered!',           msg: 'Your order has arrived. Enjoy your meal! ✦' },
        cancelled:        { icon: 'fa-circle-xmark',  title: 'Order Cancelled',      msg: 'This order has been cancelled.' },
    };

    const container = document.getElementById('orderToastContainer');
    let lastStatus  = localStorage.getItem(LS_KEY) || null;
    let poller      = null;

    function showToast(status, customMsg) {
        const cfg = TOAST_CONFIG[status];
        if (!cfg) return;

        const toast = document.createElement('a');
        toast.href  = TRACK_URL;
        toast.className = 'o-toast';
        toast.innerHTML = `
            <div class="toast-icon"><i class="fas ${cfg.icon}"></i></div>
            <div class="toast-body">
                <div class="toast-title">${cfg.title}</div>
                <div class="toast-msg">${customMsg || cfg.msg}</div>
            </div>
            <button class="toast-close" aria-label="Dismiss"><i class="fas fa-xmark"></i></button>
        `;

        // Close button
        toast.querySelector('.toast-close').addEventListener('click', function(e) {
            e.preventDefault(); e.stopPropagation();
            dismissToast(toast);
        });
        toast.addEventListener('click', function() {
            window.location.href = TRACK_URL;
        });

        container.appendChild(toast);

        // Auto dismiss after 6s
        setTimeout(() => dismissToast(toast), 6000);
    }

    function dismissToast(el) {
        el.classList.add('toast-out');
        setTimeout(() => { if (el.parentNode) el.parentNode.removeChild(el); }, 350);
    }

    async function poll() {
        try {
            const res  = await fetch('api/track-status.php?token=' + TOKEN);
            const data = await res.json();
            if (!data.success) { clearInterval(poller); return; }

            const newStatus = data.tracking_status;

            // Only toast on an actual status change
            if (lastStatus !== null && newStatus !== lastStatus) {
                let msg = TOAST_CONFIG[newStatus]?.msg || '';
                if (newStatus === 'out_for_delivery' && data.estimated_delivery) {
                    const d = new Date(data.estimated_delivery.replace(' ', 'T'));
                    msg = `Your order is on its way! Estimated: ${d.toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'})} 🛵`;
                }
                showToast(newStatus, msg);
            }

            localStorage.setItem(LS_KEY, newStatus);
            lastStatus = newStatus;

            // Stop if terminal
            if (!data.is_active) clearInterval(poller);
        } catch(e) { /* silent */ }
    }

    // Seed localStorage on first load without toasting
    if (!lastStatus) {
        fetch('api/track-status.php?token=' + TOKEN)
            .then(r => r.json())
            .then(d => { if (d.success) { lastStatus = d.tracking_status; localStorage.setItem(LS_KEY, lastStatus); }})
            .catch(() => {});
    }

    // Only poll on pages that aren't already the tracking page
    if (!window.location.pathname.includes('track.php') && !window.location.pathname.includes('order_confirmed.php')) {
        poller = setInterval(poll, POLL_MS);
    }
})();
</script>
