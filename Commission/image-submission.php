<?php
/* ==========================================================================
   IMAGE SUBMISSION PAGE   (Commission/image-submission.php)
   Style: Commission/image-submission.css      Script: Commission/image-submission.js
   Hinango sa commission.php - dito na ginagawa ang Image Submission order
   (figure details, reference images, order summary).
   Ang Order Type at Ship Out Date ay pinili sa Commission/commission.php
   (naka-save sa sessionStorage, binabasa ng image-submission.js).
   Isinusumite ang order sa Commission/submit_order.php (fetch ng image-submission.js).

   LAMAN (taas pababa):
     [1] SETUP + DATA   helpers, session, database, info ng customer
     [2] HTML           navbar, STEP 03 form + Your Order panel,
                        image modal, footer
     [3] SCRIPT         data galing sa PHP, tapos image-submission.js
   ========================================================================== */


/* ==========================================================================
   [1] SETUP + DATA
   ========================================================================== */
require_once __DIR__ . "/../helpers/security_helper.php";
figurify_start_session();

require_once __DIR__ . "/../Admin/database.php";   // $conn (iisang koneksyon ng buong site)
require_once __DIR__ . "/../helpers/content_helper.php";

/* PRESYO galing sa owner (Content Management > Prices) - Figure Name, Box & Add-ons at extras sa baba,
   at ang JSON (figurifyPrices) para sa size / product type sa image-submission.js */
$prices = figurify_site_prices();

/* NAME AT EMAIL NG NAKA-LOGIN NA CUSTOMER
   (ipapasa sa image-submission.js sa baba ng page) */
$customer_name  = "";
$customer_email = "";

if (isset($_SESSION["user_id"])) {

    $stmt = $conn->prepare("
        SELECT full_name, email
        FROM users
        WHERE user_id = ?
    ");

    $stmt->bind_param("i", $_SESSION["user_id"]);
    $stmt->execute();

    $user = $stmt->get_result()->fetch_assoc();

    if ($user) {
        $customer_name  = $user["full_name"];
        $customer_email = $user["email"];
    }

    $stmt->close();
}
/* ====================== END SETUP + DATA ====================== */


/* ==========================================================================
   [2] HTML
   ========================================================================== */
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Clay and Stuff | Image Submission</title>
    <link rel="icon" type="image/png" href="../Image/brand/logo.png">
    <?php include __DIR__ . "/../Shared/popup-and-security.php"; ?>
    <link rel="stylesheet" href="custom-box.css">
    <link rel="stylesheet" href="image-submission.css">
</head>

<body>

<!-- ====================== navbar ====================== -->
<?php include __DIR__ . "/../Shared/navbar.php"; ?>

<!-- ====================== hero (lalabas lang sa Order Summary, kapareho ng Commission page) ====================== -->

<header class="hero" id="finalHero">

    <div class="hero-content">

        <span class="hero-small">&#10022; LAST STEP &#10022;</span>

        <h1>Order Summary</h1>

        <p>Check your figures before you submit.</p>

    </div>

</header>

<!-- ====================== main ====================== -->

<main class="page">

<!-- ====================== STEP 03 - image submission form (lalabas kapag pinili ang Image Submission) ====================== -->

<section
    class="order-form show"
    id="customReferenceForm"
>

    <div class="form-shell">


        <div class="order-workspace">


            <!-- ====================== left side ====================== -->

            <div class="order-left">

                <!-- ====================== isang container: Figure Details + Custom Box (walang scroll sa loob) ====================== -->

                <div class="form-card">

                <!-- ====================== figure details (draft figure - add multiple) ====================== -->

                <div class="details-box">

                    <!-- ====================== FIGURE STYLE ====================== -->

                    <div class="field-group" id="styleFieldGroup">

                        <label>
                            Choose a figure style<span class="required-star">*</span>
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


                    <!-- ====================== PRODUCT TYPE ====================== -->

                    <div class="field-group" id="productTypeFieldGroup">

                        <label>
                            Product type<span class="required-star">*</span>
                        </label>


                        <!-- ====================== CHIBI product type grid ====================== -->

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


                        <!-- ====================== FUNKO POP product type grid ====================== -->

                        <div
                            class="product-grid product-style-group"
                            data-style-group="Funko Pop"
                            hidden
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


                        </div>


                        <!-- ====================== HIRONO product type grid ====================== -->

                        <div
                            class="product-grid product-style-group"
                            data-style-group="Hirono"
                            hidden
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


                    <!-- ====================== SIZE ====================== -->

                    <div class="field-group" id="sizeFieldGroup">

                        <label>
                            Size<span class="required-star">*</span>
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


                    <!-- ====================== FIGURE NAME ====================== -->

                    <div class="field-group">

                        <label for="figureName">
                            <span>Figure Name <span class="optional-tag">(optional)</span></span>
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

                    <!-- ====================== REFERENCE IMAGES ====================== -->

                    <div class="field-group" id="uploadFieldGroup">

                        <label>
                            Reference images<span class="required-star">*</span>
                            <span class="upload-limit-inline" id="uploadLimitText">
                                (Maximum 5 images)
                            </span>
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

                                <!-- ====================== counter nasa loob ng box, top-right ====================== -->
                                <span class="image-count-badge" id="imageCount">0 / 5</span>


                                <!-- ====================== buong box na ang clickable/droppable para sa file picker, kaya wala nang hiwalay na "+ Add Images" button ====================== -->


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


                    <!-- ====================== NOTES ====================== -->

                    <div class="field-group notes-box" id="notesFieldGroup">

                        <label for="notes">
                            Notes for figure<span class="required-star">*</span>
                        </label>

                        <textarea
                            id="notes"
                            placeholder="Please include any important details about your requested figure."
                        ></textarea>

                    </div>

                </div>
                <!-- ====================== END: .details-box (figure info) ====================== -->


                <!-- ====================== custom box container, shows Funko Box or Hirono Blind section depende sa style ======================
                     iba ang kulay (optional-box-area, nasa custom-box.css) para alam ng customer na hindi required -->

                <div class="box-addon-box optional-box-area">

                    <div class="box-addon-box-heading">

                        <h3>
                            Box &amp; Add-ons <span class="optional-badge">Optional</span>
                        </h3>

                    </div>

                    <!-- ====================== custom funko box, shows only if style = Funko Pop ====================== -->

                    <div
                        class="field-group style-addon-section"
                        id="funkoBoxSection"
                    >

                        <label>
                            Funko Box
                        </label>

                        <p class="addon-hint">
                            You can skip this. To add a custom box, choose Solo or Couple Box, then fill in the details.
                        </p>

                        <div class="box-type-grid">

                            <button
                                type="button"
                                class="box-type-option"
                                data-box-type="solo"
                                data-box-price="<?php echo $prices["funko_box"]["solo"]; ?>"
                                onclick="selectBoxType(this)"
                            >
                                <span class="image-check">✓</span>
                                <img
                                    class="box-type-image box-type-image-solo"
                                    src="../Image/boxes/funko-solo-box.jpg"
                                    alt="Solo Box"
                                    onerror="this.onerror=null;this.removeAttribute('src');this.alt='';"
                                >
                                <div class="box-type-info">
                                    <span class="box-type-name">Solo Box</span>
                                    <span class="box-type-price">₱<?php echo number_format($prices["funko_box"]["solo"], 2); ?></span>
                                </div>
                            </button>

                            <button
                                type="button"
                                class="box-type-option"
                                data-box-type="couple"
                                data-box-price="<?php echo $prices["funko_box"]["couple"]; ?>"
                                onclick="selectBoxType(this)"
                            >
                                <span class="image-check">✓</span>
                                <img
                                    class="box-type-image box-type-image-couple"
                                    src="../Image/boxes/funko-couple-box.jpg"
                                    alt="Couple Box"
                                    onerror="this.onerror=null;this.removeAttribute('src');this.alt='';"
                                >
                                <div class="box-type-info">
                                    <span class="box-type-name">Couple Box</span>
                                    <span class="box-type-price">₱<?php echo number_format($prices["funko_box"]["couple"], 2); ?></span>
                                </div>
                            </button>

                        </div>

                        <div
                            class="box-locked-message hide"
                            id="boxLockedMessage"
                        >
                            Choose a box type first.
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


                    <!-- ====================== hirono blind box, shows only if style = Hirono ====================== -->

                    <div
                        class="field-group style-addon-section"
                        id="hironoBlindSection"
                    >

                        <label>
                            Blind Box Type
                        </label>

                        <p class="addon-hint">
                            You can skip this. To add a blind box, choose Regular or Set, then fill in the details.
                        </p>

                        <div class="box-type-grid">

                            <button
                                type="button"
                                class="box-type-option"
                                data-blind-type="regular"
                                data-blind-price="<?php echo $prices["blind_box"]["regular"]; ?>"
                                onclick="selectBlindType(this)"
                            >
                                <span class="image-check">✓</span>
                                <img
                                    class="box-type-image"
                                    src="../Image/figures/hirono/hirono-style.jpg"
                                    alt="Regular Blind Box"
                                >
                                <div class="box-type-info">
                                    <span class="box-type-name">Regular Blind Box</span>
                                    <span class="box-type-price">₱<?php echo number_format($prices["blind_box"]["regular"], 2); ?></span>
                                </div>
                            </button>

                            <button
                                type="button"
                                class="box-type-option"
                                data-blind-type="set"
                                data-blind-price="<?php echo $prices["blind_box"]["set"]; ?>"
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
                                    <span class="box-type-price">₱<?php echo number_format($prices["blind_box"]["set"], 2); ?></span>
                                    <span class="box-type-note">Includes: Tear Blind Paper &amp; Digital Art only</span>
                                </div>
                            </button>

                        </div>

                        <!-- optional extras: lalabas pagkapili ng Regular / Set (kasabay ng Box Design) -->

                        <div
                            class="blind-set-contents"
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
                                    data-blind-item-price="<?php echo $prices["blind_extras"]["tear_blind_paper"]; ?>"
                                    onclick="selectBlindSetItem(this)"
                                >
                                    <span class="image-check">✓</span>
                                    <img
                                        src="../Image/boxes/tear-blind-paper.jpg"
                                        alt="Tear Blind Paper"
                                    >
                                    <span class="blind-set-item-name">Tear Blind Paper</span>
                                    <span class="blind-set-item-price">₱<?php echo number_format($prices["blind_extras"]["tear_blind_paper"], 2); ?></span>
                                </button>

                                <button
                                    type="button"
                                    class="blind-set-item"
                                    data-blind-item="pouch"
                                    data-blind-item-price="<?php echo $prices["blind_extras"]["pouch"]; ?>"
                                    onclick="selectBlindSetItem(this)"
                                >
                                    <span class="image-check">✓</span>
                                    <img
                                        src="../Image/boxes/pouch.jpg"
                                        alt="Pouch"
                                    >
                                    <span class="blind-set-item-name">Pouch</span>
                                    <span class="blind-set-item-price">₱<?php echo number_format($prices["blind_extras"]["pouch"], 2); ?></span>
                                </button>

                                <button
                                    type="button"
                                    class="blind-set-item"
                                    data-blind-item="digital_art"
                                    data-blind-item-price="<?php echo $prices["blind_extras"]["digital_art"]; ?>"
                                    onclick="selectBlindSetItem(this)"
                                >
                                    <span class="image-check">✓</span>
                                    <img
                                        src="../Image/boxes/photocard.jpg"
                                        alt="Digital Art w/ Photo Card"
                                    >
                                    <span class="blind-set-item-name">Digital Art w/ Photo Card</span>
                                    <span class="blind-set-item-price">₱<?php echo number_format($prices["blind_extras"]["digital_art"], 2); ?></span>
                                </button>

                            </div>

                        </div>


                        <div
                            class="box-detail-fields"
                            id="hironoBlindFields"
                        >

                            <div class="field-group field-group-full">

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

                        </div>


                        <!-- box details: lalabas lang pagkapili ng Box Design
                             (Box Color, Nickname, Month, Day, Letter, Box Reference Images) -->

                        <div
                            class="box-detail-fields"
                            id="hironoDesignFields"
                        >

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

                            <div class="field-group">
                                <label for="hironoDateMonth">Month</label>
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
                                <label for="hironoDateDay">Day</label>
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

                            <div class="field-group field-group-full">
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

                            <!-- BOX REFERENCE IMAGES
                                 Limit depende sa napiling Box Design:
                                 Checkered -> 5 images
                                 Hirono Peek -> 3 images -->

                            <div class="field-group field-group-full">

                                <label>
                                    Box Reference Images
                                    <span class="upload-limit-inline" id="hironoImageLimitText">
                                        (Select a Box Design first)
                                    </span>
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

                                        <!-- ====================== counter nasa loob ng box, top-right ====================== -->
                                        <span class="image-count-badge" id="hironoImageCount">0 / 0</span>


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
                <!-- ====================== END: .box-addon-box ====================== -->

                </div>
                <!-- ====================== END: .form-card ====================== -->


            </div>


            <!-- ====================== right side - order summary ====================== -->

            <aside class="summary-column">

                <div class="receipt">

                    <div class="receipt-header">
                        <h3>Your Order</h3>
                        <button type="button" class="add-figure-button" id="addFigureButton" onclick="addFigureToCart()">
                            + Add another figure
                        </button>
                    </div>

                    <!-- taas (hindi nag-i-scroll): Your Order, Order Type, Ship Out Date
                         gitna (nag-i-scroll): Figure Order - isang container bawat figure, pababa
                         baba (hindi nag-i-scroll): Estimated total, Back / Done -->

                    <div class="receipt-body">

                        <!-- ====================== ORDER TYPE + SHIP OUT DATE ====================== -->

                        <div class="order-info-list">

                            <div class="order-info-item">
                                <span class="order-info-label">Order Type</span>
                                <span class="order-info-value" id="summaryOrderType">Not selected</span>
                            </div>

                            <div class="order-info-item">
                                <span class="order-info-label">Ship Out Date</span>
                                <span class="order-info-value">
                                    <span id="summaryBookingDate">Not selected</span>
                                    <span class="order-info-sub" id="summaryBookingStatus"></span>
                                </span>
                            </div>

                        </div>

                        <!-- ====================== FIGURE ORDER (lahat ng figure, isa-isang container, scroll pababa) ====================== -->

                        <!-- "1 of 2" = aling figure ang nakikita sa listahan (nagbabago habang nag-i-scroll) -->
                        <div class="figure-order-head">
                            <h4 class="figure-order-title">Figure Order</h4>
                            <span class="figure-order-count" id="figureOrderCount">1 of 1</span>
                        </div>

                        <div class="figure-order-list" id="figureCartList"></div>

                        <!-- ====================== TOTAL (lahat ng subtotal ng figures) ====================== -->

                        <div class="summary-total">
                            <span>Estimated total</span>
                            <strong id="totalPrice">0</strong>
                        </div>

                        <div class="final-actions">
                            <button type="button" class="final-back-button" onclick="window.location.href='commission.php'">
                                Back
                            </button>
                            <button type="button" class="submit-button" onclick="openFinalSummary()">
                                Done
                            </button>
                        </div>

                    </div>

                </div>

            </aside>

        </div>

        <!-- ====================== FINAL SUMMARY (lalabas kapag pinindot ang Done) ====================== -->

        <div class="final-summary">

            <div class="final-card">

                <div class="final-info">

                    <div class="final-info-box">
                        <span class="order-info-label">Order Type</span>
                        <span class="order-info-value" id="finalOrderType"></span>
                    </div>

                    <div class="final-info-box">
                        <span class="order-info-label">Ship Out Date</span>
                        <span class="order-info-value" id="finalShipDate"></span>
                    </div>

                </div>

                <div class="final-list" id="finalFigureList"></div>

                <div class="final-totals">

                    <div class="summary-total">
                        <span>Estimated total</span>
                        <strong id="finalTotal">0</strong>
                    </div>

                </div>

                <div class="estimate-note">
                    <strong>This is an estimate.</strong>
                    Our staff reviews your photos and sends the final quotation.
                    You approve it before paying.
                </div>

                <div class="final-actions">
                    <button type="button" class="final-back-button" onclick="closeFinalSummary()">
                        Back
                    </button>
                    <button type="button" class="submit-button" id="finalSubmitButton" onclick="submitOrder()">
                        Submit Order
                    </button>
                </div>

            </div>

        </div>

</div>

</section>


</main>


<!-- ====================== image modal ====================== -->

<div
    class="image-modal"
    id="imageModal"
    onclick="closeImageModal(event)"
>

    <div class="modal-content">

        <!-- larawan + close button (nasa loob ng larawan, kanang itaas) -->
        <div class="modal-frame">

            <img
                id="modalImage"
                src=""
                alt="Large reference preview"
            >

            <button
                type="button"
                class="modal-close"
                aria-label="Close"
                onclick="closeImageModal()"
            ></button>

        </div>

        <div
            class="modal-label"
            id="modalLabel"
        >
            Reference Image
        </div>

    </div>

</div>

<!-- ====================== footer ====================== -->
<?php include __DIR__ . "/../Shared/footer.php"; ?>
<!-- ====================== END FOOTER ====================== -->


<!-- ====================== [3] SCRIPT  (data galing sa PHP, tapos image-submission.js) ====================== -->
<script>
    /* para sa "Review Your Order" summary (customer information) */
    const figurifyCustomerInfo = <?php echo json_encode(["name" => (string) $customer_name, "email" => (string) $customer_email], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>
<?php echo figurify_price_json_tag(); /* presyo ng style / product type / size at Figure Name */ ?>
<script src="image-submission.js"></script>
<!-- ====================== END SCRIPT ====================== -->

</body>
</html>
<!-- ====================== END HTML ====================== -->
