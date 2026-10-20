<?php

/* CUSTOMER - AWAITING_PAYMENT -> PAYMENT_SUBMITTED.
   Ang OWNER lang ang mag-a-approve o magre-reject nito. */

require_once __DIR__ . "/_customer_bootstrap.php";

$order_id = isset($_POST["order_id"]) ? (int) $_POST["order_id"] : 0;
$errorUrl = "payment-shipping.php?order_id=" . $order_id . "&error=1";

figurify_wf_require_post_csrf($conn, $errorUrl, $order_id);

$shipping_name     = mb_substr(trim((string) ($_POST["shipping_name"] ?? "")), 0, 150);
$shipping_address  = mb_substr(trim((string) ($_POST["shipping_address"] ?? "")), 0, 255);
$pin_address       = mb_substr(trim((string) ($_POST["pin_address"] ?? "")), 0, 255);
$shipping_contact  = trim((string) ($_POST["shipping_contact"] ?? ""));
$payment_reference = mb_substr(trim((string) ($_POST["payment_reference"] ?? "")), 0, 100);
$courier           = trim((string) ($_POST["courier"] ?? ""));

$refund_account_name   = mb_substr(trim((string) ($_POST["refund_account_name"] ?? "")), 0, 150);
$refund_account_number = trim((string) ($_POST["refund_account_number"] ?? ""));
$refund_method         = trim((string) ($_POST["refund_method"] ?? ""));
$refund_method_other   = mb_substr(trim((string) ($_POST["refund_method_other"] ?? "")), 0, 50);

$refundMethodValid = in_array($refund_method, ["gcash", "bpi", "other"], true)
    && ($refund_method !== "other" || $refund_method_other !== "");

$formOk =
    $order_id > 0 &&
    $shipping_name !== "" &&
    $shipping_address !== "" &&
    preg_match('/^[0-9]{11}$/', $shipping_contact) &&
    in_array($courier, ["lbc", "lalamove"], true) &&
    $payment_reference !== "" &&
    $refund_account_name !== "" &&
    preg_match('/^[0-9]{1,11}$/', $refund_account_number) &&
    $refundMethodValid &&
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
    "shipping_address"      => $shipping_address,
    "pin_address"           => $pin_address,
    "shipping_contact"      => $shipping_contact,
    "courier"               => $courier,
    "payment_reference"     => $payment_reference,
    "payment_proof"         => $proofPath,
    "refund_account_name"   => $refund_account_name,
    "refund_account_number" => $refund_account_number,
    "refund_method"         => ($refund_method === "other") ? $refund_method_other : $refund_method,
]);

if (!$result["ok"]) {
    figurify_customer_delete_upload($proofPath);
    figurify_customer_done($conn, "my-orders.php?order_id=" . $order_id, $result["error"], "error");
}

/* Walang hold ng slot sa pag-submit ng bayad - papasok lang sa slot ang order kapag na-approve na ng owner */

require_once __DIR__ . "/../helpers/email_template_helper.php";

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

figurify_notify_staff($conn, $order_id, "Payment submitted for order " . $order_id . " — please check the details in Verification.");

figurify_customer_done($conn, "my-orders.php?order_id=" . $order_id . "&submitted=1");
