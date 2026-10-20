<?php

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

require_once __DIR__ . "/../Admin/database.php";
define("ALL_IN_ONE_FUNCTIONS_ONLY", true); // [ALL-IN-ONE] pop up functions lang (walang HTML) - redirect file ito
require_once __DIR__ . "/../Shared/all-in-one.php";
require_once __DIR__ . "/../helpers/notification_helper.php";
require_once __DIR__ . "/../helpers/access_helper.php";
require_once __DIR__ . "/../helpers/order_workflow.php";

figurify_require_role("admin");

/* OWNER LANG - dito nagpapasya sa bayad (down payment at balance).
   Ang status sa DB ang nagsasabi kung down payment o balance ito.
   approve -> approve_payment / approve_balance
   reject  -> reject_payment / reject_balance (pwedeng mag-upload ulit ang customer)
           -> decline_payment_details (mali ang details, babalik sa customer)
           -> cancel_paid_order (hindi kayang gawin ang order -> For Refund) */

$order_id = isset($_POST["order_id"]) ? (int) $_POST["order_id"] : 0;
$action   = isset($_POST["action"]) ? strtolower(trim((string) $_POST["action"])) : "";
$source   = isset($_POST["source"]) ? strtolower(trim((string) $_POST["source"])) : "";

$backPage = in_array($source, ["for_approval", "for_balance_approval"], true) ? "for-approval.php" : "verification.php";
$backUrl  = $backPage . "?order_id=" . $order_id;

figurify_wf_require_post_csrf($conn, $backUrl, $order_id);


/* Mga dahilan: payment-related = makakapag-upload ulit ang customer.
   Order-related = kanselado na ang order, at dahil nakapagbayad na,
   For Refund. */

$paymentReasonLabels = [
    "insufficient_down_payment"    => "Insufficient Down Payment",
    "no_down_payment_received"     => "No Down Payment Received",
    "insufficient_balance_payment" => "Insufficient Balance Payment",
    "no_balance_payment_received"  => "No Balance Payment Received",
    "unclear_payment_proof"        => "Payment Proof Is Unclear or Invalid",
    "reference_mismatch"           => "Reference Number Does Not Match",
];

/* Verification-stage Decline reasons (mali ang details) */
$paymentReasonLabels = FIGURIFY_WF_DETAILS_DECLINE_REASONS + $paymentReasonLabels;

$orderReasonLabels = [
    "incomplete_order_details" => "Incomplete Order Details",
    "incorrect_order_details"  => "Incorrect Order Details",
    "unable_to_fulfill"        => "Unable to Fulfill the Order",
    "booking_date_full"        => "Booking Date Is Full",
];

$reasonKey  = isset($_POST["decline_reason"]) ? trim((string) $_POST["decline_reason"]) : "";
$otherText  = mb_substr(trim((string) ($_POST["decline_reason_other"] ?? "")), 0, 190);

$reasonLabel     = null;
$isOrderProblem  = false;

if (isset($paymentReasonLabels[$reasonKey])) {
    $reasonLabel = $paymentReasonLabels[$reasonKey];
} elseif (isset($orderReasonLabels[$reasonKey])) {
    $reasonLabel    = $orderReasonLabels[$reasonKey];
    $isOrderProblem = true;
} elseif ($reasonKey === "other" && $otherText !== "") {
    // "Other" = payment problem (ligtas na default: makakapag-upload ulit ang customer)
    $reasonLabel = $otherText;
} elseif ($reasonKey === "other_cancel" && $otherText !== "") {
    $reasonLabel    = $otherText;
    $isOrderProblem = true;
}


/* Alamin kung downpayment o balance base sa totoong state */

$check = ($order_id > 0) ? figurify_wf_can($conn, $order_id, "approve_balance") : ["ok" => false];
$isBalance = !empty($check["ok"]);

/* Downpayment: kung hindi pa na-verify ang details (Verification page),
   ang "approve" ng owner dito ay ang pag-verify ng details (gaya ng staff);
   ang tunay na pag-approve ng bayad ay sa For Approval page. */
$detailsCheck   = ($order_id > 0 && !$isBalance) ? figurify_wf_can($conn, $order_id, "verify_payment_details") : ["ok" => false];
$isDetailsCheck = !empty($detailsCheck["ok"]);

if ($action === "approve") {

    $wfAction = $isBalance ? "approve_balance" : ($isDetailsCheck ? "verify_payment_details" : "approve_payment");
    $params   = [];

} elseif ($action === "reject") {

    if ($reasonLabel === null) {
        figurify_flash("Pick a reason first.", "error");
        header("Location: " . $backUrl);
        exit();
    }

    if ($isBalance) {
        $wfAction = "reject_balance";
    } else {
        // sa Verification (hindi pa verified): DECLINE -> babalik sa customer na may dahilan
        $wfAction = $isOrderProblem ? "cancel_paid_order" : ($isDetailsCheck ? "decline_payment_details" : "reject_payment");
    }

    $params = ["reason" => $reasonLabel];

} else {

    figurify_flash("Invalid request.", "error");
    header("Location: " . $backUrl);
    exit();
}

$result = figurify_wf_perform($conn, $order_id, $wfAction, $params);

if (!$result["ok"]) {

    figurify_flash($result["error"], "error");
    $conn->close();
    header("Location: " . $backUrl);
    exit();
}

$customer_id = (int) $result["order"]["user_id"];

/* Napuno ng approved orders ang petsa? Aabisuhan ang iba pang order sa petsang iyon para makapili sila
   ng ibang petsa o makapag-cancel (hindi na kusang kina-cancel). Dito (pag-approve ng owner) lang
   binibilang ang slot, hindi na sa pag-submit ng bayad. */
if ($wfAction === "approve_payment" && !empty($result["order"]["booking_date"])) {
    require_once __DIR__ . "/../helpers/expire_helper.php";
    figurify_notify_full_date_orders($conn, (string) $result["order"]["order_type"], (string) $result["order"]["booking_date"], $order_id);
}

require_once __DIR__ . "/../helpers/email_template_helper.php";

$reasonHtml = figurify_email_e($reasonLabel ?? "");
$emailFn    = "";   /* may sariling template function */
$emailSubj  = "";
$emailParas = [];

switch ($wfAction) {

    case "verify_payment_details":
        $message = "Your payment details for Order #" . $order_id . " were checked. Waiting for the owner's approval.";
        $emailFn = "verified";
        $flash   = "Order " . $order_id . " moved to For Approval.";
        $peer    = "Payment details for order " . $order_id . " were verified by the owner — ready for approval.";
        $backUrl = "for-approval.php?order_id=" . $order_id;
        break;

    case "approve_payment":
        $message = "Your payment for Order #" . $order_id . " was approved. Your order is now processing!";
        $emailFn = "approved";
        $flash   = "Payment approved. Order " . $order_id . " is now Processing.";
        $peer    = "Payment for order " . $order_id . " was approved by the owner — ready for production.";
        $backUrl = "for-approval.php";
        break;

    case "approve_balance":
        $message    = "Your balance payment for Order #" . $order_id . " was approved. Your order is ready to ship!";
        $emailSubj  = "Your balance payment for Order #" . $order_id . " has been approved";
        $emailParas = [
            "Great news! Your balance payment for <strong>Order #" . $order_id . "</strong> has been approved by the owner, and your order is now ready to ship.",
            "We’ll notify you again once your order has been shipped, together with your tracking number.",
            figurify_email_status_line(),
            "Thank you for choosing Clay and Stuff!",
        ];
        $flash   = "Balance approved. Order " . $order_id . " is ready to ship.";
        $peer    = "Balance for order " . $order_id . " was approved by the owner — ready to ship.";
        break;

    case "reject_payment":
        $message    = "We couldn't verify your payment for Order #" . $order_id . ": " . $reasonLabel . ". Please upload a new proof in My Orders.";
        $emailSubj  = "Action needed: Payment for Order #" . $order_id;
        $emailParas = [
            "We reviewed your payment for <strong>Order #" . $order_id . "</strong>, but unfortunately we were unable to verify it.",
            "<strong>Reason:</strong> " . $reasonHtml,
            "Please log in to your account, go to the <strong>My Orders</strong> page, and submit your payment proof again.",
            "You can also view this notification on your <strong>Notifications</strong> page.",
            "Thank you for choosing Clay and Stuff!",
        ];
        $flash   = "Payment for order " . $order_id . " rejected. The customer was notified.";
        $peer    = "Payment for order " . $order_id . " was rejected by the owner (" . $reasonLabel . ").";
        break;

    case "decline_payment_details":
        $message    = "Your payment details for Order #" . $order_id . " were declined: " . $reasonLabel . ". Please submit them again in My Orders.";
        $emailSubj  = "Action needed: Payment details for Order #" . $order_id;
        $emailParas = [
            "We reviewed your payment details for <strong>Order #" . $order_id . "</strong>, but unfortunately we were unable to accept them.",
            "<strong>Reason:</strong> " . $reasonHtml,
            "Please log in to your account, go to the <strong>My Orders</strong> page, and submit your payment details again.",
            "You can also view this notification on your <strong>Notifications</strong> page.",
            "Thank you for choosing Clay and Stuff!",
        ];
        $flash   = "Order " . $order_id . " declined. The customer was notified.";
        $peer    = "Payment details for order " . $order_id . " were declined by the owner (" . $reasonLabel . ") — sent back to the customer.";
        break;

    case "reject_balance":
        $message    = "We couldn't verify your balance payment for Order #" . $order_id . ": " . $reasonLabel . ". Please upload a new proof in My Orders.";
        $emailSubj  = "Action needed: Balance payment for Order #" . $order_id;
        $emailParas = [
            "We reviewed your balance payment for <strong>Order #" . $order_id . "</strong>, but unfortunately we were unable to verify it.",
            "<strong>Reason:</strong> " . $reasonHtml,
            "Please log in to your account, go to the <strong>My Orders</strong> page, click <strong>Pay Balance</strong>, and upload your proof of payment again.",
            "You can also view this notification on your <strong>Notifications</strong> page.",
            "Thank you for choosing Clay and Stuff!",
        ];
        $flash   = "Balance for order " . $order_id . " rejected. The customer was notified.";
        $peer    = "Balance for order " . $order_id . " was rejected by the owner (" . $reasonLabel . ").";
        break;

    default: // cancel_paid_order
        $message    = "Order #" . $order_id . " was cancelled: " . $reasonLabel . ". Your refund will be sent within 7–14 business days.";
        $emailSubj  = "Your Order #" . $order_id . " has been cancelled";
        $emailParas = [
            "We’re sorry, but we are unable to process <strong>Order #" . $order_id . "</strong>, and it has been cancelled.",
            "<strong>Reason:</strong> " . $reasonHtml,
            "Since you have already submitted a payment, the owner will review it and send your refund to the refund account you provided within <strong>7–14 business days</strong>.",
            figurify_email_status_line(),
            "Thank you for your understanding!",
        ];
        $flash   = "Order " . $order_id . " cancelled and moved to For Refund.";
        $peer    = "Order " . $order_id . " was cancelled by the owner and moved to For Refund.";
        break;
}

/* system: maikling notification - Gmail: pormal na email */
figurify_notify_user($conn, $customer_id, $order_id, $message, 0, null, false);

if ($emailFn === "verified") {
    figurify_email_payment_verified($conn, $customer_id, (int) $order_id);
} elseif ($emailFn === "approved") {
    figurify_email_payment_approved($conn, $customer_id, (int) $order_id);
} else {
    figurify_email_customer($conn, $customer_id, $emailSubj, $emailParas);
}

figurify_notify_staff($conn, $order_id, $peer, (int) $_SESSION["user_id"]);
figurify_flash($flash, "success");

$conn->close();

header("Location: " . $backUrl);
exit();
