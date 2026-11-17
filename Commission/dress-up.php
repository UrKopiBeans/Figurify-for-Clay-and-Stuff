<?php
/* ==========================================================================
   DRESS UP PAGE   (Commission/dress-up.php)
   Style: Commission/custom-box.css + Commission/dress-up.css
   Script: Commission/dress-up.js (module, three.js)
   Mga pagpipilian (hair, top, bottom, shoes, kulay...): Commission/dress-up-options.php
   Kasama na rito ang Order Summary (dating finalsummary.php) - lalabas pagpindot ng Done.

   LAMAN (taas pababa):
     [1] SETUP    session, database, dress up options
     [2] HTML     navbar, figure viewer, wizard (details, outfit, box), order summary
     [3] SCRIPT   importmap ng three.js, tapos dress-up.js
   ========================================================================== */


/* ==========================================================================
   [1] SETUP
   ========================================================================== */
require_once __DIR__ . "/../helpers/security_helper.php";
figurify_start_session();

require_once __DIR__ . "/../Admin/database.php";   // $conn (iisang koneksyon ng buong site)

/* lahat ng pagpipilian sa Dress Up (hair, top, bottom, shoes, kulay...) */
require_once __DIR__ . "/dress-up-options.php";
/* ====================== END SETUP ====================== */


/* ==========================================================================
   [2] HTML
   ========================================================================== */
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Clay and Stuff | Dress Up</title>
    <link rel="icon" type="image/png" href="../Image/brand/logo.png">
    <?php include __DIR__ . "/../Shared/popup-and-security.php"; ?>
    <link rel="stylesheet" href="custom-box.css">
    <link rel="stylesheet" href="dress-up.css">
</head>

<body data-page="editor">

<!-- ====================== navbar ====================== -->
<?php include __DIR__ . "/../Shared/navbar.php"; ?>


<!-- ====================== main ====================== -->

<main class="page">

<!--
    MAPA NG dress-up.php (sunod-sunod, mula taas hanggang baba)
    1. 3D figure preview + Reset          (kaliwa)
    2. Figure Style                       (Funko Pop / Chibi / Hirono)
    3. Funko Pop panel                    (Gender, Skin Tone, Hair, Top, Bottom, Shoes)
    4. Chibi panel                        (Product Type, Gender, Skin Tone, Hair, Top, Bottom, Shoes, Keychain)
    5. Hirono panel                       (Product Type, Gender, Skin Tone, Hair, Tops, Bottom, Shoes, Keychain Hair / Hat)
    6. Size                               (para sa kahit anong style)
    7. Box / Add-ons at Back / Next       (kanan, ilalim)
    8. Order Summary                      (lalabas pagpindot ng Done; nakatago ang 1-7)
    Ang logic nasa dress-up.js, ang itsura nasa dress-up.css
-->

<!-- ====================== DRESS UP - sariling page na (dress-up.php / dress-up.css / dress-up.js) ====================== -->

<div id="dressUpModule">

    <a href="commission.php" class="dress-up-back" id="dressUpBackLink" hidden>Back</a>

        <div class="dressup-workspace">

            <div class="dressup-left">


            <div class="preview-header">

                <h2>Your Figure</h2>

                <button type="button" id="resetBtn">Reset Design</button>

            </div>


            <!-- ====================== 3D figure preview ====================== -->

            <div class="figure-area">

                <canvas
                    id="figureViewer"
                    class="figure-viewer"
                ></canvas>

                <!-- loading ng 3D: malaki sa gitna kapag wala pang figure,
                     maliit sa taas kapag may figure na (hal. naglo-load ang bagong Hair) -->
                <div class="figure-loading" id="figureLoading" role="status" aria-live="polite">
                    <span class="figure-loading-spinner" aria-hidden="true"></span>
                    <span class="figure-loading-text" id="figureLoadingText">Loading your figure…</span>
                </div>

            </div>

            </div>


            <div class="dressup-right">

            <ol class="wizard-pills" id="wizardPills" aria-label="Dress up steps">
                <li data-wizard="1"><span>Figure Details</span></li>
                <li data-wizard="2"><span>Outfit</span></li>
                <li data-wizard="3"><span>Box &amp; Add-ons</span></li>
            </ol>

            <!-- Outfit step: Hair / Top / Bottom / Shoes tabs (nilalagay ng dress-up.js) -->
            <div class="parts-tabs" id="partsTabs" hidden></div>

            <!-- scroll area: pare-pareho ang laki ng Figure Details container; kapag dumami
                 ang laman (hal. maraming Tops), dito na lang mag-i-scroll, nasa baba pa rin ang Back/Next -->
            <div class="dressup-right-scroll" id="dressUpRightScroll">


            <!-- ====================== figure details: from Figure Style up to the last option (e.g. shoes), then Box/Add-ons na kung meron ====================== -->

            <div
                class="option-section figure-details-heading"
                id="figureDetailsContainer"
            >


                <!-- ====================== figure style ====================== -->

                <div
                    class="option-section figure-style-section"
                    id="figureStyleSection"
                >

                    <div class="section-title">
                        <h2>Figure Style<span class="required-star">*</span></h2>
                    </div>

                    <div class="figure-style-grid">

                        <button
                            type="button"
                            class="figure-style-card"
                            data-tab="figure"
                            data-figure="funko"
                        >
                            <img src="../Image/figures/covers/funko-pop-style-cover.jpg" alt="Funko Pop">
                            <span>Funko Pop</span>
                        </button>

                        <button
                            type="button"
                            class="figure-style-card"
                            data-tab="figure"
                            data-figure="hirono"
                        >
                            <img src="../Image/figures/covers/hirono-style-cover.jpg" alt="Hirono">
                            <span>Hirono</span>
                        </button>

                        <button
                            type="button"
                            class="figure-style-card"
                            data-tab="figure"
                            data-figure="chibi"
                        >
                            <img src="../Image/figures/covers/chibi-style-cover.jpg" alt="Chibi">
                            <span>Chibi</span>
                        </button>

                    </div>

                </div>


                <!-- ====================== funko pop panel ====================== -->

                <div class="category-panel" id="funkoPanel" hidden>

            <?php du_render_panel($duFunko); ?>

        </div>


            <!-- ====================== chibi panel ====================== -->

            <div class="category-panel" id="chibiPanel" hidden>

            <?php du_render_panel($duChibi); ?>

        </div>


            <!-- ====================== hirono panel ====================== -->

            <div class="category-panel" id="hironoPanel" hidden>

            <?php du_render_panel($duHirono); ?>

        </div>


            <!-- ====================== SIZE - shared step after Gender/Skin, para sa kahit anong Figure Style ====================== -->

            <div
                class="option-section size-section"
                id="figureSizeSection"
                hidden
            >

                <div class="section-title">
                    <h2>Size<span class="required-star">*</span></h2>
                </div>

                <div class="size-choice-grid" id="sizeChoiceGrid"></div>

            </div>

            </div>


            <section
                class="product-details-panel"
                id="productDetailsPanel"
                hidden
            >

                <!-- ====================== one container lang, becomes "Box / Add-ons" kung Funko/Hirono, "Figure Details" pa rin kung Chibi ====================== -->

                <div class="section-title">
                    <div>
                        <span class="details-kicker" id="productDetailsKicker">FIGURE DETAILS</span>
                        <h2 id="productDetailsTitle">Choose Your Figure Details</h2>
                        <p id="productDetailsDescription">Choose the box for your selected product.</p>
                    </div>
                </div>

                <!-- ====================== scroll box: dito lang nag-i-scroll, hindi kasama ang title ====================== -->
                <div class="product-details-scroll" id="productDetailsScroll">

                <!-- ====================== Funko Pop: Custom Funko Box (kapareho ng image-submission.php) ====================== -->

                <!-- iba ang kulay (optional-box-area, nasa custom-box.css) para alam ng customer na hindi required -->
                <div class="box-section optional-box-area" id="boxChoiceGroup">
                    <label class="box-title">Funko Box</label>
                    <p class="addon-hint" id="boxChoiceHint">
                        You can skip this. To add a custom box, choose Solo or Couple Box, then fill in the details.
                    </p>
                    <div class="box-type-grid" id="boxChoiceGrid"></div>
                    <p class="box-locked-message" id="boxLockedHint" hidden>
                        Choose a box type first.
                    </p>

                    <div class="box-detail-fields" id="boxDetailFields" hidden>
                    <div class="field-group">
                        <label for="boxNameInput">Name (to print on box) <span class="optional-tag">(optional)</span></label>
                        <input class="field" id="boxNameInput" data-detail-field="boxName" type="text" maxlength="40" placeholder="Example: Omar">
                    </div>
                    <div class="field-group">
                        <label for="boxNumberInput">Box Number <span class="optional-tag">(optional)</span></label>
                        <input class="field" id="boxNumberInput" data-detail-field="boxNumber" type="text" maxlength="20" placeholder="Example: 001">
                    </div>
                    <div class="field-group">
                        <label for="boxColorInput">Box Color <span class="optional-tag">(optional)</span></label>
                        <input class="field" id="boxColorInput" data-detail-field="boxColor" type="text" maxlength="30" placeholder="Example: Pastel Pink">
                    </div>
                    </div>
                </div>

                <!-- ====================== Hirono: Blind Box (kapareho ng image-submission.php) ====================== -->

                <!-- Regular / Set muna; pagkapili, lalabas ang Optional Extras at Box Design;
                     pagkapili ng Box Design, lalabas ang Box Color, Nickname, Month, Day, Letter -->
                <div class="optional-box-area" id="hironoDetailFields" hidden>
                    <div class="box-section">
                        <label class="box-title">Blind Box Type</label>
                        <p class="addon-hint">
                            You can skip this. To add a blind box, choose Regular or Set, then fill in the details.
                        </p>
                        <div class="box-type-grid" id="hironoBlindBoxGrid"></div>
                    </div>
                    <div class="box-section" id="hironoAddonSection" hidden>
                        <div class="blind-set-title">Optional Extras (Tap To Add)</div>
                        <div class="blind-set-grid" id="hironoAddonGrid"></div>
                    </div>
                    <div class="box-section" id="hironoDesignSection" hidden>
                        <label class="box-title">Box Design</label>
                        <p class="addon-hint">Choose a box design for your Blind Box.</p>
                        <div class="box-type-grid" id="hironoBoxDesignGrid"></div>
                    </div>
                    <div class="box-detail-fields" id="hironoBoxFields" hidden></div>
                </div>

                </div>
                <!-- ====================== END: .product-details-scroll ====================== -->

            </section>


            </div>
            <!-- ====================== END: .dressup-right-scroll ====================== -->

            <div
                class="design-step-navigation"
                id="designStepNavigation"
                hidden
            >
                <div class="design-step-total">
                    <span>TOTAL</span>
                    <strong id="dressUpTotalPrice">&#8369;0</strong>
                </div>
                <button
                    type="button"
                    class="step-navigation-button"
                    id="previousDesignStep"
                >
                    Back
                </button>
                <button
                    type="button"
                    class="continue-btn step-navigation-button"
                    id="nextDesignStep"
                >
                    Next
                </button>
                <button
                    type="button"
                    class="continue-btn"
                    id="continueBtn"
                    hidden
                >
                    Done
                </button>
            </div>

            </div>

        </div>


    <!-- ====================== ORDER SUMMARY (dating finalsummary.php) ======================
         Lalabas pagpindot ng Done (dress-up.js: openOrderSummary). 4 na container na magkakatabi:
         Your Figure (3D) | Figure Details | Box & Add-ons | Figures in this Order -->

    <section class="order-summary" id="orderSummary" hidden>

        <header class="summary-hero">
            <span class="summary-hero-small">&#10022; LAST STEP &#10022;</span>
            <h1>Order Summary</h1>
            <p>Take one last look at your figure before you submit your order.</p>
        </header>

        <div class="summary-grid">

            <!-- 1. Your Figure: dito inililipat ang 3D preview (.figure-area) habang bukas ang summary -->
            <div class="summary-card summary-figure-card">
                <div class="summary-card-head">
                    <h2>Your Figure</h2>
                    <span class="figure-index-badge">FIGURE 1</span>
                </div>
                <div class="summary-figure-slot" id="summaryFigureSlot"></div>
            </div>

            <!-- 2. Figure Details: Order Type, Ship Out Date, at lahat ng napili (may larawan at kulay) -->
            <div class="summary-card">
                <div class="summary-card-head">
                    <h2>Figure Details</h2>
                    <span class="figure-index-badge">FIGURE 1</span>
                </div>
                <div class="summary-order-info">
                    <div class="summary-info-box">
                        <span class="summary-info-label">Order Type</span>
                        <strong id="dressUpSummaryOrderType">Not selected</strong>
                        <small id="dressUpSummaryRushFee"></small>
                    </div>
                    <div class="summary-info-box">
                        <span class="summary-info-label">Ship Out Date</span>
                        <strong id="dressUpSummaryBookingDate">Not selected</strong>
                        <small id="dressUpSummaryBookingStatus"></small>
                    </div>
                </div>
                <div class="summary-rows" id="summaryReceiptItems"></div>
            </div>

            <!-- 3. Box & Add-ons: lalabas lang kapag may box / add-on na pinili (dress-up.js renderBoxRows) -->
            <div class="summary-card" hidden>
                <div class="summary-card-head">
                    <h2>Box &amp; Add-ons</h2>
                </div>
                <div class="summary-rows" id="summaryBoxItems"></div>
            </div>

            <!-- 4. Figures in this Order + Total + Back / Proceed to Payment -->
            <div class="summary-card" id="dressUpCartSection">
                <div class="summary-card-head">
                    <h2>Figures in this Order</h2>
                </div>
                <div id="dressUpCartList"></div>
                <button type="button" id="finalSummaryAddFigureBtn" class="summary-add-btn">
                    + Add Another Figure
                </button>
                <div class="summary-total">
                    <span>Total Price</span>
                    <strong id="summaryTotalPrice">&#8369;0</strong>
                </div>
                <div class="summary-actions">
                    <button type="button" class="summary-back-btn" id="summaryBackBtn">Back</button>
                    <button type="button" class="summary-next-btn" id="finalSummaryContinueBtn">Proceed to Payment</button>
                </div>
            </div>

        </div>

    </section>
    <!-- ====================== END ORDER SUMMARY ====================== -->

</div>

</main>


<!-- ====================== [3] SCRIPT ====================== -->
<!-- importmap: kailangan dahil direktang nag-i-import ng three.js ang dress-up.js -->
<script type="importmap">
{
    "imports": {
        "three": "https://unpkg.com/three@0.160.0/build/three.module.js",
        "three/addons/": "https://unpkg.com/three@0.160.0/examples/jsm/"
    }
}
</script>

<!-- ====================== presyo galing sa owner (Content Management > Prices) ====================== -->
<?php echo figurify_price_json_tag(); ?>

<!-- ====================== lahat ng Dress Up logic (3D, presyo, order submit) ====================== -->
<script type="module" src="dress-up.js"></script>
<!-- ====================== END SCRIPT ====================== -->

</body>
</html>
<!-- ====================== END HTML ====================== -->
