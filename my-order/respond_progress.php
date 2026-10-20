<?php

/* CUSTOMER - sagot sa progress update.
   approved: PROGRESS_SUBMITTED -> CUSTOMER_APPROVED -> AWAITING_BALANCE (o READY_TO_SHIP)
   revision: PROGRESS_SUBMITTED -> REVISION_REQUESTED, isang beses lang (bantay ng backend) */

require_once __DIR__ . "/_customer_bootstrap.php";

$update_id = isset($_POST["update_id"]) ? (int) $_POST["update_id"] : 0;
$order_id  = isset($_POST["order_id"]) ? (int) $_POST["order_id"] : 0;
$response  = strtolower(trim((string) ($_POST["response"] ?? "")));
$note      = trim((string) ($_POST["revision_note"] ?? ""));

$backUrl = "my-orders.php" . ($order_id > 0 ? "?order_id=" . $order_id : "");

figurify_wf_require_post_csrf($conn, $backUrl, $order_id);

if ($response === "approved") {

    $result = figurify_wf_perform($conn, $order_id, "approve_progress", ["update_id" => $update_id]);

    if (!$result["ok"]) {
        figurify_customer_done($conn, $backUrl, $result["error"], "error");
    }

    if ($result["to_status"] === "awaiting_balance") {

        $balance = (float) $result["data"]["balance_due"];

        require_once __DIR__ . "/../helpers/email_template_helper.php";
        figurify_customer_notice(
            $conn,
            (int) $user_id,
            (int) $order_id,
            "Order #" . $order_id . " is finalized. Please pay the remaining balance of ₱" . number_format($balance, 2) . " in My Orders.",
            "Remaining balance for your Order #" . $order_id,
            [
                "Thank you for approving the progress of <strong>Order #" . $order_id . "</strong>! Your order has been finalized.",
                "<strong>Remaining Balance:</strong> ₱" . number_format($balance, 2),
                "Please log in to your account, go to the <strong>My Orders</strong> page, and click <strong>Pay Balance</strong>. Pay through the QR code and upload your proof of payment. We will ship your order once your balance payment has been approved.",
                "You can also view this notification on your <strong>Notifications</strong> page.",
                "Thank you for choosing Clay and Stuff!",
            ]
        );

        figurify_notify_staff($conn, $order_id, "Customer approved the progress for order " . $order_id . " — order finalized, waiting for balance payment.");

        figurify_customer_done($conn, $backUrl, "Order " . $order_id . " finalized! Please pay the balance of ₱" . number_format($balance, 2) . ".");
    }

    figurify_notify_staff($conn, $order_id, "Customer approved the progress for order " . $order_id . " — ready to ship.");

    figurify_customer_done($conn, $backUrl, "Order " . $order_id . " finalized and ready to ship!");
}

if ($response === "revision") {

    $result = figurify_wf_perform($conn, $order_id, "request_revision", ["update_id" => $update_id, "note" => $note]);

    if (!$result["ok"]) {
        figurify_customer_done($conn, $backUrl, $result["error"], "error");
    }

    figurify_notify_staff($conn, $order_id, "Customer requested their one (1) revision for order " . $order_id . ".");

    figurify_customer_done($conn, $backUrl, "Revision request sent! We'll get back to you soon.");
}

figurify_customer_done($conn, $backUrl, "Invalid request.", "error");
