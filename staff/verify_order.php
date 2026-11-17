<?php

/* ==========================================================================
   STAFF: VERIFY ORDER   (staff/verify_order.php)
   Action ng Verification page ng staff: i-verify ang details at ipasa sa owner, o i-decline.
   ========================================================================== */

require_once __DIR__ . "/../helpers/security_helper.php";
figurify_start_session();

require_once __DIR__ . "/../Admin/database.php";
require_once __DIR__ . "/../helpers/messaging_helper.php";
require_once __DIR__ . "/../helpers/order_helper.php";

figurify_require_role("staff");

/* STAFF - Verification page.
   Sinusuri ng staff kung tama ang order details, customer info at payment info.
   Tama -> Verify -> ipapasa sa owner ("for approval").
   Mali -> Decline na may dahilan -> babalik sa customer.
   Ang owner ang nagkukumpirma kung pumasok talaga ang bayad. */

$order_id = isset($_POST["order_id"]) ? (int) $_POST["order_id"] : 0;
$action   = isset($_POST["action"]) ? strtolower(trim((string) $_POST["action"])) : "";
$backUrl  = "verification.php" . ($order_id > 0 ? "?order_id=" . $order_id : "");

figurify_wf_require_post_csrf($conn, $backUrl, $order_id);

if ($action === "reject") {

    /* DECLINE: mali ang details/info -> babalik sa customer (Payment Required)
       kasama ang dahilan. Hindi ito pagpapasya sa bayad - iyon ay sa Owner. */
    $reasonKey = trim((string) ($_POST["decline_reason"] ?? ""));
    $otherText = mb_substr(trim((string) ($_POST["decline_reason_other"] ?? "")), 0, 190);
    $isOrderFix = isset(FIGURIFY_WF_ORDER_FIX_REASONS[$reasonKey]);   /* mali ang order details -> customer ang mag-e-edit */
    $reason    = FIGURIFY_WF_ORDER_FIX_REASONS[$reasonKey] ?? FIGURIFY_WF_DETAILS_DECLINE_REASONS[$reasonKey] ?? (($reasonKey === "other") ? $otherText : "");

    if ($reason === "") {
        figurify_flash("Pick a reason first.", "error");
        $conn->close();
        header("Location: " . $backUrl);
        exit();
    }

    $result = figurify_wf_perform($conn, $order_id, $isOrderFix ? "return_paid_for_correction" : "decline_payment_details", ["reason" => $reason]);

    if (!$result["ok"]) {
        figurify_flash($result["error"], "error");
        $conn->close();
        header("Location: " . $backUrl);
        exit();
    }

    figurify_customer_notice(
        $conn,
        (int) $result["order"]["user_id"],
        (int) $order_id,
        $isOrderFix
            ? "Please update the details of Order #" . $order_id . ": " . $reason . ". Click Fix My Order in My Orders. Your payment is kept."
            : "Your payment details for Order #" . $order_id . " were declined: " . $reason . ". Please submit them again in My Orders.",
        $isOrderFix ? "Please update your Order #" . $order_id : "Action needed: Payment details for Order #" . $order_id,
        [
            $isOrderFix
                ? "Please update the order details of <strong>Order #" . $order_id . "</strong>. Your payment is kept."
                : "We couldn’t accept your payment details for <strong>Order #" . $order_id . "</strong>.",
            "<strong>Reason:</strong> " . figurify_email_e($reason),
            $isOrderFix ? "Go to <strong>My Orders</strong> and click <strong>Fix My Order</strong>." : "Please submit them again in <strong>My Orders</strong>.",
        ]
    );

    figurify_notify_staff(
        $conn,
        $order_id,
        "Payment details for order " . $order_id . " were declined by staff (" . $reason . ") and sent back to the customer.",
        (int) $_SESSION["user_id"]
    );

    figurify_flash("Order #" . $order_id . " declined. The customer was notified.", "success");
    $conn->close();
    header("Location: verification.php");
    exit();
}

if ($action !== "approve") {
    figurify_flash("Invalid request.", "error");
    $conn->close();
    header("Location: " . $backUrl);
    exit();
}

$result = figurify_wf_perform($conn, $order_id, "verify_payment_details");

if (!$result["ok"]) {
    figurify_flash($result["error"], "error");
    $conn->close();
    header("Location: " . $backUrl);
    exit();
}


figurify_notify_user(
    $conn,
    (int) $result["order"]["user_id"],
    $order_id,
    "Your payment details for Order #" . $order_id . " were checked. Waiting for payment approval.",
    0,
    null,
    false
);

figurify_email_payment_verified($conn, (int) $result["order"]["user_id"], (int) $order_id);

figurify_notify_staff(
    $conn,
    $order_id,
    "Payment details for order " . $order_id . " were verified by staff and are waiting for the owner's approval.",
    (int) $_SESSION["user_id"]
);

figurify_flash("Order #" . $order_id . " verified.", "success");

$conn->close();

header("Location: verification.php");
exit();

/* ==================== END STAFF: VERIFY ORDER ==================== */
