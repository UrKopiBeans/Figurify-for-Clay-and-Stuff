<?php

/* STAFF/OWNER - verify ng order at pagpapadala ng quotation.
   PENDING_STAFF_VERIFICATION -> STAFF_VERIFIED -> QUOTATION_PENDING -> QUOTATION_SENT
   Sa server kinukuwenta ang total. */

require_once __DIR__ . "/_bootstrap.php";
require_once __DIR__ . "/../expire_helper.php";
require_once __DIR__ . "/../email_template_helper.php";

$order_id = isset($_POST["order_id"]) ? (int) $_POST["order_id"] : 0;
$backUrl  = "quotation.php?order_id=" . $order_id;

figurify_wf_require_post_csrf($conn, $backUrl, $order_id);

figurify_expire_old_quotes($conn);

$prices = (isset($_POST["quoted_price"]) && is_array($_POST["quoted_price"])) ? $_POST["quoted_price"] : [];

$result = figurify_wf_perform($conn, $order_id, "verify_and_quote", ["prices" => $prices]);

if (!$result["ok"]) {
    figurify_action_done($conn, $backUrl, $result["error"], "error");
}

$total = (float) $result["data"]["total"];

/* system: maikli lang - Gmail: buong mensahe (helpers/email_template_helper.php) */
figurify_notify_user(
    $conn,
    (int) $result["order"]["user_id"],
    $order_id,
    "Your quotation for Order #" . $order_id . " is ready: ₱" . number_format($total, 2)
        . ". Please accept or cancel it in My Orders within 2 days.",
    0,
    null,
    false
);

figurify_email_quotation_ready($conn, (int) $result["order"]["user_id"], $order_id, $total);

figurify_notify_staff(
    $conn,
    $order_id,
    "Order " . $order_id . " was verified and quoted by " . $figurifyActorLabel . ".",
    (int) $_SESSION["user_id"]
);

figurify_action_done(
    $conn,
    "quotation.php?order_id=" . $order_id . "&qfilter=quoted&sent=1",
    "Quotation of ₱" . number_format($total, 2) . " sent for order " . $order_id . "."
);
