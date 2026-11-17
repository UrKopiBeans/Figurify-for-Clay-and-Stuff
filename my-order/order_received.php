<?php
/* ==========================================================================
   ORDER RECEIVED (customer)   (my-order/order_received.php)
   POST galing sa my-orders.php. SHIPPED -> COMPLETED, tapos lalabas ang "Leave a Review".
   Kapag may problema sa dumating (problem=1): note + proof ang ise-save; hindi ito magko-complete,
   makikita ng staff/owner sa Shipments at ang owner ang magpapasya (For Refund).
   Kapag hindi pinindot, kusang mangyayari pagkalipas ng 7 araw (helpers/order_helper.php).
   ========================================================================== */

require_once __DIR__ . "/_customer_bootstrap.php";

$order_id = isset($_POST["order_id"]) ? (int) $_POST["order_id"] : 0;
$backUrl  = "my-orders.php" . ($order_id > 0 ? "?order_id=" . $order_id : "");

figurify_wf_require_post_csrf($conn, $backUrl, $order_id);

if (!empty($_POST["problem"])) {

    $can  = figurify_wf_can($conn, $order_id, "complete_order");   /* SHIPPED at sa customer na ito lang */
    $note = mb_substr(trim((string) ($_POST["problem_note"] ?? "")), 0, 500);

    if (!$can["ok"]) {
        figurify_customer_done($conn, $backUrl, $can["error"], "error");
    }

    if ($note === "") {
        figurify_customer_done($conn, $backUrl, "Please describe the problem.", "error");
    }

    $proofPath = figurify_customer_save_image($_FILES["problem_proof"] ?? [], "uploads/delivery_problems", "problem_" . $order_id);

    if ($proofPath === null) {
        figurify_customer_done($conn, $backUrl, "Please upload a clear photo of the problem (JPG, PNG or WEBP).", "error");
    }

    $stmt = $conn->prepare("UPDATE orders SET delivery_problem_note = ?, delivery_problem_proof = ?, delivery_problem_at = NOW() WHERE order_id = ? AND user_id = ?");
    $stmt->bind_param("ssii", $note, $proofPath, $order_id, $user_id);
    $stmt->execute();
    $stmt->close();

    figurify_notify_staff($conn, $order_id, "Customer reported a delivery problem for order " . $order_id . ": " . $note . " Please check the proof in Shipments.");

    figurify_customer_done($conn, $backUrl, "We received your report. The owner will check your proof and contact you.");
}

$result = figurify_wf_perform($conn, $order_id, "complete_order");

if (!$result["ok"]) {
    figurify_customer_done($conn, $backUrl, $result["error"], "error");
}

figurify_order_completed_notice($conn, $order_id, (int) $user_id, "customer");

figurify_customer_done($conn, $backUrl, "Thanks for confirming! You can now leave a review.");
/* ====================== END ORDER RECEIVED (customer) ====================== */
