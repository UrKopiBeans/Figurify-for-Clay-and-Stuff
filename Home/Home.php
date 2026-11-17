<?php
/* ==========================================================================
   HOME PAGE   (Home/Home.php)
   Style: Home/Home.css      Script: Home/Home.js
   Walang CSS o JS sa file na ito - PHP at HTML lang.

   LAMAN (taas pababa):
     [1] SETUP           helpers, session, database
     [2] FUNCTIONS       c(), c_optional(), img(), format_price(), format_heading(), review_name()
     [3] DATA            hero slides, how it works, styles, shortcuts, featured,
                         features, reviews, faq
     [4] HTML            navbar, hero, about us, how it works, collection styles,
                         featured, features banner, reviews, start an order, faq, footer

   Lahat ng text/larawan dito ay galing sa Content Management ng owner
   (c() / img()). Ang nakasulat sa code ay default lang kapag wala pang na-save.
   ========================================================================== */


/* ==========================================================================
   [1] SETUP
   - security_helper.php : figurify_start_session(), e()
   - content_helper.php  : text/image galing sa Content Management, avatar
   - database.php        : gumagawa ng $conn
   ========================================================================== */
require_once __DIR__ . "/../helpers/security_helper.php";
require_once __DIR__ . "/../helpers/content_helper.php";
require_once __DIR__ . "/../Admin/database.php";

figurify_start_session();
/* ====================== END SETUP ====================== */


/* ==========================================================================
   [2] FUNCTIONS
   ========================================================================== */

// c() = text na na-edit ng owner sa Content Management; $default kapag wala pa
function c($key, $default)
{
    global $siteContent;
    return figurify_content($siteContent, $key, $default);
}

// c_optional() = kapareho ng c(), pero kapag sinadyang iwanang blangko ng owner, blangko rin dito (hal. badge)
function c_optional($key, $default)
{
    global $siteContent;
    return array_key_exists($key, $siteContent) ? $siteContent[$key] : $default;
}

// img() = image na pinili ng owner; $default kapag wala pa
function img($key, $default)
{
    return e(figurify_resolve_image_src(c($key, ""), $default));
}

// format_price() = linisin ang presyo at lagyan ng ₱ sa unahan
function format_price($price)
{
    $price = str_replace(["₱", "&#8369;"], "", $price);
    $price = trim(preg_replace("/\beach\b/i", "", $price));
    // Ayusin ang lumang presyo na nagsisimula sa ? (hal. ?450).
    $price = preg_replace('/^\?\s*(?=\d)/', "", $price);

    if ($price !== "" && is_numeric($price[0])) {
        $price = "₱" . $price;
    }

    return $price;
}

// format_heading() = "|" ay bagong linya, *salita* ay pink (hindi italic). Ligtas na HTML ang bumabalik.
function format_heading($text)
{
    $text = e($text);
    $text = preg_replace("/\*(.+?)\*/", "<em>$1</em>", $text);

    return str_replace("|", "<br>", $text);
}

// review_name() = "Maria", "Cruz", "Santos" -> "Maria Cruz Santos" (walang middle name = "Maria Santos")
function review_name($firstName, $middleName, $lastName)
{
    return trim(preg_replace("/\s+/", " ", $firstName . " " . $middleName . " " . $lastName));
}
/* ====================== END FUNCTIONS ====================== */


/* ==========================================================================
   [3] DATA
   ========================================================================== */

// lahat ng text na na-edit ng owner (ginagamit ng c())
$siteContent = figurify_get_all_content($conn);

// naka-login ba ang bisita? (para sa paalala sa How it works)
$isLoggedIn = isset($_SESSION["user_id"]);

// HERO SLIDES - 3 slide. "|" = bagong linya, *salita* = pink
$heroSlides = [
    [
        "image"   => img("hero_image", "../Image/home/carousel-1.jpg"),
        "eyebrow" => c("hero_eyebrow", "Tiny keepsakes for big feelings"),
        "heading" => c("hero_heading", "Turning memories|into *mini masterpieces*"),
        "tagline" => c("hero_tagline", "Handmade clay figures from your favorite moments. No two are the same."),
        "link"    => "../Collection/collection.php",
        "text"    => c("hero_link_text", "Shop the collection"),
    ],
    [
        "image"   => img("slide2_image", "../Image/home/carousel-2.jpg"),
        "eyebrow" => c("slide2_eyebrow", "Customized just for you"),
        "heading" => c("slide2_heading", "Handcrafted couple figures|made *with love*"),
        "tagline" => c("slide2_tagline", "Send a photo and your story, and we'll turn it into a keepsake you can keep forever."),
        "link"    => "../Commission/commission.php",
        "text"    => c("slide2_link_text", "Commission a piece"),
    ],
    [
        "image"   => img("slide3_image", "../Image/home/carousel-3.jpg"),
        "eyebrow" => c("slide3_eyebrow", "Special events & weddings"),
        "heading" => c("slide3_heading", "Capture your special day|in *cute clay style*"),
        "tagline" => c("slide3_tagline", "From bridal parties to first dances, we sculpt the day you'll want to keep on your shelf."),
        "link"    => "../Collection/collection.php",
        "text"    => c("slide3_link_text", "Explore wedding sets"),
    ],
];

// HERO - maliit na 3 text sa ilalim ng mga button (glyph = icon, nasa code)
$heroChips = [
    ["glyph" => "★", "text" => c("hero_chip1", "Real customer reviews")],
    ["glyph" => "✿", "text" => c("hero_chip2", "100% handmade")],
    ["glyph" => "✓", "text" => c("hero_chip3", "Track every order")],
];

// ABOUT US - 3 maliit na kahon (value + label)
$aboutStats = [
    ["value" => c("about_stat1_value", "100%"),   "label" => c("about_stat1_label", "Handmade")],
    ["value" => c("about_stat2_value", "1-of-1"), "label" => c("about_stat2_label", "Every piece unique")],
    ["value" => c("about_stat3_value", "Laguna"), "label" => c("about_stat3_label", "Made in Calamba")],
];

// HOW IT WORKS - 4 hakbang (how1 ... how4)
$steps = [
    ["title" => "Pick a style",         "text" => "Choose Hirono, Chibi or Funko Pop, then the type. Funko Pop comes as a standee; Hirono and Chibi come as a standee or keychain."],
    ["title" => "Send your details",    "text" => "Upload photos or use Dress Up. Set the quantity and add notes."],
    ["title" => "Review the quotation", "text" => "We send the price. Accept it, then pay the 50% downpayment."],
    ["title" => "Track & receive",      "text" => "See progress updates, pay the balance, and get it shipped to you."],
];

// PRESYO (Content Management > Prices): "Starting at" ng bawat style at {figure_name} atbp. sa FAQ
$prices      = figurify_price_config($siteContent);
$priceTokens = figurify_price_tokens($siteContent);

// COLLECTION STYLES - 3 style (style1 ... style3); "Starting at" = pinakamababang presyo ng style
$styles = [
    ["image" => "../Image/figures/covers/hirono-style-cover.jpg",     "title" => "Hirono Style",    "text" => "Dreamy, expressive faces with a soft collectible look.",         "style" => "Hirono",    "badge" => "Most popular"],
    ["image" => "../Image/figures/covers/chibi-style-cover.jpg",      "title" => "Chibi Style",     "text" => "Cute big-head proportions, perfect for gifts and weddings.",     "style" => "Chibi",     "badge" => ""],
    ["image" => "../Image/figures/covers/funko-pop-style-cover.jpg",  "title" => "Funko Pop Style", "text" => "Boxed pop-figure look with your own face and outfit.",           "style" => "Funko Pop", "badge" => ""],
];

// 2 SHORTCUT NA CARD sa ilalim ng styles (shortcut1, shortcut2)
$shortcuts = [
    ["icon" => "📷", "title" => "Send your photos",   "text" => "Upload a picture and we sculpt it.",     "link" => "Image Submission"],
    ["icon" => "👗", "title" => "Dress up your figure", "text" => "Pick hair, outfit and shoes yourself.", "link" => "Try Dress Up"],
];

// FEATURED ORDERS - 4 product (product1 ... product4 sa Content Management)
$featuredProducts = [
    ["image" => "../Image/figures/hirono/hirono-couple-standee-1.jpg", "title" => "Hirono Couple Standee", "price" => "₱1,800"],
    ["image" => "../Image/figures/chibi/chibi-full-body-standee.jpg",  "title" => "Chibi Wedding Standee",  "price" => "₱1,200"],
    ["image" => "../Image/figures/funko-pop/funko-pop-12.jpg",         "title" => "Funko Pop Couple Set",   "price" => "₱1,500"],
    ["image" => "../Image/figures/chibi/chibi-keychain-set.jpg",       "title" => "Chibi Keychains",        "price" => "₱450"],
];

// FEATURES BANNER - 4 item (feature1 ... feature4); svg = laman ng 24x24 icon
$features = [
    ["title" => "Handmade",  "sub" => "with love",
     "svg"   => '<path d="M12 20.5C12 20.5 4 15.4 4 9.6C4 6.6 6.3 4.5 9 4.5C10.6 4.5 12 5.3 12 5.3C12 5.3 13.4 4.5 15 4.5C17.7 4.5 20 6.6 20 9.6C20 15.4 12 20.5 12 20.5Z"/>'],
    ["title" => "Customize", "sub" => "your order",
     "svg"   => '<path d="M4 6H14M4 12H10M4 18H14"/><circle cx="17" cy="6" r="2.3"/><circle cx="13" cy="12" r="2.3"/><circle cx="17" cy="18" r="2.3"/>'],
    ["title" => "Secured",   "sub" => "packaging",
     "svg"   => '<path d="M12 3.5L19.5 6.7V11.2C19.5 15.9 16.5 19.7 12 21C7.5 19.7 4.5 15.9 4.5 11.2V6.7L12 3.5Z"/><path d="M8.7 11.7L10.8 13.8L15.3 9.3"/>'],
    ["title" => "Friendly",  "sub" => "service",
     "svg"   => '<path d="M21 11.5C21 16.2 16.97 20 12 20C10.7 20 9.46 19.74 8.34 19.26L4 20.5L5.4 16.68C4.52 15.34 4 13.78 4 12.1C4 7.4 8.03 3.5 12.5 3.5C17.2 3.5 21 7.05 21 11.5Z"/><circle class="dot-fill" cx="8" cy="11.5" r="1"/><circle class="dot-fill" cx="12" cy="11.5" r="1"/><circle class="dot-fill" cx="16" cy="11.5" r="1"/>'],
];

// REVIEWS - galing sa database: 3 pinakabago na may 4 stars pataas.
// Kasama ang pangalan, email at mga larawan ng review (images = paths na pinag-hiwalay ng "|").
$homeReviews = [];

$result = $conn->query("
    SELECT r.rating, r.review_text, u.first_name, u.middle_name, u.last_name, u.email, u.full_name, u.profile_picture,
           (SELECT GROUP_CONCAT(ri.image_path ORDER BY ri.review_image_id SEPARATOR '|')
            FROM review_images ri
            WHERE ri.review_id = r.review_id) AS images
    FROM reviews r
    JOIN users u ON u.user_id = r.user_id
    WHERE r.rating >= 4
    ORDER BY r.created_at DESC
    LIMIT 3
");

if ($result) {
    $homeReviews = $result->fetch_all(MYSQLI_ASSOC);
}

// FAQ - heading at mga tanong (content_helper.php SECTION 6); blangkong tanong = hindi ipapakita
$faq = figurify_faq($siteContent);
$faqItems = array_filter($faq["items"], fn($item) => trim($item["question"]) !== "");
/* ====================== END DATA ====================== */


/* ==========================================================================
   [4] HTML
   ========================================================================== */
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Clay and Stuff | Home</title>
    <link rel="icon" type="image/png" href="../Image/brand/logo.png">
    <?php include __DIR__ . "/../Shared/popup-and-security.php"; ?>
    <link rel="stylesheet" href="Home.css">
</head>

<body class="page-home">


<!-- ====================== NAVBAR (Shared/navbar.php) ====================== -->
<?php include __DIR__ . "/../Shared/navbar.php"; ?>
<!-- ====================== END NAVBAR ====================== -->


<!-- ====================== HERO CAROUSEL (css: Home.css [2] | js: Home.js [1]) ====================== -->
<header class="hero" id="top">

    <div class="carousel-track" id="carouselTrack">
        <?php foreach ($heroSlides as $slide): ?>
            <div class="carousel-slide">
                <div class="slide-inner">
                    <div class="hero-copy">
                        <p class="hero-eyebrow"><?php echo e($slide["eyebrow"]); ?></p>
                        <h1><?php echo format_heading($slide["heading"]); ?></h1>
                        <p class="tagline"><?php echo e($slide["tagline"]); ?></p>
                        <div class="hero-actions">
                            <a class="btn" href="<?php echo e($slide["link"]); ?>"><?php echo e($slide["text"]); ?></a>
                            <a class="btn btn-light" href="../Commission/commission.php"><?php echo e(c("hero_button2", "Start an Order")); ?></a>
                        </div>
                        <ul class="hero-chips">
                            <?php foreach ($heroChips as $chip): ?>
                                <li><span><?php echo $chip["glyph"]; ?></span><?php echo e($chip["text"]); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <img class="carousel-image" src="<?php echo $slide["image"]; ?>" alt="">
            </div>
        <?php endforeach; ?>
    </div>

    <!-- mga arrow: kaliwa at kanan -->
    <button type="button" class="carousel-arrow carousel-prev" id="carouselPrev" aria-label="Previous slide"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg></button>
    <button type="button" class="carousel-arrow carousel-next" id="carouselNext" aria-label="Next slide"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg></button>

    <!-- tuldok: isa bawat slide -->
    <div class="carousel-dots" id="carouselDots">
        <?php foreach ($heroSlides as $i => $slide): ?>
            <span class="dot<?php echo $i === 0 ? " active" : ""; ?>"></span>
        <?php endforeach; ?>
    </div>

</header>
<!-- ====================== END HERO CAROUSEL ====================== -->


<main>


    <!-- ====================== ABOUT US (css: Home.css [3] | js: Home.js [2]) ====================== -->
    <section class="about-section" id="overview">
        <div class="about-grid">

            <div class="about-media">
                <div class="about-blob"></div>

                <div class="about-phone" id="aboutPhone">
                    <video id="aboutVideo" playsinline preload="metadata">
                        <source src="<?php echo e(c("about_video", "../Image/videos/about us video.mp4")); ?>#t=0.1" type="video/mp4">
                    </video>
                    <button type="button" class="about-play" id="aboutPlay" aria-label="Play video"></button>
                    <div class="about-controls">
                        <div class="about-bar" id="aboutBar"><span id="aboutBarFill"></span></div>
                        <span class="about-time" id="aboutTime">0:00 / 0:00</span>
                    </div>
                </div>

                <div class="about-badge"><?php echo e(c("about_badge", "Clay & Stuff")); ?></div>
                <div class="about-caption">▶ <?php echo e(c("about_caption", "Watch how we make them")); ?></div>
            </div>

            <div class="about-content">
                <p class="section-eyebrow"><?php echo e(c("about_heading", "About us")); ?></p>
                <h2><?php echo e(c("about_script", "Crafting smiles out of tiny pieces of clay")); ?></h2>
                <p class="about-text"><?php echo e(c("about_text", "The best memories live in small details. Each piece is shaped by hand, one at a time, a one-of-a-kind gift for you and the people you love.")); ?></p>
                <p class="about-text about-text-soft"><?php echo e(c("about_sign", "From couple figures to special moments, we turn your stories into tiny clay art you can keep forever.")); ?></p>

                <div class="about-stats">
                    <?php foreach ($aboutStats as $stat): ?>
                        <div class="about-stat">
                            <strong><?php echo e($stat["value"]); ?></strong>
                            <span><?php echo e($stat["label"]); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <a class="btn btn-big" href="../Commission/commission.php"><?php echo e(c("about_button", "Start your order")); ?></a>
            </div>

        </div>
    </section>
    <!-- ====================== END ABOUT US ====================== -->


    <!-- ====================== HOW IT WORKS (css: Home.css [4]) ====================== -->
    <section class="band band-cream" id="how-it-works">
        <div class="wrap">

            <div class="section-heading">
                <p class="section-eyebrow"><?php echo e(c("how_eyebrow", "How it works")); ?></p>
                <h2><?php echo e(c("how_heading", "Order in 4 easy steps")); ?></h2>
                <p><?php echo e(c("how_subtitle", "No guesswork. We guide you from start to finish.")); ?></p>
            </div>

            <div class="steps-grid">
                <?php foreach ($steps as $i => $step): ?>
                    <?php $n = $i + 1; ?>
                    <div class="step-card">
                        <span class="step-number"><?php echo $n; ?></span>
                        <h3><?php echo e(c("how{$n}_title", $step["title"])); ?></h3>
                        <p><?php echo e(c("how{$n}_text", $step["text"])); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (!$isLoggedIn): ?>
                <p class="steps-note"><strong><?php echo e(c("how_note_lead", "Log in first")); ?></strong> <?php echo e(c("how_note_text", "to place an order. You can follow everything under My Orders.")); ?></p>
            <?php endif; ?>

        </div>
    </section>
    <!-- ====================== END HOW IT WORKS ====================== -->


    <!-- ====================== COLLECTION STYLES (css: Home.css [5]) ====================== -->
    <section class="band" id="collections">
        <div class="wrap">

            <div class="section-heading">
                <p class="section-eyebrow"><?php echo e(c("styles_eyebrow", "Our collection")); ?></p>
                <h2><?php echo e(c("styles_heading", "Choose your style")); ?></h2>
                <p><?php echo e(c("styles_subtitle", "Three signature looks. Tap one to see sizes and prices.")); ?></p>
            </div>

            <div class="styles-grid">
                <?php foreach ($styles as $i => $style): ?>
                    <?php
                    $n     = $i + 1;
                    $title = c("style{$n}_title", $style["title"]);
                    $badge = c_optional("style{$n}_badge", $style["badge"]);
                    ?>
                    <article class="style-card">
                        <div class="style-media">
                            <?php if ($badge !== ""): ?>
                                <span class="style-badge"><?php echo e($badge); ?></span>
                            <?php endif; ?>
                            <img src="<?php echo img("style{$n}_image", $style["image"]); ?>" alt="<?php echo e($title); ?>">
                        </div>
                        <div class="style-body">
                            <h3><?php echo e($title); ?></h3>
                            <p><?php echo e(c("style{$n}_text", $style["text"])); ?></p>
                            <div class="style-foot">
                                <div class="style-price">
                                    <small>Starting at</small>
                                    <strong>₱<?php echo number_format(figurify_style_start_price($prices, $style["style"]), 2); ?></strong>
                                </div>
                                <a class="btn btn-dark" href="../Collection/collection.php">View styles</a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="shortcuts-grid">
                <?php foreach ($shortcuts as $i => $shortcut): ?>
                    <?php $n = $i + 1; ?>
                    <a class="shortcut-card" href="../Commission/commission.php">
                        <span class="shortcut-icon"><?php echo $shortcut["icon"]; ?></span>
                        <span class="shortcut-text">
                            <strong><?php echo e(c("shortcut{$n}_title", $shortcut["title"])); ?></strong>
                            <span><?php echo e(c("shortcut{$n}_text", $shortcut["text"])); ?></span>
                        </span>
                        <span class="shortcut-link"><?php echo e(c("shortcut{$n}_link", $shortcut["link"])); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

        </div>
    </section>
    <!-- ====================== END COLLECTION STYLES ====================== -->


    <!-- ====================== FEATURED ORDERS (css: Home.css [6]) ====================== -->
    <section class="band band-cream" id="featured">
        <div class="wrap">

            <div class="section-heading">
                <p class="section-eyebrow"><?php echo e(c("featured_eyebrow", "Featured")); ?></p>
                <h2><?php echo e(c("featured_heading", "Our most-loved pieces")); ?></h2>
                <p><?php echo e(c("featured_subtitle", "Shaped one at a time, made just for you.")); ?></p>
            </div>

            <div class="orders-grid">
                <?php foreach ($featuredProducts as $i => $product): ?>
                    <?php
                    $n       = $i + 1;
                    $title   = c("product{$n}_title", $product["title"]);
                    $caption = c("product{$n}_caption", "");
                    $price   = format_price(c("product{$n}_price", $product["price"]));
                    ?>
                    <div class="order-card">
                        <div class="order-media">
                            <img src="<?php echo img("product{$n}_image", $product["image"]); ?>" alt="<?php echo e($title); ?>">
                        </div>
                        <div class="order-body">
                            <h3 class="order-title"><?php echo e($title); ?></h3>
                            <?php if ($caption !== ""): ?>
                                <p class="order-caption"><?php echo e($caption); ?></p>
                            <?php endif; ?>
                            <div class="order-foot">
                                <span class="order-price"><?php echo e($price); ?></span>
                                <a class="btn btn-small" href="../Commission/commission.php">Order now</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>
    </section>
    <!-- ====================== END FEATURED ORDERS ====================== -->


    <!-- ====================== FEATURES BANNER (css: Home.css [7]) ====================== -->
    <section class="features-banner">
        <div class="features-banner-inner">
            <?php foreach ($features as $i => $feature): ?>
                <?php $n = $i + 1; ?>
                <div class="feature-item">
                    <div class="feature-icon">
                        <svg viewBox="0 0 24 24"><?php echo $feature["svg"]; ?></svg>
                    </div>
                    <div class="feature-text">
                        <div class="feature-title"><?php echo e(c("feature{$n}_title", $feature["title"])); ?></div>
                        <div class="feature-subtitle"><?php echo e(c("feature{$n}_subtitle", $feature["sub"])); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <!-- ====================== END FEATURES BANNER ====================== -->


    <!-- ====================== REVIEWS (css: Home.css [8]) ====================== -->
    <section class="band band-cream" id="reviews">
        <div class="wrap">

            <div class="section-heading">
                <p class="section-eyebrow"><?php echo e(c("reviews_eyebrow", "Reviews")); ?></p>
                <h2><?php echo e(c("reviews_heading", "What our customers say")); ?></h2>
                <p><?php echo e(c("reviews_subtitle", "Loved by collectors & gift-givers.")); ?></p>
            </div>

            <?php if (empty($homeReviews)): ?>

                <div class="reviews-empty">
                    <p>No reviews yet. Be the first to share your experience!</p>
                </div>

            <?php else: ?>

                <div class="reviews-grid">
                    <?php foreach ($homeReviews as $review): ?>
                        <?php
                        $rating  = (int) $review["rating"];
                        $stars   = str_repeat("★", $rating) . str_repeat("☆", 5 - $rating);
                        $avatar  = figurify_render_avatar($review["full_name"], $review["profile_picture"], "Customer", "../");
                        /* larawan na wala na sa server (hal. hindi na-upload sa public) = hindi ipapakita, walang sirang larawan */
                        $images  = !empty($review["images"])
                            ? array_filter(explode("|", $review["images"]), fn($imgPath) => is_file(__DIR__ . "/../" . $imgPath))
                            : [];
                        ?>
                        <div class="review-card">
                            <div class="review-author">
                                <div class="review-avatar"><?php echo $avatar; ?></div>
                                <div class="review-meta">
                                    <h4><?php echo e(review_name($review["first_name"], $review["middle_name"], $review["last_name"])); ?></h4>
                                    <span><?php echo e($review["email"]); ?></span>
                                </div>
                                <div class="review-stars"><?php echo $stars; ?></div>
                            </div>
                            <?php if (!empty($review["review_text"])): ?>
                                <p class="review-text">"<?php echo e($review["review_text"]); ?>"</p>
                            <?php endif; ?>
                            <?php if ($images): ?>
                                <div class="review-images">
                                    <?php foreach ($images as $imgPath): ?>
                                        <img src="../<?php echo e($imgPath); ?>" alt="Review photo">
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php endif; ?>

        </div>
    </section>
    <!-- ====================== END REVIEWS ====================== -->


    <!-- ====================== START AN ORDER (css: Home.css [9]) ====================== -->
    <section class="band band-cream band-tight">
        <div class="wrap">
            <div class="start-order-card">

                <div class="start-order-text">
                    <h2><?php echo e(c("cta_heading", "Have a figure in mind?")); ?></h2>
                    <p><?php echo e(c("cta_text", "Tell us your idea and we'll shape it in clay.")); ?></p>
                </div>

                <a class="btn" href="../Commission/commission.php"><?php echo e(c("cta_button", "Start an Order")); ?></a>

            </div>
        </div>
    </section>
    <!-- ====================== END START AN ORDER ====================== -->


    <!-- ====================== FAQ (css: Home.css [10]) ======================
         Isang card: heading sa gitna sa taas, mga tanong sa 2 hiwalay na hanay
         (kalahati sa kaliwa, kalahati sa kanan), kaya hindi nadadamay ang kabila kapag may bumukas.
         Pindutin ang tanong para lumabas ang sagot (<details>, walang JS). -->
    <?php if ($faqItems): ?>
        <section class="band band-cream band-tight" id="faq">
            <div class="wrap">
                <div class="faq-card">

                    <div class="faq-intro">
                        <p class="section-eyebrow"><?php echo e($faq["eyebrow"]); ?></p>
                        <h2><?php echo e($faq["heading"]); ?></h2>
                        <p><?php echo e($faq["subtitle"]); ?></p>
                    </div>

                    <div class="faq-list">
                        <?php foreach (array_chunk($faqItems, (int) ceil(count($faqItems) / 2)) as $faqColumn): ?>
                            <div class="faq-column">
                                <?php foreach ($faqColumn as $item): ?>
                                    <details class="faq-item">
                                        <summary class="faq-question"><?php echo e($item["question"]); ?></summary>
                                        <p class="faq-answer"><?php echo e(strtr($item["answer"], $priceTokens)); ?></p>
                                    </details>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                </div>
            </div>
        </section>
    <?php endif; ?>
    <!-- ====================== END FAQ ====================== -->


</main>


<!-- ====================== FOOTER (Shared/footer.php) ====================== -->
<?php include __DIR__ . "/../Shared/footer.php"; ?>
<!-- ====================== END FOOTER ====================== -->


<!-- ====================== SCRIPT (Home/Home.js) ====================== -->
<script src="Home.js"></script>

</body>
</html>
<!-- ====================== END HTML ====================== -->
