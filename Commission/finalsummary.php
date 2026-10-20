<?php

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

/* DATABASE CONNECTION - iisang config (Admin/database.php), hindi na hard-coded dito */
require_once __DIR__ . "/../Admin/database.php";

/* DEFAULT CUSTOMER INFORMATION */
$customer_name = "";
$customer_email = "";
$profile_picture = "";

/* GET LOGGED-IN CUSTOMER */
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

<!DOCTYPE html>
<html lang="en">
<head>
    <?php /* [ALL-IN-ONE] font, background at pop up ng buong system - Shared/all-in-one.php */ include __DIR__ . "/../Shared/all-in-one.php"; ?>
    <link rel="icon" type="image/png" href="../Image/brand/logo.png">

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Clay and Stuff — Final Order Summary</title>

  <link rel="stylesheet" href="commission.css?v=87" />
  <link rel="stylesheet" href="dress-up.css?v=45" />

</head>
<body data-page="finalSummary">

<!-- navbar -->

<?php include "../Shared/navbar.php"; ?>


<!-- hero (kaparehong style ng Commission / Collection hero) -->

<header class="hero final-summary-hero">

    <div class="hero-content">

        <span class="hero-small">
            &#10022; ALMOST THERE &#10022;
        </span>

        <h1>
            Your Final Summary
        </h1>

        <p>
            Take one last look at your figure before you submit your order.
        </p>

    </div>

</header>


<!-- final order summary, reuses #dressUpModule CSS/JS from commission.php (see data-page) -->

<main>

    <div id="dressUpModule">

        <!-- isang container para sa lahat ng laman ng final summary -->

        <section class="final-summary-shell">

            <div class="final-summary-layout">

                <!-- 3D figure (left) -->

                <div class="final-summary-figure">

                    <div class="preview-header">
                        <h2>Your Figure</h2>
                    </div>

                    <div class="figure-area">

                        <canvas
                            id="figureViewer"
                            class="figure-viewer"
                        ></canvas>

                        

                    </div>

                </div>

                <!-- figure details (right) -->

                <div class="final-summary-details">

                    <div class="receipt-header">
                        <h3>Figure Details <span class="figure-index-badge" id="dressUpCurrentFigureBadge">FIGURE 1</span></h3>
                    </div>

                    <!-- ORDER TYPE + BOOKING DATE (magkatabi) -->

                    <div class="final-summary-meta">

                        <div class="selected-order-box">
                            <div class="order-type-summary">
                                <div class="order-type-summary-left">
                                    <div class="selected-order-label">ORDER TYPE</div>
                                    <div class="selected-order-value" id="dressUpSummaryOrderType">Not selected</div>
                                </div>
                                <div class="order-type-price" id="dressUpSummaryOrderTypePrice">&#8369;0</div>
                            </div>
                        </div>

                        <div class="selected-order-box">
                            <div class="selected-order-label">BOOKING DATE</div>
                            <div class="selected-order-value" id="dressUpSummaryBookingDate">Not selected</div>
                            <div class="selected-order-date" id="dressUpSummaryBookingStatus">
                                Choose a date from the calendar
                            </div>
                        </div>

                    </div>

                    <!-- FIGURE DETAILS + CUSTOMIZATION PICKS (grid, magkakatabi) -->

                    <div class="figure-summary-box">

                        <div class="figure-summary-title">YOUR FIGURE</div>

                        <div id="summaryReceiptItems">
                            <div class="receipt-empty">No customization selected yet.</div>
                        </div>

                    </div>

                    <!-- TOTAL (nasa ibaba ng Figure Details) -->

                    <div class="summary-total final-summary-total">
                        <span>Total Price</span>
                        <strong id="dressUpTotalPrice">&#8369;0</strong>
                    </div>

                </div>

                <!-- custom box / add-ons (katabi ng Figure Details) -->

                <div class="final-summary-box">

                    <div class="receipt-header">
                        <h3>Custom Box &amp; Add-ons</h3>
                    </div>

                    <div id="summaryBoxItems">
                        <div class="receipt-empty">No box selected yet.</div>
                    </div>

                </div>

                <!-- other figures in this order (right) -->

                <div class="final-summary-cart" id="dressUpCartSection">

                    <div class="receipt-header">
                        <h3>FIGURES IN THIS ORDER</h3>
                    </div>

                    <div id="dressUpCartList"></div>

                    <!-- + Add Another Order: nasa loob na ng "Other Figures in This Order" -->
                    <button
                        type="button"
                        id="finalSummaryAddFigureBtn"
                        class="final-summary-add-btn final-summary-add-btn-wide"
                    >
                        + Add Another Order
                    </button>

                </div>

            </div>

            <!-- BACK (EDIT) / SUBMIT ORDER -->

            <div class="design-step-navigation final-summary-navigation">
                <a
                    href="dress-up.php?resume=1"
                    class="step-navigation-button final-summary-back-btn"
                    id="finalSummaryBackBtn"
                >
                    Back
                </a>
                <a
                    href="#payment"
                    class="continue-btn step-navigation-button"
                    id="finalSummaryContinueBtn"
                >
                    Proceed to Payment
                </a>
            </div>

        </section>

    </div>

</main>


<!-- dress up 3D engine, same setup as commission.php. commission.js not needed, reads lang from localStorage dito -->

<script type="importmap">
{
    "imports": {
        "three": "https://unpkg.com/three@0.160.0/build/three.module.js",
        "three/addons/": "https://unpkg.com/three@0.160.0/examples/jsm/"
    }
}
</script>

<script type="module" src="dress-up.js?v=97"></script>

<!-- footer -->

<?php include "../Shared/footer.php"; ?>
</body>
</html>
