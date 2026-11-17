<?php
/* ==========================================================================
   RESPOND TO PROGRESS UPDATE (customer)   (my-order/respond_progress.php)
   POST galing sa my-orders.php ([5c] PROGRESS UPDATE RESPONSE) o sa Notifications page.
     approved  PROGRESS_SUBMITTED -> CUSTOMER_APPROVED -> AWAITING_BALANCE (o READY_TO_SHIP)
     revision  PROGRESS_SUBMITTED -> REVISION_REQUESTED (isang beses lang, bantay ng backend)
   Ang mismong logic ay nasa helpers/order_helper.php (SECTION 5: figurify_progress_respond),
   kapareho ng ginagamit ng Gmail link (my-order/progress_response.php).
   ========================================================================== */

require_once __DIR__ . "/_customer_bootstrap.php";

$update_id = isset($_POST["update_id"]) ? (int) $_POST["update_id"] : 0;
$order_id  = isset($_POST["order_id"]) ? (int) $_POST["order_id"] : 0;
$response  = strtolower(trim((string) ($_POST["response"] ?? "")));
$note      = trim((string) ($_POST["revision_note"] ?? ""));

// saan babalik: Notifications page (kung doon pinindot) o My Orders
$backUrl = (($_POST["return_to"] ?? "") === "notifications")
    ? "../Notification/notifications.php"
    : "my-orders.php" . ($order_id > 0 ? "?order_id=" . $order_id : "");

figurify_wf_require_post_csrf($conn, $backUrl, $order_id);

$result = figurify_progress_respond($conn, $order_id, $update_id, $response, $note, figurify_wf_session_actor());

figurify_customer_done($conn, $backUrl, $result["message"], $result["ok"] ? "success" : "error");
/* ====================== END RESPOND TO PROGRESS UPDATE (customer) ====================== */
