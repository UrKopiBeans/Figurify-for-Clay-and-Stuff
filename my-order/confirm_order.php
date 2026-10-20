<?php

/* CUSTOMER - quotation decision.
   continue: QUOTATION_SENT -> AWAITING_PAYMENT
   cancel: Pending (bago ang quotation) / Correction Required / Quotation Ready / Payment Required
              -> CANCELLATION_REQUESTED -> CANCELLED (wala pang naaprubahang bayad) */

require_once __DIR__ . "/_customer_bootstrap.php";
require_once __DIR__ . "/../helpers/expire_helper.php";

$order_id = isset($_POST["order_id"]) ? (int) $_POST["order_id"] : 0;
$action   = strtolower(trim((string) ($_POST["action"] ?? "")));

figurify_wf_require_post_csrf($conn, "my-orders.php" . ($order_id > 0 ? "?order_id=" . $order_id : ""), $order_id);

figurify_expire_old_quotes($conn);

if ($action === "cancel") {

    $result = figurify_wf_perform($conn, $order_id, "cancel_order");

    if (!$result["ok"]) {
        figurify_customer_done($conn, "my-orders.php?order_id=" . $order_id, $result["error"], "error");
    }

    require_once __DIR__ . "/../helpers/email_template_helper.php";
    figurify_customer_notice(
        $conn,
        (int) $user_id,
        (int) $order_id,
        "You cancelled Order #" . $order_id . ".",
        "Your Order #" . $order_id . " has been cancelled",
        [
            "This is to confirm that your <strong>Order #" . $order_id . "</strong> has been cancelled as you requested.",
            "If this was a mistake, you are welcome to place a new order anytime.",
            figurify_email_status_line(),
            "Thank you for your interest in Clay and Stuff!",
        ],
        [],
        1
    );
    figurify_notify_staff($conn, $order_id, "Customer cancelled order " . $order_id . ".");

    figurify_customer_done($conn, "my-orders.php?order_id=" . $order_id . "&cancelled=1");
}

if ($action === "change_date") {

    /* Napuno na ang napiling petsa? Pumili ng bagong petsa - parehong order, walang bagong bayad. */
    $newDate = trim((string) ($_POST["new_date"] ?? ""));

    figurify_slot_lock_acquire($conn);

    $result = figurify_wf_perform($conn, $order_id, "change_date", ["booking_date" => $newDate]);

    if (!$result["ok"]) {
        figurify_customer_done($conn, "my-orders.php?order_id=" . $order_id, $result["error"], "error");
    }

    figurify_notify_staff(
        $conn,
        $order_id,
        "Customer changed the booking date of order " . $order_id . " to " . date("F j, Y", strtotime($newDate)) . "."
    );

    figurify_customer_done(
        $conn,
        "my-orders.php?order_id=" . $order_id,
        "Your booking date was changed to " . date("F j, Y", strtotime($newDate)) . ".",
        "success"
    );
}

if ($action === "continue") {

    $result = figurify_wf_perform($conn, $order_id, "accept_quotation");

    if (!$result["ok"]) {
        figurify_customer_done($conn, "my-orders.php?order_id=" . $order_id, $result["error"], "error");
    }

    figurify_notify_staff(
        $conn,
        $order_id,
        "Customer accepted the quotation for order " . $order_id . " and is proceeding to payment."
    );

    figurify_customer_done($conn, "payment-shipping.php?order_id=" . $order_id);
}

figurify_customer_done($conn, "my-orders.php");
