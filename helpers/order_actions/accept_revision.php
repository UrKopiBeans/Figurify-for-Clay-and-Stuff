<?php

/* STAFF/OWNER - REVISION_REQUESTED -> REVISION_IN_PROGRESS */

require_once __DIR__ . "/_bootstrap.php";

$order_id  = isset($_POST["order_id"]) ? (int) $_POST["order_id"] : 0;
$update_id = isset($_POST["update_id"]) ? (int) $_POST["update_id"] : 0;
$backUrl   = "active-booking.php?order_id=" . $order_id;

figurify_wf_require_post_csrf($conn, $backUrl, $order_id);

$result = figurify_wf_perform($conn, $order_id, "accept_revision", ["update_id" => $update_id]);

if (!$result["ok"]) {
    figurify_action_done($conn, $backUrl . "&progress_error=" . urlencode($result["error"]));
}

require_once __DIR__ . "/../email_template_helper.php";

$revBalance = (float) ($result["data"]["balance_due"] ?? 0);

figurify_customer_notice(
    $conn,
    (int) $result["order"]["user_id"],
    $order_id,
    "Your revision request for Order #" . $order_id . " was accepted."
        . ($revBalance > 0 ? (" Please pay the remaining balance of ₱" . number_format($revBalance, 2) . " in My Orders.") : " We're working on it now."),
    "Your revision request for Order #" . $order_id . " has been accepted",
    [
        "Good news! Your revision request for <strong>Order #" . $order_id . "</strong> has been accepted, and our team is now working on the requested changes.",
        "Once the revisions are complete, we’ll send you an updated photo of your customized figure.",
        $revBalance > 0
            ? ("<strong>Remaining Balance:</strong> ₱" . number_format($revBalance, 2) . "<br>Please log in to your account, go to the <strong>My Orders</strong> page, and click <strong>Pay Balance</strong> to complete your payment. We will ship your order once your balance payment has been approved.")
            : "",
        figurify_email_status_line(),
        "Thank you for your patience and understanding!",
    ]
);

figurify_notify_staff($conn, $order_id, "Revision for order " . $order_id . " was accepted by " . $figurifyActorLabel . ".", (int) $_SESSION["user_id"]);

figurify_action_done($conn, $backUrl, "Revision accepted for order " . $order_id . ".");
