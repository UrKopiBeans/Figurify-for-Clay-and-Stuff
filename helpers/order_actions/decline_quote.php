<?php

/* STAFF/OWNER - tanggihan ang order request bago pa magbayad
   (Order Being Reviewed / Correction Required -> Cancelled). */

require_once __DIR__ . "/_bootstrap.php";

$order_id = isset($_POST["order_id"]) ? (int) $_POST["order_id"] : 0;
$backUrl  = "quotation.php?order_id=" . $order_id;

figurify_wf_require_post_csrf($conn, $backUrl, $order_id);

$labels = [
    "incomplete_order_details" => "Incomplete Order Details",
    "unable_to_fulfill"        => "Unable to Fulfill the Order",
    "out_of_scope"             => "Outside Our Services",
];

$key   = trim((string) ($_POST["decline_reason"] ?? ""));
$other = mb_substr(trim((string) ($_POST["decline_reason_other"] ?? "")), 0, 190);

$reason = ($key === "other") ? $other : ($labels[$key] ?? "");

if ($reason === "") {
    figurify_action_done($conn, $backUrl, "Pick a reason first.", "error");
}

$result = figurify_wf_perform($conn, $order_id, "decline_order", ["reason" => $reason]);

if (!$result["ok"]) {
    figurify_action_done($conn, $backUrl, $result["error"], "error");
}

require_once __DIR__ . "/../email_template_helper.php";

figurify_customer_notice(
    $conn,
    (int) $result["order"]["user_id"],
    $order_id,
    "Sorry, we can't proceed with Order #" . $order_id . ": " . $reason . ".",
    "Update on your Order #" . $order_id,
    [
        "Thank you for your order. Unfortunately, we are unable to proceed with <strong>Order #" . $order_id . "</strong>, and it has been cancelled.",
        "<strong>Reason:</strong> " . figurify_email_e($reason),
        "If you believe this is a mistake or have any questions, please contact us. You are always welcome to place a new order.",
        figurify_email_status_line(),
        "Thank you for your understanding!",
    ]
);

figurify_notify_staff(
    $conn,
    $order_id,
    "Order " . $order_id . " was declined by " . $figurifyActorLabel . ".",
    (int) $_SESSION["user_id"]
);

figurify_action_done($conn, $backUrl, "Order " . $order_id . " declined. The customer was notified.");
