<?php

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

require_once __DIR__ . "/../Admin/database.php";
define("ALL_IN_ONE_FUNCTIONS_ONLY", true); // [ALL-IN-ONE] pop up functions lang (walang HTML) - redirect file ito
require_once __DIR__ . "/../Shared/all-in-one.php";
require_once __DIR__ . "/../helpers/notification_helper.php";
require_once __DIR__ . "/../helpers/access_helper.php";
require_once __DIR__ . "/../helpers/order_workflow.php";

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
    $reason    = FIGURIFY_WF_DETAILS_DECLINE_REASONS[$reasonKey] ?? (($reasonKey === "other") ? $otherText : "");

    if ($reason === "") {
        figurify_flash("Pick a reason first.", "error");
        $conn->close();
        header("Location: " . $backUrl);
        exit();
    }

    $result = figurify_wf_perform($conn, $order_id, "decline_payment_details", ["reason" => $reason]);

    if (!$result["ok"]) {
        figurify_flash($result["error"], "error");
        $conn->close();
        header("Location: " . $backUrl);
        exit();
    }

    require_once __DIR__ . "/../helpers/email_template_helper.php";
    figurify_customer_notice(
        $conn,
        (int) $result["order"]["user_id"],
        (int) $order_id,
        "Your payment details for Order #" . $order_id . " were declined: " . $reason . ". Please submit them again in My Orders.",
        "Action needed: Payment details for Order #" . $order_id,
        [
            "We reviewed your payment details for <strong>Order #" . $order_id . "</strong>, but unfortunately we were unable to accept them.",
            "<strong>Reason:</strong> " . figurify_email_e($reason),
            "Please log in to your account, go to the <strong>My Orders</strong> page, and submit your payment details again.",
            "You can also view this notification on your <strong>Notifications</strong> page.",
            "Thank you for choosing Clay and Stuff!",
        ]
    );

    figurify_notify_staff(
        $conn,
        $order_id,
        "Payment details for order " . $order_id . " were declined by staff (" . $reason . ") — sent back to the customer.",
        (int) $_SESSION["user_id"]
    );

    figurify_flash("Order " . $order_id . " declined. The customer was notified.", "success");
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

require_once __DIR__ . "/../helpers/email_template_helper.php";

figurify_notify_user(
    $conn,
    (int) $result["order"]["user_id"],
    $order_id,
    "Your payment details for Order #" . $order_id . " were checked. Waiting for the owner's approval.",
    0,
    null,
    false
);

figurify_email_payment_verified($conn, (int) $result["order"]["user_id"], (int) $order_id);

figurify_notify_staff(
    $conn,
    $order_id,
    "Payment details for order " . $order_id . " were verified by staff — waiting for the owner's approval.",
    (int) $_SESSION["user_id"]
);

figurify_flash("Order " . $order_id . " sent to the owner for approval.", "success");

$conn->close();

header("Location: verification.php");
exit();
