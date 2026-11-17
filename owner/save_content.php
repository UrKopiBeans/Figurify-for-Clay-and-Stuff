<?php

/* ==========================================================================
   OWNER: SAVE CONTENT   (owner/save_content.php)
   Nagse-save ng text at media ng Home, FAQ, Terms at Privacy mula sa Content Management.
   ========================================================================== */

require_once __DIR__ . "/../helpers/security_helper.php";
figurify_start_session();

require_once __DIR__ . "/../Admin/database.php";

/* CSRF - token galing sa Shared/popup-and-security.php */
if (($_SERVER["REQUEST_METHOD"] ?? "") === "POST" && !figurify_verify_csrf($_POST["csrf_token"] ?? null)) {
    http_response_code(403);
    exit("Your session has expired. Please reload the page and try again.");
}
require_once __DIR__ . "/../helpers/content_helper.php";

// Owner access guard
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../Login/Login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: content-management.php");
    exit();
}

$ownerId = $_SESSION["user_id"];

// Lahat ng editable fields sa home page. Bagong field sa content-management.php? Idagdag din dito.

$editableFields = [

    /* HERO - SLIDE 1 */
    "hero_eyebrow",
    "hero_heading",
    "hero_tagline",
    "hero_link_text",
    "hero_image",
    "hero_stamp",

    /* HERO - SLIDE 2 */
    "slide2_eyebrow",
    "slide2_heading",
    "slide2_tagline",
    "slide2_link_text",
    "slide2_image",
    "slide2_stamp",

    /* HERO - SLIDE 3 */
    "slide3_eyebrow",
    "slide3_heading",
    "slide3_tagline",
    "slide3_link_text",
    "slide3_image",
    "slide3_stamp",

    /* HERO - PARA SA LAHAT NG SLIDE */
    "hero_button2",
    "hero_chip1", "hero_chip2", "hero_chip3",

    /* ABOUT */
    "about_heading",
    "about_script",
    "about_text",
    "about_sign",
    "about_video",
    "about_badge",
    "about_caption",
    "about_stat1_value", "about_stat1_label",
    "about_stat2_value", "about_stat2_label",
    "about_stat3_value", "about_stat3_label",
    "about_button",

    /* HOW IT WORKS */
    "how_eyebrow", "how_heading", "how_subtitle",
    "how1_title", "how1_text",
    "how2_title", "how2_text",
    "how3_title", "how3_text",
    "how4_title", "how4_text",
    "how_note_lead", "how_note_text",

    /* COLLECTION STYLES */
    "styles_eyebrow", "styles_heading", "styles_subtitle",
    "style1_image", "style1_title", "style1_text", "style1_badge",
    "style2_image", "style2_title", "style2_text", "style2_badge",
    "style3_image", "style3_title", "style3_text", "style3_badge",
    "shortcut1_title", "shortcut1_text", "shortcut1_link",
    "shortcut2_title", "shortcut2_text", "shortcut2_link",

    /* FEATURED ORDERS */
    "featured_eyebrow",
    "featured_heading",
    "featured_subtitle",
    "product1_image", "product1_title", "product1_price", "product1_caption",
    "product2_image", "product2_title", "product2_price", "product2_caption",
    "product3_image", "product3_title", "product3_price", "product3_caption",
    "product4_image", "product4_title", "product4_price", "product4_caption",
    "featured_note",

    /* FEATURES BANNER */
    "feature1_title", "feature1_subtitle",
    "feature2_title", "feature2_subtitle",
    "feature3_title", "feature3_subtitle",
    "feature4_title", "feature4_subtitle",

    /* REVIEWS */
    "reviews_eyebrow",
    "reviews_heading",
    "reviews_subtitle",

    /* START AN ORDER BANNER */
    "cta_heading",
    "cta_text",
    "cta_button",

    /* FOOTER / CONTACT */
    "footer_tagline",
    "contact_address",
    "contact_phone",
    "contact_email",
    "social_facebook",
    "social_instagram",
    "social_tiktok",
    "social_gmail",
];

// links ng social icons: http(s)/mailto lang ang pinapayagan
function figurify_clean_social_link(string $field, string $value): string
{
    if ($value === "") {
        return "";
    }
    if ($field === "social_gmail" && filter_var($value, FILTER_VALIDATE_EMAIL)) {
        return "mailto:" . $value;
    }
    if (!preg_match('#^(https?://|mailto:)#i', $value)) {
        $value = "https://" . ltrim($value, "/");
    }
    return preg_match('#^(https?://|mailto:)[^\s<>"\']+$#i', $value) ? $value : "";
}

// Legal Pages (Terms and Conditions + Privacy Policy) - listahan galing sa content_helper.php SECTION 5
$editableFields = array_merge($editableFields, figurify_legal_keys());

// FAQ sa Home - listahan galing sa content_helper.php SECTION 6
$editableFields = array_merge($editableFields, figurify_faq_keys());

foreach ($editableFields as $field) {

    if (!isset($_POST[$field])) {
        continue;
    }

    $value = trim((string) $_POST[$field]);

    if (strpos($field, "social_") === 0) {
        $value = figurify_clean_social_link($field, $value);
    }

    figurify_save_content($conn, $field, $value, $ownerId);

}

// PRESYO (Prices tab) - helpers/content_helper.php SECTION 8
// figure prices: numero lang (0 pataas); kapag mali ang laman, hindi papalitan ang dati
foreach (array_keys(figurify_price_fields()) as $field) {
    $value = trim((string) ($_POST[$field] ?? ""));
    if (is_numeric($value)) {
        figurify_save_content($conn, $field, (string) max(0, (float) $value), $ownerId);
    }
}

// Dress Up items: key => presyo, o "" = walang presyo (isang JSON lang)
if (isset($_POST["part_price"]) && is_array($_POST["part_price"])) {
    $partPrices = [];
    foreach ($_POST["part_price"] as $key => $value) {
        $value = trim((string) $value);
        if (preg_match('/^[a-f0-9]{12}$/', (string) $key) && ($value === "" || is_numeric($value))) {
            $partPrices[$key] = $value === "" ? "" : (string) max(0, (float) $value);
        }
    }
    figurify_save_content($conn, "dressup_part_prices", json_encode($partPrices), $ownerId);
}

// tanggalin sa Image/carousel/ ang mga file na wala nang gumagamit
$freshContent = figurify_get_all_content($conn);
figurify_sync_category_images("carousel", array_values($freshContent));

$returnTab = in_array($_POST["return_tab"] ?? "", ["legal", "prices"], true) ? $_POST["return_tab"] : "";
header("Location: content-management.php?" . ($returnTab !== "" ? "tab=" . $returnTab . "&" : "") . "saved=1");
exit();

/* ==================== END OWNER: SAVE CONTENT ==================== */
