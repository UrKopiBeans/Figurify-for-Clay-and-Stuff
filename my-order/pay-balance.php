<?php
/* ==========================================================================
   PAY REMAINING BALANCE PAGE   (my-order/pay-balance.php)
   Style: my-order/pay-balance.css      Script: nasa dulo ng file na ito ([3] SCRIPT)
   Bukas lang kapag "awaiting_balance" ang order. Isinusumite sa submit_balance_payment.php.

   LAMAN (taas pababa):
     [1] SETUP + DATA   helpers, session, order ng customer, bawal kung hindi pa puwedeng bayaran
     [2] HTML           navbar, page hero, balance payment card, QR lightbox, footer
     [3] SCRIPT         submit, preview ng proof, QR lightbox
   ========================================================================== */


/* ==========================================================================
   [1] SETUP + DATA
   ========================================================================== */
require_once __DIR__ . "/../helpers/security_helper.php";
figurify_start_session();

require_once __DIR__ . "/../Admin/database.php";
require_once __DIR__ . "/../helpers/order_helper.php";

/* must be logged in */

if (!isset($_SESSION["user_id"])) {
    header("Location: ../Login/Login.php");
    exit();
}

$user_id  = $_SESSION["user_id"];
$order_id = isset($_GET["order_id"]) ? (int) $_GET["order_id"] : 0;


/* get this order - must belong to this customer */

$stmt = $conn->prepare(
    "SELECT order_id, total_amount, balance_to_pay, status
     FROM orders
     WHERE order_id = ? AND user_id = ?"
);

$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();

$result = $stmt->get_result();
$order   = $result->fetch_assoc();

$stmt->close();
$conn->close();


/* invalid o hindi na awaiting_balance -> balik sa My Orders */

if (!$order) {
    header("Location: my-orders.php?notice=order_not_found");
    exit();
}

if ($order["status"] !== "awaiting_balance") {

    /* nabayaran na ang balance (Back button / double click) - diretso sa order, walang babala */
    $balanceAlreadyPaidStatuses = ["to_verify_balance", "for_balance_approval", "to_ship", "address_returned", "shipped", "completed"];

    if (in_array(figurify_status_key($order["status"]), $balanceAlreadyPaidStatuses, true)) {
        header("Location: my-orders.php?order_id=" . $order_id);
        exit();
    }

    header("Location: my-orders.php?order_id=" . $order_id . "&notice=payment_unavailable");
    exit();
}

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
    <title>Pay Remaining Balance | Clay and Stuff</title>
    <link rel="icon" type="image/png" href="../Image/brand/logo.png">
    <?php include __DIR__ . "/../Shared/popup-and-security.php"; ?>
    <link rel="stylesheet" href="pay-balance.css">
</head>

<body>

<!-- ====================== NAVBAR (Shared/navbar.php) ====================== -->
<?php include __DIR__ . "/../Shared/navbar.php"; ?>
<!-- ====================== END NAVBAR ====================== -->


<!-- ====================== PAGE HERO (css: pay-balance.css [2]) ====================== -->
<header class="page-hero">
    <div class="page-wrap">
        <span class="page-eyebrow">LAST STEP</span>
        <h1>Pay Remaining Balance</h1>
        <p>You can pay now. We will keep working on your order, but we can only ship it after your balance is paid.</p>
    </div>
</header>
<!-- ====================== END PAGE HERO ====================== -->


<!-- ====================== BALANCE PAYMENT (css: pay-balance.css [3]-[7]) ====================== -->
<main class="pay-page">

    <?php if ($hasError): ?>

        <?php echo figurify_toast_now("Please upload a valid payment screenshot (JPG, PNG or WEBP, max 5MB).", "error"); ?>

    <?php endif; ?>

    <div class="pay-card">

        <form
            method="POST"
            action="submit_balance_payment.php"
            class="payment-form"
            enctype="multipart/form-data"
            onsubmit="return submitBalancePayment(this)"
        >

            <input
                type="hidden"
                name="order_id"
                value="<?php echo (int) $order["order_id"]; ?>"
            >

            <div class="pay-card-header">
                <h2>Balance Payment</h2>
                <p>Complete your remaining balance here.</p>
            </div>

            <?php $balanceDue = (float) $order["balance_to_pay"]; ?>

            <div class="payment-total-box">
                <span>Remaining Balance</span>
                <strong>₱<?php echo number_format($balanceDue, 2); ?></strong>
            </div>

            <div class="notice-box notice-box-warning">
                Make sure you pay the exact balance above. A wrong amount may delay your shipment.
            </div>

            <div class="payment-form-group">
                <label>Scan to Pay</label>

                <div class="qr-code-row">

                    <!-- buong container ang clickable (hindi lang ang QR) - kapareho ng Payment page -->
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
                <label for="balancePaymentReference">Reference / Transaction Number</label>
                <input
                    type="text"
                    name="payment_reference"
                    id="balancePaymentReference"
                    inputmode="numeric"
                    pattern="[0-9]+"
                    placeholder="e.g. GCash reference number"
                    data-digits-only
                    required
                >
            </div>

            <div class="payment-form-group">

                <label for="balancePaymentProof">Proof of Payment (Screenshot / QR Receipt)</label>

                <div class="payment-proof-upload" id="paymentProofUpload">

                    <div class="payment-proof-preview" id="paymentProofPreview">

                        <span class="payment-proof-placeholder">
                            <span class="payment-proof-title">Click to upload your payment screenshot</span>
                            <small>Upload a clear screenshot showing the amount and reference number.</small>
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
                        id="balancePaymentProof"
                        accept="image/jpeg,image/png,image/webp"
                        required
                        onchange="previewPaymentProof(this)"
                    >

                </div>

            </div>

            <!-- Back (pabalik sa My Orders) + Submit, magkatabi -->
            <div class="balance-actions">
                <a href="my-orders.php" class="btn-back">Back</a>
                <button type="submit" class="btn-submit">Submit Balance Payment</button>
            </div>

        </form>

    </div>

</main>
<!-- ====================== END BALANCE PAYMENT ====================== -->


<!-- ====================== QR LIGHTBOX (css: pay-balance.css [8] | js: [3c]) ====================== -->

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
   [3a] SUBMIT  (confirm muna; isang beses lang maisusumite - iwas double click)
   ========================================================================== */
function submitBalancePayment(form) {

    if (form.dataset.submitting) {
        return false;
    }

    /* tanong muna bago i-submit (shared pop up - Shared/popup-and-security.php) */
    if (window.csConfirmSubmit && !csConfirmSubmit(form, "Submit this payment?\nMake sure your screenshot is correct.", { okText: "Submit", cancelText: "Back" })) {
        return false;
    }

    form.dataset.submitting = "1";

    var submitButton = form.querySelector("button[type=submit]");

    if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = "Submitting payment…";
    }

    return true;

}
/* ====================== END SUBMIT ====================== */


/* ==========================================================================
   [3b] PREVIEW NG PROOF OF PAYMENT
   ========================================================================== */
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

/* ====================== END PREVIEW NG PROOF OF PAYMENT ====================== */


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
</script>
<!-- ====================== END SCRIPT ====================== -->

</body>
</html>
<!-- ====================== END HTML ====================== -->
