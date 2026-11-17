<?php
/* ==========================================================================
   TERMS AND CONDITIONS   (Legal/terms-and-conditions.php)
   Style: Legal/legal.css      Script: Legal/legal.js   (pareho sa ibang legal page)
   Ang laman ay ine-edit ng owner sa Content Management > Legal Pages (helpers/content_helper.php SECTION 5). Account, paggamit ng website at commission
   orders (payment, revision, shipping, refunds). Naka-link sa Sign Up at Payment page.

   LAMAN (taas pababa):
     [1] SETUP   session (para sa navbar)
     [2] HTML    navbar, page hero, Contents + sections, footer
   ========================================================================== */


/* ==========================================================================
   [1] SETUP
   ========================================================================== */
require_once __DIR__ . "/../helpers/security_helper.php";
require_once __DIR__ . "/../helpers/content_helper.php";
require_once __DIR__ . "/../Admin/database.php";
figurify_start_session();

/* laman ng page: galing sa Content Management (Legal Pages), default kung wala pang na-save */
$legal = figurify_legal_page(figurify_get_all_content($conn), "terms");
/* ====================== END SETUP ====================== */


/* ==========================================================================
   [2] HTML
   ========================================================================== */
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Clay and Stuff | Terms and Conditions</title>
    <link rel="icon" type="image/png" href="../Image/brand/logo.png">
    <?php include __DIR__ . "/../Shared/popup-and-security.php"; ?>
    <link rel="stylesheet" href="legal.css">
</head>

<body>

<!-- ====================== NAVBAR (Shared/navbar.php) ====================== -->
<?php include __DIR__ . "/../Shared/navbar.php"; ?>
<!-- ====================== END NAVBAR ====================== -->


<!-- ====================== PAGE HERO (css: legal.css [2]) ====================== -->
<header class="page-hero">
    <div class="page-wrap">
        <span class="page-eyebrow"><?php echo e($legal["eyebrow"]); ?></span>
        <h1><?php echo e($legal["title"]); ?></h1>
        <p><?php echo e($legal["subtitle"]); ?> Last updated: <?php echo date("M Y"); ?></p>
    </div>
</header>
<!-- ====================== END PAGE HERO ====================== -->

<!-- ====================== CONTENTS + SECTIONS (css: legal.css [3]-[5] | js: legal.js) ====================== -->
<main class="legal-page page-wrap">
    <div class="legal-layout">
        <aside class="legal-toc" aria-label="Contents">
            <p class="legal-toc-title">Contents</p>
            <nav>
                <?php foreach ($legal["sections"] as $i => $section): ?>
                    <a href="#<?php echo e($section["id"]); ?>"><?php echo ($i + 1) . ". " . e($section["title"]); ?></a>
                <?php endforeach; ?>
            </nav>
        </aside>
        <div class="legal-content">
            <?php if (trim($legal["intro"]) !== ""): ?>
                <div class="legal-intro"><?php echo figurify_legal_html($legal["intro"]); ?></div>
            <?php endif; ?>

            <?php foreach ($legal["sections"] as $i => $section): ?>
                <section class="legal-section" id="<?php echo e($section["id"]); ?>">
                    <h2><?php echo ($i + 1) . ". " . e($section["title"]); ?></h2>
                    <?php echo figurify_legal_html($section["body"]); ?>
                </section>
            <?php endforeach; ?>

            <div class="legal-note"><?php echo figurify_legal_html($legal["note"]); ?></div>

            <a class="legal-back-link" href="../Home/Home.php">Back to Home</a>
        </div>
    </div>
</main>
<!-- ====================== END CONTENTS + SECTIONS ====================== -->

<!-- ====================== FOOTER (Shared/footer.php) ====================== -->
<?php include __DIR__ . "/../Shared/footer.php"; ?>
<!-- ====================== END FOOTER ====================== -->

<!-- ====================== SCRIPT (Legal/legal.js) ====================== -->
<script src="legal.js"></script>

</body>
</html>
<!-- ====================== END HTML ====================== -->
