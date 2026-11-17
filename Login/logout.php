<?php
/* ==========================================================================
   LOG OUT   (Login/logout.php)
   Backend lang: burahin ang session + Remember me ng portal na ito lang, tapos redirect sa Home.
   Tinatawag nang direkta (?portal=customer|staff|owner) ng Log Out link
   sa Shared/navbar.php (customer) at Shared/sidebar.php (owner/staff).
   Walang pop up.
   ========================================================================== */
require_once __DIR__ . "/../helpers/security_helper.php";


/* ==========================================================================
   [PHP] BACKEND  (direktang tawag lang, hindi kapag ini-include)
   strcasecmp = hindi apektado ng laki/liit ng letra ng path (Windows / XAMPP)
   ========================================================================== */
if (strcasecmp((string) realpath($_SERVER["SCRIPT_FILENAME"]), __FILE__) === 0) {

    /* log out ng portal na ito LANG (galing sa ?portal= ng logout link). Hiwalay ang login ng
       customer, staff at owner, kaya ang ibang naka-login sa parehong browser ay hindi nagagalaw. */
    $portal = figurify_session_portal();

    figurify_end_portal_session($portal);

    $portalNames = ["customer" => "", "staff" => " of the Staff panel", "owner" => " of the Owner panel"];

    // pop up sa Home (customer session lang ang nababasa doon)
    figurify_start_session("customer");
    figurify_flash("You logged out" . $portalNames[$portal] . ".", "info");

    // Staff / Owner: bubukas ang Log In pop up sa Home (kung walang naka-login na customer); Customer: Home lang
    header("Location: ../Home/Home.php" . ($portal !== "customer" && !isset($_SESSION["user_id"]) ? "?auth=login" : ""));

    exit();
}
/* ====================== END BACKEND ====================== */
?>
