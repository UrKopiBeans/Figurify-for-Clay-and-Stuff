<?php
/* ==========================================================================
   COLLECTION PAGE   (Collection/collection.php)
   Style: Collection/collection.css      Script: Collection/collection.js
   Walang CSS o JS sa file na ito - PHP at HTML lang.

   LAMAN (taas pababa):
     [1] SETUP           helpers, session, database
     [2] FUNCTIONS       format_size()
     [3] DATA            mga natapos na figure (iisang listahan)
     [4] HTML            navbar, page hero, filters, collection,
                         empty search result, call to action, footer
   ========================================================================== */


/* ==========================================================================
   [1] SETUP
   - security_helper.php : figurify_start_session(), e()
   - content_helper.php  : figurify_collection_get_grouped(),
                           figurify_collection_build_filter_tag(),
                           figurify_collection_display_price(),
                           figurify_resolve_image_src()
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

// format_size() = gawing "3.5 inches" ang 3.5", 3.5in, o 3.5 in
function format_size($size)
{
    return trim(preg_replace('/\s*(?:"|”|″|in\.?|inches)\s*$/iu', " inches", trim($size)));
}
/* ====================== END FUNCTIONS ====================== */


/* ==========================================================================
   [3] DATA
   ========================================================================== */

// lahat ng natapos na figure sa database - iisang listahan na lang (dating "normal" + "special")
$collectionGroups = figurify_collection_get_grouped($conn);
$collectionItems  = array_merge($collectionGroups["normal"], $collectionGroups["special"]);

// mga pill sa taas: data-filter => text (binabasa ng collection.js)
$filterPills = [
    "all"        => "All Collections",
    "bestseller" => "★ Best Sellers",
    "chibi"      => "Chibi",
    "hirono"     => "Hirono",
    "funko"      => "Funko Pop",
    "custom"     => "Box & Add-ons",
];

// pagpipilian sa Sort By: data-sort => text (binabasa ng collection.js)
$sortOptions = [
    "random"     => "Random",
    "size-asc"   => "Size: Small to Large",
    "size-desc"  => "Size: Large to Small",
    "price-asc"  => "Price: Low to High",
    "price-desc" => "Price: High to Low",
];
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
    <title>Clay and Stuff | Our Collection</title>
    <link rel="icon" type="image/png" href="../Image/brand/logo.png">
    <?php include __DIR__ . "/../Shared/popup-and-security.php"; ?>
    <link rel="stylesheet" href="collection.css">
</head>

<body>


<!-- ====================== NAVBAR (Shared/navbar.php) ====================== -->
<?php include __DIR__ . "/../Shared/navbar.php"; ?>
<!-- ====================== END NAVBAR ====================== -->


<!-- ====================== PAGE HERO (css: collection.css [2]) ====================== -->
<header class="page-hero">
    <div class="page-hero-inner">
        <span class="page-eyebrow">Crafted with love</span>
        <h1>Made for You</h1>
        <p>Browse figures we have made for our customers and get ideas for your own.</p>
    </div>
</header>
<!-- ====================== END PAGE HERO ====================== -->


<main class="collection-page page-wrap">


    <!-- ====================== FILTERS, SEARCH, SORT (css: collection.css [3] | js: collection.js [3][4]) ====================== -->
    <section class="filter-section">

        <div class="pill-row" id="tagFilters">
            <?php foreach ($filterPills as $filter => $label): ?>
                <button type="button" class="pill<?php echo $filter === "all" ? " active" : ""; ?>" data-filter="<?php echo $filter; ?>"><?php echo $label; ?></button>
            <?php endforeach; ?>
        </div>

        <div class="filter-tools">

            <div class="sort-dropdown" id="sortDropdown">
                <button type="button" class="sort-button" id="sortButton">
                    Sort By: <span id="sortLabel">Random</span>
                </button>
                <div class="sort-menu" id="sortMenu">
                    <?php foreach ($sortOptions as $sort => $label): ?>
                        <button type="button" data-sort="<?php echo $sort; ?>"<?php echo $sort === "random" ? ' class="active"' : ""; ?>><?php echo $label; ?></button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- search: pinakadulo sa kanan, ang Sort By ay nasa kaliwa nito -->
            <div class="search-box">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.5 4.5"/></svg>
                <input type="text" id="searchInput" placeholder="Search a figure...">
            </div>

        </div>

    </section>
    <!-- ====================== END FILTERS, SEARCH, SORT ====================== -->


    <!-- ====================== COLLECTION: mga natapos na figure (css: collection.css [4][5]) ====================== -->
    <section class="collection-block" id="collection">

        <div class="block-heading">
            <p class="eyebrow">♡ Our little clay family ♡</p>
            <h2>Our Finished Figures</h2>
            <p class="subtitle">Figures we have already made for our customers.</p>
        </div>

        <div class="card-grid">

            <?php foreach ($collectionItems as $product): ?>
                <?php
                $image = figurify_resolve_image_src($product["image"] ?? "", "../Image/figures/chibi/chibi-head-keychain-pair.jpg");
                $title = $product["type"] !== "" ? $product["type"] : $product["style"];
                $size  = format_size($product["size"]);
                $price = figurify_collection_display_price($product["price"]);
                $tags  = figurify_collection_build_filter_tag($product);
                ?>
                <!-- isang card ng natapos na figure -->
                <article class="card" data-filter="<?php echo e($tags); ?>">

                    <div class="card-thumb">
                        <?php if (!empty($product["is_bestseller"])): ?>
                            <span class="badge">★ Best seller</span>
                        <?php endif; ?>
                        <img src="<?php echo e($image); ?>" alt="<?php echo e($title . ", " . $product["size"]); ?>">
                    </div>

                    <div class="card-body">
                        <?php if ($product["style"] !== ""): ?>
                            <span class="card-tag tag-<?php echo e($product["category"]); ?>"><?php echo e($product["style"]); ?></span>
                        <?php endif; ?>
                        <h3><?php echo e($title); ?></h3>
                        <div class="card-footer">
                            <span class="meta-chip"><?php echo $size !== "" ? e($size) : "N/A"; ?></span>
                            <span class="price"><?php echo e($price); ?></span>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>

            <?php if (empty($collectionItems)): ?>
                <p class="empty-state">No figures listed here yet.</p>
            <?php endif; ?>

        </div>

    </section>
    <!-- ====================== END COLLECTION ====================== -->


    <!-- ====================== EMPTY SEARCH RESULT (css: collection.css [6] | js: collection.js [3]) ====================== -->
    <p class="empty-state" id="emptyState" hidden>No figures match your search. Try another category or keyword.</p>
    <!-- ====================== END EMPTY SEARCH RESULT ====================== -->


    <!-- ====================== CALL TO ACTION (css: collection.css [7]) ====================== -->
    <section class="cta-box">
        <div>
            <h2>Can't find what you're looking for?</h2>
            <p>Send us your photos and we'll sculpt a figure made just for you.</p>
        </div>
        <a class="cta-button" href="../Commission/commission.php">Commission a figure</a>
    </section>
    <!-- ====================== END CALL TO ACTION ====================== -->


</main>


<!-- ====================== FOOTER (Shared/footer.php) ====================== -->
<?php include __DIR__ . "/../Shared/footer.php"; ?>
<!-- ====================== END FOOTER ====================== -->


<!-- ====================== SCRIPT (Collection/collection.js) ====================== -->
<script src="collection.js"></script>

</body>
</html>
<!-- ====================== END HTML ====================== -->
