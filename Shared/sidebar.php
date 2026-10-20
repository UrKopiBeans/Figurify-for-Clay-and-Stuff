<?php

/* ============================================================
   ISANG SIDEBAR para sa Owner AT Staff.
   - Ito lang ang sidebar sa buong system. Walang ibang sidebar code sa kahit anong page.
   - Self-contained: ang CSS at JS ay nasa file na ito, walang external link, walang internet na kailangan.
     (Gagamitin ang Plus Jakarta Sans kung na-load ito ng page; kung offline, Segoe UI ang gamit - pareho pa rin sa lahat ng page.)
   - Hindi ito umaasa sa CSS ng page, kaya kahit baguhin mo ang mga page, hindi gagalaw ang sidebar.
   - Role: galing sa $figurifyRole ("owner" / "staff") o sa folder ng page.
   - Owner lang ang: For Approval, Refunds, at ang System section.
   - Para baguhin ang menu: ang $sbMenu sa ibaba. Para baguhin ang itsura: ang <style> sa ibaba.
   ============================================================ */

$sbRole = (isset($figurifyRole) && in_array($figurifyRole, ["owner", "staff"], true))
    ? $figurifyRole
    : (basename(dirname($_SERVER["SCRIPT_FILENAME"])) === "staff" ? "staff" : "owner");
$sbIsOwner = ($sbRole === "owner");
$sbCurrent = basename($_SERVER["PHP_SELF"]);

/* icon = laman ng <svg viewBox="0 0 24 24"> */
$sbIcons = [
    "dashboard" => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/>',
    "bookings"  => '<path d="M9 6h11M9 12h11M9 18h11"/><path d="M3.5 5.5l1 1 2-2M3.5 11.5l1 1 2-2M3.5 17.5l1 1 2-2"/>',
    "calendar"  => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 9h18M8 3v4M16 3v4"/>',
    "quotation" => '<path d="M6 3h9l4 4v14H6z"/><path d="M15 3v4h4"/><path d="M9 12h6M9 16h4"/>',
    "verify"    => '<circle cx="9" cy="8" r="4"/><path d="M2 21c0-4 3-6 7-6"/><path d="M14.5 15l2 2 4-4"/>',
    "approval"  => '<circle cx="12" cy="12" r="9"/><path d="M12 8v5"/><path d="M12 16h.01"/>',
    "active"    => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 9h18M8 3v4M16 3v4"/><path d="M8.5 15l2 2 4-4"/>',
    "shipments" => '<path d="M3 7l9-4 9 4-9 4-9-4z"/><path d="M3 7v10l9 4 9-4V7"/><path d="M12 11v10"/>',
    "completed" => '<rect x="3" y="4" width="18" height="5" rx="1"/><path d="M5 9v9a2 2 0 002 2h10a2 2 0 002-2V9"/><path d="M10 13h4"/>',
    "payments"  => '<rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="M2.5 10h19"/><path d="M6.5 15h4"/>',
    "refunds"   => '<path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/><path d="M12 7.5v9M14.5 9.3c-.5-.8-1.4-1.3-2.5-1.3-1.5 0-2.5.8-2.5 1.9 0 2.6 5 1.4 5 4.1 0 1.1-1.1 2-2.6 2-1.2 0-2.2-.5-2.7-1.4"/>',
    "cancelled" => '<circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/>',
    "stats"     => '<path d="M4 19V9M10 19V5M16 19v-7M22 19H2"/>',
    "log"       => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>',
    "users"     => '<circle cx="8.5" cy="8" r="3.2"/><path d="M2.5 20c0-3.6 2.7-6 6-6s6 2.4 6 6"/><circle cx="17.5" cy="7.5" r="2.2"/><path d="M15.5 20c.2-2.4 1.6-4 3.6-4.6"/>',
    "content"   => '<path d="M14 4l6 6-9 9H5v-6z"/><path d="M4 21h16"/>',
    "logout"    => '<path d="M9 4H5a2 2 0 00-2 2v12a2 2 0 002 2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
];

/* [section, [ [file, label, icon, owner-only?, mga page na dapat mag-highlight ng item na ito], ... ]] */
$sbDashFile = $sbIsOwner ? "ownerdashboard.php" : "staffdashboard.php";
$sbMenu = [
    ["Main", [
        [$sbDashFile,          "Dashboard",           "dashboard", false, []],
        ["allbookings.php",    "All Bookings",        "bookings",  false, []],
        ["calendar.php",       "Calendar",            "calendar",  false, []],
    ]],
    ["Order Flow", [
        ["quotation.php",      "Quotation",           "quotation", false, []],
        ["verification.php",   "Verification",        "verify",    false, []],
        ["for-approval.php",   "For Approval",        "approval",  true,  []],
        ["active-booking.php", "Active Bookings",     "active",    false, []],
        ["shipments.php",      "Shipments",           "shipments", false, []],
        ["completed.php",      "Completed Orders",    "completed", false, []],
    ]],
    ["Money", [
        ["payments.php",       "Payments",            "payments",  false, []],
        ["refunds.php",        "Refunds",             "refunds",   true,  []],
        ["cancelled.php",      "Cancelled / Declined", "cancelled", false, []],
        ["reports.php",        "Statistics",          "stats",     false, []],
    ]],
    ["System", [
        ["activity-log.php",   "Activity Log",        "log",       true,  []],
        ["user-management.php", "User Management",    "users",     true,  []],
        ["content-management.php", "Content Management", "content", true, ["collection-management.php"]],
    ], true],
];

?>
<style>
/* ===================== SIDEBAR (isang CSS para sa Owner at Staff) =====================
   Lahat ng sukat ay nakasulat dito. Walang gamit na CSS ng page, walang external font/link. */

:root {
    --sb-width: 225px;      /* lapad ng sidebar - ito rin ang sinusunod ng content ng page */
    --sb-gap: 5px;          /* pagitan ng sidebar at content */
}

/* Ang content ay laging katabi ng sidebar - pareho sa lahat ng page */
body .app > main.main {
    margin-left: calc(var(--sb-width) + var(--sb-gap)) !important;
    width: calc(100% - var(--sb-width) - var(--sb-gap)) !important;
}

/* reset: para hindi mamana ang body/*, a, button rules ng page */
.sidebar,
.sidebar *,
.sb-modal-overlay,
.sb-modal-overlay * {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    
    font-style: normal;
    line-height: 1.2;
    letter-spacing: 0;
    text-transform: none;
    text-decoration: none;
}

/* ---------- kahon ng sidebar ---------- */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    z-index: 100;
    width: var(--sb-width);
    height: 100vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: #f6bdd1;
    border-right: 1px solid #eba5c0;
}

/* ---------- logo ---------- */
.sidebar .sb-brand {
    flex: 0 0 auto;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: clamp(3px, .8vh, 8px);
    padding: clamp(6px, 1.8vh, 18px) 14px clamp(4px, 1vh, 8px);
}

.sidebar .sb-logo-wrap {
    width: clamp(40px, 7vh, 72px);
    aspect-ratio: 1 / 1;
    padding: 4px;
    border-radius: 16px;
    background: #ffffff;
    box-shadow: 0 4px 12px rgba(131, 24, 67, .15);
}

.sidebar .sb-logo {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.sidebar .sb-brand-name {
    font-size: clamp(12px, 1.9vh, 16px);
    font-weight: 800;
    color: #43142f;
    text-align: center;
}

/* ---------- menu ---------- */
.sidebar .sb-nav {
    flex: 1 1 auto;
    min-height: 0;
    display: flex;
    flex-direction: column;
    gap: 1px;
    padding: 0 10px;
    overflow-x: hidden;
    overflow-y: auto;          /* para abot pa rin lahat ng menu ng Owner sa maikling screen */
    scrollbar-width: none;
}
.sidebar .sb-nav::-webkit-scrollbar { display: none; }

.sidebar .sb-section {
    flex-shrink: 0;
    padding: clamp(3px, 1.1vh, 10px) 12px clamp(1px, .4vh, 4px);
    font-size: clamp(8.5px, 1.2vh, 10.5px);
    font-weight: 800;
    letter-spacing: .14em;
    text-transform: uppercase;
    color: #a3567a;
}
.sidebar .sb-section:first-child { padding-top: 2px; }

/* item ng menu at Log Out - iisang style */
.sidebar .sb-nav-item,
.sidebar .sb-logout-btn {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    min-height: clamp(24px, 3.7vh, 36px);
    padding: 0 10px;
    border: 0;
    border-radius: 10px;
    background: transparent;
    color: #5b2a45;
    font-size: clamp(11px, 1.6vh, 13.5px);
    font-weight: 600;
    text-align: left;
    cursor: pointer;
    transition: background .15s ease, color .15s ease;
}

.sidebar .sb-nav-item:hover,
.sidebar .sb-logout-btn:hover { background: rgba(255, 255, 255, .55); }

.sidebar .sb-nav-item.active {
    background: #ffffff;
    color: #a3204c;
    font-weight: 700;
}

.sidebar .sb-nav-item span,
.sidebar .sb-logout-btn span:not(.sb-icon) {
    font: inherit;
    color: inherit;
}

.sidebar .sb-icon {
    flex: 0 0 auto;
    display: block;
    width: clamp(14px, 2.1vh, 18px);
    height: clamp(14px, 2.1vh, 18px);
    color: #8a3d62;
}
.sidebar .sb-nav-item:hover .sb-icon,
.sidebar .sb-nav-item.active .sb-icon,
.sidebar .sb-logout-btn:hover .sb-icon { color: #a3204c; }

.sidebar .sb-icon svg {
    display: block;
    width: 100%;
    height: 100%;
    fill: none;
    stroke: currentColor;
    stroke-width: 2.2;
    stroke-linecap: round;
    stroke-linejoin: round;
}

/* ---------- Log Out (nasa pinakababa) ---------- */
.sidebar .sb-bottom {
    position: relative;
    flex: 0 0 auto;
    padding: 4px 10px clamp(6px, 1.6vh, 14px);
}
.sidebar .sb-bottom::before {
    content: "";
    position: absolute;
    top: 0;
    left: 16px;
    right: 16px;
    height: 1.5px;
    border-radius: 2px;
    background: rgba(131, 24, 67, .18);
}

/* ---------- phone / tablet: icon lang ---------- */
@media (max-width: 800px) {
    :root { --sb-width: 70px; }

    .sidebar .sb-brand-name,
    .sidebar .sb-section,
    .sidebar .sb-nav-item > span:not(.sb-icon),
    .sidebar .sb-logout-btn > span:not(.sb-icon) { display: none; }

    .sidebar .sb-logo-wrap { width: 44px; }
    .sidebar .sb-nav-item,
    .sidebar .sb-logout-btn { justify-content: center; }
    .sidebar .sb-bottom::before { left: 10px; right: 10px; }
}

/* ---------- Log Out pop up ---------- */
.sb-modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 999;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(45, 14, 32, .42);
    backdrop-filter: blur(3px);
    opacity: 0;
    pointer-events: none;
    transition: opacity .2s ease;
}
.sb-modal-overlay.active { opacity: 1; pointer-events: auto; }

.sb-modal-overlay .sb-modal-box {
    width: min(360px, 92%);
    padding: 26px 24px 22px;
    border-radius: 20px;
    background: #ffffff;
    text-align: center;
    box-shadow: 0 24px 60px -18px rgba(45, 14, 32, .5);
    transform: scale(.94);
    transition: transform .2s ease;
}
.sb-modal-overlay.active .sb-modal-box { transform: scale(1); }

.sb-modal-overlay .sb-modal-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    margin: 0 auto 14px;
    border-radius: 50%;
    background: #ffedf2;
    color: #c2325f;
}
.sb-modal-overlay .sb-modal-icon svg {
    display: block;
    width: 22px;
    height: 22px;
    fill: none;
    stroke: currentColor;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.sb-modal-overlay .sb-modal-title { margin-bottom: 6px; font-size: 18px; font-weight: 700; color: #4a1d3b; }
.sb-modal-overlay .sb-modal-desc  { margin-bottom: 22px; font-size: 14px; color: #7a5468; }

.sb-modal-overlay .sb-modal-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }

.sb-modal-overlay .sb-modal-btn {
    height: 44px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: background .15s ease;
}
.sb-modal-overlay .sb-modal-btn-confirm { border: 1.5px solid #c2325f; background: #c2325f; color: #ffffff; }
.sb-modal-overlay .sb-modal-btn-confirm:hover { border-color: #a62a50; background: #a62a50; }
.sb-modal-overlay .sb-modal-btn-cancel { border: 1.5px solid #c2325f; background: #ffffff; color: #c2325f; }
.sb-modal-overlay .sb-modal-btn-cancel:hover { background: #ffedf2; }
</style>

<aside class="sidebar">

    <div class="sb-brand">
        <div class="sb-logo-wrap">
            <img src="../Image/brand/logo-sidebar.png" alt="Clay and Stuff" class="sb-logo">
        </div>
        <div class="sb-brand-name"><?php echo $sbIsOwner ? "Owner Panel" : "Staff Panel"; ?></div>
    </div>

    <nav class="sb-nav">
<?php foreach ($sbMenu as $sbGroup): ?>
<?php if (!empty($sbGroup[2]) && !$sbIsOwner) { continue; } ?>
        <div class="sb-section"><?php echo htmlspecialchars($sbGroup[0]); ?></div>
<?php foreach ($sbGroup[1] as [$sbFile, $sbLabel, $sbIcon, $sbOwnerOnly, $sbAlso]): ?>
<?php if ($sbOwnerOnly && !$sbIsOwner) { continue; } ?>
<?php $sbOn = ($sbFile === $sbCurrent || in_array($sbCurrent, $sbAlso, true)); ?>
        <a href="<?php echo $sbFile; ?>" class="sb-nav-item<?php echo $sbOn ? " active" : ""; ?>">
            <span class="sb-icon"><svg viewBox="0 0 24 24"><?php echo $sbIcons[$sbIcon]; ?></svg></span>
            <span><?php echo htmlspecialchars($sbLabel); ?></span>
        </a>
<?php endforeach; ?>
<?php endforeach; ?>
    </nav>

    <div class="sb-bottom">
        <button type="button" class="sb-logout-btn" id="sbLogoutBtn">
            <span class="sb-icon"><svg viewBox="0 0 24 24"><?php echo $sbIcons["logout"]; ?></svg></span>
            <span>Log Out</span>
        </button>
    </div>

</aside>

<div class="sb-modal-overlay" id="sbLogoutModal">
    <div class="sb-modal-box">
        <div class="sb-modal-icon"><svg viewBox="0 0 24 24"><?php echo $sbIcons["logout"]; ?></svg></div>
        <div class="sb-modal-title">Log out?</div>
        <div class="sb-modal-desc">You can log back in anytime.</div>
        <div class="sb-modal-actions">
            <button type="button" class="sb-modal-btn sb-modal-btn-confirm" id="sbLogoutConfirm">Log Out</button>
            <button type="button" class="sb-modal-btn sb-modal-btn-cancel" id="sbLogoutCancel">Cancel</button>
        </div>
    </div>
</div>

<script>
(function () {
    var overlay = document.getElementById("sbLogoutModal");
    var logoutHref = "../Login/logout.php?portal=<?php echo $sbRole; ?>";

    function close() { overlay.classList.remove("active"); }

    document.getElementById("sbLogoutBtn").addEventListener("click", function () { overlay.classList.add("active"); });
    document.getElementById("sbLogoutCancel").addEventListener("click", close);
    document.getElementById("sbLogoutConfirm").addEventListener("click", function () { window.location.href = logoutHref; });
    overlay.addEventListener("click", function (e) { if (e.target === overlay) { close(); } });
    document.addEventListener("keydown", function (e) { if (e.key === "Escape") { close(); } });
})();
</script>
