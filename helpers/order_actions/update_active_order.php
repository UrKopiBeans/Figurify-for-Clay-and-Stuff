<?php

/* STAFF/OWNER - actions sa Active Booking at Shipments:
   - finalize: CUSTOMER_APPROVED -> AWAITING_BALANCE (o READY_TO_SHIP kung bayad na lahat)
     ang balance ay galing sa quotation (total - 50% down payment)
   - ship_out: READY_TO_SHIP -> SHIPPED (may checklist)
   - complete_order: SHIPPED -> COMPLETED */

require_once __DIR__ . "/_bootstrap.php";
require_once __DIR__ . "/../review_helper.php";
require_once __DIR__ . "/../email_template_helper.php";

$order_id   = isset($_POST["order_id"]) ? (int) $_POST["order_id"] : 0;
$action     = strtolower(trim((string) ($_POST["action"] ?? "")));
$redirectTo = strtolower(trim((string) ($_POST["redirect_to"] ?? "active-booking")));

$backUrl = (($redirectTo === "shipments") ? "shipments.php" : "active-booking.php") . "?order_id=" . $order_id;

figurify_wf_require_post_csrf($conn, $backUrl, $order_id);

$actionMap = [
    "request_balance" => "finalize_order",
    "finalize"        => "finalize_order",
    "ship_out"        => "ship_order",
    "complete_order"  => "complete_order",
];

if (!isset($actionMap[$action])) {

    figurify_wf_log_activity_row(
        $conn,
        figurify_wf_session_actor(),
        "unauthorized_attempt",
        "Blocked unsupported action \"" . mb_substr($action, 0, 40) . "\""
            . (isset($_POST["status"]) ? (" with status \"" . mb_substr((string) $_POST["status"], 0, 40) . "\"") : "")
            . " via update_active_order.php",
        $order_id > 0 ? $order_id : null
    );

    figurify_action_done($conn, $backUrl . "&progress_error=" . urlencode("Direct status changes are not allowed."));
}

$wfAction = $actionMap[$action];

$params = [];

if ($wfAction === "ship_order") {
    $params = [
        "tracking_number" => $_POST["tracking_number"] ?? "",
        "shipping_fee"    => $_POST["shipping_fee"] ?? "",
    ];
}

/* resibo ng courier (optional) sa Ship Out */
$shipReceiptPath = null;

if ($wfAction === "ship_order" && isset($_FILES["shipping_receipt"]) && ($_FILES["shipping_receipt"]["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {

    [$shipReceiptPath, $shipReceiptError] = figurify_save_uploaded_image(
        $_FILES["shipping_receipt"],
        "uploads/shipping_receipts",
        "receipt_" . $order_id
    );

    if ($shipReceiptPath === null) {
        figurify_action_done($conn, $backUrl, $shipReceiptError ?: "Please upload a valid receipt photo.", "error");
    }
}

$result = figurify_wf_perform($conn, $order_id, $wfAction, $params);

if (!$result["ok"] && $shipReceiptPath !== null) {
    figurify_delete_upload($shipReceiptPath);
    $shipReceiptPath = null;
}

if ($result["ok"] && $shipReceiptPath !== null) {

    /* idagdag ang column kung wala pa (best-effort), saka i-save ang path */
    $saveReceipt = function () use ($conn, $shipReceiptPath, $order_id): bool {
        try {
            $stmt = $conn->prepare("UPDATE orders SET shipping_receipt = ? WHERE order_id = ?");
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param("si", $shipReceiptPath, $order_id);
            $ok = $stmt->execute();
            $stmt->close();
            return $ok;
        } catch (Throwable $e) {
            return false;
        }
    };

    if (!$saveReceipt()) {
        try {
            $conn->query("ALTER TABLE orders ADD COLUMN shipping_receipt VARCHAR(255) NULL AFTER tracking_number");
        } catch (Throwable $e) {
            /* baka meron na - itutuloy pa rin */
        }
        $saveReceipt();
    }
}

if (!$result["ok"]) {
    // Shipments page ay walang progress_error display - flash ang gamit doon
    if ($redirectTo === "shipments") {
        figurify_action_done($conn, $backUrl, $result["error"], "error");
    }
    figurify_action_done($conn, $backUrl . "&progress_error=" . urlencode($result["error"]));
}

$customerId = (int) $result["order"]["user_id"];

if ($wfAction === "finalize_order") {

    if ($result["to_status"] === "awaiting_balance") {

        $balance = (float) $result["data"]["balance_due"];

        figurify_customer_notice(
            $conn,
            $customerId,
            $order_id,
            "Order #" . $order_id . " is finalized. Please pay the remaining balance of ₱" . number_format($balance, 2) . " in My Orders.",
            "Remaining balance for your Order #" . $order_id,
            [
                "Your customized figure for <strong>Order #" . $order_id . "</strong> has been finalized.",
                "<strong>Remaining Balance:</strong> ₱" . number_format($balance, 2),
                "Please log in to your account, go to the <strong>My Orders</strong> page, and click <strong>Pay Balance</strong>. Pay through the QR code and upload your proof of payment. We will ship your order once your balance payment has been approved.",
                "You can also view this notification on your <strong>Notifications</strong> page.",
                "Thank you for choosing Clay and Stuff!",
            ]
        );

        figurify_notify_staff($conn, $order_id, "Order " . $order_id . " was finalized — waiting for the customer's balance payment.", (int) $_SESSION["user_id"]);

        figurify_action_done($conn, $backUrl, "Order " . $order_id . " finalized. Balance of ₱" . number_format($balance, 2) . " requested.");
    }

    figurify_customer_notice(
        $conn,
        $customerId,
        $order_id,
        "Order #" . $order_id . " is finalized and ready to ship.",
        "Your Order #" . $order_id . " is ready to ship",
        [
            "Good news! <strong>Order #" . $order_id . "</strong> has been finalized and is now ready to ship.",
            "We’ll notify you again once your order has been shipped, together with your tracking number.",
            figurify_email_status_line(),
            "Thank you for choosing Clay and Stuff!",
        ]
    );
    figurify_notify_staff($conn, $order_id, "Order " . $order_id . " is ready to ship.", (int) $_SESSION["user_id"]);

    figurify_action_done($conn, $backUrl, "Order " . $order_id . " finalized and ready to ship.");
}

if ($wfAction === "ship_order") {

    $tracking = trim((string) $params["tracking_number"]);

    $courierRow = $conn->query("SELECT courier FROM orders WHERE order_id = " . (int) $order_id)->fetch_assoc();
    $courierName = strtoupper(trim((string) ($courierRow["courier"] ?? "")));

    figurify_customer_notice(
        $conn,
        $customerId,
        $order_id,
        "Order #" . $order_id . " has been shipped! Tracking number: " . $tracking . ". Tap Order Received once it arrives.",
        "Your Order #" . $order_id . " has been shipped",
        [
            "Great news! <strong>Order #" . $order_id . "</strong> has been shipped and is on its way to you.",
            ($courierName !== "" ? "<strong>Courier:</strong> " . figurify_email_e($courierName) . "<br>" : "")
                . "<strong>Tracking Number:</strong> " . figurify_email_e($tracking),
            "You can use the tracking number on the courier’s website or app to follow your package.",
            "Once you receive your order, please log in to your account, go to the <strong>My Orders</strong> page, and click <strong>Order Received</strong>. "
                . "After that, you can leave a review of your figure. If you don’t click it, your order will be marked as received automatically after <strong>7 days</strong>.",
            figurify_email_status_line(),
            "Thank you for choosing Clay and Stuff! We hope you love your customized figure.",
        ]
    );

    figurify_notify_staff($conn, $order_id, "Order " . $order_id . " was shipped out.", (int) $_SESSION["user_id"]);

    figurify_action_done($conn, $backUrl, "Order " . $order_id . " shipped. The customer was notified.");
}


/* complete_order - may "Leave a Review" email (helpers/order_complete_helper.php) */

require_once __DIR__ . "/../order_complete_helper.php";

figurify_order_completed_notice($conn, (int) $order_id, (int) $customerId, "staff", (int) $_SESSION["user_id"]);

figurify_action_done($conn, $backUrl, "Order " . $order_id . " completed.");
