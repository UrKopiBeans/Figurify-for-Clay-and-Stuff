<?php

/* CUSTOMER - "Order Received"
   SHIPPED -> COMPLETED. Pagkatapos nito lalabas ang "Leave a Review".
   Kapag hindi pinindot, awtomatiko itong mangyayari pagkalipas ng 7 araw
   (helpers/order_complete_helper.php). */

require_once __DIR__ . "/_customer_bootstrap.php";
require_once __DIR__ . "/../helpers/order_complete_helper.php";

$order_id = isset($_POST["order_id"]) ? (int) $_POST["order_id"] : 0;
$backUrl  = "my-orders.php" . ($order_id > 0 ? "?order_id=" . $order_id : "");

figurify_wf_require_post_csrf($conn, $backUrl, $order_id);

$result = figurify_wf_perform($conn, $order_id, "complete_order");

if (!$result["ok"]) {
    figurify_customer_done($conn, $backUrl, $result["error"], "error");
}

figurify_order_completed_notice($conn, $order_id, (int) $user_id, "customer");

figurify_customer_done($conn, $backUrl, "Thanks for confirming! You can now leave a review.");
