<?php

/* Order Received / Complete (SHIPPED -> COMPLETED):
   1. customer - pinindot ang "Order Received" sa My Orders
   2. staff/owner - "Complete Order" sa Active Booking o Shipments
   3. system - kusa pagkalipas ng 7 araw mula ma-ship
   Pagka-complete, lalabas ang "Leave a Review" at may email na may review link. */

require_once __DIR__ . "/notification_helper.php";
require_once __DIR__ . "/order_workflow.php";
require_once __DIR__ . "/review_helper.php";
require_once __DIR__ . "/email_template_helper.php";

if (!defined("FIGURIFY_AUTO_RECEIVE_DAYS")) {
    define("FIGURIFY_AUTO_RECEIVE_DAYS", 7);
}

if (!function_exists("figurify_order_completed_notice")) {

    /* $via: "customer" | "staff" | "auto" */
    function figurify_order_completed_notice(mysqli $conn, int $orderId, int $customerId, string $via, ?int $staffActorId = null): void
    {
        $reviewToken = figurify_ensure_review_token($conn, $orderId);
        $reviewUrl   = figurify_review_url($orderId, $reviewToken);

        if ($via === "customer") {
            $systemMsg = "Thanks for confirming! Order #" . $orderId . " is complete. You can now leave a review.";
            $opening   = "Thank you for confirming that you have received <strong>Order #" . $orderId . "</strong>. Your order is now complete.";
        } elseif ($via === "auto") {
            $systemMsg = "Order #" . $orderId . " was marked as received and is now complete. You can now leave a review.";
            $opening   = "It has been " . FIGURIFY_AUTO_RECEIVE_DAYS . " days since <strong>Order #" . $orderId . "</strong> was shipped, so we have marked it as received. Your order is now complete.";
        } else {
            $systemMsg = "Order #" . $orderId . " is complete! You can now leave a review.";
            $opening   = "Your <strong>Order #" . $orderId . "</strong> has been completed.";
        }

        figurify_customer_notice(
            $conn,
            $customerId,
            $orderId,
            $systemMsg,
            "Your Order #" . $orderId . " is complete",
            [
                $opening,
                "We’d love to hear what you think! You can leave a rating, a short review, and a photo of your figure by clicking the link below:",
                '<a href="' . figurify_email_e($reviewUrl) . '">Leave a Review</a>',
                "You can also leave your review anytime from the <strong>My Orders</strong> page. Your review may be featured on our home page.",
                "Thank you for choosing Clay and Stuff! We hope to create something special for you again soon.",
            ]
        );

        $staffMsg = [
            "customer" => "Customer confirmed they received order " . $orderId . ". Order completed.",
            "auto"     => "Order " . $orderId . " was automatically completed (" . FIGURIFY_AUTO_RECEIVE_DAYS . " days after shipping).",
        ][$via] ?? ("Order " . $orderId . " completed.");

        figurify_notify_staff($conn, $orderId, $staffMsg, $staffActorId);
    }


    /* awtomatikong "Order Received" pagkalipas ng 7 araw mula nang ma-ship */
    function figurify_autocomplete_shipped_orders(mysqli $conn): int
    {
        $done = 0;

        try {
            $stmt = $conn->prepare(
                "SELECT order_id, user_id FROM orders
                 WHERE status = 'shipped'
                   AND shipped_at IS NOT NULL
                   AND shipped_at <= (NOW() - INTERVAL " . (int) FIGURIFY_AUTO_RECEIVE_DAYS . " DAY)"
            );

            if (!$stmt) {
                return 0;
            }

            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            foreach ($rows as $row) {
                $orderId = (int) $row["order_id"];
                $result  = figurify_wf_perform($conn, $orderId, "complete_order", [], figurify_wf_system_actor());

                if (!$result["ok"]) {
                    continue;
                }

                figurify_order_completed_notice($conn, $orderId, (int) $row["user_id"], "auto");
                $done++;
            }
        } catch (\Throwable $e) {
            error_log("[figurify auto-receive] " . $e->getMessage());
        }

        return $done;
    }


    /* petsa kung kailan awtomatikong mamarkahang received */
    function figurify_auto_receive_date(?string $shippedAt): string
    {
        if (empty($shippedAt)) {
            return "";
        }

        return date("F j, Y", strtotime($shippedAt) + FIGURIFY_AUTO_RECEIVE_DAYS * 86400);
    }
}
