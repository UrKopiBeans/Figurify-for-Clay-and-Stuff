<?php

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

require_once __DIR__ . "/../helpers/content_helper.php";
require_once __DIR__ . "/../helpers/collection_helper.php";

// db connection
$conn = new mysqli("localhost", "root", "", "figurify_db");

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// products come from the db now, not hardcoded
$collectionGroups = figurify_collection_get_grouped($conn);
$normalProducts = $collectionGroups["normal"];
$specialProducts = $collectionGroups["special"];

$customer_name = "";
$customer_email = "";
$profile_picture = "";

// get logged in customer, if any
if (isset($_SESSION["user_id"])) {

    $user_id = $_SESSION["user_id"];

    $stmt = $conn->prepare("
        SELECT full_name, email, profile_picture
        FROM users
        WHERE user_id = ?
    ");

    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        $customer_name = $user["full_name"];
        $customer_email = $user["email"];
        $profile_picture = $user["profile_picture"];
    }

    $stmt->close();
}

?>
<!doctype html>
<html lang="en">
<head>
    <?php /* [ALL-IN-ONE] font, background at pop up ng buong system - Shared/all-in-one.php */ include __DIR__ . "/../Shared/all-in-one.php"; ?>
    <link rel="icon" type="image/png" href="../Image/brand/logo.png">
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Clay and Stuff — Our Collection</title>


    <link rel="stylesheet" href="collection.css?v=8" />
</head>
<body>

<!-- navbar -->
<?php include "../Shared/navbar.php"; ?>

<header class="page-hero">
    <div class="page-wrap">
        <span class="page-eyebrow">✦ Crafted with love ✦</span>
        <h1>Made for You</h1>
        <p>Browse our ready-made figures, or get inspired before you commission your very own.</p>
    </div>
</header>

<main class="collection-page page-wrap">

    <!-- filters, search, sort -->
    <section class="filter-section">

        <div class="pill-row" id="tagFilters">
            <button type="button" class="pill active" data-filter="all">All Collections</button>
            <button type="button" class="pill" data-filter="bestseller">Best Sellers</button>
            <button type="button" class="pill" data-filter="chibi">Chibi</button>
            <button type="button" class="pill" data-filter="hirono">Hirono</button>
            <button type="button" class="pill" data-filter="funko">Funko Pop</button>
            <button type="button" class="pill" data-filter="custom">Custom Box</button>
        </div>

        <div class="filter-tools">

            <div class="search-box">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.5 4.5"/></svg>
                <input type="text" id="searchInput" placeholder="Search a figure...">
            </div>

            <div class="sort-dropdown" id="sortDropdown">
                <button class="sort-button" id="sortButton" type="button">
                    Sort By: <span id="sortLabel">Random</span>
                </button>

                <div class="sort-menu" id="sortMenu">
                    <button type="button" data-sort="random" class="active">Random</button>
                    <button type="button" data-sort="size-asc">Size: Small to Large</button>
                    <button type="button" data-sort="size-desc">Size: Large to Small</button>
                    <button type="button" data-sort="price-asc">Price: Low to High</button>
                    <button type="button" data-sort="price-desc">Price: High to Low</button>
                </div>
            </div>

        </div>

    </section>

    <!-- Normal Figures -->
    <section class="collection-block" id="normal">
        <div class="block-heading">
            <p class="eyebrow">♡ Our little clay family ♡</p>
            <h2>Normal Figures</h2>
            <p class="subtitle">Choose a figure and make it uniquely yours!</p>
        </div>

        <div class="card-grid">

            <?php foreach ($normalProducts as $product): ?>
                <?php figurify_collection_render_card($product, "../Image/figures/chibi/chibi-head-keychain-pair.jpg"); ?>
            <?php endforeach; ?>

            <?php if (empty($normalProducts)): ?>
                <p class="empty-state">Wala pang naka-listang produkto dito.</p>
            <?php endif; ?>

        </div>
    </section>

    <!-- Special Figures -->
    <section class="collection-block" id="special">
        <div class="block-heading">
            <p class="eyebrow">✦ There is no limit to imagination ✦</p>
            <h2>Special Figures</h2>
            <p class="subtitle">For the people, places, and moments you want to remember.</p>
        </div>

        <div class="card-grid">

            <?php foreach ($specialProducts as $product): ?>
                <?php figurify_collection_render_card($product, "../Image/figures/hirono/hirono-style.jpg"); ?>
            <?php endforeach; ?>

            <?php if (empty($specialProducts)): ?>
                <p class="empty-state">Wala pang naka-listang produkto dito.</p>
            <?php endif; ?>

        </div>
    </section>

    <p class="empty-state" id="emptyState" hidden>No figures match your search just yet — try another category</p>

</main>

<!-- footer -->
<?php include "../Shared/footer.php"; ?>

<script src="collection.js?v=2"></script>

</body>
</html>
