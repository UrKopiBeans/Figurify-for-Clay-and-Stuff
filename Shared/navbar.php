<?php
/* ==========================================================================
   NAVBAR   (Shared/navbar.php)
   Navbar ng customer side. Iisang file: PHP sa taas, isang <style>, HTML,
   isang <script> sa ibaba.
   Ini-include ng bawat customer page:  <?php include __DIR__ . "/../Shared/navbar.php"; ?>

   Ang mga pop up ay nasa sariling file sa Login/ folder (may sariling
   PHP, style, HTML at script), ini-include lang dito:
     hindi naka-login  -> Login/Login.php           (Log In pop up)
                          Login/signup.php          (Sign Up pop up)
                          Login/forgot_password.php (Forgot Password pop up)

   LAMAN (taas pababa):
     [PHP]     session, pangalan, avatar, kasalukuyang page
     [STYLE]   [1] NAVBAR  [2] RESPONSIVE
     [HTML]    navbar, pop ups (include)
     [SCRIPT]  [1] PROFILE DROPDOWN  [2] MOBILE MENU  [3] NOTIFICATION AUTO-REFRESH
               [4] NAKA-SAVE NA ORDER (Commission)
   ========================================================================== */


/* ==========================================================================
   [PHP] SETUP
   ========================================================================== */
require_once __DIR__ . "/../helpers/security_helper.php";
require_once __DIR__ . "/../helpers/content_helper.php";
figurify_start_session();

$isLoggedIn = isset($_SESSION["user_id"]);

/* ang naka-save na order sa browser ay para sa isang login lang: bagong marker kada login (nabubura ang session kapag nag log out) */
if ($isLoggedIn && empty($_SESSION["order_owner"])) {
    $_SESSION["order_owner"] = bin2hex(random_bytes(8));
}
$orderOwner = $isLoggedIn ? $_SESSION["order_owner"] : "guest";

$customer_name  = $_SESSION["full_name"] ?? "";
$customer_email = $_SESSION["email"] ?? "";

// base path para tama ang src ng avatar kahit saang folder naka-include ang navbar
$navbar_documentRoot = rtrim(str_replace("\\", "/", realpath($_SERVER["DOCUMENT_ROOT"])), "/");
$navbar_projectRoot  = rtrim(str_replace("\\", "/", realpath(__DIR__ . "/..")), "/");
$navbar_siteBase     = substr($navbar_projectRoot, strlen($navbar_documentRoot)) . "/";

// profile picture kung may na-upload, kung wala ay bilog na may unang letra
$navbar_avatar_html = figurify_render_avatar(
    $customer_name,
    $_SESSION["profile_picture"] ?? null,
    "Profile",
    $navbar_siteBase
);

$current_page = basename($_SERVER["PHP_SELF"]);
/* ====================== END PHP SETUP ====================== */
?>

<style>
/* ==========================================================================
   [1] NAVBAR
   Lahat ay naka-scope sa .navbar-wrapper para hindi madamay ang page.
   ========================================================================== */
.navbar-wrapper {
    position: sticky;
    top: 0;
    z-index: 1000;
    padding: 0 24px;
    background: #f39bc1;
    border-bottom: 1px solid #eba5c0;
    font-family: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif;
    font-size: 16px;
    line-height: 1.5;
}

.navbar-wrapper * { box-sizing: border-box; }
.navbar-wrapper button { font-family: inherit; }

/* logo | links | kanan (profile o Log In) */
.navbar-wrapper .navbar {
    position: relative;
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    align-items: center;
    gap: 12px;
    height: 75px;
}

/* ---------- logo ---------- */
.navbar-wrapper .brand {
    display: flex;
    align-items: center;
    justify-content: center;
    justify-self: start;
    gap: 10px;
    text-decoration: none;
    color: #43142f;
}

.navbar-wrapper .brand span {
    font-family: 'Fraunces', Georgia, 'Times New Roman', serif;
    font-size: 24px;
    font-weight: 700;
    letter-spacing: -.2px;
    line-height: 1;
}

.navbar-wrapper .brand-photo { width: 56px; height: 56px; flex-shrink: 0; }
.navbar-wrapper .brand-photo img { width: 100%; height: 100%; object-fit: contain; }

/* ---------- links (OVERVIEW, COLLECTIONS, COMMISSION) ---------- */
.navbar-wrapper .nav-links {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.navbar-wrapper .nav-links > a {
    position: relative;
    padding: 9px 20px;
    border: 1.5px solid transparent;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 600;
    letter-spacing: .2px;
    line-height: 1.2;
    text-align: center;
    text-decoration: none;
    color: #5b2a45;
    transition: background .2s ease, color .2s ease, border-color .2s ease;
}

.navbar-wrapper .nav-links > a:hover {
    color: #a3204c;
    background: rgba(255, 255, 255, .55);
}

/* kasalukuyang page: puting kahon na may border */
.navbar-wrapper .nav-links > a.active {
    color: #a3204c;
    font-weight: 700;
    background: #fff;
    border-color: #eba5c0;
    box-shadow: 0 2px 6px -2px rgba(131, 24, 67, .18);
}

/* ---------- kanan ---------- */
.navbar-wrapper .nav-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    justify-self: end;
    gap: 12px;
}

/* Log In button (hindi naka-login) - binubuksan ang auth pop up */
.navbar-wrapper .btn-login-only {
    display: inline-block;
    padding: 10px 16px;
    border-radius: 999px;
    background: #c0325e;
    color: #fff;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    transition: background .2s ease;
}

.navbar-wrapper .btn-login-only:hover { background: #a82a52; }

/* ---------- profile pill (naka-login, desktop) ---------- */
.navbar-wrapper .profile-dropdown-container { position: relative; }

.navbar-wrapper .user-profile-badge {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 5px 14px 5px 6px;
    background: #fff;
    border: 1px solid #efdfe8;
    border-radius: 99px;
    box-shadow: 0 4px 12px rgba(131, 24, 67, .05);
    cursor: pointer;
    transition: all .25s ease;
    position: relative;
}

.navbar-wrapper .profile-avatar {
    width: 36px;
    height: 36px;
    flex-shrink: 0;
    overflow: hidden;
    border: 2px solid #e58aa9;
    border-radius: 50%;
    background: #fff;
}

.navbar-wrapper .profile-avatar img { width: 100%; height: 100%; object-fit: cover; }

/* unang letra ng pangalan - galing sa figurify_render_avatar() */
.navbar-wrapper .avatar-initials {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 15px;
    font-weight: 800;
}

.navbar-wrapper .profile-info {
    display: flex;
    flex-direction: column;
    text-align: left;
    line-height: 1.2;
}

.navbar-wrapper .profile-info strong { color: #43142f; font-size: 12px; font-weight: 800; }
.navbar-wrapper .profile-info span { color: #7a5a6e; font-size: 10px; font-weight: 600; opacity: .9; }

.navbar-wrapper .dropdown-arrow {
    width: 14px;
    height: 14px;
    flex-shrink: 0;
    margin-left: 2px;
    stroke: #7a5a6e;
    transition: transform .2s ease;
}

.navbar-wrapper .user-profile-badge[aria-expanded="true"] .dropdown-arrow { transform: rotate(180deg); }

/* ---------- dropdown ng profile (lumalabas kapag may .show) ---------- */
.navbar-wrapper .dropdown-menu {
    position: absolute;
    z-index: 1001;
    top: 54px;
    right: 0;
    width: 210px;
    padding: 8px;
    background: #fff;
    border: 1px solid #efdfe8;
    border-radius: 16px;
    box-shadow: 0 18px 40px -10px rgba(67, 20, 47, .18);
    opacity: 0;
    visibility: hidden;
    transform: translateY(-12px) scale(.96);
    transition: all .25s cubic-bezier(.16, 1, .3, 1);
}

.navbar-wrapper .dropdown-menu.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0) scale(1);
}

/* My Profile / My Orders / Notification / Log Out (dropdown at mobile menu) */
.navbar-wrapper .dropdown-item {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 10px 12px;
    border-radius: 10px;
    color: #701a75;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    transition: all .15s ease;
}

.navbar-wrapper .dropdown-item svg {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
    stroke: #c0325e;
    transition: transform .2s ease;
}

.navbar-wrapper .dropdown-item:hover {
    background: #fdf2f8;
    color: #c0325e;
    transform: translateX(3px);
}

.navbar-wrapper .dropdown-item:hover svg { transform: scale(1.15); }

/* bilang ng hindi pa nababasang notification (kapareho ng staff/owner) */
.navbar-wrapper .notif-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 19px;
    height: 19px;
    padding: 0 5px;
    border: 2px solid #fff;
    border-radius: 99px;
    background: #d96b78;
    color: #fff;
    font-size: 10px;
    font-weight: 800;
    line-height: 1;
}

.navbar-wrapper .notif-count[hidden] { display: none; }
.navbar-wrapper .dropdown-item .notif-count { margin-left: auto; }

/* nasa gilid ng profile picture sa navbar */
.navbar-wrapper .user-profile-badge > .notif-count { position: absolute; top: -6px; left: 28px; }

.navbar-wrapper .dropdown-divider { height: 1px; margin: 4px 2px; background: #fce7f3; }

.navbar-wrapper .dropdown-item.logout {
    background: none;
    border: none;
    color: #be123c;
    text-align: left;
    cursor: pointer;
}

.navbar-wrapper .dropdown-item.logout svg { stroke: #be123c; }
.navbar-wrapper .dropdown-item.logout:hover { background: #ffe4e6; }

/* ---------- account sa loob ng mobile menu (nakatago sa desktop) ---------- */
.navbar-wrapper .nav-links-account { display: none; }

.navbar-wrapper .nav-links-account-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 4px 12px 12px;
}

/* buong pangalan at email, bumababa sa susunod na linya kapag mahaba */
.navbar-wrapper .nav-links-account-header .profile-info { flex: 1; min-width: 0; }

.navbar-wrapper .nav-links-account-header .profile-info strong,
.navbar-wrapper .nav-links-account-header .profile-info span {
    display: block;
    white-space: normal;
    word-break: break-word;
}

/* ---------- hamburger button (lumalabas sa [2] RESPONSIVE) ---------- */
.navbar-wrapper .mobile-menu-btn {
    display: none;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 40px;
    height: 40px;
    border: none;
    border-radius: 50%;
    background: #fbf3f7;
    color: #43142f;
    cursor: pointer;
}

.navbar-wrapper .mobile-menu-btn svg { width: 22px; height: 22px; stroke: #43142f; }
/* ====================== END NAVBAR ====================== */


/* ==========================================================================
   [2] RESPONSIVE
   ========================================================================== */

/* tablet / phone: hamburger menu */
@media (max-width: 820px) {
    /* flex sa halip na grid para laging nasa kanan ang hamburger */
    .navbar-wrapper .navbar { display: flex; justify-content: space-between; }

    /* links = puting menu sa ilalim ng hamburger (bukas kapag may .active) */
    .navbar-wrapper .nav-links {
        display: none;
        position: absolute;
        top: 82px;
        right: 16px;
        left: auto;
        flex-direction: column;
        width: 260px;
        max-width: calc(100vw - 32px);
        padding: 12px;
        border-radius: 20px;
        background: #fff;
        box-shadow: 0 18px 40px -10px rgba(67, 20, 47, .18);
    }

    .navbar-wrapper .nav-links.active { display: flex; }

    .navbar-wrapper .nav-links > a { width: 100%; padding: 10px 0; color: #7a5a6e; }
    .navbar-wrapper .nav-links > a:hover { color: #c0325e; }

    .navbar-wrapper .nav-links > a.active {
        color: #c0325e;
        background: #fbf3f7;
        border-color: #f0c9d8;
        box-shadow: none;
    }

    .navbar-wrapper .mobile-menu-btn { display: flex; }

    /* profile pill ay nakatago; nasa loob na ng hamburger menu ang account */
    .navbar-wrapper .profile-dropdown-container { display: none; }

    .navbar-wrapper .nav-links-account {
        display: flex;
        flex-direction: column;
        gap: 2px;
        width: 100%;
        margin-bottom: 10px;
        padding: 10px;
        border: 1px solid #fce7f3;
        border-radius: 14px;
    }
}

/* phone: logo lang, walang "Clay and Stuff" na text */
@media (max-width: 500px) {
    .navbar-wrapper { padding: 0 14px; }
    .navbar-wrapper .brand span { display: none; }
}
/* ====================== END RESPONSIVE ====================== */

/* ===== kulay ng bilog na may unang letra (galing sa figurify_render_avatar) ===== */
.navbar-wrapper .avatar-initials.avatar-color-0 { background: #e77fbd; }
.navbar-wrapper .avatar-initials.avatar-color-1 { background: #b9a0ef; }
.navbar-wrapper .avatar-initials.avatar-color-2 { background: #f472b6; }
.navbar-wrapper .avatar-initials.avatar-color-3 { background: #9b3f72; }
.navbar-wrapper .avatar-initials.avatar-color-4 { background: #db2777; }
.navbar-wrapper .avatar-initials.avatar-color-5 { background: #a88be5; }
.navbar-wrapper .avatar-initials.avatar-color-6 { background: #831843; }
.navbar-wrapper .avatar-initials.avatar-color-7 { background: #c026d3; }
</style>


<!-- ==========================================================================
     [HTML] NAVBAR
     ========================================================================== -->
<header class="navbar-wrapper">
<nav class="navbar">

    <!-- logo -->
    <a href="../Home/Home.php" class="brand">
        <div class="brand-photo">
            <img src="../Image/brand/logo.png" alt="Clay and Stuff">
        </div>
        <span>Clay and Stuff</span>
    </a>

    <!-- links (sa mobile: ito ang hamburger menu) -->
    <div class="nav-links" id="navLinks">

        <?php if ($isLoggedIn): ?>
            <!-- account - sa mobile menu lang lumalabas -->
            <div class="nav-links-account">

                <div class="nav-links-account-header">
                    <div class="profile-avatar"><?php echo $navbar_avatar_html; ?></div>
                    <div class="profile-info">
                        <strong><?php echo htmlspecialchars($customer_name ?: "Customer"); ?></strong>
                        <span><?php echo htmlspecialchars($customer_email); ?></span>
                    </div>
                </div>

                <a href="../Profile/profile.php" class="dropdown-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    My Profile
                </a>

                <a href="../my-order/my-orders.php" class="dropdown-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                    My Orders
                </a>

                <a href="../Notification/notifications.php" class="dropdown-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    Notification
                    <span class="notif-count" data-notif-count hidden></span>
                </a>

                <div class="dropdown-divider"></div>

                <a href="../Login/logout.php?portal=customer" class="dropdown-item logout">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    Log Out
                </a>

            </div>
        <?php endif; ?>

        <a href="../Home/Home.php" class="<?php echo $current_page === "Home.php" ? "active" : ""; ?>">OVERVIEW</a>
        <a href="../Collection/collection.php" class="<?php echo $current_page === "collection.php" ? "active" : ""; ?>">COLLECTIONS</a>
        <a href="../Commission/commission.php" class="<?php echo in_array($current_page, ["commission.php", "image-submission.php", "dress-up.php"], true) ? "active" : ""; ?>">COMMISSION</a>

    </div>

    <!-- kanan: profile (naka-login) o Log In, + hamburger -->
    <div class="nav-actions">

        <?php if ($isLoggedIn): ?>
            <div class="profile-dropdown-container">

                <div class="user-profile-badge" id="profileButton" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false">
                    <div class="profile-avatar"><?php echo $navbar_avatar_html; ?></div>
                    <span class="notif-count" data-notif-count hidden></span>
                    <div class="profile-info">
                        <strong><?php echo htmlspecialchars($customer_name ?: "Customer"); ?></strong>
                        <span><?php echo htmlspecialchars($customer_email); ?></span>
                    </div>
                    <svg class="dropdown-arrow" viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                </div>

                <div class="dropdown-menu" id="profileMenu">

                    <a href="../Profile/profile.php" class="dropdown-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        My Profile
                    </a>

                    <a href="../my-order/my-orders.php" class="dropdown-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                        My Orders
                    </a>

                    <a href="../Notification/notifications.php" class="dropdown-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        Notification
                        <span class="notif-count" data-notif-count hidden></span>
                    </a>

                    <div class="dropdown-divider"></div>

                    <a href="../Login/logout.php?portal=customer" class="dropdown-item logout">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                        Log Out
                    </a>

                </div>

            </div>
        <?php else: ?>
            <!-- bubuksan ang Log In pop up (Login/Login.php); kung walang JS, papunta sa Login.php -->
            <a href="../Login/Login.php" class="btn-login-only" data-auth-open="login">Log In</a>
        <?php endif; ?>

        <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Toggle Menu" type="button">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>

    </div>

</nav>
</header>
<!-- ====================== END NAVBAR ====================== -->


<!-- ==========================================================================
     [HTML] POP UPS  (nasa Login/ folder ang PHP, style, HTML at script ng bawat isa)
     ========================================================================== -->
<?php
if (!$isLoggedIn) {
    include __DIR__ . "/../Login/Login.php";
    include __DIR__ . "/../Login/signup.php";
    include __DIR__ . "/../Login/forgot_password.php";
}
?>
<!-- ====================== END POP UPS ====================== -->


<script>
(function () {

    /* ==========================================================================
       [1] PROFILE DROPDOWN  (naka-login, desktop)
       ========================================================================== */
    const profileButton = document.getElementById("profileButton");
    const profileMenu   = document.getElementById("profileMenu");

    if (profileButton && profileMenu) {

        profileButton.addEventListener("click", function (event) {
            event.stopPropagation();
            const isOpen = profileMenu.classList.toggle("show");
            profileButton.setAttribute("aria-expanded", isOpen ? "true" : "false");
        });

        // Enter / Space sa keyboard = click
        profileButton.addEventListener("keydown", function (event) {
            if (event.key === "Enter" || event.key === " ") {
                event.preventDefault();
                profileButton.click();
            }
        });

        // isara kapag cinlick ang labas ng dropdown
        document.addEventListener("click", function (event) {
            if (!profileButton.contains(event.target) && !profileMenu.contains(event.target)) {
                profileMenu.classList.remove("show");
                profileButton.setAttribute("aria-expanded", "false");
            }
        });
    }
    /* ====================== END PROFILE DROPDOWN ====================== */


    /* ==========================================================================
       [2] MOBILE MENU  (hamburger)
       ========================================================================== */
    const mobileMenuBtn = document.getElementById("mobileMenuBtn");
    const navLinks      = document.getElementById("navLinks");

    mobileMenuBtn.addEventListener("click", function (event) {
        event.stopPropagation();
        navLinks.classList.toggle("active");
    });
    /* ====================== END MOBILE MENU ====================== */


    /* ==========================================================================
       [3] NOTIFICATION AUTO-REFRESH  (naka-login)
       Tinitingnan kada 3 segundo ang Notification/customer_bell.php; nire-reload
       ang page kapag may bagong notification. Hindi nire-reload kapag nakatago
       ang tab o may tina-type ang user.
       Sa Commission, Dress Up at Image Submission: badge lang, HINDI nire-reload - para hindi
       maputol ang pag-submit ng order (may bagong notification agad pagka-submit) at ang disenyo.
       ========================================================================== */
    const isLoggedIn = <?php echo $isLoggedIn ? "true" : "false"; ?>;
    // nasa Commission folder ba (Commission, Dress Up, Image Submission)? galing sa PHP (totoong folder ng file),
    // hindi sa link - para gumana rin sa maikling link (clayandstuff.com/dress-up.php). Ginagamit din sa [4].
    const inOrderPages = <?php echo basename(dirname(str_replace("\\", "/", $_SERVER["SCRIPT_FILENAME"] ?? ""))) === "Commission" ? "true" : "false"; ?>;

    if (isLoggedIn) {

        let knownMaxNotificationId = null;

        function isUserTyping() {
            const active = document.activeElement;
            const tag = active && active.tagName ? active.tagName.toLowerCase() : "";
            return tag === "textarea" || tag === "input" || tag === "select";
        }

        function checkNotifications() {
            fetch("../Notification/customer_bell.php?action=list")
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    const notifications = data.notifications || [];

                    // ilagay ang bilang ng hindi pa nababasa sa badge (nakatago kapag 0)
                    const unread = data.unread_count || 0;
                    document.querySelectorAll("[data-notif-count]").forEach(function (el) {
                        el.textContent = unread > 9 ? "9+" : String(unread);
                        el.hidden = unread === 0;
                    });

                    const currentMaxId = notifications.reduce(function (max, note) {
                        return note.notification_id > max ? note.notification_id : max;
                    }, 0);

                    // unang check: itatala lang, walang reload
                    if (knownMaxNotificationId === null) {
                        knownMaxNotificationId = currentMaxId;
                        return;
                    }

                    if (currentMaxId <= knownMaxNotificationId) return;   // walang bago
                    if (inOrderPages || document.hidden || isUserTyping()) return;

                    window.location.reload();
                })
                .catch(function () {});
        }

        checkNotifications();
        setInterval(checkNotifications, 3000);
    }
    /* ====================== END NOTIFICATION AUTO-REFRESH ====================== */


    /* ==========================================================================
       [4] NAKA-SAVE NA ORDER (Commission)
       Ang napili sa Commission, Image Submission at Dress Up ay naka-save sa browser para sa
       order na ito lang. Dito ang IISANG listahan ng mga ito at ang pambura (window.figurifyClearOrder).
       - Lumipat sa ibang page (Home, Collection, atbp.) = nananatili ang napili
       - Hindi nabubura kapag nag Log In o Sign Up (nananatili sa parehong page ang customer)
       - Nabubura kapag nag log out, naipasa na ang order, o lumipat ng order method
         (dress-up.js binubura ang Image Submission; image-submission.js binubura ang Dress Up)
       ========================================================================== */
    const ORDER_KEYS = {
        schedule: ["figurifyCommissionOrderType", "figurifyCommissionBookingDate", "figurifyCommissionMethod"],
        dressUp: ["figurifyCustomization", "figurifyDressUpCart", "figurifyDressUpDraft", "figurifyDressUpEditing", "figurifyDressUpQty", "figurifyCommissionFigureCategory"],
        imageSubmission: ["figurifyCartSession"]
    };

    // parts (optional) = hal. ["dressUp"] lang ang buburahin; walang parts = lahat
    window.figurifyClearOrder = function (parts) {
        const list = parts || Object.keys(ORDER_KEYS);

        try {
            list.forEach(function (part) {
                ORDER_KEYS[part].forEach(function (key) { sessionStorage.removeItem(key); });
            });

            if (list.includes("imageSubmission")) {
                indexedDB.deleteDatabase("figurifyImageSubmission");
            }
        } catch (error) {}
    };

    // log out = bagong simula, kaya nabubura ang naka-save na order
    document.querySelectorAll('a[href*="logout.php"]').forEach(function (link) {
        link.addEventListener("click", function () { window.figurifyClearOrder(); });
    });

    // pagkatapos ma-submit ang order: ?fresh=1 sa link = burahin ang lahat ng napili bago basahin ng page (back to 0),
    // tapos tanggalin ang ?fresh=1 para hindi maulit kapag nag-refresh
    try {
        const pageUrl = new URL(window.location.href);

        if (pageUrl.searchParams.get("fresh") === "1") {
            window.figurifyClearOrder();
            pageUrl.searchParams.delete("fresh");
            history.replaceState(null, "", pageUrl.pathname + pageUrl.search + pageUrl.hash);
        }
    } catch (error) {}

    // iba na ang marker (nag log out at nag log in ulit, nag-expire ang session, o na-submit na ang order): burahin ang naiwang order.
    // Ang guest na nag-log in sa Submit Order ay hindi binubura (nananatili ang napili niya)
    try {
        const orderOwner = <?php echo json_encode($orderOwner); ?>;
        const savedOwner = sessionStorage.getItem("figurifyOrderOwner");

        if (savedOwner && savedOwner !== "guest" && savedOwner !== orderOwner) {
            window.figurifyClearOrder();
        }

        sessionStorage.setItem("figurifyOrderOwner", orderOwner);
    } catch (error) {}

    // galing sa back/forward cache pero nabura na ang napili (lumabas sa order): i-reload para blangko rin ang page
    window.addEventListener("pageshow", function (event) {
        if (event.persisted && inOrderPages && !sessionStorage.getItem("figurifyCommissionOrderType")) {
            window.location.reload();
        }
    });

})();
</script>
