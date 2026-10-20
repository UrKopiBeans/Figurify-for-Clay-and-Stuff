<?php
// Auto-cancels "quoted" orders the customer didn't confirm within 2 days.
// Called before fetching orders/notifications. Can also run as a cron job
// via Admin/check_expired_orders.php.
// Dumadaan sa workflow layer (SYSTEM actor, action "expire_quote") para
// may history row at hindi lumalampas sa transition rules.

require_once __DIR__ . "/notification_helper.php";
require_once __DIR__ . "/order_workflow.php";

function figurify_expire_old_quotes(mysqli $conn): void
{
    figurify_move_pending_dressup_to_payment($conn);

    $stmt = $conn->prepare(
        "SELECT order_id
         FROM orders
         WHERE status = 'quoted'
           AND quote_expires_at IS NOT NULL
           AND quote_expires_at < NOW()"
    );

    $stmt->execute();
    $expiredIds = array_map("intval", array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), "order_id"));
    $stmt->close();

    foreach ($expiredIds as $orderId) {

        $result = figurify_wf_perform($conn, $orderId, "expire_quote", [], figurify_wf_system_actor());

        if ($result["ok"]) {
            require_once __DIR__ . "/email_template_helper.php";
            figurify_customer_notice(
                $conn,
                (int) $result["order"]["user_id"],
                $orderId,
                "Order #" . $orderId . " was cancelled because the quotation wasn't accepted within 2 days.",
                "Your Order #" . $orderId . " has been cancelled",
                [
                    "We didn’t receive a response to the quotation for <strong>Order #" . $orderId . "</strong> within 2 days, so the order has been automatically cancelled.",
                    "You were not charged for this order. If you still want your customized figure, you are welcome to place a new order anytime.",
                    figurify_email_status_line(),
                    "Thank you for your interest in Clay and Stuff!",
                ]
            );
        }
    }
}


/* Lumang Dress Up order na "pending" pa (ginawa bago ang direct-payment
   update): walang quotation ang Dress Up, kaya ililipat sa "awaiting_payment"
   para makapagbayad na ang customer. Dumadaan sa workflow (may history). */

function figurify_move_pending_dressup_to_payment(mysqli $conn): void
{
    $res = $conn->query("SELECT order_id FROM orders WHERE status = 'pending' AND order_method = 'create_style'");

    if (!$res) {
        return;
    }

    foreach (array_map("intval", array_column($res->fetch_all(MYSQLI_ASSOC), "order_id")) as $orderId) {

        $result = figurify_wf_perform($conn, $orderId, "dressup_to_payment", [], figurify_wf_system_actor());

        if ($result["ok"]) {
            require_once __DIR__ . "/email_template_helper.php";
            figurify_customer_notice(
                $conn,
                (int) $result["order"]["user_id"],
                $orderId,
                "Your Dress Up Order #" . $orderId . " is ready for payment.",
                "Your Dress Up Order #" . $orderId . " is ready for payment",
                [
                    "Your Dress Up <strong>Order #" . $orderId . "</strong> is ready for payment.",
                    "Please log in to your account, go to the <strong>My Orders</strong> page, and click <strong>Proceed to Payment</strong> to complete your payment and shipping details.",
                    "You can also view this notification on your <strong>Notifications</strong> page.",
                    "Thank you for choosing Clay and Stuff!",
                ]
            );
        }
    }
}


/* Kapag napuno na ang petsa (2 rush/araw, 10 rush/buwan, 15 non-rush) ng mga
   na-APPROVE ng owner, HINDI na kina-cancel ang iba. Inaabisuhan lang sila na puno
   na ang petsa nila para makapili sila ng ibang petsa (same order) o makapag-cancel
   kung hindi na tutuloy. Ang mga order na hindi pa naaaprubahan (Pending, Correction,
   Quoted, Payment Required, Payment Submitted / For Approval) ang inaabisuhan. */
function figurify_notify_full_date_orders(mysqli $conn, string $orderType, string $dateStr, int $exceptOrderId = 0): int
{
    $ts = strtotime($dateStr);
    if (!$ts) {
        return 0;
    }
    $dateStr = date("Y-m-d", $ts);
    $month   = date("Y-m", $ts);

    $counts = figurify_get_order_type_day_counts($conn, $orderType);
    $where  = null;

    if ($orderType === "rush") {
        $monthTotal = 0;
        foreach ($counts as $d => $c) {
            if (strpos($d, $month) === 0) {
                $monthTotal += $c;
            }
        }
        if ($monthTotal >= FIGURIFY_RUSH_MONTHLY_CAP) {
            $where = ["DATE_FORMAT(booking_date, '%Y-%m') = ?", $month];
        } elseif (($counts[$dateStr] ?? 0) >= FIGURIFY_RUSH_DAILY_CAP) {
            $where = ["DATE(booking_date) = ?", $dateStr];
        }
    } elseif (($counts[$dateStr] ?? 0) >= FIGURIFY_NONRUSH_DATE_CAP) {
        $where = ["DATE(booking_date) = ?", $dateStr];
    }

    if ($where === null) {
        return 0;
    }

    $stmt = $conn->prepare(
        "SELECT order_id, user_id FROM orders
         WHERE order_type = ?
           AND order_id <> ?
           AND status IN ('pending','needs_correction','quoted','awaiting_payment','to_verify','for_approval')
           AND " . $where[0]
    );
    $stmt->bind_param("sis", $orderType, $exceptOrderId, $where[1]);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $n = 0;
    require_once __DIR__ . "/email_template_helper.php";

    foreach ($rows as $row) {
        $orderId = (int) $row["order_id"];

        figurify_customer_notice(
            $conn,
            (int) $row["user_id"],
            $orderId,
            "The booking date of Order #" . $orderId . " is now full. Please choose a new date or cancel your order in My Orders.",
            "The booking date of your Order #" . $orderId . " is now full",
            [
                "We’re sorry — the booking date you selected for <strong>Order #" . $orderId . "</strong> is now fully booked.",
                "Your order has <strong>not</strong> been cancelled. Please log in, go to <strong>My Orders</strong>, and pick a new available date for the same order. If you no longer wish to continue, you can also cancel your order there.",
                figurify_email_status_line(),
                "Thank you for your understanding!",
            ]
        );
        $n++;
    }

    return $n;
}
