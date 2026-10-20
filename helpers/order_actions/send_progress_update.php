<?php

/* STAFF/OWNER - pagpapadala ng progress photo.
   Normal: PROCESSING / REVISION_IN_PROGRESS -> PROGRESS_SUBMITTED (customer ang sasagot)
   Final photo (is_final=1): para sa Ready to Ship, hindi nagpapalit ng status. */

require_once __DIR__ . "/_bootstrap.php";

$order_id = isset($_POST["order_id"]) ? (int) $_POST["order_id"] : 0;
$message  = trim((string) ($_POST["staff_message"] ?? ""));
$isFinal  = (($_POST["is_final"] ?? "") === "1");

$backUrl  = "active-booking.php?order_id=" . $order_id;

figurify_wf_require_post_csrf($conn, $backUrl, $order_id);

$wfAction = $isFinal ? "submit_final_photo" : "submit_progress";

/* pre-check bago i-save ang file (para walang naiiwang upload sa denied request) */
$can = figurify_wf_can($conn, $order_id, $wfAction);

if (!$can["ok"]) {
    figurify_wf_perform($conn, $order_id, $wfAction, []); // para maitala ang denied attempt
    figurify_action_done($conn, $backUrl . "&progress_error=" . urlencode($can["error"]));
}

[$imagePath, $uploadError] = figurify_save_uploaded_image(
    $_FILES["progress_image"] ?? [],
    "uploads/orders/" . $order_id . "/progress",
    "progress_" . $order_id
);

if ($imagePath === null) {
    figurify_action_done($conn, $backUrl . "&progress_error=" . urlencode($uploadError));
}

$result = figurify_wf_perform($conn, $order_id, $wfAction, [
    "image_path" => $imagePath,
    "message"    => $message,
]);

if (!$result["ok"]) {
    figurify_delete_upload($imagePath);
    figurify_action_done($conn, $backUrl . "&progress_error=" . urlencode($result["error"]));
}


/* notifications (pagkatapos lang ng matagumpay na transition) */

$custStmt = $conn->prepare("SELECT user_id, full_name, email FROM users WHERE user_id = ? LIMIT 1");
$custId   = (int) $result["order"]["user_id"];
$custStmt->bind_param("i", $custId);
$custStmt->execute();
$customer = $custStmt->get_result()->fetch_assoc();
$custStmt->close();

$isRevision = !$isFinal && !empty($result["data"]["is_revision"]);

require_once __DIR__ . "/../email_template_helper.php";

/* system: maikling notification - Gmail: buong template na may larawan */
if ($isFinal) {
    $notify = "Your Order #" . $order_id . " is finished! It will be shipped out soon.";
} elseif ($isRevision) {
    $notify = "Your revised progress photo for Order #" . $order_id . " is ready. Please review it in My Orders.";
} else {
    $notify = "New progress photo for Order #" . $order_id . ". Please review it in My Orders.";
}

figurify_notify_user($conn, $custId, $order_id, $notify, 0, null, false);

figurify_email_progress_update(
    $conn,
    $custId,
    (int) $order_id,
    __DIR__ . "/../../" . $imagePath,
    (string) $message,
    $isRevision,
    $isFinal
);

figurify_notify_staff(
    $conn,
    $order_id,
    $isFinal
        ? ("Final photo sent for order " . $order_id . ".")
        : ("Progress update sent for order " . $order_id . " — waiting for the customer."),
    (int) $_SESSION["user_id"]
);

figurify_action_done($conn, $backUrl, $isFinal
    ? "Final photo sent for order " . $order_id . "."
    : "Progress update sent for order " . $order_id . ".");
