<?php

// Footer has its own DB connection kasi may pages na nagsasara na
// ng $conn nila bago pa ma-include ito.

require_once __DIR__ . "/../helpers/content_helper.php";

$footerContent = [];

$footerConn = @new mysqli("localhost", "root", "", "figurify_db");

if ($footerConn && !$footerConn->connect_error) {
    $footerConn->set_charset("utf8mb4");
    $footerContent = figurify_get_all_content($footerConn);
    $footerConn->close();
}

function cs_footer_text(string $key, string $default): string
{
    global $footerContent;
    return figurify_content($footerContent, $key, $default);
}

$cs_footer_email = cs_footer_text("contact_email", "clayandstuff@gmail.com");

?>

<style>
/* ===== Shared footer (iisang footer para sa buong site) ===== */
.cs-footer {
    --csf-bg: #f6bdd1;
    --csf-text: #43142f;
    --csf-muted: #6a2f4f;
    --csf-dim: #85506b;
    --csf-crimson: #c0325e;
    --csf-line: rgba(67, 20, 47, .16);
    position: relative;
    margin-top: 90px;
    background: var(--csf-bg);
    color: var(--csf-muted);
    
    line-height: 1.55;
}
.cs-footer *, .cs-footer *::before, .cs-footer *::after { box-sizing: border-box; }

.cs-footer-inner {
    width: min(1328px, 92%);
    margin: 0 auto;
}

/* CTA card, nakapatong sa taas ng footer */
.cs-footer-cta {
    position: relative;
    top: -32px;
    margin-bottom: -8px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    padding: 16px 26px;
    border-radius: 18px;
    background: linear-gradient(100deg, #fff7fa 0%, #fff0f6 55%, #f7eefc 100%);
    border: 1px solid #f0b5cd;
    box-shadow: 0 18px 40px -22px rgba(67, 20, 47, .35);
}
.cs-footer-cta h3 {
    margin: 0 0 2px;
    
    font-size: 22px;
    font-weight: 700;
    line-height: 1.2;
    color: #43142f;
}
.cs-footer-cta p {
    margin: 0;
    font-size: 14px;
    color: #6e5767;
}
.cs-footer-cta-actions { display: flex; gap: 12px; flex-shrink: 0; }
.cs-footer-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 10px 22px;
    border-radius: 11px;
    font-size: 15px;
    font-weight: 700;
    text-decoration: none;
    border: 2px solid transparent;
    transition: background-color .15s ease, color .15s ease, border-color .15s ease;
}
.cs-footer-btn.primary { background: var(--csf-crimson); border-color: var(--csf-crimson); color: #fff; }
.cs-footer-btn.primary:hover { background: #a82a52; border-color: #a82a52; }
.cs-footer-btn.ghost { background: #fff; border-color: var(--csf-crimson); color: var(--csf-crimson); }
.cs-footer-btn.ghost:hover { background: #fff3f7; }

/* main grid */
.cs-footer-main {
    display: grid;
    grid-template-columns: 1.35fr 1fr 1fr 1fr 1.3fr;
    gap: 30px;
    padding: 0 0 34px;
}
.cs-footer-brand { display: flex; flex-direction: column; gap: 14px; }
.cs-footer-logo { text-decoration: none; }
.cs-footer-logo strong {
    
    font-size: 24px;
    font-weight: 700;
    line-height: 1.2;
    color: var(--csf-text);
}
.cs-footer-tagline { margin: 0; font-size: 15px; color: var(--csf-muted); max-width: 300px; }

.cs-footer-social { display: flex; gap: 10px; margin-top: 6px; }
.cs-footer-social a {
    width: 38px;
    height: 38px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    color: #5b2a45;
    background: rgba(255, 255, 255, .55);
    border: 1px solid var(--csf-line);
    transition: background-color .15s ease, border-color .15s ease, color .15s ease;
}
.cs-footer-social a:hover { background: var(--csf-crimson); border-color: var(--csf-crimson); color: #fff; }
.cs-footer-social svg { width: 17px; height: 17px; display: block; }

.cs-footer-col h4 {
    margin: 4px 0 18px;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 1.6px;
    text-transform: uppercase;
    color: var(--csf-text);
}
.cs-footer-col ul { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 12px; }
.cs-footer-col a {
    color: var(--csf-muted);
    text-decoration: none;
    font-size: 15px;
    transition: color .15s ease;
}
.cs-footer-col a:hover { color: #a3204c; text-decoration: underline; }

.cs-footer-contact { gap: 6px !important; }
.cs-footer-contact li { color: var(--csf-muted); font-size: 15px; }
.cs-footer-contact a { word-break: break-word; }

.cs-footer-bottom {
    padding: 22px 0 26px;
    border-top: 1px solid var(--csf-line);
    text-align: center;
    font-size: 13.5px;
    color: var(--csf-dim);
}
.cs-footer-bottom p { margin: 0; }

@media (max-width: 1000px) {
    .cs-footer-main { grid-template-columns: 1fr 1fr 1fr; }
    .cs-footer-brand { grid-column: 1 / -1; }
}
@media (max-width: 700px) {
    .cs-footer { margin-top: 80px; }
    .cs-footer-cta { flex-direction: column; align-items: flex-start; padding: 18px 20px; top: -28px; }
    .cs-footer-cta-actions { flex-wrap: wrap; }
    .cs-footer-main { grid-template-columns: 1fr 1fr; gap: 28px; }
    .cs-footer-contact-col { grid-column: 1 / -1; }
}
</style>

<footer class="cs-footer">
    <div class="cs-footer-inner">

        <div class="cs-footer-cta">
            <div>
                <h3>Have a figure in mind?</h3>
                <p>Tell us your idea and we'll shape it in clay.</p>
            </div>
            <div class="cs-footer-cta-actions">
                <a class="cs-footer-btn primary" href="../Commission/commission.php">Start an Order</a>
            </div>
        </div>

        <div class="cs-footer-main">

            <div class="cs-footer-brand">
                <a class="cs-footer-logo" href="../Home/Home.php">
                    <strong>Clay and Stuff</strong>
                </a>
                <p class="cs-footer-tagline">
                    <?php echo htmlspecialchars(cs_footer_text("footer_tagline", "Hand-sculpted polymer clay pieces made from your favorite moments."), ENT_QUOTES, "UTF-8"); ?>
                </p>
                <div class="cs-footer-social">
                    <a href="<?php echo htmlspecialchars(cs_footer_text("social_facebook", "https://facebook.com"), ENT_QUOTES, "UTF-8"); ?>" target="_blank" rel="noopener" class="soc-fb" title="Facebook" aria-label="Facebook">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M13.5 21v-7.6h2.6l.4-3h-3V8.5c0-.9.3-1.5 1.5-1.5h1.6V4.3c-.3 0-1.2-.1-2.3-.1-2.3 0-3.9 1.4-3.9 4v2.2H7.8v3h2.6V21h3.1Z"/></svg>
                    </a>
                    <a href="<?php echo htmlspecialchars(cs_footer_text("social_instagram", "https://instagram.com"), ENT_QUOTES, "UTF-8"); ?>" target="_blank" rel="noopener" class="soc-ig" title="Instagram" aria-label="Instagram">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.3" cy="6.7" r="1" fill="currentColor" stroke="none"/></svg>
                    </a>
                    <a href="<?php echo htmlspecialchars(cs_footer_text("social_tiktok", "https://tiktok.com"), ENT_QUOTES, "UTF-8"); ?>" target="_blank" rel="noopener" class="soc-tt" title="TikTok" aria-label="TikTok">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M16.6 3h-3.1v12.1a2.7 2.7 0 1 1-2.7-2.7c.3 0 .5 0 .8.1V9.3a5.8 5.8 0 1 0 5 5.8V9.2a7.4 7.4 0 0 0 4.3 1.4V7.5a4.3 4.3 0 0 1-4.3-4.5Z"/></svg>
                    </a>
                    <a href="<?php echo htmlspecialchars(cs_footer_text("social_gmail", "") !== "" ? cs_footer_text("social_gmail", "") : "mailto:" . $cs_footer_email, ENT_QUOTES, "UTF-8"); ?>" class="soc-mail" title="Email Us" aria-label="Email">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 7 8 6 8-6"/></svg>
                    </a>
                </div>
            </div>

            <div class="cs-footer-col">
                <h4>Explore</h4>
                <ul>
                    <li><a href="../Home/Home.php">Overview</a></li>
                    <li><a href="../Collection/collection.php">Collections</a></li>
                    <li><a href="../Commission/commission.php">Commission</a></li>
                </ul>
            </div>

            <div class="cs-footer-col">
                <h4>Order</h4>
                <ul>
                    <li><a href="../Commission/dress-up.php">Dress Up</a></li>
                    <li><a href="../Commission/commission.php">Image Submission</a></li>
                    <li><a href="../my-order/my-orders.php">My Orders</a></li>
                </ul>
            </div>

            <div class="cs-footer-col">
                <h4>Legal</h4>
                <ul>
                    <li><a href="../Legal/privacy-policy.php">Privacy Policy</a></li>
                    <li><a href="../Legal/terms-and-conditions.php">Terms and Conditions</a></li>
                </ul>
            </div>

            <div class="cs-footer-col cs-footer-contact-col">
                <h4>Contact Us</h4>
                <ul class="cs-footer-contact">
                    <li><?php echo htmlspecialchars(cs_footer_text("contact_address", "Brgy. Palingon, Calamba City, Laguna"), ENT_QUOTES, "UTF-8"); ?></li>
                    <li><?php echo htmlspecialchars(cs_footer_text("contact_phone", "+63 910 281 4331"), ENT_QUOTES, "UTF-8"); ?></li>
                    <li><a href="mailto:<?php echo htmlspecialchars($cs_footer_email, ENT_QUOTES, "UTF-8"); ?>"><?php echo htmlspecialchars($cs_footer_email, ENT_QUOTES, "UTF-8"); ?></a></li>
                </ul>
            </div>

        </div>

        <div class="cs-footer-bottom">
            <p>&copy; <?php echo date("Y"); ?> Clay and Stuff</p>
        </div>

    </div>
</footer>
