<?php
/* ==========================================================================
   CONFIRM ORDER (customer)   (my-order/confirm_order.php)
   POST galing sa my-orders.php. Mga action:
     cancel       Pending / Correction Required / Quotation Ready / Payment Required -> CANCELLED
     change_date  bagong ship out date kapag puno na ang napili (parehong order, walang bagong bayad)
     cancel_full_date  nabayaran na pero puno ang petsa -> For Refund
     continue     QUOTATION_SENT -> AWAITING_PAYMENT (papunta sa payment-shipping.php)
     update_address  ADDRESS_RETURNED -> READY_TO_SHIP (inayos ang shipping details na ibinalik ng staff)
   ========================================================================== */

require_once __DIR__ . "/_customer_bootstrap.php";
require_once __DIR__ . "/../helpers/expiration_helper.php";

$order_id = isset($_POST["order_id"]) ? (int) $_POST["order_id"] : 0;
$action   = strtolower(trim((string) ($_POST["action"] ?? "")));

figurify_wf_require_post_csrf($conn, "my-orders.php" . ($order_id > 0 ? "?order_id=" . $order_id : ""), $order_id);

figurify_expire_old_quotes($conn);

if ($action === "cancel") {

    $result = figurify_wf_perform($conn, $order_id, "cancel_order");

    if (!$result["ok"]) {
        figurify_customer_done($conn, "my-orders.php?order_id=" . $order_id, $result["error"], "error");
    }

    figurify_customer_notice(
        $conn,
        (int) $user_id,
        (int) $order_id,
        "You cancelled Order #" . $order_id . ".",
        "Your Order #" . $order_id . " has been cancelled",
        [
            "<strong>Order #" . $order_id . "</strong> was cancelled as you requested.",
            "You can place a new order anytime.",
            figurify_email_status_line(),
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

    $result = figurify_wf_perform($conn, $order_id, "change_date", ["ship_out_date" => $newDate]);

    if (!$result["ok"]) {
        figurify_customer_done($conn, "my-orders.php?order_id=" . $order_id, $result["error"], "error");
    }

    figurify_notify_staff(
        $conn,
        $order_id,
        "Customer changed the ship out date of order " . $order_id . " to " . date("F j, Y", strtotime($newDate)) . "."
    );

    figurify_customer_done(
        $conn,
        "my-orders.php?order_id=" . $order_id,
        "Your ship out date was changed to " . date("F j, Y", strtotime($newDate)) . ".",
        "success"
    );
}

if ($action === "cancel_full_date") {

    /* Nabayaran na pero napuno ang petsa: For Refund - owner ang magre-refund */
    $result = figurify_wf_perform($conn, $order_id, "cancel_full_date");

    if (!$result["ok"]) {
        figurify_customer_done($conn, "my-orders.php?order_id=" . $order_id, $result["error"], "error");
    }

    figurify_notify_staff($conn, $order_id, "Customer cancelled paid order " . $order_id . " because the ship out date is full. It is now For Refund (owner).");

    figurify_customer_done($conn, "my-orders.php?order_id=" . $order_id, "Your order was cancelled and sent to the owner for your refund.", "success");
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

if ($action === "update_address") {

    /* Ibinalik ng staff dahil hindi mahanap ng courier ang address -> ayusin at ibalik sa Ready to Ship */
    $result = figurify_wf_perform($conn, $order_id, "update_shipping_details", [
        "shipping_name"    => $_POST["shipping_name"] ?? "",
        "shipping_address" => $_POST["shipping_address"] ?? "",
        "pin_address"      => $_POST["pin_address"] ?? "",
        "shipping_contact" => $_POST["shipping_contact"] ?? "",
        "courier"          => $_POST["courier"] ?? "",
    ]);

    if (!$result["ok"]) {
        figurify_customer_done($conn, "my-orders.php?order_id=" . $order_id, $result["error"], "error");
    }

    figurify_notify_staff($conn, $order_id, "Customer updated the shipping details of order " . $order_id . ". It is ready to ship again.");

    figurify_customer_done($conn, "my-orders.php?order_id=" . $order_id, "Your shipping details were updated. We will ship your order soon.", "success");
}

figurify_customer_done($conn, "my-orders.php");
/* ====================== END CONFIRM ORDER (customer) ====================== */
