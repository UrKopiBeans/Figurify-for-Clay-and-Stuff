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
   - Arrow button sa taas: paliitin ang sidebar (icon lang) para lumaki ang page.
     Naaalala ng browser (localStorage).
   - Phone / tablet / iPad (1024px pababa): nakatago ang sidebar; may pink na bar sa taas na may ☰ button
     na nagbubukas nito (drawer). Kasama rin dito ang ayos ng input (16px) para hindi mag-zoom ang iPhone.
   - Kasama rin dito ang Previous / Next ng LAHAT ng table ng owner/staff
     (dating nasa lumang shared file). Ang itsura ng Previous / Next (.cs-pager)
     ay nasa CSS ng bawat owner/staff page.
   - Phone (600px pababa): ang order / user table ay nagiging card (label + value),
     kaya hindi na kailangang mag-scroll pakanan. 1180px pababa (nasa ilalim ng listahan
     ang detalye): kapag may napiling order, diretso ang page sa detalye nito; nakatago
     ang walang lamang detail box kapag wala pang napili.
   - Kasama rin dito ang petsa at oras sa topbar (.cs-tb-datetime) ng lahat ng owner/staff page.
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
        ["activity-log.php",   "Activity Log",        "log",       false, []],
        ["user-management.php", "User Management",    "users",     true,  []],
        ["content-management.php", "Content Management", "content", true, ["collection-management.php"]],
    ]],
];

?>
<style>
/* ===================== SIDEBAR (isang CSS para sa Owner at Staff) =====================
   Lahat ng sukat ay nakasulat dito. Walang gamit na CSS ng page, walang external font/link. */

/* Ang content ay laging katabi ng sidebar (240px, dikit, walang pagitan) - pareho sa lahat ng page.
   Mas specific ang selector na ito kaysa sa .main ng bawat page, kaya nananalo ito nang walang override trick.
   Kapag babaguhin ang lapad ng sidebar, palitan ang 240px dito at sa .sidebar sa ibaba
   (at ang 110px sa [ICON LANG] sa ibaba). */
body .app > main.main {
    margin-left: 240px;
    width: calc(100% - 240px);
    transition: margin-left .2s ease, width .2s ease;
}

/* reset: para hindi mamana ang body/*, a, button rules ng page */
.sidebar,
.sidebar * {
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
    width: 240px;
    height: 100vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: #f39bc1;
    border-right: 1px solid #eba5c0;
    transition: width .2s ease;
}

/* ---------- logo (image lang, walang background) + pangalan sa kanan ---------- */
.sidebar .sb-brand {
    flex: 0 0 auto;
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 10px;
    padding: clamp(8px, 1.8vh, 16px) 16px clamp(6px, 1.2vh, 12px);
}

.sidebar .sb-logo {
    display: block;
    flex: 0 0 auto;
    width: clamp(38px, 6vh, 52px);
    height: clamp(38px, 6vh, 52px);
    object-fit: contain;
    background: none;
    border: 0;
    border-radius: 0;
    box-shadow: none;
}

.sidebar .sb-brand-text {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.sidebar .sb-brand-name {
    font-family: 'Fraunces', Georgia, 'Times New Roman', serif;
    font-size: clamp(15px, 2.2vh, 19px);
    font-weight: 700;
    line-height: 1.1;
    letter-spacing: -0.2px;
    color: #43142f;
    white-space: nowrap;
}

.sidebar .sb-brand-role {
    font-size: clamp(10.5px, 1.45vh, 12px);
    font-weight: 600;
    color: #a3567a;
}

/* ---------- arrow button: kahon na may radius, nasa kanan ng "Clay and Stuff" (pati kapag maliit ang sidebar) ---------- */
.sb-toggle {
    flex: 0 0 auto;
    display: flex;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;
    width: 28px;
    height: 28px;
    margin: 0 0 0 auto;
    padding: 0;
    border: 1px solid #eba5c0;
    border-radius: 999px;
    background: #ffffff;
    color: #8a3d62;
    box-shadow: 0 4px 10px -4px rgba(74, 29, 59, .35);
    cursor: pointer;
    transition: color .15s ease, background .15s ease;
}
.sb-toggle:hover { background: #ffedf2; color: #a3204c; }
.sb-toggle:focus-visible { outline: 2px solid #a3204c; outline-offset: 2px; }

.sb-toggle svg {
    display: block;
    width: 16px;
    height: 16px;
    fill: none;
    stroke: currentColor;
    stroke-width: 2.4;
    stroke-linecap: round;
    stroke-linejoin: round;
    transition: transform .2s ease;
}

/* ---------- menu ---------- */
/* Walang scroll: ang mga item ay lumiliit nang kaunti kung maikli ang screen,
   at lumalaki (hanggang 46px) kung maluwag. */
.sidebar .sb-nav {
    flex: 1 1 auto;
    min-height: 0;
    display: flex;
    flex-direction: column;
    gap: 4px;                  /* pagitan ng bawat item para hindi magkadikit ang hover */
    padding: 4px 12px 6px;
    overflow: hidden;
}

/* maliit na pagitan sa pagitan ng mga grupo (walang title na) */
.sidebar .sb-nav-item.sb-group-start { margin-top: 0; }

/* item ng menu at Log Out - iisang style */
.sidebar .sb-nav-item,
.sidebar .sb-logout-btn {
    flex: 0 1 auto;
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    height: clamp(28px, 4.6vh, 44px);
    min-height: 0;
    padding: 0 12px;
    border: 0;
    border-radius: 12px;
    background: transparent;
    color: #5b2a45;
    font-size: clamp(12.5px, 1.95vh, 15px);
    text-align: left;
    white-space: nowrap;
    cursor: pointer;
    transition: background .15s ease, color .15s ease;
}
.sidebar .sb-logout-btn { font-weight: 600; }

.sidebar .sb-nav-item:hover,
.sidebar .sb-logout-btn:hover { background: rgba(255, 255, 255, .55); }

.sidebar .sb-nav-item.active {
    background: #ffffff;
    color: #a3204c;
    font-weight: 600;   /* parehong bigat ng ibang item - hindi lumalapad/lumalaki ang word kapag napili */
}

/* walang laki/galaw na effect kapag cliniclick o hinover */
.sidebar .sb-nav-item,
.sidebar .sb-nav-item:hover,
.sidebar .sb-nav-item:active,
.sidebar .sb-nav-item:focus,
.sidebar .sb-logout-btn:active {
    transform: none;
    font-weight: 600;
}
.sidebar .sb-nav-item:focus { outline: none; }
.sidebar .sb-nav-item:focus-visible { outline: 2px solid #a3204c; outline-offset: -2px; }

.sidebar .sb-nav-item span,
.sidebar .sb-logout-btn span:not(.sb-icon) {
    font: inherit;
    color: inherit;
}

.sidebar .sb-icon {
    flex: 0 0 auto;
    display: block;
    width: clamp(16px, 2.4vh, 20px);
    height: clamp(16px, 2.4vh, 20px);
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
    padding: 6px 12px clamp(8px, 1.6vh, 14px);
}


/* ---------- [ICON LANG] pinaliit gamit ang arrow button (desktop / laptop lang) ----------
   Ang class na "sb-collapsed" sa <html> ay inilalagay ng script sa ibaba (bago lumabas ang sidebar). */
html.sb-collapsed .sidebar { width: 110px; }

html.sb-collapsed body .app > main.main {
    margin-left: 110px;
    width: calc(100% - 110px);
}

html.sb-collapsed .sidebar .sb-brand-text,
html.sb-collapsed .sidebar .sb-nav-item > span:not(.sb-icon),
html.sb-collapsed .sidebar .sb-logout-btn > span:not(.sb-icon) { display: none; }

/* logo at arrow magkatabi pa rin (arrow sa kanan) - HINDI lumiliit ang logo at arrow (parehong laki kapag malaki ang sidebar) */
html.sb-collapsed .sidebar .sb-brand { gap: 6px; padding-left: 12px; padding-right: 12px; }
html.sb-collapsed .sb-toggle svg { transform: rotate(180deg); }

/* icon lang: parisukat na kahon ang hover / napili (kasing-lapad ng taas), nasa gitna */
html.sb-collapsed .sidebar .sb-nav-item,
html.sb-collapsed .sidebar .sb-logout-btn {
    justify-content: center;
    width: clamp(28px, 4.6vh, 44px);
    margin: 0 auto;
    padding: 0;
}

/* ---------- [PHONE / TABLET / IPAD] 1024px pababa: drawer + pink na bar sa taas ---------- */
.sb-mobile-bar,
.sb-overlay {
    display: none;
}

@media (max-width: 1024px) {
    .sb-toggle { display: none; }

    /* pink na bar sa taas: ☰ + logo + pangalan */
    .sb-mobile-bar {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 99;
        display: flex;
        align-items: center;
        gap: 10px;
        height: 56px;
        padding: 0 12px;
        background: #f39bc1;
        border-bottom: 1px solid #eba5c0;
        box-sizing: border-box;
    }

    .sb-menu-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 42px;
        height: 42px;
        margin: 0;
        padding: 0;
        border: 0;
        border-radius: 999px;
        background: rgba(255, 255, 255, .55);
        color: #5b2a45;
        cursor: pointer;
    }

    .sb-menu-btn svg {
        display: block;
        width: 22px;
        height: 22px;
        fill: none;
        stroke: currentColor;
        stroke-width: 2.4;
        stroke-linecap: round;
    }

    .sb-mobile-bar img {
        display: block;
        width: 36px;
        height: 36px;
        object-fit: contain;
    }

    .sb-mobile-name {
        font-family: 'Fraunces', Georgia, 'Times New Roman', serif;
        font-size: 17px;
        font-weight: 700;
        color: #43142f;
        white-space: nowrap;
    }

    /* page: buong lapad, nasa ilalim ng pink na bar */
    body .app > main.main {
        margin-left: 0;
        margin-top: 56px;
        width: 100%;
    }

    /* sidebar = drawer na lumalabas mula sa kaliwa */
    .sidebar {
        width: 264px;
        max-width: 85vw;
        transform: translateX(-100%);
        transition: transform .25s ease;
        box-shadow: 0 0 40px rgba(45, 14, 32, .25);
    }

    html.sb-open .sidebar {
        transform: none;
    }

    .sb-overlay {
        position: fixed;
        inset: 0;
        z-index: 99;
        display: block;
        background: rgba(45, 14, 32, .42);
        opacity: 0;
        pointer-events: none;
        transition: opacity .2s ease;
    }

    html.sb-open .sb-overlay {
        opacity: 1;
        pointer-events: auto;
    }

    html.sb-open body {
        overflow: hidden;
    }

    /* iPhone: nag-zo-zoom kapag mas maliit sa 16px ang text ng input */
    body .app > main.main :is(input, select, textarea) {
        font-size: 16px;
    }
}

/* ---------- 1180px pababa: ang detail box ay nasa ilalim ng listahan ---------- */
@media (max-width: 1180px) {
    /* pag tumalon sa detalye, hindi matatakpan ng pink na bar sa taas */
    .qv-shell .qv-right-col {
        scroll-margin-top: 66px;
    }

    /* walang napiling order = walang laman ang detail box, kaya itago */
    html body .app .qv-shell .qv-right-col--blank {
        display: none;
    }
}

/* ---------- [PHONE] 600px pababa: table = mga card (label sa taas, value sa ilalim) ----------
   Ang class na "cs-cards" at ang data-label ng bawat cell ay inilalagay ng script sa ibaba ([2]). */
@media (max-width: 600px) {
    html body .app table.cs-cards,
    html body .app table.cs-cards tbody {
        display: block;
        width: 100%;
        min-width: 0;
    }

    html body .app table.cs-cards thead {
        display: none;
    }

    html body .app table.cs-cards tbody tr:not(.cs-pg-off):not(.qv-hidden) {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px 14px;
        padding: 14px 12px;
        border-bottom: 1px solid #f6e8ed;
    }

    html body .app .qv-shell .qv-list-panel table.qv-table.cs-cards tbody td,
    html body .app table.qv-table.cs-cards tbody td,
    html body .app table.um-table.cs-cards tbody td {
        display: block;
        width: auto;
        max-width: none;
        min-width: 0;
        height: auto;
        padding: 0;
        border: 0;
        background: none;
        white-space: normal;
        overflow: visible;
        overflow-wrap: anywhere;
        text-align: left;
    }

    html body .app table.cs-cards tbody td[data-label]::before {
        content: attr(data-label);
        display: block;
        margin-bottom: 2px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: #7c6670;
    }

    /* buong lapad: mga button at ang "walang laman" na row */
    html body .app table.um-table.cs-cards tbody td.um-col-actions,
    html body .app table.cs-cards tbody td[colspan] {
        grid-column: 1 / -1;
        text-align: left;
    }
}
</style>

<script>
/* [0] icon lang ba ang sidebar? - inilalagay bago lumabas ang sidebar para walang "kislap" */
(function () {
    var saved = false;
    try { saved = localStorage.getItem("figurifySidebarCollapsed") === "1"; } catch (e) {}
    var small = window.matchMedia("(max-width: 1024px)");
    /* icon lang = desktop lang; sa phone / tablet / iPad drawer na (buong pangalan ng menu) */
    function apply() {
        document.documentElement.classList.toggle("sb-collapsed", saved && !small.matches);
        if (!small.matches) { document.documentElement.classList.remove("sb-open"); }
    }
    apply();
    small.addEventListener("change", apply);
    window.figurifySidebarSetCollapsed = function (value) {
        saved = value;
        try { localStorage.setItem("figurifySidebarCollapsed", value ? "1" : "0"); } catch (e) {}
        apply();
    };
})();
</script>

<aside class="sidebar">

    <div class="sb-brand">
        <img src="../Image/brand/logo-sidebar.png" alt="Clay and Stuff" class="sb-logo">
        <div class="sb-brand-text">
            <span class="sb-brand-name">Clay and Stuff</span>
            <span class="sb-brand-role"><?php echo $sbIsOwner ? "Owner Panel" : "Staff Panel"; ?></span>
        </div>
        <!-- arrow button: paliitin / palakihin ang sidebar (JS: [1b]) -->
        <button type="button" class="sb-toggle" id="sbToggle" aria-label="Collapse sidebar" title="Collapse sidebar">
            <svg viewBox="0 0 24 24"><path d="M15 6l-6 6 6 6"/></svg>
        </button>
    </div>

    <nav class="sb-nav">
<?php $sbGroupIndex = 0; ?>
<?php foreach ($sbMenu as $sbGroup): ?>
<?php if (!empty($sbGroup[2]) && !$sbIsOwner) { continue; } ?>
<?php $sbItemIndex = 0; ?>
<?php foreach ($sbGroup[1] as [$sbFile, $sbLabel, $sbIcon, $sbOwnerOnly, $sbAlso]): ?>
<?php if ($sbOwnerOnly && !$sbIsOwner) { continue; } ?>
<?php $sbOn = ($sbFile === $sbCurrent || in_array($sbCurrent, $sbAlso, true)); ?>
<?php $sbStart = ($sbGroupIndex > 0 && $sbItemIndex === 0); ?>
        <a href="<?php echo $sbFile; ?>" title="<?php echo htmlspecialchars($sbLabel); ?>" class="sb-nav-item<?php echo $sbOn ? " active" : ""; ?><?php echo $sbStart ? " sb-group-start" : ""; ?>">
            <span class="sb-icon"><svg viewBox="0 0 24 24"><?php echo $sbIcons[$sbIcon]; ?></svg></span>
            <span><?php echo htmlspecialchars($sbLabel); ?></span>
        </a>
<?php $sbItemIndex++; ?>
<?php endforeach; ?>
<?php $sbGroupIndex++; ?>
<?php endforeach; ?>
    </nav>

    <div class="sb-bottom">
        <a href="../Login/logout.php?portal=<?php echo $sbRole; ?>" class="sb-logout-btn">
            <span class="sb-icon"><svg viewBox="0 0 24 24"><?php echo $sbIcons["logout"]; ?></svg></span>
            <span>Log Out</span>
        </a>
    </div>

</aside>

<!-- phone / tablet lang: pink na bar sa taas + madilim na likod kapag bukas ang menu -->
<div class="sb-mobile-bar">
    <button type="button" class="sb-menu-btn" id="sbMenuBtn" aria-label="Open menu" aria-expanded="false">
        <svg viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
    </button>
    <img src="../Image/brand/logo-sidebar.png" alt="">
    <span class="sb-mobile-name">Clay and Stuff</span>
</div>
<div class="sb-overlay" id="sbOverlay"></div>

<script>
/* ==========================================================================
   [1b] JS - ARROW BUTTON: paliitin / palakihin ang sidebar
   (pagkatapos gumalaw, "resize" para mag-ayos ang mga chart / table ng page)
   ========================================================================== */
(function () {
    var button = document.getElementById("sbToggle");

    function syncLabel() {
        var label = document.documentElement.classList.contains("sb-collapsed") ? "Expand sidebar" : "Collapse sidebar";
        button.setAttribute("aria-label", label);
        button.title = label;
    }

    button.addEventListener("click", function () {
        window.figurifySidebarSetCollapsed(!document.documentElement.classList.contains("sb-collapsed"));
        syncLabel();
        setTimeout(function () { window.dispatchEvent(new Event("resize")); }, 220);
    });

    syncLabel();
})();
/* ==========================================================================
   END [1b] ARROW BUTTON
   ========================================================================== */


/* ==========================================================================
   [1c] JS - PHONE / TABLET MENU (☰): buksan / isara ang drawer
   ========================================================================== */
(function () {
    var menuBtn = document.getElementById("sbMenuBtn");
    var overlay = document.getElementById("sbOverlay");

    function setOpen(open) {
        document.documentElement.classList.toggle("sb-open", open);
        menuBtn.setAttribute("aria-expanded", open ? "true" : "false");
        menuBtn.setAttribute("aria-label", open ? "Close menu" : "Open menu");
    }

    menuBtn.addEventListener("click", function () {
        setOpen(!document.documentElement.classList.contains("sb-open"));
    });
    overlay.addEventListener("click", function () { setOpen(false); });
    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") { setOpen(false); }
    });
})();
/* ==========================================================================
   END [1c] PHONE / TABLET MENU
   ========================================================================== */


/* ==========================================================================
   [2] JS - OWNER / STAFF TABLES: Previous / Next (10 rows kada page)
       Para sa table.qv-table, table.um-table at table.st-data-table
   ========================================================================== */
(function () {
    var PAGE_SIZE = 10;

    var ICON_PREV = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>';
    var ICON_NEXT = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>';

    /* ang row ay "kasali" kung hindi siya itinago ng search / filter ng page */
    function isEligible(row) {
        return !row.classList.contains("qv-hidden") && !row.hidden;
    }

    /* phone card: bawat cell ay may label galing sa header ng column niya */
    function labelCells(table) {
        if (!table.tHead || !table.tHead.rows.length) { return; }
        var heads = Array.prototype.map.call(table.tHead.rows[0].cells, function (th) { return th.textContent.trim(); });
        Array.prototype.forEach.call(table.tBodies[0].rows, function (row) {
            Array.prototype.forEach.call(row.cells, function (cell, i) {
                if (!cell.hasAttribute("data-label") && cell.colSpan === 1 && heads[i]) {
                    cell.setAttribute("data-label", heads[i]);
                }
            });
        });
    }

    function setup(table) {
        if (table.getAttribute("data-cs-pager") || !table.tBodies.length) { return; }
        table.setAttribute("data-cs-pager", "1");
        if (!table.classList.contains("st-data-table")) { table.classList.add("cs-cards"); }

        var tbody = table.tBodies[0];
        var holder = table.closest(".qv-list, .um-table-wrap, .st-data-scroll") || table.parentNode;

        var bar = document.createElement("div");
        bar.className = "cs-pager";
        bar.setAttribute("role", "navigation");
        bar.setAttribute("aria-label", "Table pages");
        bar.innerHTML =
            '<button type="button" class="cs-pager-btn cs-pager-prev">' + ICON_PREV + 'Previous</button>' +
            '<span class="cs-pager-info"><strong></strong><span></span></span>' +
            '<button type="button" class="cs-pager-btn cs-pager-next">Next' + ICON_NEXT + '</button>';
        holder.parentNode.insertBefore(bar, holder.nextSibling);

        var btnPrev = bar.querySelector(".cs-pager-prev");
        var btnNext = bar.querySelector(".cs-pager-next");
        var infoTop = bar.querySelector(".cs-pager-info strong");
        var infoSub = bar.querySelector(".cs-pager-info span");

        var page = 1;
        var lastSignature = null;
        var observer = new MutationObserver(function () { render(); });

        function dataRows() {
            return Array.prototype.filter.call(tbody.rows, function (r) {
                return !r.classList.contains("um-empty-row");
            });
        }

        function render() {
            labelCells(table);
            var all = dataRows();
            var ok = all.filter(isEligible);

            /* nagbago ang search/filter -> balik sa page 1; unang labas -> page ng napiling order */
            var signature = ok.length + ":" + ok.map(function (r) { return all.indexOf(r); }).join(",");
            if (signature !== lastSignature) {
                if (lastSignature === null) {
                    var activeAt = -1;
                    ok.forEach(function (r, i) { if (activeAt < 0 && r.classList.contains("active")) { activeAt = i; } });
                    page = activeAt >= 0 ? Math.floor(activeAt / PAGE_SIZE) + 1 : 1;
                } else {
                    page = 1;
                }
                lastSignature = signature;
            }

            var total = ok.length;
            var pages = Math.max(1, Math.ceil(total / PAGE_SIZE));
            page = Math.min(Math.max(page, 1), pages);

            var start = (page - 1) * PAGE_SIZE;
            var end = start + PAGE_SIZE;

            observer.disconnect();

            var seen = 0;
            all.forEach(function (row) {
                if (isEligible(row)) {
                    row.classList.toggle("cs-pg-off", !(seen >= start && seen < end));
                    seen++;
                } else {
                    row.classList.remove("cs-pg-off");
                }
            });

            bar.hidden = (total === 0);
            infoTop.textContent = total === 0 ? "" : "Page " + page + " of " + pages;
            infoSub.textContent = total === 0 ? "" : "Showing " + (start + 1) + " to " + Math.min(end, total) + " of " + total;
            btnPrev.disabled = page <= 1;
            btnNext.disabled = page >= pages;

            observer.observe(tbody, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ["class", "style", "hidden"]
            });
        }

        function go(step) {
            page += step;
            render();
            if (holder.scrollTo) { holder.scrollTo(0, 0); }
        }

        btnPrev.addEventListener("click", function () { go(-1); });
        btnNext.addEventListener("click", function () { go(1); });

        render();
    }

    function init() {
        document.querySelectorAll("table.qv-table, table.um-table, table.st-data-table").forEach(setup);

        /* phone / tablet: may napiling order -> diretso sa detalye (nasa ilalim ng listahan) */
        var detail = document.querySelector(".qv-shell .qv-right-col");
        if (detail && document.querySelector(".qv-row.active") && window.matchMedia("(max-width: 1180px)").matches) {
            detail.scrollIntoView({ block: "start" });
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
/* ==========================================================================
   END [2] OWNER / STAFF TABLES
   ========================================================================== */

/* ==========================================================================
   [3] TOPBAR DATE / TIME - petsa at oras sa taas ng owner at staff page
   (.cs-tb-date at .cs-tb-time, en-PH, kada segundo). Isang kopya lang ito para sa lahat ng page.
   ========================================================================== */
(function () {
    function start() {
        var dateEl = document.querySelector(".cs-tb-datetime .cs-tb-date");
        var timeEl = document.querySelector(".cs-tb-datetime .cs-tb-time");
        if (!dateEl || !timeEl) { return; }

        function tick() {
            var now = new Date();
            dateEl.textContent = now.toLocaleDateString("en-PH", {
                weekday: "long", year: "numeric", month: "long", day: "numeric"
            });
            timeEl.textContent = now.toLocaleTimeString("en-PH", {
                hour: "2-digit", minute: "2-digit", second: "2-digit", hour12: true
            });
        }

        tick();
        setInterval(tick, 1000);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", start);
    } else {
        start();
    }
})();
/* ==========================================================================
   END [3] TOPBAR DATE / TIME
   ========================================================================== */

/* ==========================================================================
   [4] SECTION FILTER - puting dropdown ng Collection at User Management
   (HTML galing sa cs_section_filter() sa helpers/content_helper.php).
   Itinatago/ipinapakita ang section na may tamang id at tinatandaan ang napili.
   ========================================================================== */
(function () {
    function init() {
        document.querySelectorAll(".sf-dropdown[data-sf-key]").forEach(function (dd) {
            var trigger = dd.querySelector(".sf-trigger");
            var label   = dd.querySelector(".sf-label");
            var options = Array.prototype.slice.call(dd.querySelectorAll(".sf-option"));
            var storeKey = "sf-filter:" + dd.getAttribute("data-sf-key");

            function apply(opt) {
                options.forEach(function (o) {
                    var el = document.getElementById(o.getAttribute("data-target"));
                    if (el) { el.classList.toggle("sf-off", o !== opt); }
                    o.classList.toggle("active", o === opt);
                });
                label.textContent = opt.getAttribute("data-text");
            }

            function close() {
                dd.classList.remove("open");
                trigger.setAttribute("aria-expanded", "false");
            }

            trigger.addEventListener("click", function (e) {
                e.stopPropagation();
                var open = !dd.classList.contains("open");
                dd.classList.toggle("open", open);
                trigger.setAttribute("aria-expanded", open ? "true" : "false");
            });

            options.forEach(function (opt) {
                opt.addEventListener("click", function () {
                    apply(opt);
                    close();
                    try { sessionStorage.setItem(storeKey, opt.getAttribute("data-target")); } catch (err) {}
                    document.querySelectorAll(".sf-scroll").forEach(function (s) { s.scrollTop = 0; });
                });
            });

            document.addEventListener("click", function (e) {
                if (!dd.contains(e.target)) { close(); }
            });
            document.addEventListener("keydown", function (e) {
                if (e.key === "Escape") { close(); }
            });

            /* ibalik ang huling napiling filter (hal. pagkatapos mag-save) */
            var start = options[0];
            try {
                var saved = sessionStorage.getItem(storeKey);
                options.forEach(function (o) {
                    if (saved && o.getAttribute("data-target") === saved) { start = o; }
                });
            } catch (err) {}
            apply(start);
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
/* ==========================================================================
   END [4] SECTION FILTER
   ========================================================================== */
</script>
