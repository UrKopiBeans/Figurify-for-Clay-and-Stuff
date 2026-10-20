<?php

/* STAFF/OWNER - REVISION_REQUESTED -> PROCESSING (may dahilan).
   Nagamit na ang iisang revision ng customer (hindi ibinabalik). */

require_once __DIR__ . "/_bootstrap.php";

$order_id  = isset($_POST["order_id"]) ? (int) $_POST["order_id"] : 0;
$update_id = isset($_POST["update_id"]) ? (int) $_POST["update_id"] : 0;
$backUrl   = "active-booking.php?order_id=" . $order_id;

figurify_wf_require_post_csrf($conn, $backUrl, $order_id);

$labels = [
    "unable_to_replicate"        => "Unable to Match the Requested Change",
    "requires_additional_cost"   => "Requires Additional Cost",
    "beyond_material_capability" => "Beyond Our Material/Process Capability",
    "would_delay_completion"     => "Would Significantly Delay Completion",
];

$key    = trim((string) ($_POST["decline_reason"] ?? ""));
$other  = mb_substr(trim((string) ($_POST["decline_reason_other"] ?? "")), 0, 190);
$reason = ($key === "other") ? $other : ($labels[$key] ?? "");

$result = figurify_wf_perform($conn, $order_id, "decline_revision", ["update_id" => $update_id, "reason" => $reason]);

if (!$result["ok"]) {
    figurify_action_done($conn, $backUrl . "&progress_error=" . urlencode($result["error"]));
}

require_once __DIR__ . "/../email_template_helper.php";

figurify_customer_notice(
    $conn,
    (int) $result["order"]["user_id"],
    $order_id,
    "We can't do the revision for Order #" . $order_id . ": " . $reason . ". We'll continue with your order.",
    "About your revision request for Order #" . $order_id,
    [
        "Thank you for your revision request for <strong>Order #" . $order_id . "</strong>. Unfortunately, we are unable to make the requested changes.",
        "<strong>Reason:</strong> " . figurify_email_e($reason),
        "We’ll continue working on your order and will send you the next progress photo for your review and approval.",
        figurify_email_status_line(),
        "Thank you for your understanding!",
    ]
);

figurify_notify_staff($conn, $order_id, "Revision for order " . $order_id . " was declined by " . $figurifyActorLabel . ".", (int) $_SESSION["user_id"]);

figurify_action_done($conn, $backUrl, "Revision declined for order " . $order_id . ".");
