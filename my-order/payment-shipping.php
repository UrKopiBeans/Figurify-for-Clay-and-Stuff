<?php

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

require_once __DIR__ . "/../Admin/database.php";
require_once __DIR__ . "/../helpers/order_status_helper.php";


/* must be logged in */

if (!isset($_SESSION["user_id"])) {
    header("Location: ../Login/Login.php");
    exit();
}

$user_id  = $_SESSION["user_id"];
$order_id = isset($_GET["order_id"]) ? (int) $_GET["order_id"] : 0;


/* get this order - must belong to this customer */

$stmt = $conn->prepare(
    "SELECT order_id, total_amount, status
     FROM orders
     WHERE order_id = ? AND user_id = ?"
);

$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();

$result = $stmt->get_result();
$order   = $result->fetch_assoc();

$stmt->close();
$conn->close();


/* invalid o hindi na awaiting_payment -> balik sa My Orders */

if (!$order) {
    header("Location: my-orders.php?notice=order_not_found");
    exit();
}

if ($order["status"] !== "awaiting_payment") {

    /* nakabayad na (hal. pinindot ang Back pagkatapos magbayad, o na-double
       click ang Submit) - diretso na lang sa order, walang babala */
    $notYetPayableStatuses = ["pending", "quoted", "cancelled"];

    if (!in_array(figurify_status_key($order["status"]), $notYetPayableStatuses, true)) {
        header("Location: my-orders.php?order_id=" . $order_id);
        exit();
    }

    header("Location: my-orders.php?order_id=" . $order_id . "&notice=payment_unavailable");
    exit();
}

$hasError = isset($_GET["error"]) && $_GET["error"] === "1";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php /* [ALL-IN-ONE] font, background at pop up ng buong system - Shared/all-in-one.php */ include __DIR__ . "/../Shared/all-in-one.php"; ?>
    <link rel="icon" type="image/png" href="../Image/brand/logo.png">

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Payment & Shipping Details — Clay and Stuff</title>

<link rel="stylesheet" href="payment-shipping.css?v=1">

</head>
<body>

<?php include "../Shared/navbar.php"; ?>

<header class="ps-hero">
    <div class="ps-hero-inner">
        <span class="ps-eyebrow">Almost there</span>
        <h1>Payment &amp; Shipping Details</h1>
        <p>Please fill out your shipping details and complete payment below.</p>
    </div>
</header>

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

        <!-- left column: shipping + refund -->

        <div class="ps-col">

            <section class="ps-card shipping-details-container">

                <h2>Shipping Details</h2>

                <div class="payment-form-group">
                    <label for="shippingName">Full Name (Last Name, First Name, Middle Name)</label>
                    <input
                        type="text"
                        name="shipping_name"
                        id="shippingName"
                        placeholder="e.g. Santos, Maria L."
                        required
                    >
                </div>

                <div class="payment-form-group">
                    <label for="shippingAddressLine1">Full Address</label>
                    <div class="address-stack">
                        <input
                            type="text"
                            id="shippingAddressLine1"
                            placeholder="House / Building / Street"
                            required
                        >
                        <input
                            type="text"
                            id="shippingAddressLine2"
                            placeholder="Barangay / City / Province"
                            required
                        >
                    </div>
                    <input type="hidden" name="shipping_address" id="shippingAddress">
                </div>

                <div class="payment-form-group">
                    <label for="pinAddress">Pin Address / Google Maps Link (Optional)</label>
                    <input
                        type="text"
                        name="pin_address"
                        id="pinAddress"
                        placeholder="Paste Google Maps pin link or landmark"
                    >
                </div>

                <div class="payment-form-group">
                    <label for="shippingContact">Contact Number</label>
                    <input
                        type="text"
                        name="shipping_contact"
                        id="shippingContact"
                        inputmode="numeric"
                        pattern="[0-9]{11}"
                        maxlength="11"
                        placeholder="e.g. 09171234567"
                        onkeypress="return (event.charCode >= 48 && event.charCode <= 57)"
                        oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)"
                        required
                    >
                </div>

                <div class="payment-form-group">
                    <label for="courier">Courier</label>
                    <p class="courier-note">
                        Artist Location: Brgy. Palingon, Calamba City, Laguna
                    </p>
                    <select name="courier" id="courier" required>
                        <option value="">-- Select courier -- (LBC / Lalamove)</option>
                        <option value="lbc">LBC</option>
                        <option value="lalamove">Lalamove</option>
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

                <div class="payment-form-group">
                    <label for="refundAccountName">Account Name</label>
                    <input
                        type="text"
                        name="refund_account_name"
                        id="refundAccountName"
                        required
                    >
                </div>

                <div class="payment-form-group">
                    <label for="refundAccountNumber">Account Number / E-Wallet Number</label>
                    <input
                        type="text"
                        name="refund_account_number"
                        id="refundAccountNumber"
                        inputmode="numeric"
                        pattern="[0-9]{1,11}"
                        maxlength="11"
                        placeholder="e.g. 09171234567"
                        onkeypress="return (event.charCode >= 48 && event.charCode <= 57)"
                        oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)"
                        required
                    >
                </div>

                <div class="payment-form-group">
                    <span class="ps-label">Preferred Bank / Method</span>

                    <div class="refund-method-options" id="refundMethodOptions">

                        <label class="refund-method-option">
                            <input type="radio" name="refund_method" value="gcash" onchange="toggleRefundMethodOther(this)" required>
                            GCash
                        </label>

                        <label class="refund-method-option">
                            <input type="radio" name="refund_method" value="bpi" onchange="toggleRefundMethodOther(this)">
                            BPI
                        </label>

                        <label class="refund-method-option">
                            <input type="radio" name="refund_method" value="other" onchange="toggleRefundMethodOther(this)">
                            Other
                        </label>

                    </div>

                    <input
                        type="text"
                        name="refund_method_other"
                        id="refundMethodOther"
                        placeholder="Please specify bank/method (Other)"
                    >
                </div>

                <p class="ps-foot-note">Ensure refund account details match your valid ID.</p>

            </section>

        </div>


        <!-- right column: payment -->

        <div class="ps-col">

            <section class="ps-card payment-details-container">

                <h2>Payment</h2>

                <div class="price-breakdown-list">

                    <div class="price-row total-row">
                        <span>Total Price</span>
                        <span>₱<?php echo number_format($order["total_amount"], 2); ?></span>
                    </div>

                    <div class="price-row downpayment-row">
                        <span>50% Downpayment</span>
                        <span>₱<?php echo number_format($order["total_amount"] / 2, 2); ?></span>
                    </div>

                </div>

                <div class="notice-box">
                    Please make sure the downpayment amount you pay is complete and correct. An incorrect payment may cause your order to be cancelled.
                </div>

                <div class="payment-form-group">
                    <span class="ps-label">Scan to Pay</span>

                    <div class="qr-code-row">

                        <div class="qr-code-item">
                            <img
                                src="../Image/payment/qr-gcash.jpg"
                                alt="GCash QR Code"
                                class="qr-code-img"
                                onclick="openQrLightbox(this.src, 'GCash QR Code')"
                            >
                            <span class="qr-code-caption">GCash</span>
                        </div>

                        <div class="qr-code-item">
                            <img
                                src="../Image/payment/qr-bpi.jpg"
                                alt="BPI QR Code"
                                class="qr-code-img"
                                onclick="openQrLightbox(this.src, 'BPI QR Code')"
                            >
                            <span class="qr-code-caption">BPI</span>
                        </div>

                    </div>

                    <p class="payment-form-hint">Tap a QR code to enlarge it for scanning.</p>
                </div>

                <div class="payment-form-group">
                    <label for="paymentReference">Reference / Transaction Number</label>
                    <input
                        type="text"
                        name="payment_reference"
                        id="paymentReference"
                        placeholder="e.g. GCash reference number"
                        required
                    >
                </div>

                <div class="payment-form-group">

                    <label for="paymentProof">Proof of Payment (Screenshot / QR Receipt)</label>

                    <div class="payment-proof-upload" id="paymentProofUpload">

                        <div class="payment-proof-preview" id="paymentProofPreview">

                            <span class="payment-proof-placeholder">
                                Click to upload your payment screenshot
                                <br>
                                <span style="font-size:11px;">JPG, PNG, WEBP up to 5MB</span>
                            </span>

                            <img
                                id="paymentProofPreviewImg"
                                alt="Payment proof preview"
                                style="display:none;"
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

                    <button type="submit" class="payment-submit-btn">
                        Submit Payment
                    </button>

                    <!-- submits the separate cancel form below (forms can't be nested) -->
                    <button type="submit" form="cancelOrderForm" class="payment-cancel-btn" formnovalidate>
                        Cancel
                    </button>

                </div>

            </section>

        </div>

    </form>

    <form
        id="cancelOrderForm"
        method="POST"
        action="confirm_order.php"
        class="payment-shipping-cancel-form"
        onsubmit="return csConfirmSubmit(this, 'Cancel this order?\nThis can\'t be undone.', {okText: 'Cancel Order', cancelText: 'Keep Order'});"
    >
        <input type="hidden" name="order_id" value="<?php echo (int) $order["order_id"]; ?>">
        <input type="hidden" name="action" value="cancel">
    </form>

</main>


<!-- qr code lightbox -->

<div class="qr-lightbox-overlay" id="qrLightboxOverlay" onclick="closeQrLightbox(event)">

    <div class="qr-lightbox-box">
        <button type="button" class="qr-lightbox-close" aria-label="Close" onclick="closeQrLightbox()">&times;</button>
        <img id="qrLightboxImg" alt="">
        <p id="qrLightboxLabel"></p>
        <p class="qr-lightbox-hint">Scan this QR code using your GCash / BPI app</p>
    </div>

</div>


<script>

/* combine the 2 address fields into 1 hidden field before submit -
   yun lang ang binabasa ng submit_payment.php */

var paymentFormSubmitting = false;

function prepareShippingAddress() {

    /* isang beses lang maisusumite ang bayad (iwas double click) */
    if (paymentFormSubmitting) {
        return false;
    }

    /* tanong muna bago i-submit ang bayad (shared pop up - Shared/all-in-one.php) */
    var paymentForm = document.getElementById("paymentDetailsForm");

    if (window.csConfirmSubmit && !csConfirmSubmit(
        paymentForm,
        "Submit this payment?\nMake sure your shipping details and screenshot are correct.",
        { okText: "Submit", cancelText: "Back" }
    )) {
        return false;
    }

    var line1 = document.getElementById("shippingAddressLine1").value.trim();
    var line2 = document.getElementById("shippingAddressLine2").value.trim();

    document.getElementById("shippingAddress").value = line1 + ", " + line2;

    paymentFormSubmitting = true;

    var submitButton = document.querySelector(".payment-submit-btn");

    if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = "Submitting payment…";
    }

    return true;

}


function previewPaymentProof(input) {

    var placeholder = document.querySelector("#paymentProofPreview .payment-proof-placeholder");
    var previewImg  = document.getElementById("paymentProofPreviewImg");

    if (!input.files || !input.files[0] || !previewImg) {
        return;
    }

    var reader = new FileReader();

    reader.onload = function (event) {

        previewImg.src = event.target.result;
        previewImg.style.display = "block";

        if (placeholder) {
            placeholder.style.display = "none";
        }

    };

    reader.readAsDataURL(input.files[0]);

}


/* click a QR code to enlarge it */

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
    document.body.style.overflow = "hidden";

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
    document.body.style.overflow = "";

}

document.addEventListener("keydown", function (event) {

    if (event.key === "Escape") {
        closeQrLightbox();
    }

});


/* highlight picked refund method, show "Other" field lang kapag Other */

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

    otherInput.style.display = otherSelected ? "block" : "none";
    otherInput.required      = otherSelected;

    if (!otherSelected) {
        otherInput.value = "";
    }

}

</script>

<?php include "../Shared/footer.php"; ?>

</body>
</html>
