<?php

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

/* DATABASE CONNECTION */
$conn = new mysqli("localhost", "root", "", "figurify_db");

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

require_once __DIR__ . "/../helpers/booking_helper.php";

$bookedDates = figurify_get_booked_dates($conn);

/* Rush/Non-Rush booking limits, ginagamit ng calendar sa commission.js
   para i-enforce ang daily/monthly caps ng Rush Order. */
$rushDayCounts        = figurify_get_rush_day_counts($conn);
$nonrushDateCounts    = figurify_get_nonrush_date_counts($conn);

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

<title>Clay and Stuff — Commission</title>

  <link rel="stylesheet" href="commission.css?v=88" />

</head>
<body>

<!-- navbar -->
<?php include "../Shared/navbar.php"; ?>

<!-- hero -->

<header class="hero">

    <div class="hero-content">

        <span class="hero-small">
            LET'S MAKE SOMETHING PERSONAL
        </span>

        <h1>
            Commission a Figure,
            <br>
            Made Just for You
        </h1>

        <p>
            Turn your ideas, memories, and favorite people
            into a little clay masterpiece.
        </p>

    </div>

</header>


<!-- main -->

<main class="page">


<!-- STEP 01 - order schedule -->

<section>

    <div class="section-header">


        <div>

            <h2>
                When Do You Need Your Figure?
            </h2>

            <p>
                Pick your order type and choose a booking date
                before deciding how you'd like to create your
                figure.
            </p>

        </div>

    </div>


    <div class="form-shell schedule-shell">

        <div class="schedule-center">

                <!-- RUSH / NON-RUSH -->

                <div class="order-type">


                    <!-- RUSH -->

                    <button
                        type="button"
                        class="order-card"
                        id="rushCard"
                        onclick="selectOrderType('rush')"
                    >

                        <div class="check">
                            ✓
                        </div>

                        <h4>
                            Rush Order
                        </h4>

                        <div class="order-fee">
                            + Rush Fee Per Figure
                        </div>

                    </button>


                    <!-- NON RUSH -->

                    <button
                        type="button"
                        class="order-card"
                        id="nonrushCard"
                        onclick="selectOrderType('nonrush')"
                    >

                        <div class="check">
                            ✓
                        </div>

                        <h4>
                            Non-Rush Order
                        </h4>

                        <div class="order-fee">
                            No additional fee
                        </div>

                    </button>

                </div>


                <!-- calendar -->

                <div class="calendar-box">

                    <div class="form-title">

                        <span>
                            BOOKING DATE
                        </span>

                        <h3>
                            Choose Your Date
                        </h3>

                    </div>


                    <div class="calendar-header">

                        <button
                            type="button"
                            onclick="changeMonth(-1)"
                            id="prevMonth"
                            class="calendar-arrow-btn"
                            aria-label="Previous month"
                        >
                            <svg class="calendar-arrow-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>

                        <h3 id="calendarTitle">
                            Calendar
                        </h3>

                        <button
                            type="button"
                            onclick="changeMonth(1)"
                            id="nextMonth"
                            class="calendar-arrow-btn"
                            aria-label="Next month"
                        >
                            <svg class="calendar-arrow-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>

                    </div>


                    <div class="weekdays">

                        <span>SUN</span>
                        <span>MON</span>
                        <span>TUE</span>
                        <span>WED</span>
                        <span>THU</span>
                        <span>FRI</span>
                        <span>SAT</span>

                    </div>


                    <div
                        class="days"
                        id="calendarDays"
                    ></div>


                    <!-- legend, part na ng calendar card mismo -->

                    <div class="calendar-legend">

                        <div class="calendar-legend-item">
                            <span class="calendar-legend-dot legend-available"></span>
                            <span>Available</span>
                        </div>

                        <div class="calendar-legend-item" id="legendBookedItem" style="display:none;">
                            <span class="calendar-legend-dot legend-booked"></span>
                            <span>Fully Booked</span>
                        </div>

                        <div class="calendar-legend-item">
                            <span class="calendar-legend-dot legend-unavailable"></span>
                            <span>Unavailable</span>
                        </div>

                        <div class="calendar-legend-item">
                            <span class="calendar-legend-dot legend-selected"></span>
                            <span>Selected</span>
                        </div>

                        <div class="calendar-legend-item">
                            <span class="calendar-legend-dot legend-today"></span>
                            <span>Today</span>
                        </div>

                    </div>

                </div>

                <!-- notes - static content, laging visible, hindi dependent sa Rush/Non-Rush -->

                <div
                    class="calendar-note"
                    id="calendarNote"
                >
                    <strong>Notes</strong>

                    <ul>
                        <li>
                            <strong>Rush Order</strong> — pick any available date at least
                            <strong>7 days</strong> from today. Rush fee: ₱200 per Head Only
                            Keychain, ₱500 per other figure.
                        </li>
                        <li>
                            <strong>Non-Rush Order</strong> — no extra fee. Only the
                            <strong>last day of the month</strong> can be picked, and you must
                            book at least 7 days before it.
                        </li>
                        <li>
                            Red dates are fully booked and gray dates are unavailable.
                        </li>
                        <li>
                            Your date is secured once the owner approves your
                            down payment (50%). Dates only become fully booked after approval.
                        </li>
                    </ul>
                </div>

            </div>

        </div>

</section>


<!-- STEP 02 -->

<section>

    <div class="section-header">


        <div>

            <h2>
                How Would You Like To Create It?
            </h2>

            <p>
                Choose how you want us to create your figure.
            </p>

        </div>

    </div>


    <div class="method-grid">


        <!-- IMAGE SUBMISSION -->

        <button
            type="button"
            class="method-card"
            id="referenceCard"
            onclick="selectMethod('reference')"
        >

            <div class="method-picture">
                <svg class="method-icon method-icon-3d" viewBox="0 0 96 96" aria-hidden="true">
                    <defs>
                        <linearGradient id="camBody" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0" stop-color="#ffb3d6"/>
                            <stop offset=".55" stop-color="#f06fa9"/>
                            <stop offset="1" stop-color="#c93f80"/>
                        </linearGradient>
                        <linearGradient id="camTop" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0" stop-color="#ffd3e7"/>
                            <stop offset="1" stop-color="#f08bb9"/>
                        </linearGradient>
                        <radialGradient id="camLensRing" cx=".4" cy=".35" r=".75">
                            <stop offset="0" stop-color="#ffffff"/>
                            <stop offset=".6" stop-color="#f3dcea"/>
                            <stop offset="1" stop-color="#c7a3b8"/>
                        </radialGradient>
                        <radialGradient id="camLens" cx=".38" cy=".32" r=".8">
                            <stop offset="0" stop-color="#8a6bd8"/>
                            <stop offset=".55" stop-color="#4b2a72"/>
                            <stop offset="1" stop-color="#21102f"/>
                        </radialGradient>
                        <radialGradient id="camShadow" cx=".5" cy=".5" r=".5">
                            <stop offset="0" stop-color="#9b3f72" stop-opacity=".35"/>
                            <stop offset="1" stop-color="#9b3f72" stop-opacity="0"/>
                        </radialGradient>
                    </defs>
                    <ellipse cx="48" cy="86" rx="32" ry="6" fill="url(#camShadow)"/>
                    <path d="M33 22c1-3.5 3.6-5 6.6-5h16.8c3 0 5.6 1.5 6.6 5l1.6 5H31.4z" fill="url(#camTop)"/>
                    <rect x="12" y="25" width="72" height="54" rx="16" fill="url(#camBody)"/>
                    <path d="M18 34c0-4.4 3.6-8 8-8h44c4.4 0 8 3.6 8 8" fill="none" stroke="#ffffff" stroke-opacity=".55" stroke-width="3" stroke-linecap="round"/>
                    <circle cx="48" cy="53" r="19" fill="url(#camLensRing)"/>
                    <circle cx="48" cy="53" r="13.5" fill="url(#camLens)"/>
                    <circle cx="48" cy="53" r="6" fill="#2b1540" opacity=".6"/>
                    <ellipse cx="43" cy="47.5" rx="4.6" ry="3.2" fill="#ffffff" opacity=".85" transform="rotate(-25 43 47.5)"/>
                    <circle cx="53" cy="58" r="1.6" fill="#ffffff" opacity=".5"/>
                    <rect x="68" y="32" width="9" height="6" rx="3" fill="#fff4b8"/>
                    <rect x="69.5" y="33" width="4" height="2" rx="1" fill="#ffffff" opacity=".8"/>
                </svg>
            </div>

            <div class="method-content">

                <span class="method-label">
                    STAFF QUOTATION
                </span>

                <h3>
                    Image Submission
                </h3>

                <p>
                    Upload your reference images and tell us
                    what you want us to create. Our staff will
                    review your request and provide the final
                    quotation. You may add multiple figures
                    to a single order.
                </p>

                <strong>
                    Build your custom order
                </strong>

            </div>

        </button>


        <!-- CREATE STYLE -->

        <button
            type="button"
            class="method-card"
            id="styleCard"
            onclick="openDressUp()"
        >

            <div class="method-picture">
                <svg class="method-icon method-icon-3d" viewBox="0 0 96 96" aria-hidden="true">
                    <defs>
                        <linearGradient id="dressBody" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0" stop-color="#ffb8da"/>
                            <stop offset=".5" stop-color="#f272ad"/>
                            <stop offset="1" stop-color="#c63b7d"/>
                        </linearGradient>
                        <linearGradient id="dressSkirt" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0" stop-color="#f784ba"/>
                            <stop offset="1" stop-color="#cf4688"/>
                        </linearGradient>
                        <linearGradient id="dressBelt" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0" stop-color="#d9c9ff"/>
                            <stop offset="1" stop-color="#9f86e6"/>
                        </linearGradient>
                        <radialGradient id="dressShadow" cx=".5" cy=".5" r=".5">
                            <stop offset="0" stop-color="#9b3f72" stop-opacity=".35"/>
                            <stop offset="1" stop-color="#9b3f72" stop-opacity="0"/>
                        </radialGradient>
                    </defs>
                    <ellipse cx="48" cy="87" rx="30" ry="6" fill="url(#dressShadow)"/>
                    <path d="M37 8c0-1.7 1.3-3 3-3s3 1.3 3 3v7h-6zM53 8c0-1.7 1.3-3 3-3s3 1.3 3 3v7h-6z" fill="#e45b9b"/>
                    <path d="M36 14c3 4.6 7 6.6 12 6.6S57 18.6 60 14l5 17-6 4H37l-6-4z" fill="url(#dressBody)"/>
                    <path d="M37 35h22l18 42c-8 4-18 6-29 6s-21-2-29-6z" fill="url(#dressSkirt)"/>
                    <path d="M28 70c6 3 13 4.6 20 4.6s14-1.6 20-4.6" fill="none" stroke="#ffffff" stroke-opacity=".35" stroke-width="2.4" stroke-linecap="round"/>
                    <rect x="35" y="32" width="26" height="8" rx="4" fill="url(#dressBelt)"/>
                    <circle cx="48" cy="36" r="4.2" fill="#fff4b8"/>
                    <circle cx="47" cy="35" r="1.4" fill="#ffffff"/>
                    <path d="M41 21c-1.6 3-2.4 6.5-2.6 9.5" fill="none" stroke="#ffffff" stroke-opacity=".7" stroke-width="3" stroke-linecap="round"/>
                    <path d="M33 50c-3 8-6 15-8 21" fill="none" stroke="#ffffff" stroke-opacity=".45" stroke-width="3.2" stroke-linecap="round"/>
                </svg>
            </div>

            <div class="method-content">

                <span class="method-label">
                    CREATE YOUR DESIGN
                </span>

                <h3>
                    Dress Up
                </h3>

                <p>
                    Choose a style and customize your own
                    figure using our design tools.
                </p>

                <strong>
                    Open Dress Up
                </strong>

            </div>

        </button>

    </div>

</section>


<!-- image submission form -->

<section
    class="order-form"
    id="customReferenceForm"
>

    <div class="section-header">

        <div class="section-icon">
            ♡
        </div>

        <div>

            <h2>
                Build Your Image Submission Order
            </h2>

            <p>
                Add one or more figures to your order using
                your reference images and notes.
            </p>

        </div>

    </div>


    <div class="form-shell">


        <div class="order-workspace">


            <!-- left side -->

            <div class="order-left">


                <!-- figure details (draft figure - add multiple) -->

                <div class="details-box">

                    <div class="details-box-heading">

                        <div class="details-box-heading-left">

                            <h3>
                                Figure Details
                            </h3>

                            <span
                                class="figure-index-badge"
                                id="figureIndexBadge"
                            >
                                FIGURE 1
                            </span>

                        </div>

                        <div class="figure-header-actions">

                            

                            <button
                                type="button"
                                class="clear-figure-button"
                                onclick="cancelEditFigure()"
                            >
                                Reset
                            </button>

                        </div>

                    </div>

                    <div
                        class="editing-note"
                        id="editingNote"
                    >
                        ✎ You are editing a figure already in
                        your order. Saving will update it in
                        place.
                    </div>


                    <!-- FIGURE STYLE -->

                    <div class="field-group">

                        <label>
                            Figure Style
                        </label>

                        <div class="option-grid">


                            <button
                                type="button"
                                class="image-option style-option"
                                data-style="Funko Pop"
                                onclick="selectFigureStyle(this)"
                            >

                                <span class="image-check">
                                    ✓
                                </span>

                                <img
                                    class="image-option-image"
                                    src="../Image/figures/covers/funko-pop-style-cover.jpg"
                                    alt="Funko Pop"
                                >

                                <span class="image-option-name">
                                    Funko Pop
                                </span>

                            </button>


                            <button
                                type="button"
                                class="image-option style-option"
                                data-style="Hirono"
                                onclick="selectFigureStyle(this)"
                            >

                                <span class="image-check">
                                    ✓
                                </span>

                                <img
                                    class="image-option-image"
                                    src="../Image/figures/covers/hirono-style-cover.jpg"
                                    alt="Hirono"
                                >

                                <span class="image-option-name">
                                    Hirono
                                </span>

                            </button>


                            <button
                                type="button"
                                class="image-option style-option"
                                data-style="Chibi"
                                onclick="selectFigureStyle(this)"
                            >

                                <span class="image-check">
                                    ✓
                                </span>

                                <img
                                    class="image-option-image"
                                    src="../Image/figures/covers/chibi-style-cover.jpg"
                                    alt="Chibi"
                                >

                                <span class="image-option-name">
                                    Chibi
                                </span>

                            </button>

                        </div>

                    </div>




                    <!-- PRODUCT TYPE -->

                    <div class="field-group" id="productTypeFieldGroup">

                        <label>
                            Product Type
                        </label>


                        <!-- CHIBI product type grid -->

                        <div
                            class="product-grid product-style-group"
                            data-style-group="Chibi"
                        >

                            <button
                                type="button"
                                class="image-option product-option"
                                data-product="Full Body Standee"
                                onclick="selectProductType(this)"
                            >

                                <span class="image-check">
                                    ✓
                                </span>

                                <img
                                    class="image-option-image"
                                    src="../Image/figures/covers/chibi-full-body-standee-cover.jpg"
                                    alt="Full Body Standee"
                                >

                                <span class="image-option-name">
                                    Full Body Standee
                                </span>

                            </button>


                            <button
                                type="button"
                                class="image-option product-option"
                                data-product="Full Body Keychain"
                                onclick="selectProductType(this)"
                            >

                                <span class="image-check">
                                    ✓
                                </span>

                                <img
                                    class="image-option-image"
                                    src="../Image/figures/covers/chibi-full-body-keychain-cover.jpg"
                                    alt="Full Body Keychain"
                                >

                                <span class="image-option-name">
                                    Full Body Keychain
                                </span>

                            </button>


                            <button
                                type="button"
                                class="image-option product-option"
                                data-product="Half Body Keychain"
                                onclick="selectProductType(this)"
                            >

                                <span class="image-check">
                                    ✓
                                </span>

                                <img
                                    class="image-option-image"
                                    src="../Image/figures/covers/chibi-half-body-keychain-cover.jpg"
                                    alt="Half Body Keychain"
                                >

                                <span class="image-option-name">
                                    Half Body Keychain
                                </span>

                            </button>


                            <button
                                type="button"
                                class="image-option product-option"
                                data-product="Head Only Keychain"
                                onclick="selectProductType(this)"
                            >

                                <span class="image-check">
                                    ✓
                                </span>

                                <img
                                    class="image-option-image"
                                    src="../Image/figures/covers/chibi-head-only-keychain-cover.jpg"
                                    alt="Head Only Keychain"
                                >

                                <span class="image-option-name">
                                    Head Only Keychain
                                </span>

                            </button>

                        </div>


                        <!-- FUNKO POP product type grid -->

                        <div
                            class="product-grid product-style-group"
                            data-style-group="Funko Pop"
                            style="display:none;"
                        >

                            <button
                                type="button"
                                class="image-option product-option"
                                data-product="Full Body Standee"
                                onclick="selectProductType(this)"
                            >

                                <span class="image-check">
                                    ✓
                                </span>

                                <img
                                    class="image-option-image"
                                    src="../Image/figures/covers/funko-pop-full-body-standee-cover.jpg"
                                    alt="Full Body Standee"
                                >

                                <span class="image-option-name">
                                    Full Body Standee
                                </span>

                            </button>


                            <button
                                type="button"
                                class="image-option product-option"
                                data-product="Full Body Keychain"
                                onclick="selectProductType(this)"
                            >

                                <span class="image-check">
                                    ✓
                                </span>

                                <img
                                    class="image-option-image"
                                    src="../Image/figures/funko-pop/funko-pop-full-body-keychain.jpg"
                                    alt="Full Body Keychain"
                                >

                                <span class="image-option-name">
                                    Full Body Keychain
                                </span>

                            </button>


                            <button
                                type="button"
                                class="image-option product-option"
                                data-product="Half Body Keychain"
                                onclick="selectProductType(this)"
                            >

                                <span class="image-check">
                                    ✓
                                </span>

                                <img
                                    class="image-option-image"
                                    src="../Image/figures/funko-pop/funko-pop-half-body-keychain.jpg"
                                    alt="Half Body Keychain"
                                >

                                <span class="image-option-name">
                                    Half Body Keychain
                                </span>

                            </button>


                            <button
                                type="button"
                                class="image-option product-option"
                                data-product="Head Only Keychain"
                                onclick="selectProductType(this)"
                            >

                                <span class="image-check">
                                    ✓
                                </span>

                                <img
                                    class="image-option-image"
                                    src="../Image/figures/funko-pop/funko-pop-head-only-keychain.jpg"
                                    alt="Head Only Keychain"
                                >

                                <span class="image-option-name">
                                    Head Only Keychain
                                </span>

                            </button>

                        </div>


                        <!-- HIRONO product type grid -->

                        <div
                            class="product-grid product-style-group"
                            data-style-group="Hirono"
                            style="display:none;"
                        >

                            <button
                                type="button"
                                class="image-option product-option"
                                data-product="Full Body Standee"
                                onclick="selectProductType(this)"
                            >

                                <span class="image-check">
                                    ✓
                                </span>

                                <img
                                    class="image-option-image"
                                    src="../Image/figures/covers/hirono-full-body-standee-cover.jpg"
                                    alt="Full Body Standee"
                                >

                                <span class="image-option-name">
                                    Full Body Standee
                                </span>

                            </button>


                            <button
                                type="button"
                                class="image-option product-option"
                                data-product="Full Body Keychain"
                                onclick="selectProductType(this)"
                            >

                                <span class="image-check">
                                    ✓
                                </span>

                                <img
                                    class="image-option-image"
                                    src="../Image/figures/covers/hirono-full-body-keychain-cover.jpg"
                                    alt="Full Body Keychain"
                                >

                                <span class="image-option-name">
                                    Full Body Keychain
                                </span>

                            </button>


                            <button
                                type="button"
                                class="image-option product-option"
                                data-product="Half Body Keychain"
                                onclick="selectProductType(this)"
                            >

                                <span class="image-check">
                                    ✓
                                </span>

                                <img
                                    class="image-option-image"
                                    src="../Image/figures/covers/hirono-half-body-keychain-cover.jpg"
                                    alt="Half Body Keychain"
                                >

                                <span class="image-option-name">
                                    Half Body Keychain
                                </span>

                            </button>


                            <button
                                type="button"
                                class="image-option product-option"
                                data-product="Head Only Keychain"
                                onclick="selectProductType(this)"
                            >

                                <span class="image-check">
                                    ✓
                                </span>

                                <img
                                    class="image-option-image"
                                    src="../Image/figures/covers/hirono-head-only-keychain-cover.jpg"
                                    alt="Head Only Keychain"
                                >

                                <span class="image-option-name">
                                    Head Only Keychain
                                </span>

                            </button>

                        </div>

                    </div>


                    <!-- SIZE -->

                    <div class="field-group" id="sizeFieldGroup">

                        <label>
                            Size
                        </label>

                        <div
                            class="size-grid"
                            id="sizeGrid"
                        >

                            <button
                                type="button"
                                class="size-option"
                                data-size="2"
                                onclick="selectSize(this)"
                            >
                                2"
                            </button>

                            <button
                                type="button"
                                class="size-option"
                                data-size="3"
                                onclick="selectSize(this)"
                            >
                                3"
                            </button>

                            <button
                                type="button"
                                class="size-option"
                                data-size="3.5"
                                onclick="selectSize(this)"
                            >
                                3.5"
                            </button>

                            <button
                                type="button"
                                class="size-option"
                                data-size="4"
                                onclick="selectSize(this)"
                            >
                                4"
                            </button>

                            <button
                                type="button"
                                class="size-option"
                                data-size="5"
                                onclick="selectSize(this)"
                            >
                                5"
                            </button>

                        </div>


                        <div
                            class="no-size-message"
                            id="noSizeMessage"
                        >
                            ✓ No size required for this product.
                        </div>

                    </div>


                    <!-- FIGURE NAME -->

                    <div class="field-group">

                        <label for="figureName">
                            Figure Name — ₱50
                        </label>

                        <input
                            class="field figure-name-field"
                            type="text"
                            id="figureName"
                            maxlength="50"
                            placeholder="Enter your Figure name"
                            oninput="updateFigureName()"
                        >

                    </div>

                    <!-- REFERENCE IMAGES -->

                    <div class="field-group">

                        <label>
                            Reference Images
                            <span class="upload-limit-inline" id="uploadLimitText">
                                (Maximum 5 images)
                            </span>
                            <span class="image-count-badge" id="imageCount">0 / 5</span>
                        </label>

                        <div class="image-upload-area">

                            <div
                                class="upload-dropzone"
                                id="uploadDropzone"
                            >

                                <input
                                    type="file"
                                    id="referenceImages"
                                    class="upload-input"
                                    accept="image/*"
                                    multiple
                                >


                                <!-- buong box na ang clickable/droppable para sa file picker, kaya wala nang hiwalay na "+ Add Images" button -->


                                <div class="image-preview-wrap">

                                    <div
                                        class="upload-subtitle"
                                        id="uploadDragText"
                                    >
                                        Drag and drop your images here
                                    </div>

                                    <div
                                        class="image-preview-grid"
                                        id="imagePreviewGrid"
                                    ></div>

                                </div>

                            </div>

                        </div>

                    </div>


                    <!-- NOTES -->

                    <div class="field-group notes-box">

                        <label for="notes">
                            Notes
                        </label>

                        <textarea
                            id="notes"
                            placeholder="Please include any important details about your requested figure."
                        ></textarea>

                    </div>

                </div>
                <!-- /.details-box (figure info) -->


                <!-- box/add-ons container, shows Funko Box or Hirono Blind section depende sa style -->

                <div class="box-addon-box">

                    <div class="box-addon-box-heading">

                        <h3>
                            Box / Add-ons
                        </h3>

                    </div>

                    <!-- custom funko box, shows only if style = Funko Pop -->

                    <div
                        class="field-group style-addon-section"
                        id="funkoBoxSection"
                    >

                        <label>
                            Custom Funko Box
                        </label>

                        <p class="addon-hint">
                            Funko Pop figures come with a custom
                            box. Choose Solo or Couple Box, and
                            fill in the box details below.
                        </p>

                        <div class="box-type-grid">

                            <button
                                type="button"
                                class="box-type-option"
                                data-box-type="solo"
                                data-box-price="500"
                                onclick="selectBoxType(this)"
                            >
                                <span class="image-check">✓</span>
                                <img
                                    class="box-type-image"
                                    src="../Image/boxes/funko-solo-box.jpg"
                                    alt="Solo Box"
                                    style="background:linear-gradient(145deg,#ffe0ef,#f6c6de);"
                                    onerror="this.onerror=null;this.removeAttribute('src');this.alt='';"
                                >
                                <div class="box-type-info">
                                    <span class="box-type-name">Solo Box</span>
                                    <span class="box-type-price">₱500</span>
                                </div>
                            </button>

                            <button
                                type="button"
                                class="box-type-option"
                                data-box-type="couple"
                                data-box-price="800"
                                onclick="selectBoxType(this)"
                            >
                                <span class="image-check">✓</span>
                                <img
                                    class="box-type-image"
                                    src="../Image/boxes/funko-couple-box.jpg"
                                    alt="Couple Box"
                                    style="background:linear-gradient(145deg,#e8ddff,#d5c4f7);"
                                    onerror="this.onerror=null;this.removeAttribute('src');this.alt='';"
                                >
                                <div class="box-type-info">
                                    <span class="box-type-name">Couple Box</span>
                                    <span class="box-type-price">₱800</span>
                                </div>
                            </button>

                        </div>

                        <div
                            class="box-locked-message hide"
                            id="boxLockedMessage"
                        >
                            Please select a box type above to
                            unlock the box details form.
                        </div>

                        <div
                            class="box-detail-fields show"
                            id="funkoBoxFields"
                        >

                            <div class="field-group">
                                <label for="funkoBoxName">Name (to print on box) <span class="optional-tag">(optional)</span></label>
                                <input
                                    class="field"
                                    type="text"
                                    id="funkoBoxName"
                                    maxlength="50"
                                    placeholder="Example: Omar"
                                    oninput="updateAllSummary()"
                                >
                            </div>

                            <div class="field-group">
                                <label for="funkoBoxNumber">Box Number <span class="optional-tag">(optional)</span></label>
                                <input
                                    class="field"
                                    type="text"
                                    id="funkoBoxNumber"
                                    maxlength="20"
                                    placeholder="Example: 001"
                                    oninput="updateAllSummary()"
                                >
                            </div>

                            <div class="field-group">
                                <label for="funkoBoxColor">Box Color <span class="optional-tag">(optional)</span></label>
                                <input
                                    class="field"
                                    type="text"
                                    id="funkoBoxColor"
                                    maxlength="30"
                                    placeholder="Example: Pastel Pink"
                                    oninput="updateAllSummary()"
                                >
                            </div>

                        </div>

                    </div>


                    <!-- hirono blind box, shows only if style = Hirono -->

                    <div
                        class="field-group style-addon-section"
                        id="hironoBlindSection"
                    >

                        <label>
                            Blind Box Type
                        </label>

                        <p class="addon-hint">
                            Hirono figures are offered as a blind
                            box. Choose Regular or Set, then fill
                            in the details and any optional extras
                            below.
                        </p>

                        <div class="box-type-grid">

                            <button
                                type="button"
                                class="box-type-option"
                                data-blind-type="regular"
                                data-blind-price="150"
                                onclick="selectBlindType(this)"
                            >
                                <span class="image-check">✓</span>
                                <img
                                    class="box-type-image"
                                    src="../Image/boxes/hirono-box-peek.jpg"
                                    alt="Regular Blind Box"
                                >
                                <div class="box-type-info">
                                    <span class="box-type-name">Regular Blind Box</span>
                                    <span class="box-type-price">₱150</span>
                                </div>
                            </button>

                            <button
                                type="button"
                                class="box-type-option"
                                data-blind-type="set"
                                data-blind-price="350"
                                onclick="selectBlindType(this)"
                            >
                                <span class="image-check">✓</span>
                                <img
                                    class="box-type-image"
                                    src="../Image/boxes/blind-box-set.jpg"
                                    alt="Blind Box Set"
                                >
                                <div class="box-type-info">
                                    <span class="box-type-name">Blind Box Set</span>
                                    <span class="box-type-price">₱350</span>
                                    <span class="box-type-note">Includes: Tear Blind Paper &amp; Digital Art only</span>
                                </div>
                            </button>

                        </div>

                        <!-- optional extras: individually selectable, hindi na naka-tie sa Regular vs Set,
                             customer picks kung ano lang gusto niya at may sariling add-on price bawat isa -->

                        <div
                            class="blind-set-contents show"
                            id="blindSetContents"
                        >

                            <div class="blind-set-title" id="blindSetTitle">
                                Optional Extras (Tap To Add)
                            </div>

                            <div class="blind-set-grid">

                                <button
                                    type="button"
                                    class="blind-set-item"
                                    data-blind-item="tear_blind_paper"
                                    data-blind-item-price="50"
                                    onclick="selectBlindSetItem(this)"
                                >
                                    <span class="image-check">✓</span>
                                    <img
                                        src="../Image/boxes/tear-blind-paper.jpg"
                                        alt="Tear Blind Paper"
                                    >
                                    <span class="blind-set-item-name">Tear Blind Paper</span>
                                    <span class="blind-set-item-price">₱50</span>
                                </button>

                                <button
                                    type="button"
                                    class="blind-set-item"
                                    data-blind-item="pouch"
                                    data-blind-item-price="120"
                                    onclick="selectBlindSetItem(this)"
                                >
                                    <span class="image-check">✓</span>
                                    <img
                                        src="../Image/boxes/pouch.jpg"
                                        alt="Pouch"
                                    >
                                    <span class="blind-set-item-name">Pouch</span>
                                    <span class="blind-set-item-price">₱120</span>
                                </button>

                                <button
                                    type="button"
                                    class="blind-set-item"
                                    data-blind-item="digital_art"
                                    data-blind-item-price="150"
                                    onclick="selectBlindSetItem(this)"
                                >
                                    <span class="image-check">✓</span>
                                    <img
                                        src="../Image/boxes/photocard.jpg"
                                        alt="Digital Art w/ Photo Card"
                                    >
                                    <span class="blind-set-item-name">Digital Art w/ Photo Card</span>
                                    <span class="blind-set-item-price">₱150</span>
                                </button>

                            </div>

                        </div>


                        <div
                            class="box-detail-fields"
                            id="hironoBlindFields"
                        >

                            <div class="field-group" style="grid-column:1 / -1;">

                                <label>Box Design</label>

                                <p class="addon-hint">
                                    Choose a box design for your
                                    Blind Box.
                                </p>

                                <div class="box-type-grid">

                                    <button
                                        type="button"
                                        class="box-type-option"
                                        data-box-design="checkered"
                                        onclick="selectBoxDesign(this)"
                                    >
                                        <span class="image-check">✓</span>
                                        <img
                                            class="box-type-image"
                                            src="../Image/boxes/hirono-box-checkered.jpg"
                                            alt="Checkered"
                                        >
                                        <div class="box-type-info">
                                            <span class="box-type-name">Checkered</span>
                                        </div>
                                    </button>

                                    <button
                                        type="button"
                                        class="box-type-option"
                                        data-box-design="hirono_peek"
                                        onclick="selectBoxDesign(this)"
                                    >
                                        <span class="image-check">✓</span>
                                        <img
                                            class="box-type-image"
                                            src="../Image/boxes/hirono-box-peek.jpg"
                                            alt="Hirono Peek"
                                        >
                                        <div class="box-type-info">
                                            <span class="box-type-name">Hirono Peek</span>
                                        </div>
                                    </button>

                                </div>

                            </div>

                            <div class="field-group" id="hironoBoxColorGroup">
                                <label for="hironoBoxColor">Box Color</label>
                                <div id="hironoBoxColorWrap">
                                    <input
                                        class="field"
                                        type="text"
                                        id="hironoBoxColor"
                                        maxlength="30"
                                        placeholder="Example: Cream White"
                                        oninput="updateAllSummary()"
                                    >
                                </div>
                            </div>

                            <div class="field-group">
                                <label for="hironoNickname">Nickname</label>
                                <input
                                    class="field"
                                    type="text"
                                    id="hironoNickname"
                                    maxlength="50"
                                    placeholder="Example: Bubbles"
                                    oninput="updateAllSummary()"
                                >
                            </div>

                            <div class="field-group" style="grid-column:1 / -1;">
                                <label for="hironoLetter">Letter (Short Love Letter)</label>
                                <textarea
                                    class="field"
                                    id="hironoLetter"
                                    maxlength="300"
                                    rows="4"
                                    placeholder="Write a short love letter/message here..."
                                    oninput="updateAllSummary()"
                                ></textarea>
                            </div>

                            <div class="field-group">
                                <label for="hironoDateMonth">Date (Month)</label>
                                <select
                                    class="field"
                                    id="hironoDateMonth"
                                    onchange="updateAllSummary()"
                                >
                                    <option value="">Month</option>
                                    <option value="01">January</option>
                                    <option value="02">February</option>
                                    <option value="03">March</option>
                                    <option value="04">April</option>
                                    <option value="05">May</option>
                                    <option value="06">June</option>
                                    <option value="07">July</option>
                                    <option value="08">August</option>
                                    <option value="09">September</option>
                                    <option value="10">October</option>
                                    <option value="11">November</option>
                                    <option value="12">December</option>
                                </select>
                            </div>

                            <div class="field-group">
                                <label for="hironoDateDay">Date (Day)</label>
                                <select
                                    class="field"
                                    id="hironoDateDay"
                                    onchange="updateAllSummary()"
                                >
                                    <option value="">Day</option>
                                    <?php for($d = 1; $d <= 31; $d++): ?>
                                    <option value="<?php echo str_pad($d, 2, "0", STR_PAD_LEFT); ?>">
                                        <?php echo $d; ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </div>


                            <!-- BOX REFERENCE IMAGES
                                 Limit depende sa napiling Box Design:
                                 Checkered -> 5 images
                                 Hirono Peek -> 3 images -->

                            <div class="field-group" style="grid-column:1 / -1;">

                                <label>
                                    Box Reference Images
                                    <span class="upload-limit-inline" id="hironoImageLimitText">
                                        (Select a Box Design first)
                                    </span>
                                    <span class="image-count-badge" id="hironoImageCount">0 / 0</span>
                                </label>

                                <!-- kapareho ng Reference Images sa Figure Details: buong box ang
                                     clickable at pwedeng i-drag & drop, walang hiwalay na "+ Add Images" button -->

                                <div class="image-upload-area">

                                    <div
                                        class="upload-dropzone"
                                        id="hironoImageDropzone"
                                    >

                                        <input
                                            type="file"
                                            id="hironoImages"
                                            class="upload-input"
                                            accept="image/*"
                                            multiple
                                        >



                                        <div class="image-preview-wrap">

                                            <div
                                                class="upload-subtitle"
                                                id="hironoUploadDragText"
                                            >
                                                Select a Box Design above first
                                            </div>

                                            <div
                                                class="image-preview-grid"
                                                id="hironoImagePreviewGrid"
                                            ></div>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>
                <!-- /.box-addon-box -->

            </div>


            <!-- right side - order summary -->

            <aside class="summary-column">

                <div class="receipt">


                    <div class="receipt-header">

                        <h3>
                            Order Summary
                        </h3>


                    </div>


                    


                    <!-- ORDER TYPE -->

                    <div class="selected-order-box">

                        <div class="order-type-summary">

                            <div class="order-type-summary-left">

                                <div class="selected-order-label">
                                    ORDER TYPE
                                </div>

                                <div
                                    class="selected-order-value"
                                    id="summaryOrderType"
                                >
                                    Not selected
                                </div>

                            </div>

                            <div
                                class="order-type-price"
                                id="orderTypePrice"
                            >
                                0
                            </div>

                        </div>

                    </div>


                    <!-- BOOKING DATE -->

                    <div class="selected-order-box">

                        <div class="selected-order-label">
                            BOOKING DATE
                        </div>

                        <div
                            class="selected-order-value"
                            id="summaryBookingDate"
                        >
                            Not selected
                        </div>

                        <div
                            class="selected-order-date"
                            id="summaryBookingStatus"
                        >
                            Choose a date from the calendar
                        </div>

                    </div>


                    <!-- figures in this order (multi-figure cart) -->

                    <div class="figure-cart-box">

                        <div class="figure-cart-title">

                            <span>
                                FIGURES IN THIS ORDER
                            </span>

                            <button type="button" class="add-figure-button cart-add-btn" id="addFigureButton" onclick="addFigureToCart()">
                                + Add Another Order
                            </button>

                        </div>


                        <div id="figureCartList"></div>


                        <div
                            class="cart-empty"
                            id="cartEmptyMessage"
                        >
                            No figures added yet.
                        </div>


                        <div class="cart-subtotal-row">

                            <span>
                                Figures Subtotal
                            </span>

                            <span id="cartSubtotalPrice">
                                0
                            </span>

                        </div>

                    </div>


                    <!-- CURRENT DRAFT FIGURE (NOT YET ADDED) -->

                    <div class="figure-summary-box">

                        <div class="figure-summary-title">
                            CURRENT FIGURE (UNSAVED)
                        </div>


                        <!-- FIGURE STYLE -->

                        <div class="figure-summary-item summary-no-price">

                            <div class="figure-summary-info">

                                <div
                                    class="figure-summary-name"
                                    id="summaryStyle"
                                >
                                    
                                </div>

                                <div class="figure-summary-description">
                                    Figure Style
                                </div>

                            </div>

                            <div class="figure-summary-price">
                                0
                            </div>

                        </div>


                        <!-- PRODUCT TYPE -->

                        <div class="figure-summary-item">

                            <div class="figure-summary-info">

                                <div
                                    class="figure-summary-name"
                                    id="summaryProduct"
                                >
                                    
                                </div>

                                <div class="figure-summary-description">
                                    Product Type
                                </div>

                            </div>

                            <div
                                class="figure-summary-price"
                                id="productPrice"
                            >
                                
                            </div>

                        </div>


                        <!-- SIZE -->

                        <div class="figure-summary-item">

                            <div class="figure-summary-info">

                                <div
                                    class="figure-summary-name"
                                    id="summarySize"
                                >
                                    
                                </div>

                                <div class="figure-summary-description">
                                    Size
                                </div>

                            </div>

                            <div
                                class="figure-summary-price"
                                id="sizePrice"
                            >
                                
                            </div>

                        </div>


                        <!-- FIGURE NAME - kapareho ng pattern ng Style/Product/Size rows sa taas -->

                        <div class="figure-summary-item">

                            <div class="figure-summary-info">

                                <div
                                    class="figure-summary-name"
                                    id="summaryFigureName"
                                >
                                    
                                </div>

                                <div class="figure-summary-description">
                                    Figure Name
                                </div>

                            </div>

                            <div
                                class="figure-summary-price"
                                id="nameFee"
                            >
                                
                            </div>

                        </div>


                        <!-- CUSTOM BOX / BLIND BOX ADD-ON -->

                        <div
                            class="figure-name-summary box-addon-summary-row"
                            id="boxAddonSummaryRow"
                            style="margin-top:6px;"
                        >

                            <span id="boxAddonLabel">
                                Box Add-on
                            </span>

                            <strong id="boxAddonFee">
                                0
                            </strong>

                        </div>


                        <!-- box add-on sub-details: selected extras + box design, one per line -->

                        <div
                            class="box-addon-sub-details"
                            id="boxAddonSubDetails"
                        ></div>

                    </div>


                    <!-- TOTAL -->

                    <div class="summary-total">

                        <span>
                            Estimated Total
                        </span>

                        <strong id="totalPrice">
                            0
                        </strong>

                    </div>

                    <!-- SUBMIT (inside the summary card) -->
                    <div class="submit-area">
                        <button
                            type="button"
                            class="submit-button"
                            onclick="submitOrder()"
                        >
                            SUBMIT ORDER
                        </button>
                    </div>

                </div>

            </aside>

        </div>


</div>

</section>


<!-- Dress Up ay may sarili nang page: dress-up.php (pinipili dito ang Order Type at Booking Date, tapos click ang Dress Up card) -->



</main>


<!-- image modal -->

<div
    class="image-modal"
    id="imageModal"
    onclick="closeImageModal(event)"
>

    <div class="modal-content">

        <button
            type="button"
            class="modal-close"
            onclick="closeImageModal()"
        >
            ×
        </button>

        <img
            id="modalImage"
            src=""
            alt="Large reference preview"
        >

        <div
            class="modal-label"
            id="modalLabel"
        >
            Reference Image
        </div>

    </div>

</div>

<script>
    const figurifyBookedDates = <?php echo json_encode($bookedDates); ?>;

    /* Rush: { "YYYY-MM-DD": count } - para sa daily/monthly rush cap (JS-computed) */
    const figurifyRushDayCounts = <?php echo json_encode($rushDayCounts); ?>;

    /* Non-Rush: { "YYYY-MM-DD": count } - para sa 15-order-per-date cap */
    const figurifyNonrushDateCounts = <?php echo json_encode($nonrushDateCounts); ?>;

    const FIGURIFY_RUSH_DAILY_CAP    = <?php echo (int) FIGURIFY_RUSH_DAILY_CAP; ?>;
    const FIGURIFY_RUSH_MONTHLY_CAP  = <?php echo (int) FIGURIFY_RUSH_MONTHLY_CAP; ?>;
    const FIGURIFY_NONRUSH_DATE_CAP  = <?php echo (int) FIGURIFY_NONRUSH_DATE_CAP; ?>;

    /* para sa "Review Your Order" summary (customer information) */
    const figurifyCustomerInfo = <?php echo json_encode(["name" => (string) $customer_name, "email" => (string) $customer_email], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>

<script src="commission.js?v=20"></script>




<!-- footer -->

<?php include "../Shared/footer.php"; ?>
</body>
</html>
