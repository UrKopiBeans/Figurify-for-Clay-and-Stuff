<?php
// Progress photo na hindi sinasagot ng customer:
// - 48 oras: paalala (system + email)
// - 72 oras: kusang ma-approve ("auto_approve_progress")
// Tumatakbo pag binuksan ang Active Booking at sa Admin/run_scheduled_tasks.php.

require_once __DIR__ . "/notification_helper.php";
require_once __DIR__ . "/order_workflow.php";

function figurify_send_progress_reminders(mysqli $conn): int
{
    $sent = 0;

    try {

        figurify_wf_ensure_schema($conn);

        $stmt = $conn->prepare(
            "SELECT pu.update_id, pu.order_id, pu.created_at, o.user_id
             FROM order_progress_updates pu
             JOIN orders o ON o.order_id = pu.order_id
             WHERE pu.customer_response = 'pending'
               AND pu.is_final = 0
               AND pu.reminder_sent_at IS NULL
               AND o.status = 'processing'
               AND pu.created_at <= (NOW() - INTERVAL " . (int) FIGURIFY_PROGRESS_REMINDER_HOURS . " HOUR)
               AND pu.created_at >  (NOW() - INTERVAL " . (int) FIGURIFY_PROGRESS_AUTO_APPROVE_HOURS . " HOUR)"
        );
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $mark = $conn->prepare("UPDATE order_progress_updates SET reminder_sent_at = NOW() WHERE update_id = ? AND reminder_sent_at IS NULL");

        foreach ($rows as $row) {

            $updateId = (int) $row["update_id"];
            $mark->bind_param("i", $updateId);
            $mark->execute();

            if ($mark->affected_rows !== 1) {
                continue; // naunahan na ng ibang request
            }

            $deadline = date("F j, Y g:i A", strtotime($row["created_at"]) + FIGURIFY_PROGRESS_AUTO_APPROVE_HOURS * 3600);

            require_once __DIR__ . "/email_template_helper.php";
            figurify_customer_notice(
                $conn,
                (int) $row["user_id"],
                (int) $row["order_id"],
                "Reminder: please review the progress photo for Order #" . $row["order_id"] . " by " . $deadline . ".",
                "Reminder: Please review the progress photo for Order #" . $row["order_id"],
                [
                    "This is a friendly reminder to review the latest progress photo for <strong>Order #" . (int) $row["order_id"] . "</strong>.",
                    "Please log in to your account and visit the <strong>My Orders</strong> page. If we don’t hear from you by <strong>" . figurify_email_e($deadline) . "</strong>, the photo will be approved automatically and your order will be finalized.",
                    "You can also view this notification on your <strong>Notifications</strong> page.",
                    "Thank you for choosing Clay and Stuff!",
                ]
            );

            $sent++;
        }

        $mark->close();

    } catch (\Throwable $e) {
        error_log("[figurify reminders] " . $e->getMessage());
    }

    return $sent;
}

function figurify_autoapprove_stale_progress_updates(mysqli $conn): int
{
    $approved = 0;

    figurify_send_progress_reminders($conn);

    try {

        $stmt = $conn->prepare(
            "SELECT DISTINCT pu.order_id
             FROM order_progress_updates pu
             JOIN orders o ON o.order_id = pu.order_id
             WHERE pu.customer_response = 'pending'
               AND pu.is_final = 0
               AND o.status = 'processing'
               AND pu.created_at <= (NOW() - INTERVAL " . (int) FIGURIFY_PROGRESS_AUTO_APPROVE_HOURS . " HOUR)"
        );

        if ($stmt === false) {
            return 0;
        }

        $stmt->execute();
        $orderIds = array_map("intval", array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), "order_id"));
        $stmt->close();

        foreach ($orderIds as $orderId) {

            $result = figurify_wf_perform($conn, $orderId, "auto_approve_progress", [], figurify_wf_system_actor());

            if (!$result["ok"]) {
                continue;
            }

            $approved++;

            $balance = (float) ($result["data"]["balance_due"] ?? 0);

            require_once __DIR__ . "/email_template_helper.php";
            figurify_customer_notice(
                $conn,
                (int) $result["order"]["user_id"],
                $orderId,
                "The progress photo for Order #" . $orderId . " was approved automatically."
                    . ($balance > 0 ? (" Please pay the remaining balance of ₱" . number_format($balance, 2) . ".") : " Your order is ready to ship."),
                "The progress photo for Order #" . $orderId . " was approved",
                [
                    "We didn’t hear back from you within " . FIGURIFY_PROGRESS_AUTO_APPROVE_HOURS . " hours about the latest progress photo for <strong>Order #" . $orderId . "</strong>, so it has been approved automatically and your order has been finalized.",
                    $balance > 0
                        ? ("<strong>Remaining Balance:</strong> ₱" . number_format($balance, 2) . "<br>Please go to the <strong>My Orders</strong> page and click <strong>Pay Balance</strong> to complete your payment.")
                        : "Your order is now ready to ship. We’ll notify you once it has been shipped.",
                    figurify_email_status_line(),
                    "Thank you for choosing Clay and Stuff!",
                ]
            );
        }

    } catch (\Throwable $e) {
        return $approved;
    }

    return $approved;
}
