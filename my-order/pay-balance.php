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
    $balanceAlreadyPaidStatuses = ["to_verify_balance", "for_balance_approval", "to_ship", "shipped", "completed"];

    if (in_array(figurify_status_key($order["status"]), $balanceAlreadyPaidStatuses, true)) {
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

    <title>Pay Remaining Balance — Clay and Stuff</title>


    <link rel="stylesheet" href="pay-balance.css?v=2">
</head>
<body>

<?php include "../Shared/navbar.php"; ?>

<header class="page-hero">
    <div class="page-wrap">
        <span class="page-eyebrow">LAST STEP</span>
        <h1>Pay Remaining Balance</h1>
        <p>Please complete your remaining balance so we can ship out your order.</p>
    </div>
</header>

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
            onsubmit="if (this.dataset.submitting) { return false; } if (window.csConfirmSubmit &amp;&amp; !csConfirmSubmit(this, 'Submit this payment?\nMake sure your screenshot is correct.', { okText: 'Submit', cancelText: 'Back' })) { return false; } this.dataset.submitting = '1'; var b = this.querySelector('button[type=submit]'); if (b) { b.disabled = true; b.textContent = 'Submitting payment…'; } return true;"
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
                <strong>₱<?php echo fmod($balanceDue, 1.0) == 0.0 ? number_format($balanceDue, 0) : number_format($balanceDue, 2); ?></strong>
            </div>

            <div class="notice-box notice-box-warning">
                Please make sure the amount you pay matches the remaining balance above. An incorrect payment may delay your shipment.
            </div>

            <div class="payment-form-group">
                <label>Scan to Pay</label>

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
                <label for="balancePaymentReference">Reference / Transaction Number</label>
                <input
                    type="text"
                    name="payment_reference"
                    id="balancePaymentReference"
                    placeholder="e.g. GCash reference number"
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
                            style="display:none;"
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

            <button type="submit" class="btn-submit">Submit Balance Payment</button>

        </form>

    </div>

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

</script>

<?php include "../Shared/footer.php"; ?>

</body>
</html>
