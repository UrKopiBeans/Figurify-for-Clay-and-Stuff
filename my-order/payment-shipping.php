<?php
/* ==========================================================================
   PAYMENT & SHIPPING PAGE   (my-order/payment-shipping.php)
   Style: my-order/payment-shipping.css      Script: nasa dulo ng file na ito ([3] SCRIPT)
   Bukas lang kapag "awaiting_payment" ang order. Isinusumite sa submit_payment.php.

   LAMAN (taas pababa):
     [1] SETUP + DATA   helpers, session, order ng customer, bawal kung hindi pa puwedeng bayaran,
                        naka-save na Shipping/Refund details (auto-fill, galing sa My Profile / huling order)
     [2] HTML           navbar, page hero, Shipping / Refund / Payment cards, QR lightbox, footer
     [3] SCRIPT         submit, numbers lang, preview ng proof, QR lightbox, refund method,
                        payment option (Half / Full)
   ========================================================================== */


/* ==========================================================================
   [1] SETUP + DATA
   ========================================================================== */
require_once __DIR__ . "/../helpers/security_helper.php";
figurify_start_session();

require_once __DIR__ . "/../Admin/database.php";
require_once __DIR__ . "/../helpers/order_helper.php";
require_once __DIR__ . "/../helpers/saved_details_helper.php";

/* must be logged in */

if (!isset($_SESSION["user_id"])) {
    header("Location: ../Login/Login.php");
    exit();
}

$user_id  = $_SESSION["user_id"];
$order_id = isset($_GET["order_id"]) ? (int) $_GET["order_id"] : 0;


/* get this order - must belong to this customer */

figurify_wf_ensure_schema($conn);   // may payment_option column na

$stmt = $conn->prepare(
    "SELECT order_id, total_amount, status, payment_option
     FROM orders
     WHERE order_id = ? AND user_id = ?"
);

$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();

$result = $stmt->get_result();
$order   = $result->fetch_assoc();

$stmt->close();

/* invalid o hindi na awaiting_payment -> balik sa My Orders (bago gamitin ang $order sa baba) */

if (!$order) {
    $conn->close();
    header("Location: my-orders.php?notice=order_not_found");
    exit();
}

if ($order["status"] !== "awaiting_payment") {

    /* nakabayad na (hal. pinindot ang Back pagkatapos magbayad, o na-double
       click ang Submit) - diretso na lang sa order, walang babala */
    $notYetPayableStatuses = ["pending", "quoted", "cancelled"];

    $conn->close();

    if (!in_array(figurify_status_key($order["status"]), $notYetPayableStatuses, true)) {
        header("Location: my-orders.php?order_id=" . $order_id);
        exit();
    }

    header("Location: my-orders.php?order_id=" . $order_id . "&notice=payment_unavailable");
    exit();
}

/* auto-fill: naka-save na details; Full Name = pangalan sa account (Sign Up / My Profile) */
$saved = figurify_saved_details_get($conn, (int) $user_id);
$accountName = (string) ($_SESSION["full_name"] ?? "");

/* Payment option: Half (50% downpayment) o Full - kwenta galing sa helpers/order_helper.php.
   Naka-select ang dati nang pinili (hal. ibinalik ng staff ang bayad para ayusin). */
$paymentOptions = [];
foreach (["half" => "Pay the remaining balance before your order ships.", "full" => "Pay everything now, with no balance to pay later."] as $optionKey => $optionNote) {
    $optionOrder = ["total_amount" => $order["total_amount"], "payment_option" => $optionKey];
    $paymentOptions[$optionKey] = [
        "label"  => figurify_wf_downpayment_label($optionOrder),
        "amount" => figurify_wf_downpayment_amount($optionOrder),
        "note"   => $optionNote,
    ];
}
$chosenOption = figurify_wf_is_full_payment($order) ? "full" : "half";

$savedMethodIsOther = $saved["refund_method"] !== "" && !in_array($saved["refund_method"], ["gcash", "bpi"], true);

$conn->close();


$hasError = isset($_GET["error"]) && $_GET["error"] === "1";
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
    <title>Payment & Shipping Details | Clay and Stuff</title>
    <link rel="icon" type="image/png" href="../Image/brand/logo.png">
    <?php include __DIR__ . "/../Shared/popup-and-security.php"; ?>
    <link rel="stylesheet" href="payment-shipping.css">
</head>

<body>

<!-- ====================== NAVBAR (Shared/navbar.php) ====================== -->
<?php include __DIR__ . "/../Shared/navbar.php"; ?>
<!-- ====================== END NAVBAR ====================== -->


<!-- ====================== PAGE HERO (css: payment-shipping.css [2]) ====================== -->
<header class="ps-hero">
    <div class="ps-hero-inner">
        <span class="ps-eyebrow">Almost there</span>
        <h1>Payment &amp; Shipping Details</h1>
        <p>Please fill out your shipping details and complete payment below.</p>
    </div>
</header>
<!-- ====================== END PAGE HERO ====================== -->


<!-- ====================== PAYMENT FORM (css: payment-shipping.css [3]-[7]) ====================== -->
<main class="ps-page">

    <?php if ($hasError): ?>

        <?php echo figurify_toast_now("Please upload a valid payment screenshot (JPG, PNG or WEBP, max 5MB).", "error"); ?>

    <?php endif; ?>

    <form
        id="paymentDetailsForm"
        method="POST"
        action="submit_payment.php"
        class="payment-form ps-layout"
        enctype="multipart/form-data"
        onsubmit="return prepareShippingAddress()"
    >

        <input
            type="hidden"
            name="order_id"
            value="<?php echo (int) $order["order_id"]; ?>"
        >

        <!-- ====================== LEFT: SHIPPING + REFUND ====================== -->

        <div class="ps-col">

            <section class="ps-card shipping-details-container">

                <h2>Shipping Details</h2>

                <div class="payment-form-group">
                    <label for="shippingName">Full Name <span class="required-star">*</span></label>
                    <input
                        type="text"
                        name="shipping_name"
                        id="shippingName"
                        placeholder="e.g. Maria Santos"
                        value="<?php echo e($accountName); ?>"
                        required
                    >
                </div>

                <div class="payment-form-group">
                    <label for="shippingAddressLine1">Full Address <span class="required-star">*</span></label>
                    <div class="address-stack">
                        <input
                            type="text"
                            name="shipping_address_line1"
                            id="shippingAddressLine1"
                            placeholder="House No. / Building / Street / Brgy"
                            value="<?php echo e($saved["address_line1"]); ?>"
                            required
                        >
                        <input
                            type="text"
                            name="shipping_address_line2"
                            id="shippingAddressLine2"
                            placeholder="City / Province"
                            value="<?php echo e($saved["address_line2"]); ?>"
                            required
                        >
                    </div>
                </div>

                <div class="payment-form-group">
                    <label for="pinAddress">Pin Address / Google Maps Link (Optional)</label>
                    <input
                        type="text"
                        name="pin_address"
                        id="pinAddress"
                        placeholder="Paste Google Maps pin link or landmark"
                        value="<?php echo e($saved["pin_address"]); ?>"
                    >
                </div>

                <div class="payment-form-group">
                    <label for="shippingContact">Contact Number <span class="required-star">*</span></label>
                    <input
                        type="text"
                        name="shipping_contact"
                        id="shippingContact"
                        inputmode="numeric"
                        pattern="[0-9]{11}"
                        maxlength="11"
                        placeholder="e.g. 09171234567"
                        value="<?php echo e($saved["shipping_contact"]); ?>"
                        data-digits-only
                        required
                    >
                </div>

                <div class="payment-form-group">
                    <label for="courier">Courier <span class="required-star">*</span></label>
                    <p class="courier-note">
                        Artist Location: Brgy. Palingon, Calamba City, Laguna
                    </p>
                    <select name="courier" id="courier" required>
                        <option value="">Select courier (LBC / Lalamove)</option>
                        <option value="lbc"<?php echo $saved["courier"] === "lbc" ? " selected" : ""; ?>>LBC</option>
                        <option value="lalamove"<?php echo $saved["courier"] === "lalamove" ? " selected" : ""; ?>>Lalamove</option>
                    </select>
                </div>

                <p class="ps-foot-note">Please ensure all details are correct to avoid delivery delays.</p>

            </section>


            <section class="ps-card refund-details-container">

                <h2>Refund / Return Details</h2>

                <p class="refund-note">
                    We collect this upfront so we can process any
                    return smoothly if ever needed.
                </p>

                <div class="ps-row">

                    <div class="payment-form-group">
                        <label for="refundAccountName">Account Name <span class="required-star">*</span></label>
                        <input
                            type="text"
                            name="refund_account_name"
                            id="refundAccountName"
                            value="<?php echo e($saved["refund_account_name"]); ?>"
                            required
                        >
                    </div>

                    <div class="payment-form-group">
                        <label for="refundAccountNumber">Account Number / E-Wallet Number <span class="required-star">*</span></label>
                        <input
                            type="text"
                            name="refund_account_number"
                            id="refundAccountNumber"
                            inputmode="numeric"
                            pattern="[0-9]+"
                            placeholder="e.g. 09171234567"
                            value="<?php echo e($saved["refund_account_number"]); ?>"
                            data-digits-only
                            required
                        >
                    </div>

                </div>

                <div class="payment-form-group">
                    <span class="ps-label">Preferred Bank / Method <span class="required-star">*</span></span>

                    <div class="refund-method-options" id="refundMethodOptions">

                        <label class="refund-method-option">
                            <input type="radio" name="refund_method" value="gcash" onchange="toggleRefundMethodOther(this)"<?php echo $saved["refund_method"] === "gcash" ? " checked" : ""; ?> required>
                            GCash
                        </label>

                        <label class="refund-method-option">
                            <input type="radio" name="refund_method" value="bpi" onchange="toggleRefundMethodOther(this)"<?php echo $saved["refund_method"] === "bpi" ? " checked" : ""; ?>>
                            BPI
                        </label>

                        <label class="refund-method-option">
                            <input type="radio" name="refund_method" value="other" onchange="toggleRefundMethodOther(this)"<?php echo $savedMethodIsOther ? " checked" : ""; ?>>
                            Other
                        </label>

                    </div>

                    <input
                        type="text"
                        name="refund_method_other"
                        id="refundMethodOther"
                        placeholder="Please specify bank/method (Other)"
                        value="<?php echo $savedMethodIsOther ? e($saved["refund_method"]) : ""; ?>"
                        <?php echo $savedMethodIsOther ? "" : "hidden"; ?>
                    >
                </div>

                <p class="ps-foot-note">Ensure refund account details match your valid ID.</p>

            </section>

        </div>


        <!-- ====================== END LEFT ====================== -->


        <!-- ====================== RIGHT: PAYMENT ====================== -->

        <div class="ps-col">

            <section class="ps-card payment-details-container">

                <h2>Payment</h2>

                <div class="payment-form-group">
                    <span class="ps-label">Payment Option <span class="required-star">*</span></span>

                    <div class="payment-option-list">
                        <?php foreach ($paymentOptions as $optionKey => $option): ?>
                            <label class="payment-option-card">
                                <input
                                    type="radio"
                                    name="payment_option"
                                    value="<?php echo $optionKey; ?>"
                                    data-amount="₱<?php echo number_format($option["amount"], 2); ?>"
                                    onchange="selectPaymentOption(this)"
                                    <?php echo $optionKey === $chosenOption ? "checked" : ""; ?>
                                    required
                                >
                                <span class="payment-option-name"><?php echo e($option["label"]); ?></span>
                                <span class="payment-option-amount">₱<?php echo number_format($option["amount"], 2); ?></span>
                                <small class="payment-option-note"><?php echo e($option["note"]); ?></small>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="price-breakdown-list">

                    <div class="price-row total-row">
                        <span>Total Price</span>
                        <span>₱<?php echo number_format($order["total_amount"], 2); ?></span>
                    </div>

                    <div class="price-row downpayment-row">
                        <span>Amount to Pay Now</span>
                        <span id="amountToPay">₱<?php echo number_format($paymentOptions[$chosenOption]["amount"], 2); ?></span>
                    </div>

                </div>

                <div class="notice-box">
                    Make sure you pay the exact amount. A wrong amount may get your order cancelled.
                </div>

                <div class="payment-form-group">
                    <span class="ps-label">Scan to Pay</span>

                    <div class="qr-code-row">

                        <!-- buong container ang clickable (hindi lang ang QR) -->
                        <div class="qr-code-item" onclick="openQrLightbox('../Image/payment/qr-gcash.jpg', 'GCash QR Code')">
                            <img src="../Image/payment/qr-gcash.jpg" alt="GCash QR Code" class="qr-code-img">
                            <span class="qr-code-caption">GCash</span>
                            <span class="qr-code-account">Account Name: Rosemarie P.</span>
                            <span class="qr-code-account">Number: 0963 786 2263</span>
                        </div>

                        <div class="qr-code-item" onclick="openQrLightbox('../Image/payment/qr-bpi.jpg', 'BPI QR Code')">
                            <img src="../Image/payment/qr-bpi.jpg" alt="BPI QR Code" class="qr-code-img">
                            <span class="qr-code-caption">BPI</span>
                            <span class="qr-code-account">Account Name: Rosemarie Pia</span>
                            <span class="qr-code-account">Number: 2669304997</span>
                        </div>

                    </div>

                    <p class="payment-form-hint">Tap a GCash or BPI box to enlarge the QR code for scanning.</p>
                </div>

                <div class="payment-form-group">
                    <label for="paymentReference">Reference / Transaction Number <span class="required-star">*</span></label>
                    <input
                        type="text"
                        name="payment_reference"
                        id="paymentReference"
                        inputmode="numeric"
                        pattern="[0-9]+"
                        placeholder="e.g. GCash reference number"
                        data-digits-only
                        required
                    >
                </div>

                <div class="payment-form-group">

                    <label for="paymentProof">Proof of Payment (Screenshot / QR Receipt) <span class="required-star">*</span></label>

                    <div class="payment-proof-upload" id="paymentProofUpload">

                        <div class="payment-proof-preview" id="paymentProofPreview">

                            <span class="payment-proof-placeholder">
                                Click to upload your payment screenshot
                                <br>
                                <span class="payment-proof-format">JPG, PNG, WEBP up to 5MB</span>
                            </span>

                            <img
                                id="paymentProofPreviewImg"
                                alt="Payment proof preview"
                                hidden
                            >

                        </div>

                        <input
                            type="file"
                            name="payment_proof"
                            id="paymentProof"
                            accept="image/jpeg,image/png,image/webp"
                            required
                            onchange="previewPaymentProof(this)"
                        >

                    </div>

                </div>

                <div class="payment-terms-group">
                    <input type="checkbox" id="paymentAgreeTerms" name="agree_terms" required>
                    <label for="paymentAgreeTerms">
                        I have read and agree to the
                        <a href="../Legal/terms-and-conditions.php#orders" target="_blank" rel="noopener">Terms and Conditions</a>
                        and <a href="../Legal/privacy-policy.php" target="_blank" rel="noopener">Privacy Policy</a>.
                    </label>
                </div>

                <div class="payment-actions-row">

                    <!-- Back: bumabalik sa My Orders (magkatabi sila ng Submit Payment) -->
                    <a href="my-orders.php" class="payment-back-btn">
                        Back
                    </a>

                    <button type="submit" class="payment-submit-btn">
                        Submit Payment
                    </button>

                </div>

            </section>

        </div>
        <!-- ====================== END RIGHT ====================== -->

    </form>

</main>
<!-- ====================== END PAYMENT FORM ====================== -->


<!-- ====================== QR LIGHTBOX (css: payment-shipping.css [8] | js: [3c]) ====================== -->
<div class="qr-lightbox-overlay" id="qrLightboxOverlay" onclick="closeQrLightbox(event)">

    <div class="qr-lightbox-box">
        <!-- larawan + close (X) sa loob ng larawan, kanang itaas (kapareho ng ibang image viewer) -->
        <div class="qr-lightbox-frame">
            <img id="qrLightboxImg" alt="">
            <button type="button" class="qr-lightbox-close" aria-label="Close" onclick="closeQrLightbox()"></button>
        </div>
        <p id="qrLightboxLabel"></p>
        <p class="qr-lightbox-hint">Scan this QR code using your GCash / BPI app</p>
    </div>

</div>
<!-- ====================== END QR LIGHTBOX ====================== -->


<!-- ====================== FOOTER (Shared/footer.php) ====================== -->
<?php include __DIR__ . "/../Shared/footer.php"; ?>
<!-- ====================== END FOOTER ====================== -->


<!-- ====================== [3] SCRIPT ====================== -->
<script>

/* ==========================================================================
   [3a] SUBMIT  (confirm bago isumite; iwas double click)
   ========================================================================== */

var paymentFormSubmitting = false;

function prepareShippingAddress() {

    /* isang beses lang maisusumite ang bayad (iwas double click) */
    if (paymentFormSubmitting) {
        return false;
    }

    /* tanong muna bago i-submit ang bayad (shared pop up - Shared/popup-and-security.php) */
    var paymentForm = document.getElementById("paymentDetailsForm");

    if (window.csConfirmSubmit && !csConfirmSubmit(
        paymentForm,
        "Submit this payment?\nMake sure your shipping details and screenshot are correct.",
        { okText: "Submit", cancelText: "Back" }
    )) {
        return false;
    }

    paymentFormSubmitting = true;

    var submitButton = document.querySelector(".payment-submit-btn");

    if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = "Submitting payment…";
    }

    return true;

}

/* ====================== END SUBMIT ====================== */


/* ==========================================================================
   [3b] FIELDS  (numbers lang, preview ng proof of payment)
   ========================================================================== */

/* Contact No., Account No. at Reference No.: numbers lang - data-digits-only (Shared/popup-and-security.php [6]) */


function previewPaymentProof(input) {

    var placeholder = document.querySelector("#paymentProofPreview .payment-proof-placeholder");
    var previewImg  = document.getElementById("paymentProofPreviewImg");

    if (!input.files || !input.files[0] || !previewImg) {
        return;
    }

    var reader = new FileReader();

    reader.onload = function (event) {

        previewImg.src = event.target.result;
        previewImg.hidden = false;

        if (placeholder) {
            placeholder.hidden = true;
        }

    };

    reader.readAsDataURL(input.files[0]);

}

/* ====================== END FIELDS ====================== */


/* ==========================================================================
   [3c] QR LIGHTBOX  (click ng QR = palakihin)
   ========================================================================== */

function openQrLightbox(src, label) {

    var overlay = document.getElementById("qrLightboxOverlay");
    var image   = document.getElementById("qrLightboxImg");
    var caption = document.getElementById("qrLightboxLabel");

    if (!overlay || !image) {
        return;
    }

    image.src = src;
    image.alt = label ? (label + " QR code") : "Payment QR code";

    if (caption) {
        caption.textContent = label || "";
    }

    overlay.classList.add("show");
    document.body.classList.add("no-scroll");

}

function closeQrLightbox(event) {

    /* kapag nag-click sa loob mismo ng picture, huwag isara */
    if (event && event.target && event.target.closest && event.target.closest(".qr-lightbox-box") && !event.target.closest(".qr-lightbox-close")) {
        return;
    }

    var overlay = document.getElementById("qrLightboxOverlay");

    if (!overlay) {
        return;
    }

    overlay.classList.remove("show");
    document.body.classList.remove("no-scroll");

}

document.addEventListener("keydown", function (event) {

    if (event.key === "Escape") {
        closeQrLightbox();
    }

});

/* ====================== END QR LIGHTBOX ====================== */


/* ==========================================================================
   [3d] REFUND METHOD  (highlight ng napili; "Other" field lang kapag Other)
   ========================================================================== */

function toggleRefundMethodOther(selectedRadio) {

    var cards = document.querySelectorAll("#refundMethodOptions .refund-method-option");

    cards.forEach(function (card) {
        card.classList.remove("selected");
    });

    if (selectedRadio) {
        var chosenCard = selectedRadio.closest(".refund-method-option");
        if (chosenCard) {
            chosenCard.classList.add("selected");
        }
    }

    var otherSelected = document.querySelector('input[name="refund_method"][value="other"]').checked;
    var otherInput     = document.getElementById("refundMethodOther");

    if (!otherInput) {
        return;
    }

    otherInput.hidden = !otherSelected;
    otherInput.required      = otherSelected;

    if (!otherSelected) {
        otherInput.value = "";
    }

}

/* ==========================================================================
   [3e] PAYMENT OPTION  (Half / Full - pinapalitan ang "Amount to Pay Now")
   ========================================================================== */

function selectPaymentOption(radio) {
    document.getElementById("amountToPay").textContent = radio.dataset.amount;
}
/* ====================== END PAYMENT OPTION ====================== */

/* auto-fill: ipakita agad ang naka-save na refund method (highlight + "Other" field) */
toggleRefundMethodOther(document.querySelector('#refundMethodOptions input[name="refund_method"]:checked'));
/* ====================== END REFUND METHOD ====================== */
</script>
<!-- ====================== END SCRIPT ====================== -->

</body>
</html>
<!-- ====================== END HTML ====================== -->
