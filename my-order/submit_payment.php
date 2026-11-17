<?php
/* ==========================================================================
   SUBMIT PAYMENT (customer)   (my-order/submit_payment.php)
   POST galing sa payment-shipping.php: shipping, refund details, payment option (50% o full)
   at proof ng bayad.
   AWAITING_PAYMENT -> PAYMENT_SUBMITTED. Ang OWNER lang ang mag-a-approve o magre-reject.
   ========================================================================== */

require_once __DIR__ . "/_customer_bootstrap.php";
require_once __DIR__ . "/../helpers/saved_details_helper.php";

$order_id = isset($_POST["order_id"]) ? (int) $_POST["order_id"] : 0;
$errorUrl = "payment-shipping.php?order_id=" . $order_id . "&error=1";

figurify_wf_require_post_csrf($conn, $errorUrl, $order_id);

/* Shipping + Refund details: iisang paglinis at check (helpers/saved_details_helper.php) */
$details = figurify_saved_details_from_post($_POST, true);
$d       = $details["data"];

$shipping_name     = mb_substr(trim((string) ($_POST["shipping_name"] ?? "")), 0, 150);
$payment_reference = mb_substr(trim((string) ($_POST["payment_reference"] ?? "")), 0, 100);
$payment_option    = (string) ($_POST["payment_option"] ?? "");

$formOk =
    $order_id > 0 &&
    $details["error"] === "" &&
    $shipping_name !== "" &&
    in_array($payment_option, ["half", "full"], true) &&
    $payment_reference !== "" &&
    !empty($_POST["agree_terms"]);

/* i-check muna ang state bago mag-save ng file */
$can = $formOk ? figurify_wf_can($conn, $order_id, "submit_payment") : ["ok" => false, "error" => "Please complete all required fields."];

if (!$can["ok"]) {
    if ($formOk) {
        figurify_wf_perform($conn, $order_id, "submit_payment"); // itala ang denied attempt
        figurify_customer_done($conn, "my-orders.php?order_id=" . $order_id, $can["error"], "error");
    }
    figurify_customer_done($conn, $errorUrl);
}

$proofPath = figurify_customer_save_image($_FILES["payment_proof"] ?? [], "uploads/payment_proofs", "payment_" . $order_id);

if ($proofPath === null) {
    figurify_customer_done($conn, $errorUrl);
}

$result = figurify_wf_perform($conn, $order_id, "submit_payment", [
    "shipping_name"         => $shipping_name,
    "shipping_address"      => mb_substr($d["address_line1"] . ", " . $d["address_line2"], 0, 255),
    "pin_address"           => $d["pin_address"],
    "shipping_contact"      => $d["shipping_contact"],
    "courier"               => $d["courier"],
    "payment_reference"     => $payment_reference,
    "payment_option"        => $payment_option,
    "payment_proof"         => $proofPath,
    "refund_account_name"   => $d["refund_account_name"],
    "refund_account_number" => $d["refund_account_number"],
    "refund_method"         => $d["refund_method"],
]);

if (!$result["ok"]) {
    figurify_customer_delete_upload($proofPath);
    figurify_customer_done($conn, "my-orders.php?order_id=" . $order_id, $result["error"], "error");
}

/* auto-save: ang nilagay sa form ay lalabas na sa My Profile at sa susunod na order */
figurify_saved_details_save($conn, (int) $user_id, $d);

/* Walang hold ng slot sa pag-submit ng bayad - papasok lang sa slot ang order kapag na-approve na ng owner */

figurify_notify_user(
    $conn,
    $user_id,
    $order_id,
    "We received your payment for Order #" . $order_id . ". It's now waiting for verification.",
    0,
    null,
    false
);

figurify_email_payment_received($conn, (int) $user_id, (int) $order_id);

figurify_notify_staff($conn, $order_id, "Payment submitted for order " . $order_id . ". Please check the details in Verification.");

figurify_customer_done($conn, "my-orders.php?order_id=" . $order_id . "&submitted=1");
/* ====================== END SUBMIT PAYMENT (customer) ====================== */
