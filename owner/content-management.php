<?php

require_once "owner-header.php";
require_once __DIR__ . "/../helpers/content_helper.php";

// Content Management - pag-eedit ng owner ng text/image sa Home page (hero, about, featured, faq, footer)

$content = figurify_get_all_content($conn);

$saved = isset($_GET["saved"]);

// tab: "legal" = Terms and Conditions + Privacy Policy; "prices" = lahat ng presyo; wala = Home Page
$tab = in_array($_GET["tab"] ?? "", ["legal", "prices"], true) ? $_GET["tab"] : "home";

// presyo (helpers/content_helper.php SECTION 8) - "Starting at" ng Home at ng Prices tab
$prices = figurify_price_config($content);

// Prices tab: listahan ng Dress Up items (du_part_items) galing sa Commission/dress-up-options.php
if ($tab === "prices") {
    require_once __DIR__ . "/../Commission/dress-up-options.php";
}


// Reusable image field - click preview para magbukas ng file dialog, auto-upload (AJAX) papunta /Image.
// Ginagamit ng Hero slides (3) at Featured Products (4).
function figurify_cm_image_field(string $name, string $label, array $content, string $fallback, string $ratioClass = "cm-ratio-square", string $sizeHint = ""): void
{
    $value = figurify_content($content, $name, "");
    $previewSrc = figurify_resolve_image_src($value, $fallback);
    ?>
    <label class="content-field content-field-full">
        <?php echo e($label); ?>
        <div class="content-media-field">
            <div
                class="content-media-preview content-media-upload <?php echo e($ratioClass); ?>"
                onclick="this.querySelector('input[type=file]').click()"
                <?php if ($sizeHint !== ""): ?>title="<?php echo e($sizeHint); ?>"<?php endif; ?>
            >
                <img src="<?php echo e($previewSrc); ?>" alt="<?php echo e($label !== "" ? $label . " preview" : "Image preview"); ?>">
                <div class="content-media-upload-overlay">
                    <span>Click to change</span>
                </div>
                <input
                    type="file"
                    accept="image/*"
                    hidden
                    onchange="figurifyUploadMedia(this, 'image')"
                >
            </div>
            <input type="hidden" name="<?php echo e($name); ?>" value="<?php echo e($value); ?>">
            <p class="content-media-status"></p>
        </div>
    </label>
    <?php
}

// Same idea pero para sa VIDEO field (about_video), upload papunta /Image/videos
function figurify_cm_video_field(string $name, string $label, array $content, string $fallback, string $labelClass = "content-field content-field-full"): void
{
    $value = figurify_content($content, $name, "");
    $previewSrc = $value !== "" ? $value : $fallback;
    ?>
    <label class="<?php echo e($labelClass); ?>">
        <?php echo e($label); ?>
        <div class="content-media-field">
            <div
                class="content-media-preview content-media-upload cm-ratio-video"
                onclick="this.querySelector('input[type=file]').click()"
            >
                <video src="<?php echo e($previewSrc); ?>" muted loop autoplay playsinline></video>
                <div class="content-media-upload-overlay">
                    <span>Click to change</span>
                </div>
                <input
                    type="file"
                    accept="video/*"
                    hidden
                    onchange="figurifyUploadMedia(this, 'video')"
                >
            </div>
            <input type="hidden" name="<?php echo e($name); ?>" value="<?php echo e($value); ?>">
            <p class="content-media-status"></p>
        </div>
    </label>
    <?php
}

?>

<!DOCTYPE html>

<html lang="en">

<head>
    <?php /* [POP UP AT SECURITY] pop up at security script lang (walang CSS) - Shared/popup-and-security.php */ include __DIR__ . "/../Shared/popup-and-security.php"; ?>
    <link rel="icon" type="image/png" href="../Image/brand/logo.png">

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1"
>

<title>Figurify | Content Management</title>

<!-- ===================== STYLE NG PAGE NA ITO: content-management.css ===================== -->
<link rel="stylesheet" href="content-management.css">

</head>


<body>

<div class="app">

<?php require_once __DIR__ . "/../Shared/sidebar.php"; ?>


<main class="main">


<header class="topbar">

    <div class="welcome">

        <h2 class="portal-title">
            Content Management
        </h2>


    </div>


        <div class="top-actions">

<!-- ==========================================================
     [TOPBAR-DATETIME]  (dating Shared/topbar-datetime.php - nasa page na ito na)
     ANO ITO: petsa at oras sa taas ng page, sa kaliwa ng notification bell.
     PAANO GUMAGANA: ang JS sa Shared/sidebar.php ([3] TOPBAR DATE / TIME) ang nagpapalit ng .cs-tb-date at .cs-tb-time
     kada segundo (en-PH). Self-contained: hindi kailangan ng #currentDate / #currentTime.
     ========================================================== -->

<div class="cs-tb-datetime" aria-label="Current date and time">
    <span class="cs-tb-date">Loading date...</span>
    <span class="cs-tb-dot">•</span>
    <span class="cs-tb-time">Loading time...</span>
</div>

<!-- [END TOPBAR-DATETIME] -->

            <?php include __DIR__ . "/../Shared/notification-bell.php"; ?>

        </div>

    </header>


    <div class="cm-tabs">
        <a href="content-management.php" class="cm-tab<?php echo $tab === "home" ? " active" : ""; ?>">Home Page</a>
        <a href="collection-management.php" class="cm-tab">Collection</a>
        <a href="content-management.php?tab=legal" class="cm-tab<?php echo $tab === "legal" ? " active" : ""; ?>">Legal Pages</a>
        <a href="content-management.php?tab=prices" class="cm-tab<?php echo $tab === "prices" ? " active" : ""; ?>">Prices</a>
    </div>


<?php if ($saved): ?>

    <?php
        echo figurify_saved_toast([
            "legal"  => "Changes saved. You will see this on the Terms and Privacy pages now.",
            "prices" => "Prices saved. Dress Up, Image Submission, FAQ and Home now use the new prices.",
            "home"   => "Changes saved. You will see this on the Home page now.",
        ][$tab]); // "Changes saved" pop up (helpers/security_helper.php)
    ?>

<?php endif; ?>


<?php if ($tab === "legal"): ?>

<!-- ====================== LEGAL PAGES (Terms and Conditions + Privacy Policy) ======================
     Laman: helpers/content_helper.php SECTION 5 | Save: save_content.php | Page: Legal/ -->
<form
    method="POST"
    action="save_content.php"
    class="content-form cmx-form"
>
    <input type="hidden" name="return_tab" value="legal">

    <!-- 2 column: Terms and Conditions (kaliwa) | Privacy Policy (kanan) -->
    <div class="cmx-legal-columns">
    <?php foreach (["terms" => "Terms and Conditions", "privacy" => "Privacy Policy"] as $legalKey => $legalName): ?>
        <?php $legal = figurify_legal_page($content, $legalKey); ?>

        <section class="card content-section cmx-card" id="section-<?php echo $legalKey; ?>">

            <h3 class="cmx-title"><?php echo e($legalName); ?></h3>

            <p class="cmx-hint">
                Sa mahabang text: blank line = bagong talata, simulan sa "- " ang linya para maging listahan,
                **text** = bold, {{text}} = naka-highlight, [text](link) = link.
            </p>

            <div class="cmx-legal-fields">
                <label class="content-field">
                    Small text sa itaas
                    <input type="text" name="<?php echo $legalKey; ?>_eyebrow" value="<?php echo e($legal["eyebrow"]); ?>">
                </label>
                <label class="content-field">
                    Title
                    <input type="text" name="<?php echo $legalKey; ?>_title" value="<?php echo e($legal["title"]); ?>">
                </label>
                <label class="content-field">
                    Subtitle (kasunod: "Last updated")
                    <input type="text" name="<?php echo $legalKey; ?>_subtitle" value="<?php echo e($legal["subtitle"]); ?>">
                </label>
            </div>

            <label class="content-field content-field-full">
                Intro (sa itaas ng mga section, puwedeng walang laman)
                <textarea name="<?php echo $legalKey; ?>_intro" rows="1"><?php echo e($legal["intro"]); ?></textarea>
            </label>

            <?php foreach ($legal["sections"] as $i => $section): ?>
                <div class="cmx-legal-section">
                    <label class="content-field">
                        Section <?php echo $i + 1; ?> title
                        <input type="text" name="<?php echo $legalKey; ?>_sec<?php echo $i + 1; ?>_title" value="<?php echo e($section["title"]); ?>">
                    </label>
                    <label class="content-field">
                        Section <?php echo $i + 1; ?> text
                        <textarea name="<?php echo $legalKey; ?>_sec<?php echo $i + 1; ?>_body" rows="1"><?php echo e($section["body"]); ?></textarea>
                    </label>
                </div>
            <?php endforeach; ?>

            <label class="content-field content-field-full">
                Note sa ibaba
                <textarea name="<?php echo $legalKey; ?>_note" rows="1"><?php echo e($legal["note"]); ?></textarea>
            </label>

        </section>
    <?php endforeach; ?>
    </div>

    <div class="content-form-actions">
        <button type="submit" class="content-save-btn">Save Changes</button>
    </div>

</form>
<!-- ====================== END LEGAL PAGES ====================== -->

<?php elseif ($tab === "prices"): ?>

<!-- ====================== PRICES ======================
     Listahan: helpers/content_helper.php SECTION 8 + Commission/dress-up-options.php (du_part_items)
     Save: save_content.php | Ginagamit ng: Dress Up, Image Submission, submit_order.php, FAQ at Home -->
<?php
// presyo ng figure, naka-grupo (Funko Pop, Hirono, Chibi, Figure Name, Box & Add-ons)
$priceGroups = [];
foreach (figurify_price_fields() as $key => $field) {
    $priceGroups[$field[0]][$key] = $field[1];
}

// Dress Up: na-save na presyo ng owner (ang listahan ay galing sa du_part_items)
$savedPartPrices = figurify_part_prices($content);
?>
<form
    method="POST"
    action="save_content.php"
    class="content-form cmx-form"
>
    <input type="hidden" name="return_tab" value="prices">

    <!-- 1. Figure Style > Product Type > Size, Figure Name, Box & Add-ons -->
    <section class="card content-section cmx-card" id="section-prices">

        <h3 class="cmx-title">Figure Prices</h3>

        <p class="cmx-hint">
            Sabay na nagbabago sa Dress Up, Image Submission, FAQ at "Starting at" sa Home.
            Sa FAQ, isulat ang {figure_name} para kusang lumabas ang presyo ng Figure Name.
        </p>

        <div class="cmx-price-groups">
            <?php foreach ($priceGroups as $group => $fields): ?>
                <div class="cmx-price-group">
                    <div class="cmx-sub"><?php echo e($group); ?></div>
                    <?php foreach ($fields as $key => $label): ?>
                        <label class="cmx-price-row">
                            <span><?php echo e($label); ?></span>
                            <span class="cmx-peso"><input type="number" name="<?php echo e($key); ?>" value="<?php echo figurify_price_value($content, $key); ?>" min="0" step="1" required></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>

    </section>

    <!-- 2. Dress Up: Hairstyle / Top / Bottom / Shoes (hiwalay na container) -->
    <section class="card content-section cmx-card" id="section-part-prices">

        <h3 class="cmx-title">Dress Up Outfit Prices</h3>

        <p class="cmx-hint">
            Puwedeng lagyan ng presyo o iwanang blangko. Blangko = walang presyo, walang lalabas sa Dress Up at hindi ito kasama sa kuwenta.
            Ito ang presyo sa pinakamaliit na size; sa bawat mas malaking size, nadadagdagan ng 25%.
        </p>

        <!-- Figure Style (hal. Funko Pop) > Boy / Girl (hiwalay) > Hairstyle / Top / Bottom / Shoes -->
        <?php foreach (du_part_items() as $style => $genders): ?>
            <!-- iisang name = isang style lang ang bukas (pag-click ng Hirono, sasara ang Funko Pop) -->
            <details class="cmx-part-style" name="cmxPartStyle">
                <summary><?php echo e($style); ?></summary>
                <?php foreach (["Boy", "Girl"] as $gender): ?>
                    <?php if (!empty($genders[$gender])): ?>
                        <div class="cmx-part-gender"><?php echo e($style . " " . $gender); ?></div>
                        <?php foreach ($genders[$gender] as $part => $items): ?>
                            <div class="cmx-sub"><?php echo e($part); ?></div>
                            <div class="cmx-part-grid">
                                <?php foreach ($items as $key => $item): ?>
                                    <?php $partPrice = array_key_exists($key, $savedPartPrices) ? $savedPartPrices[$key] : $item["default"]; ?>
                                    <label class="cmx-part-card">
                                        <img src="../Commission/<?php echo e($item["image"]); ?>" alt="" loading="lazy">
                                        <span><?php echo e($item["name"]); ?></span>
                                        <span class="cmx-peso"><input type="number" name="part_price[<?php echo e($key); ?>]" value="<?php echo (float) $partPrice > 0 ? e($partPrice) : ""; ?>" min="0" step="1" placeholder="No price"></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </details>
        <?php endforeach; ?>

    </section>

    <div class="content-form-actions">
        <button type="submit" class="content-save-btn">Save Prices</button>
    </div>

</form>
<!-- ====================== END PRICES ====================== -->

<?php else: ?>

<form
    method="POST"
    action="save_content.php"
    class="content-form cmx-form"
>

    <!-- HERO CAROUSEL (buong lapad, 3 slide magkatabi) -->

    <section class="card content-section cmx-card" id="section-hero">

        <h3 class="cmx-title">Hero Carousel</h3>

        <?php
        $heroSlides = [
            1 => [
                "prefix" => "hero",
                "image"  => "hero_image",
                "fallbackImg" => "../Image/home/carousel-1.jpg",
                "eyebrow" => "Tiny keepsakes for big feelings",
                "heading" => "Turning memories|into *mini masterpieces*",
                "headingLabel" => "Main heading (| = new line, *word* = pink)",
                "tagline" => "Handmade clay figures from your favorite moments. No two are the same.",
                "button"  => "Shop the collection",
                "stamp"   => "100%|handmade",
                "stampLabel" => "Stamp badge (| = new line)",
            ],
            2 => [
                "prefix" => "slide2",
                "image"  => "slide2_image",
                "fallbackImg" => "../Image/home/carousel-2.jpg",
                "eyebrow" => "Customized just for you",
                "heading" => "Handcrafted couple figures|made *with love*",
                "headingLabel" => "Heading (| = new line, *word* = pink)",
                "tagline" => "Send a photo and your story, and we'll turn it into a keepsake you can keep forever.",
                "button"  => "Commission a piece",
                "stamp"   => "Made|for two",
                "stampLabel" => "Stamp badge",
            ],
            3 => [
                "prefix" => "slide3",
                "image"  => "slide3_image",
                "fallbackImg" => "../Image/home/carousel-3.jpg",
                "eyebrow" => "Special events & weddings",
                "heading" => "Capture your special day|in *cute clay style*",
                "headingLabel" => "Heading (| = new line, *word* = pink)",
                "tagline" => "From bridal parties to first dances, we sculpt the day you'll want to keep on your shelf.",
                "button"  => "Explore wedding sets",
                "stamp"   => "Keep the|day",
                "stampLabel" => "Stamp badge",
            ],
        ];
        ?>

        <div class="cmx-grid-3">

            <?php foreach ($heroSlides as $slideNo => $slide): ?>

                <?php $p = $slide["prefix"]; ?>

                <div class="cmx-stack">

                    <div class="cmx-sub">Slide <?php echo $slideNo; ?></div>

                    <?php figurify_cm_image_field($slide["image"], "", $content, $slide["fallbackImg"], "cm-ratio-hero", "Recommended size: 1600×600px or larger, wide/landscape image."); ?>

                    <label class="content-field">
                        Eyebrow text
                        <input type="text" name="<?php echo $p; ?>_eyebrow" value="<?php echo e(figurify_content($content, $p . "_eyebrow", $slide["eyebrow"])); ?>">
                    </label>

                    <label class="content-field">
                        <?php echo e($slide["headingLabel"]); ?>
                        <?php if ($slideNo === 1): ?>
                            <textarea name="hero_heading" rows="1"><?php echo e(figurify_content($content, "hero_heading", $slide["heading"])); ?></textarea>
                        <?php else: ?>
                            <input type="text" name="<?php echo $p; ?>_heading" value="<?php echo e(figurify_content($content, $p . "_heading", $slide["heading"])); ?>">
                        <?php endif; ?>
                    </label>

                    <label class="content-field">
                        Tagline
                        <textarea name="<?php echo $p; ?>_tagline" rows="1"><?php echo e(figurify_content($content, $p . "_tagline", $slide["tagline"])); ?></textarea>
                    </label>

                    <label class="content-field">
                        Button text
                        <input type="text" name="<?php echo $p; ?>_link_text" value="<?php echo e(figurify_content($content, $p . "_link_text", $slide["button"])); ?>">
                    </label>

                    <label class="content-field">
                        <?php echo e($slide["stampLabel"]); ?>
                        <input type="text" name="<?php echo $p; ?>_stamp" value="<?php echo e(figurify_content($content, $p . "_stamp", $slide["stamp"])); ?>">
                    </label>

                </div>

            <?php endforeach; ?>

        </div>

        <!-- para sa lahat ng slide: pangalawang button at 3 maliit na text sa ilalim ng mga button -->
        <div class="cmx-grid-2 cmx-products">

            <label class="content-field">
                Second button text (all slides)
                <input type="text" name="hero_button2" value="<?php echo e(figurify_content($content, "hero_button2", "Start an Order")); ?>">
            </label>

            <?php foreach ([1 => "Real customer reviews", 2 => "100% handmade", 3 => "Track every order"] as $i => $defaultChip): ?>
                <label class="content-field">
                    Small text <?php echo $i; ?> (below buttons)
                    <input type="text" name="hero_chip<?php echo $i; ?>" value="<?php echo e(figurify_content($content, "hero_chip{$i}", $defaultChip)); ?>">
                </label>
            <?php endforeach; ?>

        </div>

    </section>


    <!-- ABOUT US + HOW IT WORKS (magkatabi) -->

    <div class="cmx-row-2">

        <section class="card content-section cmx-card" id="section-about">

            <h3 class="cmx-title">About Us</h3>

            <div class="cmx-stack">

                <?php figurify_cm_video_field("about_video", "", $content, "../Image/videos/about us video.mp4", "content-field"); ?>

                <label class="content-field">
                    Badge text (tilted, top of the video)
                    <input type="text" name="about_badge" value="<?php echo e(figurify_content($content, "about_badge", "Clay & Stuff")); ?>">
                </label>

                <label class="content-field">
                    Caption (white pill beside the video)
                    <input type="text" name="about_caption" value="<?php echo e(figurify_content($content, "about_caption", "Watch how we make them")); ?>">
                </label>

                <label class="content-field">
                    Eyebrow (small text on top)
                    <input type="text" name="about_heading" value="<?php echo e(figurify_content($content, "about_heading", "About us")); ?>">
                </label>

                <label class="content-field">
                    Heading
                    <input type="text" name="about_script" value="<?php echo e(figurify_content($content, "about_script", "Crafting smiles out of tiny pieces of clay")); ?>">
                </label>

                <label class="content-field">
                    Main paragraph
                    <textarea name="about_text" rows="1"><?php echo e(figurify_content($content, "about_text", "The best memories live in small details. Each piece is shaped by hand, one at a time, a one-of-a-kind gift for you and the people you love.")); ?></textarea>
                </label>

                <label class="content-field">
                    Sign-off line
                    <textarea name="about_sign" rows="1"><?php echo e(figurify_content($content, "about_sign", "From couple figures to special moments, we turn your stories into tiny clay art you can keep forever.")); ?></textarea>
                </label>

                <?php foreach ([1 => ["100%", "Handmade"], 2 => ["1-of-1", "Every piece unique"], 3 => ["Laguna", "Made in Calamba"]] as $i => $defaultStat): ?>
                    <div class="content-field">
                        Small box <?php echo $i; ?> (big text + small text)
                        <div class="cmx-pair">
                            <input type="text" name="about_stat<?php echo $i; ?>_value" placeholder="Big text" aria-label="Small box <?php echo $i; ?> big text" value="<?php echo e(figurify_content($content, "about_stat{$i}_value", $defaultStat[0])); ?>">
                            <input type="text" name="about_stat<?php echo $i; ?>_label" placeholder="Small text" aria-label="Small box <?php echo $i; ?> small text" value="<?php echo e(figurify_content($content, "about_stat{$i}_label", $defaultStat[1])); ?>">
                        </div>
                    </div>
                <?php endforeach; ?>

                <label class="content-field">
                    Button text
                    <input type="text" name="about_button" value="<?php echo e(figurify_content($content, "about_button", "Start your order")); ?>">
                </label>

            </div>

        </section>

        <section class="card content-section cmx-card" id="section-how">

            <h3 class="cmx-title">How It Works</h3>

            <div class="cmx-stack">

                <label class="content-field">
                    Eyebrow (small text on top)
                    <input type="text" name="how_eyebrow" value="<?php echo e(figurify_content($content, "how_eyebrow", "How it works")); ?>">
                </label>

                <label class="content-field">
                    Heading
                    <input type="text" name="how_heading" value="<?php echo e(figurify_content($content, "how_heading", "Order in 4 easy steps")); ?>">
                </label>

                <label class="content-field">
                    Subtitle
                    <input type="text" name="how_subtitle" value="<?php echo e(figurify_content($content, "how_subtitle", "No guesswork. We guide you from start to finish.")); ?>">
                </label>

                <?php
                $defaultSteps = [
                    1 => ["title" => "Pick a style",         "text" => "Choose Hirono, Chibi or Funko Pop, then the type. Funko Pop comes as a standee; Hirono and Chibi come as a standee or keychain."],
                    2 => ["title" => "Send your details",    "text" => "Upload photos or use Dress Up. Set the quantity and add notes."],
                    3 => ["title" => "Review the quotation", "text" => "We send the price. Accept it, then pay the 50% downpayment."],
                    4 => ["title" => "Track & receive",      "text" => "See progress updates, pay the balance, and get it shipped to you."],
                ];
                ?>

                <?php foreach ($defaultSteps as $i => $default): ?>
                    <div class="content-field">
                        Step <?php echo $i; ?>
                        <input type="text" name="how<?php echo $i; ?>_title" placeholder="Title" aria-label="Step <?php echo $i; ?> title" value="<?php echo e(figurify_content($content, "how{$i}_title", $default["title"])); ?>">
                        <textarea name="how<?php echo $i; ?>_text" rows="1" placeholder="Description" aria-label="Step <?php echo $i; ?> description"><?php echo e(figurify_content($content, "how{$i}_text", $default["text"])); ?></textarea>
                    </div>
                <?php endforeach; ?>

                <div class="content-field">
                    Login reminder (shows only to visitors who are not logged in)
                    <div class="cmx-pair">
                        <input type="text" name="how_note_lead" placeholder="Bold text" aria-label="Reminder bold text" value="<?php echo e(figurify_content($content, "how_note_lead", "Log in first")); ?>">
                        <input type="text" name="how_note_text" placeholder="Rest of the sentence" aria-label="Reminder text" value="<?php echo e(figurify_content($content, "how_note_text", "to place an order. You can follow everything under My Orders.")); ?>">
                    </div>
                </div>

            </div>

        </section>

    </div>


    <!-- COLLECTION STYLES (buong lapad) -->

    <section class="card content-section cmx-card" id="section-styles">

        <h3 class="cmx-title">Collection Styles</h3>

        <div class="cmx-grid-3">

            <label class="content-field">
                Eyebrow (small text on top)
                <input type="text" name="styles_eyebrow" value="<?php echo e(figurify_content($content, "styles_eyebrow", "Our collection")); ?>">
            </label>

            <label class="content-field">
                Section heading
                <input type="text" name="styles_heading" value="<?php echo e(figurify_content($content, "styles_heading", "Choose your style")); ?>">
            </label>

            <label class="content-field">
                Section subtitle
                <input type="text" name="styles_subtitle" value="<?php echo e(figurify_content($content, "styles_subtitle", "Three signature looks. Tap one to see sizes and prices.")); ?>">
            </label>

        </div>

        <?php
        $defaultStyles = [
            1 => ["image" => "figures/covers/hirono-style-cover.jpg",    "title" => "Hirono Style",    "text" => "Dreamy, expressive faces with a soft collectible look.",     "style" => "Hirono",    "badge" => "Most popular"],
            2 => ["image" => "figures/covers/chibi-style-cover.jpg",     "title" => "Chibi Style",     "text" => "Cute big-head proportions, perfect for gifts and weddings.", "style" => "Chibi",     "badge" => ""],
            3 => ["image" => "figures/covers/funko-pop-style-cover.jpg", "title" => "Funko Pop Style", "text" => "Boxed pop-figure look with your own face and outfit.",       "style" => "Funko Pop", "badge" => ""],
        ];
        ?>

        <div class="cmx-grid-3 cmx-products">

            <?php foreach ($defaultStyles as $i => $default): ?>

                <div class="cmx-stack">

                    <div class="cmx-sub">Style <?php echo $i; ?></div>

                    <?php figurify_cm_image_field("style{$i}_image", "", $content, "../Image/" . $default["image"], "cm-ratio-square"); ?>

                    <label class="content-field">
                        Title
                        <input type="text" name="style<?php echo $i; ?>_title" value="<?php echo e(figurify_content($content, "style{$i}_title", $default["title"])); ?>">
                    </label>

                    <label class="content-field">
                        Description
                        <textarea name="style<?php echo $i; ?>_text" rows="1"><?php echo e(figurify_content($content, "style{$i}_text", $default["text"])); ?></textarea>
                    </label>

                    <label class="content-field">
                        Starting price (kusang galing sa Prices tab)
                        <input type="text" value="₱<?php echo number_format(figurify_style_start_price($prices, $default["style"]), 2); ?>" readonly>
                    </label>

                    <label class="content-field">
                        Badge (leave blank for none)
                        <input type="text" name="style<?php echo $i; ?>_badge" value="<?php echo e(array_key_exists("style{$i}_badge", $content) ? $content["style{$i}_badge"] : $default["badge"]); ?>">
                    </label>

                </div>

            <?php endforeach; ?>

        </div>

        <?php
        $defaultShortcuts = [
            1 => ["title" => "Send your photos",      "text" => "Upload a picture and we sculpt it.",       "link" => "Image Submission"],
            2 => ["title" => "Dress up your figure", "text" => "Pick hair, outfit and shoes yourself.",   "link" => "Try Dress Up"],
        ];
        ?>

        <div class="cmx-grid-2 cmx-products">

            <?php foreach ($defaultShortcuts as $i => $default): ?>

                <div class="cmx-stack">

                    <div class="cmx-sub">Shortcut card <?php echo $i; ?></div>

                    <label class="content-field">
                        Title
                        <input type="text" name="shortcut<?php echo $i; ?>_title" value="<?php echo e(figurify_content($content, "shortcut{$i}_title", $default["title"])); ?>">
                    </label>

                    <label class="content-field">
                        Description
                        <input type="text" name="shortcut<?php echo $i; ?>_text" value="<?php echo e(figurify_content($content, "shortcut{$i}_text", $default["text"])); ?>">
                    </label>

                    <label class="content-field">
                        Link text
                        <input type="text" name="shortcut<?php echo $i; ?>_link" value="<?php echo e(figurify_content($content, "shortcut{$i}_link", $default["link"])); ?>">
                    </label>

                </div>

            <?php endforeach; ?>

        </div>

    </section>


    <!-- FEATURED ORDERS (buong lapad) -->

    <section class="card content-section cmx-card" id="section-featured">

            <h3 class="cmx-title">Featured Orders</h3>

            <div class="cmx-stack">

                <label class="content-field">
                    Eyebrow (small text on top)
                    <input type="text" name="featured_eyebrow" value="<?php echo e(figurify_content($content, "featured_eyebrow", "Featured")); ?>">
                </label>

                <label class="content-field">
                    Section heading
                    <input type="text" name="featured_heading" value="<?php echo e(figurify_content($content, "featured_heading", "Our most-loved pieces")); ?>">
                </label>

                <label class="content-field">
                    Section subtitle
                    <input type="text" name="featured_subtitle" value="<?php echo e(figurify_content($content, "featured_subtitle", "Shaped one at a time, made just for you.")); ?>">
                </label>

            </div>

            <?php
            $defaultProducts = [
                1 => ["image" => "figures/hirono/hirono-couple-standee-1.jpg",  "title" => "Hirono Couple Standee",  "price" => "₱1,800"],
                2 => ["image" => "figures/chibi/chibi-full-body-standee.jpg",    "title" => "Chibi Wedding Standee",  "price" => "₱1,200"],
                3 => ["image" => "figures/funko-pop/funko-pop-12.jpg",           "title" => "Funko Pop Couple Set",   "price" => "₱1,500"],
                4 => ["image" => "figures/chibi/chibi-keychain-set.jpg",         "title" => "Chibi Keychains",        "price" => "₱450"],
            ];
            ?>

            <div class="cmx-grid-2 cmx-products">

                <?php foreach ($defaultProducts as $i => $default): ?>

                    <div class="cmx-stack">

                        <div class="cmx-sub">Product <?php echo $i; ?></div>

                        <?php figurify_cm_image_field("product{$i}_image", "", $content, "../Image/" . $default["image"], "cm-ratio-square"); ?>

                        <label class="content-field">
                            Title
                            <input type="text" name="product<?php echo $i; ?>_title" value="<?php echo e(figurify_content($content, "product{$i}_title", $default["title"])); ?>">
                        </label>

                        <label class="content-field">
                            Price
                            <input type="text" name="product<?php echo $i; ?>_price" value="<?php echo e(figurify_content($content, "product{$i}_price", $default["price"])); ?>">
                        </label>

                        <label class="content-field">
                            Caption (optional)
                            <input type="text" name="product<?php echo $i; ?>_caption" value="<?php echo e(figurify_content($content, "product{$i}_caption", "")); ?>">
                        </label>

                    </div>

                <?php endforeach; ?>

            </div>

    </section>


    <!-- START AN ORDER (madilim na card sa ilalim ng Reviews) -->

    <section class="card content-section cmx-card" id="section-cta">

        <h3 class="cmx-title">Start an Order Banner</h3>

        <div class="cmx-grid-3">

            <label class="content-field">
                Heading
                <input type="text" name="cta_heading" value="<?php echo e(figurify_content($content, "cta_heading", "Have a figure in mind?")); ?>">
            </label>

            <label class="content-field">
                Text
                <input type="text" name="cta_text" value="<?php echo e(figurify_content($content, "cta_text", "Tell us your idea and we'll shape it in clay.")); ?>">
            </label>

            <label class="content-field">
                Button text
                <input type="text" name="cta_button" value="<?php echo e(figurify_content($content, "cta_button", "Start an Order")); ?>">
            </label>

        </div>

    </section>


    <!-- FEATURES BANNER + REVIEWS + FOOTER (tatlong magkatabi) -->

    <div class="cmx-row-3">

        <section class="card content-section cmx-card" id="section-features">

            <h3 class="cmx-title">Features Banner</h3>

            <?php
            $defaultFeatures = [
                1 => ["title" => "Handmade", "subtitle" => "with love"],
                2 => ["title" => "Customize", "subtitle" => "your order"],
                3 => ["title" => "Secured", "subtitle" => "packaging"],
                4 => ["title" => "Friendly", "subtitle" => "service"],
            ];
            ?>

            <div class="cmx-stack">

                <?php foreach ($defaultFeatures as $i => $default): ?>

                    <div class="content-field">
                        Item <?php echo $i; ?>
                        <div class="cmx-pair">
                            <input type="text" name="feature<?php echo $i; ?>_title" placeholder="Title" aria-label="Item <?php echo $i; ?> title" value="<?php echo e(figurify_content($content, "feature{$i}_title", $default["title"])); ?>">
                            <input type="text" name="feature<?php echo $i; ?>_subtitle" placeholder="Subtitle" aria-label="Item <?php echo $i; ?> subtitle" value="<?php echo e(figurify_content($content, "feature{$i}_subtitle", $default["subtitle"])); ?>">
                        </div>
                    </div>

                <?php endforeach; ?>

            </div>

        </section>


        <section class="card content-section cmx-card" id="section-reviews">

            <h3 class="cmx-title">Reviews</h3>

            <div class="cmx-stack">

                <label class="content-field">
                    Eyebrow (small text on top)
                    <input type="text" name="reviews_eyebrow" value="<?php echo e(figurify_content($content, "reviews_eyebrow", "Reviews")); ?>">
                </label>

                <label class="content-field">
                    Heading
                    <input type="text" name="reviews_heading" value="<?php echo e(figurify_content($content, "reviews_heading", "What our customers say")); ?>">
                </label>

                <label class="content-field">
                    Subtitle
                    <input type="text" name="reviews_subtitle" value="<?php echo e(figurify_content($content, "reviews_subtitle", "Loved by collectors & gift-givers.")); ?>">
                </label>

            </div>

        </section>


        <section class="card content-section cmx-card" id="section-footer">

            <h3 class="cmx-title">Footer &amp; Contact</h3>

            <div class="cmx-stack">

                <label class="content-field">
                    Tagline below logo
                    <input type="text" name="footer_tagline" value="<?php echo e(figurify_content($content, "footer_tagline", "Handmade clay figures, made with love from your favorite moments.")); ?>">
                </label>

                <label class="content-field">
                    Address
                    <input type="text" name="contact_address" value="<?php echo e(figurify_content($content, "contact_address", "Brgy. Palingon, Calamba City, Laguna")); ?>">
                </label>

                <label class="content-field">
                    Phone number
                    <input type="text" name="contact_phone" value="<?php echo e(figurify_content($content, "contact_phone", "+63 910 281 4331")); ?>">
                </label>

                <label class="content-field">
                    Email
                    <input type="text" name="contact_email" value="<?php echo e(figurify_content($content, "contact_email", "clayandstuff@gmail.com")); ?>">
                </label>

                <label class="content-field">
                    Facebook link
                    <input type="text" name="social_facebook" placeholder="https://facebook.com/yourpage" value="<?php echo e(figurify_content($content, "social_facebook", "https://www.facebook.com/clayandstufff")); ?>">
                </label>

                <label class="content-field">
                    Instagram link
                    <input type="text" name="social_instagram" placeholder="https://instagram.com/yourpage" value="<?php echo e(figurify_content($content, "social_instagram", "https://www.instagram.com/clayandstufff")); ?>">
                </label>

                <label class="content-field">
                    TikTok link
                    <input type="text" name="social_tiktok" placeholder="https://tiktok.com/@yourpage" value="<?php echo e(figurify_content($content, "social_tiktok", "https://www.tiktok.com/@clay_and_stuff")); ?>">
                </label>

                <label class="content-field">
                    Gmail link
                    <input type="text" name="social_gmail" placeholder="clayandstuff@gmail.com" value="<?php echo e(figurify_content($content, "social_gmail", "clayandstuff.system@gmail.com")); ?>">
                </label>

            </div>

        </section>

    </div>


    <!-- ==================== FAQ (sa ilalim ng Reviews sa Home; default nasa content_helper.php SECTION 6) ==================== -->

    <?php $faq = figurify_faq($content); ?>

    <section class="card content-section cmx-card" id="section-faq">

        <h3 class="cmx-title">FAQ (Frequently Asked Questions)</h3>

        <p class="cmx-hint">Use + Add Question for a new question and Delete to remove one (up to <?php echo count($faq["items"]); ?> questions). Click Save Changes after. Write {figure_name} (or another price name from the Prices tab, e.g. {box_solo}) to show the current price.</p>

        <div class="cmx-grid-3">

            <label class="content-field">
                Eyebrow (small text on top)
                <input type="text" name="faq_eyebrow" value="<?php echo e($faq["eyebrow"]); ?>">
            </label>

            <label class="content-field">
                Heading
                <input type="text" name="faq_heading" value="<?php echo e($faq["heading"]); ?>">
            </label>

            <label class="content-field">
                Subtitle
                <input type="text" name="faq_subtitle" value="<?php echo e($faq["subtitle"]); ?>">
            </label>

        </div>

        <!-- blangkong tanong = nakatago (bakanteng puwesto para sa + Add Question); Delete = binubura ang laman -->
        <div class="cmx-grid-2 cmx-products cmx-faq-list" id="faqList">

            <?php foreach ($faq["items"] as $i => $item): ?>
                <?php $n = $i + 1; ?>
                <div class="content-field cmx-faq-item"<?php echo trim($item["question"]) === "" ? " hidden" : ""; ?>>
                    <span class="cmx-faq-head">
                        <span class="cmx-faq-number"></span>
                        <button type="button" class="cmx-faq-delete" onclick="cmxDeleteFaq(this)">Delete</button>
                    </span>
                    <input type="text" name="faq<?php echo $n; ?>_question" placeholder="Question" aria-label="Question <?php echo $n; ?>" value="<?php echo e($item["question"]); ?>">
                    <textarea name="faq<?php echo $n; ?>_answer" rows="1" placeholder="Answer" aria-label="Answer <?php echo $n; ?>"><?php echo e($item["answer"]); ?></textarea>
                </div>
            <?php endforeach; ?>

        </div>

        <button type="button" class="cmx-faq-add" id="faqAddButton" onclick="cmxAddFaq()">+ Add Question</button>

    </section>

    <!-- ==================== END FAQ ==================== -->


    <div class="content-form-actions">
        <button
            type="submit"
            class="content-save-btn"
        >
            Save Changes
        </button>
    </div>

</form>

<?php endif; ?>


</main>

</div>

<script>
/* ==========================================================================
   [MEDIA UPLOAD (larawan / video)]
   ========================================================================== */
// Media upload (larawan/video) - auto-upload (AJAX) papunta upload_content_media.php, then set hidden
// input value (isu-submit kasama ng form) AT update live preview.
function figurifyUploadMedia(fileInput, kind) {

    const file = fileInput.files && fileInput.files[0];

    if (!file) {
        return;
    }

    const field = fileInput.closest(".content-media-field");
    const hiddenInput = field.querySelector('input[type="hidden"]');
    const status = field.querySelector(".content-media-status");
    const previewImg = field.querySelector("img");
    const previewVideo = field.querySelector("video");

    if (status) {
        status.textContent = "Uploading...";
        status.classList.remove("error");
    }

    const formData = new FormData();
    formData.append("file", file);
    formData.append("kind", kind);
    formData.append("category", "carousel");

    // ipadala ang lumang value para mabura ng server ang lumang file
    if (hiddenInput && hiddenInput.value) {
        formData.append("old_value", hiddenInput.value);
    }

    formData.append("csrf_token", window.csCsrfToken || "");

    fetch("upload_content_media.php", {
        method: "POST",
        body: formData
    })
        .then(response => response.json())
        .then(data => {

            if (!data.ok) {
                if (status) {
                    status.textContent = data.error || "Failed to upload file.";
                    status.classList.add("error");
                }
                return;
            }

            if (hiddenInput) {
                hiddenInput.value = data.value;
            }

            if (previewImg) {
                previewImg.src = data.src;
            }

            if (previewVideo) {
                previewVideo.src = data.src;
                previewVideo.load();
            }

            if (status) {
                status.textContent = "Uploaded. Click \"Save Changes\" to apply.";
            }

        })
        .catch(() => {
            if (status) {
                status.textContent = "Upload error. Please try again.";
                status.classList.add("error");
            }
        });

    // I-reset para pwede ulit pumili ng parehong file sa susunod.
    fileInput.value = "";
}
/* ==========================================================================
   END [MEDIA UPLOAD (larawan / video)]
   ========================================================================== */


/* Auto-grow ng lahat ng textbox sa page na ito: lumalaki habang nagta-type,
   kaya walang scrollbar sa loob ng textbox at tuloy-tuloy ang scroll ng page. */
(function () {
    function grow(t) {
        if (!t.offsetParent) {
            return; // nakatago pa (ibang tab) - aayusin pag lumabas na
        }
        // dagdagan ang rows hanggang kasya ang laman (walang inline style)
        t.rows = 1;
        while (t.scrollHeight > t.clientHeight && t.rows < 60) {
            t.rows += 1;
        }
    }

    function growAll() {
        document.querySelectorAll("textarea.cs-autogrow").forEach(grow);
    }

    document.querySelectorAll("textarea").forEach(function (t) {
        t.classList.add("cs-autogrow");
        t.addEventListener("input", function () { grow(t); });
    });

    growAll();
    window.addEventListener("load", growAll);

    var timer;
    window.addEventListener("resize", function () {
        clearTimeout(timer);
        timer = setTimeout(growAll, 100);
    });

    // kapag lumipat ng tab (Home Page / Collection) o bumukas ang nakatagong section
    document.addEventListener("click", function () { setTimeout(growAll, 0); });
})();

/* ==================== FAQ: + Add Question / Delete ====================
   Bakanteng puwesto (blangkong tanong) ang ginagamit ng Add; ang Delete ay binubura ang laman
   (blangko = nakatago sa Home pagka-Save Changes). */
function cmxRefreshFaqAdd() {
    const addButton = document.getElementById("faqAddButton");
    if (addButton) {
        addButton.hidden = !document.querySelector("#faqList .cmx-faq-item[hidden]");
    }
}

function cmxAddFaq() {
    const slot = document.querySelector("#faqList .cmx-faq-item[hidden]");
    if (!slot) {
        return;
    }
    slot.hidden = false;
    slot.querySelector("input").focus();
    cmxRefreshFaqAdd();
}

function cmxDeleteFaq(button) {
    const item = button.closest(".cmx-faq-item");
    csConfirm("Delete this question?\nIt will be removed from the Home page after you save.", { okText: "Delete" }).then(function (ok) {
        if (!ok) {
            return;
        }
        item.querySelectorAll("input, textarea").forEach(function (field) { field.value = ""; });
        item.hidden = true;
        cmxRefreshFaqAdd();
    });
}

cmxRefreshFaqAdd();
/* ==================== END FAQ ==================== */

/* ==================== SAVE CHANGES: ipadala lang ang binago ====================
   Ang text na hindi ginalaw ay hindi na sine-save, kaya ang Home, Terms at Privacy
   ay susunod pa rin sa bagong default na text ng code. Laging kasama ang hidden input (larawan, video). */
document.querySelectorAll("form.content-form").forEach(function (form) {
    form.addEventListener("submit", function () {
        form.querySelectorAll('input[type="text"], textarea').forEach(function (field) {
            field.disabled = field.value === field.defaultValue;
        });
    });
});

// pag bumalik gamit ang Back button, buksan ulit ang mga field
window.addEventListener("pageshow", function () {
    document.querySelectorAll(".content-form :disabled").forEach(function (field) {
        field.disabled = false;
    });
});
/* ==================== END SAVE CHANGES ==================== */
</script>

</body>

</html>