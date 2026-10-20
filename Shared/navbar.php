<?php

require_once __DIR__ . "/../helpers/session_helper.php";
require_once __DIR__ . "/../helpers/avatar_helper.php";
figurify_start_session();

$isLoggedIn = isset($_SESSION["user_id"]);

$customer_name = $_SESSION["full_name"] ?? "";
$customer_email = $_SESSION["email"] ?? "";

// base path so avatar src is correct wherever navbar is included
$navbar_documentRoot = rtrim(str_replace("\\", "/", realpath($_SERVER["DOCUMENT_ROOT"])), "/");
$navbar_projectRoot  = rtrim(str_replace("\\", "/", realpath(__DIR__ . "/..")), "/");
$navbar_siteBase     = substr($navbar_projectRoot, strlen($navbar_documentRoot)) . "/";

// uses saved profile picture if may na-upload, else initials avatar
$navbar_avatar_html = figurify_render_avatar(
    $customer_name,
    $_SESSION["profile_picture"] ?? null,
    "Profile",
    $navbar_siteBase
);

$current_page = basename($_SERVER['PHP_SELF']);


?>


<style>
/* everything scoped under .navbar-wrapper so it doesn't leak
   into the rest of the page */

.navbar-wrapper {
    --nb-primary-pink: #e58aa9;
    --nb-brand-dark: #43142f;
    --nb-brand-accent: #c0325e;
    --nb-nav-bg: #f6bdd1;
    --nb-link: #5b2a45;
    --nb-link-active: #a3204c;
    --nb-border-color: #efdfe8;
    --nb-shadow-dropdown: 0 18px 40px -10px rgba(67, 20, 47, 0.18);

    position: sticky;
    top: 0;
    z-index: 1000;
    padding: 0 24px;
    background: var(--nb-nav-bg);
    border-bottom: 1px solid #eba5c0;
    box-shadow: none;
    
    line-height: 1.5;
}

.navbar-wrapper * {
    box-sizing: border-box;
    
}

.navbar-wrapper .navbar {
    max-width: 100%;
    margin: 0 auto;
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    align-items: center;
    gap: 12px;
    position: relative;
    height: 75px;
    background: transparent;
    padding: 0;
    border-bottom: none;
}

/* BRAND */

.navbar-wrapper .brand {
    display: flex;
    align-items: center;
    justify-content: center;
    justify-self: start;
    gap: 10px;
    text-decoration: none;
    color: var(--nb-brand-dark);
}

.navbar-wrapper .brand span {
    
    font-weight: 700;
    font-size: 24px;
    letter-spacing: -0.2px;
    line-height: 1;
}

.navbar-wrapper .brand-photo {
    width: 56px;
    height: 56px;
    flex-shrink: 0;
}

.navbar-wrapper .brand-photo img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

/* NAV LINKS */

.navbar-wrapper .nav-links {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 30px;
}

.navbar-wrapper .nav-links > a {
    position: relative;
    text-decoration: none;
    color: var(--nb-link);
    font-size: 15px;
    font-weight: 500;
    padding: 8px 0;
    transition: color 0.2s ease;
    letter-spacing: 0.3px;
    text-align: center;
}

.navbar-wrapper .nav-links > a:hover {
    color: var(--nb-link-active);
}

/* active link: crimson + underline */
.navbar-wrapper .nav-links > a.active {
    color: var(--nb-link-active);
    font-weight: 600;
}

.navbar-wrapper .nav-links > a.active::after {
    content: "";
    position: absolute;
    left: 0;
    right: 0;
    bottom: -4px;
    height: 3px;
    border-radius: 3px;
    background: var(--nb-link-active);
}

/* RIGHT ACTIONS */

.navbar-wrapper .nav-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    justify-self: end;
    gap: 12px;
}

/* TEXT-ONLY LOG IN BUTTON (logged out) */

.navbar-wrapper .btn-login-only {
    text-decoration: none;
    background: var(--nb-brand-accent);
    color: #ffffff;
    font-size: 14px;
    font-weight: 700;
    padding: 10px 16px;
    border-radius: 8px;
    box-shadow: none;
    transition: background 0.2s ease;
    display: inline-block;
}

.navbar-wrapper .btn-login-only:hover {
    background: #a82a52;
}

/* PROFILE DROPDOWN (logged in) */

.navbar-wrapper .profile-dropdown-container {
    position: relative;
}

.navbar-wrapper .user-profile-badge {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 5px 14px 5px 6px;
    background: #ffffff;
    border: 1px solid var(--nb-border-color);
    border-radius: 99px;
    cursor: pointer;
    transition: all 0.25s ease;
    box-shadow: 0 4px 12px rgba(131, 24, 67, 0.05);
}

.navbar-wrapper .user-profile-badge:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(219, 39, 119, 0.15);
    border-color: var(--nb-primary-pink);
}

.navbar-wrapper .profile-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    overflow: hidden;
    border: 2px solid var(--nb-primary-pink);
    flex-shrink: 0;
    background: #ffffff;
}

.navbar-wrapper .profile-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.navbar-wrapper .avatar-initials {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    font-weight: 800;
    font-size: 15px;
}

.navbar-wrapper .profile-info {
    display: flex;
    flex-direction: column;
    text-align: left;
    line-height: 1.2;
}

.navbar-wrapper .profile-info strong {
    color: var(--nb-brand-dark);
    font-size: 12px;
    font-weight: 800;
}

.navbar-wrapper .profile-info span {
    color: #7a5a6e;
    font-size: 10px;
    font-weight: 600;
    opacity: 0.9;
}

.navbar-wrapper .dropdown-arrow {
    width: 14px;
    height: 14px;
    stroke: #7a5a6e;
    margin-left: 2px;
    transition: transform 0.2s ease;
    flex-shrink: 0;
}

.navbar-wrapper .user-profile-badge[aria-expanded="true"] .dropdown-arrow {
    transform: rotate(180deg);
}

/* DROPDOWN MENU */

.navbar-wrapper .dropdown-menu {
    position: absolute;
    top: 54px;
    right: 0;
    width: 210px;
    background: #ffffff;
    border: 1px solid var(--nb-border-color);
    border-radius: 16px;
    padding: 8px;
    box-shadow: var(--nb-shadow-dropdown);
    opacity: 0;
    visibility: hidden;
    transform: translateY(-12px) scale(0.96);
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    z-index: 1001;
}

.navbar-wrapper .dropdown-menu.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0) scale(1);
}

.navbar-wrapper .dropdown-item {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 10px 12px;
    border-radius: 10px;
    text-decoration: none;
    color: #701a75;
    font-size: 13px;
    font-weight: 700;
    transition: all 0.15s ease;
}

.navbar-wrapper .dropdown-item svg {
    width: 18px;
    height: 18px;
    stroke: var(--nb-brand-accent);
    transition: transform 0.2s ease;
    flex-shrink: 0;
}

.navbar-wrapper .dropdown-item:hover {
    background: #fdf2f8;
    color: var(--nb-brand-accent);
    transform: translateX(3px);
}

.navbar-wrapper .dropdown-item:hover svg {
    transform: scale(1.15);
}

.navbar-wrapper .dropdown-divider {
    height: 1px;
    background: #fce7f3;
    margin: 4px 2px;
}

.navbar-wrapper .dropdown-item.logout {
    color: #be123c;
    width: 100%;
    background: none;
    border: none;
    text-align: left;
    cursor: pointer;
}

.navbar-wrapper .dropdown-item.logout svg {
    stroke: #be123c;
}

.navbar-wrapper .dropdown-item.logout:hover {
    background: #ffe4e6;
}

/* mobile-only account block inside hamburger menu, hidden on
   desktop (desktop uses the separate profile pill instead) */
.navbar-wrapper .nav-links-account {
    display: none;
}

.navbar-wrapper .nav-links-account-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 4px 12px 12px;
}

/* show full name/email even on small screens, just wrap if needed */
.navbar-wrapper .nav-links-account-header .profile-info {
    min-width: 0;
    flex: 1;
}

.navbar-wrapper .nav-links-account-header .profile-info strong,
.navbar-wrapper .nav-links-account-header .profile-info span {
    display: block;
    white-space: normal;
    word-break: break-word;
}

/* LOGOUT CONFIRMATION MODAL */

.nb-logout-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(61,36,48,.4);
    backdrop-filter: blur(3px);
    -webkit-backdrop-filter: blur(3px);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    pointer-events: none;
    transition: opacity .2s ease;
    z-index: 2000;
}

.nb-logout-modal-overlay.active {
    opacity: 1;
    pointer-events: auto;
}

.nb-logout-modal-box {
    background: #ffffff;
    width: min(300px, 90%);
    padding: 26px 22px 22px;
    border-radius: 18px;
    text-align: center;
    box-shadow: 0 25px 60px rgba(131,24,67,.25);
    
}

.nb-logout-modal-icon {
    width: 46px;
    height: 46px;
    margin: 0 auto 14px;
    border-radius: 50%;
    background: #fce7f3;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #db2777;
}

.nb-logout-modal-icon svg {
    width: 22px;
    height: 22px;
    stroke: currentColor;
    fill: none;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.nb-logout-modal-title {
    font-size: 16px;
    font-weight: 800;
    color: #831843;
    margin-bottom: 6px;
}

.nb-logout-modal-desc {
    font-size: 13px;
    font-weight: 600;
    color: #9f1239;
    opacity: .85;
    margin-bottom: 18px;
}

.nb-logout-modal-actions {
    display: flex;
    gap: 8px;
}

.nb-logout-modal-btn {
    flex: 1;
    height: 38px;
    border: 0;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: .15s ease;
    
}

.nb-logout-modal-btn-cancel {
    background: #f3e8ef;
    color: #701a75;
}

.nb-logout-modal-btn-cancel:hover {
    background: #e9d7e3;
}

.nb-logout-modal-btn-confirm {
    background: #db2777;
    color: #ffffff;
}

.nb-logout-modal-btn-confirm:hover {
    background: #be123c;
}

/* MOBILE MENU BUTTON */

.navbar-wrapper .mobile-menu-btn {
    display: none;
    background: #fbf3f7;
    border: none;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    color: var(--nb-brand-dark);
    cursor: pointer;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.navbar-wrapper .mobile-menu-btn svg {
    width: 22px;
    height: 22px;
    stroke: var(--nb-brand-dark);
}

/* mobile / tablet */

@media (max-width: 1000px) {
    .navbar-wrapper { padding: 0 24px; }
}

@media (max-width: 820px) {

    /* flex + space-between instead of grid, keeps hamburger
       pinned right (nav-links floats now) */
    .navbar-wrapper .navbar {
        display: flex;
        justify-content: space-between;
    }

    .navbar-wrapper .nav-links {
        display: none;
        position: absolute;
        top: 82px;
        right: 16px;
        left: auto;
        width: 260px;
        max-width: calc(100vw - 32px);
        flex-direction: column;
        background: #ffffff;
        padding: 12px;
        border-radius: 20px;
        box-shadow: var(--nb-shadow-dropdown);
    }

    .navbar-wrapper .nav-links.active {
        display: flex;
    }

    .navbar-wrapper .nav-links > a {
        width: 100%;
        text-align: center;
        color: #7a5a6e;
        padding: 10px 0;
        border-radius: 10px;
    }

    .navbar-wrapper .nav-links > a:hover {
        color: var(--nb-brand-accent);
    }

    .navbar-wrapper .nav-links > a.active {
        color: var(--nb-brand-accent);
        background: #fbf3f7;
    }

    .navbar-wrapper .nav-links > a.active::after {
        display: none;
    }

    .navbar-wrapper .mobile-menu-btn {
        display: flex;
    }

    /* only hides on the desktop pill, not .nav-links-account */
    .navbar-wrapper .profile-dropdown-container .profile-info span {
        display: none;
    }

    /* hide the separate profile pill on mobile, use hamburger menu only */
    .navbar-wrapper .profile-dropdown-container {
        display: none;
    }

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

@media (max-width: 500px) {

    .navbar-wrapper {
        padding: 0 14px;
    }

    .navbar-wrapper .brand span {
        display: none;
    }

    /* desktop pill only, not the mobile hamburger menu */
    .navbar-wrapper .profile-dropdown-container .profile-info {
        display: none;
    }

    .navbar-wrapper .user-profile-badge {
        padding: 4px;
    }

    .navbar-wrapper .dropdown-menu {
        width: 190px;
        right: -6px;
    }
}
/* logout pop up - kapareho ng bagong confirm pop up */
.nb-logout-modal-overlay { background: rgba(45, 14, 32, .42) !important; -webkit-backdrop-filter: blur(3px); backdrop-filter: blur(3px); }
.nb-logout-modal-box {
    width: min(360px, 92%) !important;
    padding: 26px 24px 22px !important;
    border-radius: 20px !important;
    box-shadow: 0 24px 60px -18px rgba(45, 14, 32, .5) !important;
    
}
.nb-logout-modal-icon {
    width: 48px !important;
    height: 48px !important;
    margin: 0 auto 14px !important;
    border-radius: 50% !important;
    background: #fce7f3 !important;
    color: #db2777 !important;
    display: flex !important;
    align-items: center;
    justify-content: center;
}
.nb-logout-modal-icon svg { width: 22px !important; height: 22px !important; }
.nb-logout-modal-title { font-size: 18px !important; font-weight: 700 !important; color: #3b1029 !important; margin-bottom: 6px !important;  }
.nb-logout-modal-desc { font-size: 14px !important; color: #7a5468 !important; margin-bottom: 22px !important; }
.nb-logout-modal-actions { display: grid !important; grid-template-columns: 1fr 1fr; gap: 10px !important; }
.nb-logout-modal-btn { height: 44px !important; border-radius: 12px !important; font-size: 14px !important; font-weight: 700 !important; }
.nb-logout-modal-btn-cancel { background: #fff !important; color: #c2325f !important; border: 1.5px solid #c2325f !important; }
.nb-logout-modal-btn-cancel:hover { background: #ffedf2 !important; }
.nb-logout-modal-btn-confirm { background: #c2325f !important; color: #fff !important; border: 1.5px solid #c2325f !important; }
.nb-logout-modal-btn-confirm:hover { background: #a62a50 !important; border-color: #a62a50 !important; }
</style>


<header class="navbar-wrapper">
<nav class="navbar">

    <a href="../Home/Home.php" class="brand">

        <div class="brand-photo">

            <img
                src="../Image/brand/logo.png"
                alt="Clay and Stuff"
            >

        </div>

        <span>Clay and Stuff</span>

    </a>


    <!-- NAV LINKS -->

    <div class="nav-links" id="navLinks">

        <?php if ($isLoggedIn): ?>

            <!-- mobile-only account section, part of hamburger menu -->

            <div class="nav-links-account">

                <div class="nav-links-account-header">

                    <div class="profile-avatar">
                        <?php echo $navbar_avatar_html; ?>
                    </div>

                    <div class="profile-info">
                        <strong>
                            <?php echo htmlspecialchars($customer_name ?: "Customer"); ?>
                        </strong>
                        <span>
                            <?php echo htmlspecialchars($customer_email); ?>
                        </span>
                    </div>

                </div>

                <a href="../Profile/profile.php" class="dropdown-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    My Profile
                </a>

                <a href="../my-order/my-orders.php" class="dropdown-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                    My Orders
                </a>

                <a href="../Notification/notifications.php" class="dropdown-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    Notification
                </a>

                <div class="dropdown-divider"></div>

                <button type="button" class="dropdown-item logout" id="nbLogoutBtnMobile">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    Log Out
                </button>

            </div>

        <?php endif; ?>

        <!-- OVERVIEW -->

        <a
            href="../Home/Home.php"
            class="<?php echo ($current_page == 'Home.php') ? 'active' : ''; ?>"
        >
            OVERVIEW
        </a>


        <!-- COLLECTIONS -->

        <a
            href="../Collection/collection.php"
            class="<?php echo ($current_page == 'collection.php') ? 'active' : ''; ?>"
        >
            COLLECTIONS
        </a>


        <!-- COMMISSION -->

        <a
            href="../Commission/commission.php"
            class="<?php echo in_array($current_page, ['commission.php', 'dress-up.php', 'finalsummary.php'], true) ? 'active' : ''; ?>"
        >
            COMMISSION
        </a>

    </div>


    <!-- RIGHT ACTIONS -->

    <div class="nav-actions">

<?php if ($isLoggedIn): ?>

    <!-- PROFILE (LOGGED IN) -->

    <div class="profile-dropdown-container">

        <div
            class="user-profile-badge"
            id="profileButton"
            role="button"
            tabindex="0"
            aria-haspopup="true"
            aria-expanded="false"
        >

            <div class="profile-avatar">
                <?php echo $navbar_avatar_html; ?>
            </div>

            <div class="profile-info">
                <strong>
                    <?php
                    echo htmlspecialchars(
                        $customer_name ?: "Customer"
                    );
                    ?>
                </strong>
                <span>
                    <?php
                    echo htmlspecialchars(
                        $customer_email
                    );
                    ?>
                </span>
            </div>

            <svg class="dropdown-arrow" viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>

        </div>


        <!-- DROPDOWN -->

        <div
            class="dropdown-menu"
            id="profileMenu"
        >

            <!-- MY PROFILE -->

            <a href="../Profile/profile.php" class="dropdown-item">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                My Profile
            </a>

            <!-- MY ORDERS -->

            <a href="../my-order/my-orders.php" class="dropdown-item">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                My Orders
            </a>

            <!-- NOTIFICATION -->

            <a href="../Notification/notifications.php" class="dropdown-item">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                Notification
            </a>

            <div class="dropdown-divider"></div>

            <!-- LOG OUT -->

            <button type="button" class="dropdown-item logout" id="nbLogoutBtn">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Log Out
            </button>

        </div>

    </div>

<?php else: ?>

    <!-- LOGGED OUT -->

    <a href="../Login/Login.php" class="btn-login-only">
        Log In
    </a>

<?php endif; ?>

        <!-- MOBILE MENU BUTTON -->

        <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Toggle Menu" type="button">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>

    </div>

</nav>

</header>

<?php
// site-wide login/signup popup, opens instead of going to Login.php
if (!$isLoggedIn) {
    include __DIR__ . "/auth-modal.php";
}
?>

<!-- LOGOUT CONFIRMATION MODAL -->

<div class="nb-logout-modal-overlay" id="nbLogoutModal">
    <div class="nb-logout-modal-box">
        <div class="nb-logout-modal-icon">
            <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        </div>
        <div class="nb-logout-modal-title">Log out?</div>
        <div class="nb-logout-modal-desc">You can log back in anytime.</div>
        <div class="nb-logout-modal-actions">
            <button type="button" class="nb-logout-modal-btn nb-logout-modal-btn-confirm" id="nbLogoutConfirm">Log Out</button>
            <button type="button" class="nb-logout-modal-btn nb-logout-modal-btn-cancel" id="nbLogoutCancel">Cancel</button>
        </div>
    </div>
</div>



<script>

const profileButton =
    document.getElementById("profileButton");

const profileMenu =
    document.getElementById("profileMenu");


if (profileButton && profileMenu) {

    profileButton.addEventListener("click", function(event) {

        event.stopPropagation();

        const isOpen = profileMenu.classList.toggle("show");

        profileButton.setAttribute("aria-expanded", isOpen ? "true" : "false");

    });

    profileButton.addEventListener("keydown", function(event) {

        if (event.key === "Enter" || event.key === " ") {
            event.preventDefault();
            profileButton.click();
        }

    });


    document.addEventListener("click", function(event) {

        if (
            !profileButton.contains(event.target) &&
            !profileMenu.contains(event.target)
        ) {

            profileMenu.classList.remove("show");
            profileButton.setAttribute("aria-expanded", "false");

        }

    });

}


/* LOGOUT CONFIRMATION */

const nbLogoutBtn = document.getElementById("nbLogoutBtn");
const nbLogoutBtnMobile = document.getElementById("nbLogoutBtnMobile");
const nbLogoutModal = document.getElementById("nbLogoutModal");
const nbLogoutCancel = document.getElementById("nbLogoutCancel");
const nbLogoutConfirm = document.getElementById("nbLogoutConfirm");

if (nbLogoutModal) {

    const openNbLogoutModal = function (event) {
        event.stopPropagation();
        nbLogoutModal.classList.add("active");
    };

    if (nbLogoutBtn) {
        nbLogoutBtn.addEventListener("click", openNbLogoutModal);
    }

    // logout button sa mobile menu, same modal
    if (nbLogoutBtnMobile) {
        nbLogoutBtnMobile.addEventListener("click", openNbLogoutModal);
    }

    if (nbLogoutCancel) {
        nbLogoutCancel.addEventListener("click", function () {
            nbLogoutModal.classList.remove("active");
        });
    }

    nbLogoutModal.addEventListener("click", function (event) {
        if (event.target === nbLogoutModal) {
            nbLogoutModal.classList.remove("active");
        }
    });

    if (nbLogoutConfirm) {
        nbLogoutConfirm.addEventListener("click", function () {
            window.location.href = "../Login/logout.php?portal=customer";
        });
    }

}


/* MOBILE MENU TOGGLE */

const mobileMenuBtn =
    document.getElementById("mobileMenuBtn");

const navLinks =
    document.getElementById("navLinks");

if (mobileMenuBtn && navLinks) {

    mobileMenuBtn.addEventListener("click", function(event) {

        event.stopPropagation();
        navLinks.classList.toggle("active");

    });

}

</script>

<?php if ($isLoggedIn): ?>

<script>

/* Auto-refresh ng customer: nagpo-poll kada 3s at nire-reload kapag may bagong notification.
   Hindi nire-reload kapag naka-hide ang tab o may tina-type ang user. */

(function () {

    const ENDPOINT = "../Notification/customer_bell.php";

    let knownMaxNotificationId = null;

    function isUserComposingRightNow() {

        const active = document.activeElement;
        const tag = active && active.tagName ? active.tagName.toLowerCase() : "";

        return (tag === "textarea" || tag === "input" || tag === "select");

    }


    function fetchNotifications() {

        fetch(ENDPOINT + "?action=list")
            .then(function (res) { return res.json(); })
            .then(function (data) {

                const notifications = data.notifications || [];

                const currentMaxId = notifications.reduce(
                    function (max, note) {
                        return note.notification_id > max ? note.notification_id : max;
                    },
                    0
                );

                if (knownMaxNotificationId === null) {
                    // first fetch, just set the baseline, no reload yet
                    knownMaxNotificationId = currentMaxId;
                    return;
                }

                if (currentMaxId <= knownMaxNotificationId) {
                    return; // wala namang bago
                }

                if (document.hidden || isUserComposingRightNow()) {
                    return;
                }

                window.location.reload();

            })
            .catch(function () {});

    }


    fetchNotifications();

    setInterval(fetchNotifications, 3000); // poll every 3s


})();

</script>

<?php endif; ?>
