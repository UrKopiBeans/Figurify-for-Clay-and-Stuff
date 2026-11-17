<?php

/* ==========================================================================
   OWNER: PROCESS REFUND   (owner/process_refund.php)
   Nagre-record ng refund (Full, Partial o No Payment Received) mula sa Refunds page.
   ========================================================================== */

require_once __DIR__ . "/../helpers/security_helper.php";
figurify_start_session();

require_once __DIR__ . "/../Admin/database.php";
require_once __DIR__ . "/../helpers/messaging_helper.php";
require_once __DIR__ . "/../helpers/order_helper.php";

// OWNER LANG ang makakagawa ng refund (pati ang workflow layer ay owner-only)
figurify_require_role("admin");

/* REFUND PROCESS - lahat dumadaan sa helpers/order_helper.php
   action:
   - start: start_refund (Active Bookings, Shipments o Completed Orders na may bayad -> Refund Required)
   - complete: complete_refund (Refund Required -> Refunded, may halaga + ref no. + proof)
   - no_payment: refund_no_payment (Refund Required -> Cancelled, walang pumasok na bayad) */

$order_id = isset($_POST["order_id"]) ? (int) $_POST["order_id"] : 0;
$action   = strtolower(trim((string) ($_POST["action"] ?? "")));
$from     = trim((string) ($_POST["from"] ?? ""));

function figurify_refund_redirect(mysqli $conn, string $page, int $orderId, string $tab = ""): void
{
    $query = [];

    if ($tab !== "") {
        $query[] = "tab=" . urlencode($tab);
    }
    if ($orderId > 0) {
        $query[] = "order_id=" . $orderId;
    }

    $conn->close();
    header("Location: " . $page . (empty($query) ? "" : "?" . implode("&", $query)));
    exit();
}

figurify_wf_require_post_csrf($conn, "refunds.php" . ($order_id > 0 ? "?order_id=" . $order_id : ""), $order_id);

if ($order_id <= 0 || !in_array($action, ["start", "complete", "no_payment"], true)) {
    figurify_flash("Invalid refund request.", "error");
    figurify_refund_redirect($conn, "refunds.php", 0);
}


/* START REFUND */

if ($action === "start") {

    $backPages = ["completed" => "completed.php", "shipments" => "shipments.php"];
    $back      = $backPages[$from] ?? "active-booking.php";

    $startReasonLabels = [
        "not_completed_by_ship_out_date" => "Order Not Completed by the Ship Out Date",
        "unable_to_fulfill"             => "Unable to Fulfill the Order",
        "customer_cancelled"            => "Customer Cancelled the Order",
        "defective_or_incorrect"        => "Defective or Incorrect Order",
        "damaged_during_shipping"       => "Damaged During Shipping",
    ];

    $reasonKey = trim((string) ($_POST["refund_reason"] ?? ""));
    $reason    = ($reasonKey === "other")
        ? mb_substr(trim((string) ($_POST["refund_reason_other"] ?? "")), 0, 190)
        : ($startReasonLabels[$reasonKey] ?? "");

    if ($reason === "") {
        figurify_flash("Pick a reason first.", "error");
        figurify_refund_redirect($conn, $back, $order_id);
    }

    $result = figurify_wf_perform($conn, $order_id, "start_refund", ["reason" => $reason]);

    if (!$result["ok"]) {
        figurify_flash($result["error"], "error");
        figurify_refund_redirect($conn, $back, $order_id);
    }

    figurify_customer_notice(
        $conn,
        (int) $result["order"]["user_id"],
        (int) $order_id,
        "Order #" . $order_id . " will be refunded: " . $reason . ". Expect your refund within 7 to 14 business days.",
        "Refund for your Order #" . $order_id,
        [
            "<strong>Order #" . $order_id . "</strong> will be refunded.",
            "<strong>Reason:</strong> " . figurify_email_e($reason),
            "Expect it in your refund account within <strong>7 to 14 business days</strong>.",
            figurify_email_status_line(),
        ]
    );

    figurify_notify_staff($conn, $order_id, "Order #" . $order_id . " was moved to For Refund by the owner.", (int) $_SESSION["user_id"]);

    figurify_flash("Order #" . $order_id . " moved to For Refund.", "success");
    figurify_refund_redirect($conn, "refunds.php", $order_id, "for_refund");
}


/* NO PAYMENT */

if ($action === "no_payment") {

    $result = figurify_wf_perform($conn, $order_id, "refund_no_payment");

    if (!$result["ok"]) {
        figurify_flash($result["error"], "error");
        figurify_refund_redirect($conn, "refunds.php", $order_id, "for_refund");
    }

    $reasonText = !empty($result["order"]["refund_reason"]) ? (" Reason: " . $result["order"]["refund_reason"] . ".") : "";

    figurify_customer_notice(
        $conn,
        (int) $result["order"]["user_id"],
        (int) $order_id,
        "Order #" . $order_id . " has been cancelled." . $reasonText . " No payment was received, so there's nothing to refund.",
        "Your Order #" . $order_id . " has been cancelled",
        [
            "<strong>Order #" . $order_id . "</strong> has been cancelled." . figurify_email_e($reasonText),
            "We received no payment for this order, so there’s nothing to refund.",
            figurify_email_status_line(),
        ]
    );

    figurify_flash("Order #" . $order_id . " cancelled.", "success");
    figurify_refund_redirect($conn, "refunds.php", 0, "for_refund");
}


/* COMPLETE */

$can = figurify_wf_can($conn, $order_id, "complete_refund");

if (!$can["ok"]) {
    figurify_wf_perform($conn, $order_id, "complete_refund"); // itala ang denied attempt
    figurify_flash($can["error"], "error");
    figurify_refund_redirect($conn, "refunds.php", $order_id, "for_refund");
}

/* proof ng refund (screenshot ng GCash/BPI transfer) - required, totoong image lang */

$refundProofPath = null;
$allowedImageMimes = ["image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp"];

if (
    isset($_FILES["refund_proof"]) &&
    $_FILES["refund_proof"]["error"] === UPLOAD_ERR_OK &&
    $_FILES["refund_proof"]["size"] > 0 &&
    $_FILES["refund_proof"]["size"] <= 5 * 1024 * 1024 &&
    is_uploaded_file($_FILES["refund_proof"]["tmp_name"])
) {
    $mimeType = mime_content_type($_FILES["refund_proof"]["tmp_name"]);

    if (isset($allowedImageMimes[$mimeType]) && @getimagesize($_FILES["refund_proof"]["tmp_name"]) !== false) {

        $fileName  = "refund_" . $order_id . "_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $allowedImageMimes[$mimeType];
        $uploadDir = __DIR__ . "/../uploads/refund_proofs/";

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        if (move_uploaded_file($_FILES["refund_proof"]["tmp_name"], $uploadDir . $fileName)) {
            $refundProofPath = "uploads/refund_proofs/" . $fileName;
        }
    }
}

if ($refundProofPath === null) {
    figurify_flash("Upload the refund proof (JPG, PNG or WEBP, max 5MB).", "error");
    figurify_refund_redirect($conn, "refunds.php", $order_id, "for_refund");
}

$result = figurify_wf_perform($conn, $order_id, "complete_refund", [
    "refund_type"      => strtolower(trim((string) ($_POST["refund_type"] ?? ""))),
    "refund_amount"    => $_POST["refund_amount"] ?? "",
    "refund_reference" => $_POST["refund_reference"] ?? "",
    "refund_proof"     => $refundProofPath,
]);

if (!$result["ok"]) {
    @unlink(__DIR__ . "/../" . $refundProofPath);
    figurify_flash($result["error"], "error");
    figurify_refund_redirect($conn, "refunds.php", $order_id, "for_refund");
}

$order      = $result["order"];
$amount     = (float) $result["data"]["amount"];
$refundType = strtolower(trim((string) $_POST["refund_type"]));
$reference  = mb_substr(trim((string) $_POST["refund_reference"]), 0, 100);
$methodText = !empty($order["refund_method"]) ? strtoupper((string) $order["refund_method"]) : "refund";

figurify_customer_notice(
    $conn,
    (int) $order["user_id"],
    (int) $order_id,
    "Your refund of ₱" . number_format($amount, 2) . " for Order #" . $order_id . " was sent. Reference No.: " . $reference . ".",
    "Your refund for Order #" . $order_id . " has been sent",
    [
        "Your " . figurify_email_e($refundType) . " refund for <strong>Order #" . $order_id . "</strong> has been sent.",
        "<strong>Amount:</strong> ₱" . number_format($amount, 2)
            . "<br><strong>Sent to:</strong> " . figurify_email_e($methodText) . " (" . figurify_email_e($order["refund_account_name"] ?? "") . ")"
            . "<br><strong>Reference No.:</strong> " . figurify_email_e($reference),
        "See the proof of refund in <strong>My Orders</strong>.",
    ]
);

figurify_notify_staff($conn, $order_id, "Order #" . $order_id . " has been refunded by the owner.", (int) $_SESSION["user_id"]);

figurify_flash("Refund for order " . $order_id . " saved. The customer was notified.", "success");
figurify_refund_redirect($conn, "refunds.php", $order_id, "refunded");

/* ==================== END OWNER: PROCESS REFUND ==================== */
