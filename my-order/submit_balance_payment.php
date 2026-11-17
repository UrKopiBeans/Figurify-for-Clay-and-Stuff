<?php
/* ==========================================================================
   SUBMIT BALANCE PAYMENT (customer)   (my-order/submit_balance_payment.php)
   POST galing sa pay-balance.php: proof ng remaining balance.
   AWAITING_BALANCE -> BALANCE_SUBMITTED. Ang OWNER ang mag-a-approve.
   ========================================================================== */

require_once __DIR__ . "/_customer_bootstrap.php";

$order_id = isset($_POST["order_id"]) ? (int) $_POST["order_id"] : 0;
$errorUrl = "pay-balance.php?order_id=" . $order_id . "&error=1";

figurify_wf_require_post_csrf($conn, $errorUrl, $order_id);

$reference = mb_substr(trim((string) ($_POST["payment_reference"] ?? "")), 0, 100);

if ($order_id <= 0 || $reference === "") {
    figurify_customer_done($conn, $errorUrl);
}

$can = figurify_wf_can($conn, $order_id, "submit_balance");

if (!$can["ok"]) {
    figurify_wf_perform($conn, $order_id, "submit_balance");
    figurify_customer_done($conn, "my-orders.php?order_id=" . $order_id, $can["error"], "error");
}

$proofPath = figurify_customer_save_image($_FILES["payment_proof"] ?? [], "uploads/payment_proofs", "balance_" . $order_id);

if ($proofPath === null) {
    figurify_customer_done($conn, $errorUrl);
}

$result = figurify_wf_perform($conn, $order_id, "submit_balance", [
    "payment_reference" => $reference,
    "payment_proof"     => $proofPath,
]);

if (!$result["ok"]) {
    figurify_customer_delete_upload($proofPath);
    figurify_customer_done($conn, "my-orders.php?order_id=" . $order_id, $result["error"], "error");
}

figurify_customer_notice(
    $conn,
    (int) $user_id,
    (int) $order_id,
    "We received your balance payment for Order #" . $order_id . ". It's now waiting for approval.",
    "We received your balance payment for Order #" . $order_id,
    [
        "We received your balance payment for <strong>Order #" . $order_id . "</strong>.",
        "We’ll ship your order once it’s approved.",
        figurify_email_status_line(),
    ]
);
figurify_notify_staff($conn, $order_id, "Balance payment submitted for order " . $order_id . " and needs owner approval.");

figurify_customer_done($conn, "my-orders.php?order_id=" . $order_id . "&submitted=1");
/* ====================== END SUBMIT BALANCE PAYMENT (customer) ====================== */
