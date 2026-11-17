<?php
/* ==========================================================================
   FOOTER   (Shared/footer.php)
   Iisang footer ng buong customer site. Iisang file: PHP sa taas, isang
   <style>, tapos HTML. Walang JavaScript ang footer.
   Ini-include sa dulo ng bawat customer page:  <?php include __DIR__ . "/../Shared/footer.php"; ?>

   LAMAN (taas pababa):
     [PHP]     text ng footer galing sa Content Management
     [STYLE]   [1] FOOTER  [2] RESPONSIVE
     [HTML]    logo, Explore, Order, Contact Us (may social icons), copyright
     (Ang "Have a figure in mind?" ay nasa Home page na - Home.php > START AN ORDER)
   ========================================================================== */


/* ==========================================================================
   [PHP] SETUP
   May sariling DB connection ang footer kasi may mga page na nagsasara na
   ng $conn nila bago pa ma-include ito. Ang login ay galing sa FIGURIFY_DB_*
   ng helpers/security_helper.php (kapareho ng Admin/database.php).
   ========================================================================== */
require_once __DIR__ . "/../helpers/security_helper.php";
require_once __DIR__ . "/../helpers/content_helper.php";

$footerContent = [];

$footerConn = @new mysqli(FIGURIFY_DB_HOST, FIGURIFY_DB_USER, FIGURIFY_DB_PASS, FIGURIFY_DB_NAME);

if ($footerConn && !$footerConn->connect_error) {
    $footerConn->set_charset("utf8mb4");
    $footerContent = figurify_get_all_content($footerConn);
    $footerConn->close();
}

// text na na-edit ng owner; $default kapag wala pa
function cs_footer_text(string $key, string $default): string
{
    global $footerContent;
    return figurify_content($footerContent, $key, $default);
}

$cs_footer_email = cs_footer_text("contact_email", "clayandstuff@gmail.com");
/* ====================== END PHP SETUP ====================== */
?>

<style>
/* ==========================================================================
   [1] FOOTER
   Lahat ay may .cs-footer prefix para hindi madamay ang page.
   ========================================================================== */
.cs-footer {
    position: relative;
    padding-top: 46px;
    background: #f39bc1;
    color: #6a2f4f;
    line-height: 1.55;
}

.cs-footer *,
.cs-footer *::before,
.cs-footer *::after { box-sizing: border-box; }

.cs-footer-inner { width: min(1328px, 92%); margin: 0 auto; }

/* ---------- 4 hanay: logo | Explore | Order | Contact Us ---------- */
.cs-footer-main {
    display: grid;
    grid-template-columns: 1.4fr 1fr 1fr 1.3fr;
    gap: 30px;
    padding: 0 0 34px;
}

/* logo + tagline */
.cs-footer-brand { display: flex; flex-direction: column; gap: 14px; }

.cs-footer-logo { display: inline-flex; align-items: center; gap: 10px; text-decoration: none; }
.cs-footer-logo img { display: block; width: 46px; height: 46px; flex-shrink: 0; object-fit: contain; }

.cs-footer-logo strong {
    font-family: 'Fraunces', Georgia, 'Times New Roman', serif;
    font-size: 24px;
    font-weight: 700;
    line-height: 1.2;
    color: #43142f;
}

.cs-footer-tagline { max-width: 300px; margin: 0; font-size: 15px; }

/* title ng bawat hanay (EXPLORE, ORDER, CONTACT US) */
.cs-footer-col h4 {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 4px 0 18px;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 1.6px;
    text-transform: uppercase;
    color: #43142f;
}

/* icon sa title at sa Privacy / Terms */
.cs-footer-col h4 svg,
.cs-footer-legal a svg {
    width: 17px;
    height: 17px;
    flex-shrink: 0;
    fill: none;
    stroke: currentColor;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.cs-footer-col ul {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.cs-footer-col a {
    display: inline-block;
    padding: 4px 0;
    color: #6a2f4f;
    font-size: 15px;
    text-decoration: none;
    transition: color .15s ease;
}

.cs-footer-col a:hover { color: #a3204c; }

/* ---------- Contact Us: address, phone, email ---------- */
.cs-footer-col .cs-footer-contact { gap: 10px; }

.cs-footer-contact li { display: flex; align-items: flex-start; gap: 10px; font-size: 15px; }
.cs-footer-contact a { word-break: break-word; }

.cs-footer-contact .cs-ci {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
    margin-top: 3px;
    fill: none;
    stroke: #c0325e;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
}

/* ---------- social icons (Facebook, Instagram, TikTok, Email) ---------- */
.cs-footer-social { display: flex; gap: 10px; margin-top: 18px; }

.cs-footer-col .cs-footer-social a {
    width: 48px;
    height: 48px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 0;
    border-radius: 50%;
    color: #fff;
    box-shadow: 0 4px 10px -4px rgba(67, 20, 47, .45);
}

.cs-footer-col .cs-footer-social a:hover {
    color: #fff;
    text-decoration: none;
    transform: translateY(-2px);
    box-shadow: 0 8px 14px -6px rgba(67, 20, 47, .5);
}

.cs-footer-social a.soc-fb   { background: #1877f2; }
.cs-footer-social a.soc-ig   { background: radial-gradient(circle at 30% 107%, #fdf497 0%, #fdf497 5%, #fd5949 45%, #d6249f 60%, #285aeb 90%); }
.cs-footer-social a.soc-tt   { background: #000; }
.cs-footer-social a.soc-mail { background: #ea4335; }
.cs-footer-social svg { display: block; width: 22px; height: 22px; }
.cs-footer-social a.soc-fb svg { width: 28px; height: 28px; }
.cs-footer-col .cs-footer-social a:not([href]) { cursor: default; }

/* ---------- ibaba: copyright | Privacy Policy, Terms and Conditions ---------- */
.cs-footer-bottom {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 10px 24px;
    padding: 22px 0 26px;
    border-top: 1px solid rgba(67, 20, 47, .16);
    font-size: 13.5px;
    color: #85506b;
}

.cs-footer-bottom p { margin: 0; }

.cs-footer-legal {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 22px;
}

.cs-footer-legal a {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    min-height: 36px;
    color: #6a2f4f;
    font-size: 13.5px;
    text-decoration: none;
    transition: color .15s ease;
}

.cs-footer-legal a:hover { color: #a3204c; }
/* ====================== END FOOTER ====================== */


/* ==========================================================================
   [2] RESPONSIVE
   ========================================================================== */

/* tablet: logo sa sariling hanay, 2 column sa ilalim */
@media (max-width: 1000px) {
    .cs-footer-main { grid-template-columns: 1fr 1fr; }
    .cs-footer-brand { grid-column: 1 / -1; }
}

/* phone */
@media (max-width: 700px) {
    .cs-footer-main { gap: 28px; }
    .cs-footer-contact-col { grid-column: 1 / -1; }
    .cs-footer-bottom { flex-direction: column; align-items: flex-start; }
    .cs-footer-legal { justify-content: flex-start; }
}
/* ====================== END RESPONSIVE ====================== */
</style>


<!-- ==========================================================================
     [HTML] FOOTER
     ========================================================================== -->
<footer class="cs-footer">
    <div class="cs-footer-inner">

        <div class="cs-footer-main">

            <!-- logo + tagline -->
            <div class="cs-footer-brand">
                <a class="cs-footer-logo" href="../Home/Home.php">
                    <img src="../Image/brand/logo.png" alt="Clay and Stuff">
                    <strong>Clay and Stuff</strong>
                </a>
                <p class="cs-footer-tagline">
                    <?php echo htmlspecialchars(cs_footer_text("footer_tagline", "Handmade clay figures, made with love from your favorite moments."), ENT_QUOTES, "UTF-8"); ?>
                </p>
            </div>

            <!-- Explore -->
            <div class="cs-footer-col">
                <h4><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5-5 2 2-5z"/></svg>Explore</h4>
                <ul>
                    <li><a href="../Home/Home.php">Overview</a></li>
                    <li><a href="../Collection/collection.php">Collections</a></li>
                    <li><a href="../Commission/commission.php">Commission</a></li>
                </ul>
            </div>

            <!-- Order -->
            <div class="cs-footer-col">
                <h4><svg viewBox="0 0 24 24"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>Order</h4>
                <ul>
                    <li><a href="../Commission/dress-up.php">Dress Up</a></li>
                    <li><a href="../Commission/commission.php">Image Submission</a></li>
                    <li><a href="../my-order/my-orders.php">My Orders</a></li>
                </ul>
            </div>

            <!-- Contact Us + social icons -->
            <div class="cs-footer-col cs-footer-contact-col">
                <h4><svg viewBox="0 0 24 24"><path d="M4 14v-2a8 8 0 0 1 16 0v2"/><rect x="2.5" y="14" width="4.5" height="6" rx="1.5"/><rect x="17" y="14" width="4.5" height="6" rx="1.5"/><path d="M19.5 20c0 1.4-1.6 2-4 2h-2"/></svg>Contact Us</h4>
                <ul class="cs-footer-contact">
                    <li><svg class="cs-ci" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg><span><?php echo htmlspecialchars(cs_footer_text("contact_address", "Brgy. Palingon, Calamba City, Laguna"), ENT_QUOTES, "UTF-8"); ?></span></li>
                    <li><svg class="cs-ci" viewBox="0 0 24 24"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/></svg><span><?php echo htmlspecialchars(cs_footer_text("contact_phone", "+63 910 281 4331"), ENT_QUOTES, "UTF-8"); ?></span></li>
                    <li><svg class="cs-ci" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 7 8 6 8-6"/></svg><a href="mailto:<?php echo htmlspecialchars($cs_footer_email, ENT_QUOTES, "UTF-8"); ?>"><?php echo htmlspecialchars($cs_footer_email, ENT_QUOTES, "UTF-8"); ?></a></li>
                </ul>

                <?php
                /* link ng social icons - galing sa Content Management; kapag walang laman, hindi pa naka-link ang icon */
                $cs_social = [
                    "fb"   => cs_footer_text("social_facebook", "https://www.facebook.com/clayandstufff"),
                    "ig"   => cs_footer_text("social_instagram", "https://www.instagram.com/clayandstufff"),
                    "tt"   => cs_footer_text("social_tiktok", "https://www.tiktok.com/@clay_and_stuff"),
                    "mail" => cs_footer_text("social_gmail", "clayandstuff.system@gmail.com"),
                ];
                if ($cs_social["mail"] !== "" && filter_var($cs_social["mail"], FILTER_VALIDATE_EMAIL)) {
                    $cs_social["mail"] = "mailto:" . $cs_social["mail"];
                }
                $cs_href = function (string $key) use ($cs_social): string {
                    return $cs_social[$key] !== "" ? ' href="' . htmlspecialchars($cs_social[$key], ENT_QUOTES, "UTF-8") . '" target="_blank" rel="noopener"' : "";
                };
                ?>
                <div class="cs-footer-social">
                    <a<?php echo $cs_href("fb"); ?> class="soc-fb" title="Facebook" aria-label="Facebook">
                        <svg viewBox="5.5 3 13 19" fill="currentColor"><path d="M13.5 21v-7.6h2.6l.4-3h-3V8.5c0-.9.3-1.5 1.5-1.5h1.6V4.3c-.3 0-1.2-.1-2.3-.1-2.3 0-3.9 1.4-3.9 4v2.2H7.8v3h2.6V21h3.1Z"/></svg>
                    </a>
                    <a<?php echo $cs_href("ig"); ?> class="soc-ig" title="Instagram" aria-label="Instagram">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.3" cy="6.7" r="1" fill="currentColor" stroke="none"/></svg>
                    </a>
                    <a<?php echo $cs_href("tt"); ?> class="soc-tt" title="TikTok" aria-label="TikTok">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M16.6 3h-3.1v12.1a2.7 2.7 0 1 1-2.7-2.7c.3 0 .5 0 .8.1V9.3a5.8 5.8 0 1 0 5 5.8V9.2a7.4 7.4 0 0 0 4.3 1.4V7.5a4.3 4.3 0 0 1-4.3-4.5Z"/></svg>
                    </a>
                    <a<?php echo $cs_href("mail"); ?> class="soc-mail" title="Email Us" aria-label="Email">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 7 8 6 8-6"/></svg>
                    </a>
                </div>
            </div>

        </div>

        <!-- copyright + Privacy / Terms -->
        <div class="cs-footer-bottom">
            <p>&copy; <?php echo date("Y"); ?> Clay and Stuff. All rights reserved.</p>
            <nav class="cs-footer-legal" aria-label="Legal">
                <a href="../Legal/privacy-policy.php"><svg viewBox="0 0 24 24"><path d="M12 3 4 6v6c0 5 3.4 8.4 8 9 4.6-.6 8-4 8-9V6z"/><path d="m9 12 2 2 4-4"/></svg>Privacy Policy</a>
                <a href="../Legal/terms-and-conditions.php"><svg viewBox="0 0 24 24"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h6"/></svg>Terms and Conditions</a>
            </nav>
        </div>

    </div>
</footer>
<!-- ====================== END FOOTER ====================== -->
