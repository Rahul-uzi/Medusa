<?php
/**
 * includes/active_order_bar.php
 * Include this in every page's <body> (before </body>).
 * Shows a floating "Track My Order" button when the customer has an active order.
 * No session_start() here — assumes config.php already started the session.
 */
$_activeToken   = $_SESSION['active_order_token'] ?? null;
$_activeOrderId = $_SESSION['active_order_id']    ?? null;

if (!$_activeToken || strlen($_activeToken) !== 64 || !ctype_xdigit($_activeToken)) return;
?>

<div id="activeOrderBar" style="display:none;">
    <!-- Desktop: bottom-right pill -->
    <a href="track.php?token=<?php echo htmlspecialchars($_activeToken); ?>" class="aob-pill" id="aobPill">
        <span class="aob-dot" id="aobDot"></span>
        <span class="aob-icon"><i class="fas fa-motorcycle"></i></span>
        <span class="aob-text">
            <span class="aob-order" id="aobOrderNum">Order Active</span>
            <span class="aob-label" id="aobLabel">Tap to track live →</span>
        </span>
        <span class="aob-close" id="aobClose" title="Dismiss"><i class="fas fa-xmark"></i></span>
    </a>

    <!-- Mobile: full-width bottom bar -->
    <a href="track.php?token=<?php echo htmlspecialchars($_activeToken); ?>" class="aob-mobile" id="aobMobile">
        <span class="aob-dot aob-dot-sm"></span>
        <span class="aob-mobile-text">
            <span id="aobMobileLabel">Order Active · Tap to track</span>
        </span>
        <i class="fas fa-arrow-right"></i>
    </a>
</div>

<style>
/* ── Active Order Bar ── */
.aob-pill {
    position: fixed;
    bottom: 28px;
    right: 24px;
    z-index: 9000;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border-radius: 60px;
    background: #111;
    border: 1px solid rgba(201,168,76,0.35);
    box-shadow: 0 8px 32px rgba(0,0,0,0.55);
    text-decoration: none;
    color: #f5f0e8;
    cursor: pointer;
    transition: all 0.25s ease;
    max-width: 280px;
    animation: aobSlideIn 0.4s cubic-bezier(0.34,1.56,0.64,1) both;
}
.aob-pill:hover { border-color: rgba(201,168,76,0.7); transform: translateY(-2px); box-shadow: 0 14px 40px rgba(0,0,0,0.7); }
.aob-pill.pulse-delivery { animation: aobPulseGold 1.8s ease-in-out infinite; }

@keyframes aobSlideIn { from { opacity:0; transform:translateY(30px) scale(0.9); } to { opacity:1; transform:translateY(0) scale(1); } }
@keyframes aobPulseGold { 0%,100% { box-shadow:0 8px 32px rgba(0,0,0,0.55),0 0 0 0 rgba(201,168,76,0.3); } 50% { box-shadow:0 8px 32px rgba(0,0,0,0.55),0 0 0 10px rgba(0,0,0,0); } }

.aob-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #c9a84c;
    flex-shrink: 0;
    box-shadow: 0 0 0 0 rgba(201,168,76,0.4);
    animation: aobDotPulse 2s ease-in-out infinite;
}
.aob-dot-sm { width: 8px; height: 8px; }
@keyframes aobDotPulse { 0%,100%{box-shadow:0 0 0 0 rgba(201,168,76,0.5)} 50%{box-shadow:0 0 0 6px rgba(0,0,0,0)} }

.aob-icon { font-size: 1.05rem; color: #c9a84c; flex-shrink: 0; }
.aob-text { display: flex; flex-direction: column; line-height: 1.3; flex: 1; min-width: 0; }
.aob-order { font-family: 'Cormorant Garamond', 'Jost', serif; font-size: 0.95rem; font-weight: 600; color: #c9a84c; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.aob-label { font-size: 0.72rem; color: rgba(245,240,232,0.6); letter-spacing: 0.3px; }
.aob-close {
    font-size: 0.85rem;
    color: rgba(245,240,232,0.35);
    padding: 4px 4px 4px 8px;
    transition: color 0.2s;
    flex-shrink: 0;
}
.aob-close:hover { color: rgba(245,240,232,0.9); }

/* Mobile bar */
.aob-mobile {
    display: none;
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 9000;
    padding: 14px 20px;
    background: #111;
    border-top: 1px solid rgba(201,168,76,0.3);
    text-decoration: none;
    color: #f5f0e8;
    align-items: center;
    gap: 12px;
    animation: aobMobileSlide 0.35s ease both;
}
@keyframes aobMobileSlide { from { transform:translateY(100%); } to { transform:translateY(0); } }
.aob-mobile-text { flex: 1; font-size: 0.88rem; font-weight: 600; color: #c9a84c; }

@media (max-width: 600px) {
    .aob-pill  { display: none !important; }
    .aob-mobile { display: flex !important; }
}
</style>

<script>
(function() {
    const TOKEN    = <?php echo json_encode($_activeToken); ?>;
    const POLL_MS  = 30000;
    const bar      = document.getElementById('activeOrderBar');
    const pill     = document.getElementById('aobPill');
    const orderNum = document.getElementById('aobOrderNum');
    const label    = document.getElementById('aobLabel');
    const mobLabel = document.getElementById('aobMobileLabel');
    const dot      = document.getElementById('aobDot');

    const STATUS_LABELS = {
        placed:'Order Placed', confirmed:'Confirmed', preparing:'Preparing',
        out_for_delivery:'Out for Delivery', delivered:'Delivered', cancelled:'Cancelled'
    };

    // Dismiss button — hide for session (not clearing session token)
    document.getElementById('aobClose').addEventListener('click', function(e) {
        e.preventDefault(); e.stopPropagation();
        bar.style.display = 'none';
        sessionStorage.setItem('aob_dismissed', '1');
    });

    // Don't show if dismissed this session
    if (sessionStorage.getItem('aob_dismissed') === '1') return;

    async function fetchStatus() {
        try {
            const res  = await fetch('api/track-status.php?token=' + TOKEN);
            const data = await res.json();
            if (!data.success) { bar.style.display = 'none'; return; }

            // Update text
            const sl = STATUS_LABELS[data.tracking_status] || data.status_label;
            if (orderNum) orderNum.textContent = data.order_number ? 'Order ' + data.order_number : 'Your Order';
            if (label)    label.textContent = sl + ' · Track Live →';
            if (mobLabel) mobLabel.textContent = (data.order_number || 'Your Order') + ' · ' + sl;

            // Pulse effect for out_for_delivery
            if (pill) pill.classList.toggle('pulse-delivery', data.tracking_status === 'out_for_delivery');

            if (!data.is_active) {
                // Terminal — clear session on next PHP load by hitting a cleanup endpoint
                bar.style.display = 'none';
                clearInterval(poller);
                return;
            }
            bar.style.display = 'block';
        } catch(e) { /* silent */ }
    }

    fetchStatus();
    const poller = setInterval(fetchStatus, POLL_MS);
})();
</script>
