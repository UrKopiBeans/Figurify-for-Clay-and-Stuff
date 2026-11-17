<?php

/* =====================================================================
   EXPIRATION HELPER   (helpers/expiration_helper.php)

   Pinagsama sa iisang file ang dating 2 helper:
     expire_helper, progress_expire_helper

   Walang pinalitang pangalan ng function, kaya ang mga page na gumagamit
   nito ay hindi kailangang galawin maliban sa require line
   (palitan lang ng "expiration_helper.php").

   INDEX (hanapin ang "SECTION n" para tumalon):
     SECTION 1 - QUOTE EXPIRY      Quotation na hindi tinanggap sa loob ng 2 araw
                                   + lumang Dress Up order na "pending"
                                   + abiso kapag napuno na ang ship out date
     SECTION 2 - PROGRESS EXPIRY   Progress photo na hindi sinasagot ng customer
                                   (24 oras = paalala, 2 araw / 48 oras = kusang approve)

   BUOD NG TAKBO (paano sila gumagana):
     Walang sariling timer ang mga ito. Tinatawag sila ng mga page tuwing
     may bumubukas (hal. My Orders, Notifications, Active Booking), at ng
     Admin/run_scheduled_tasks.php (cron / Task Scheduler). Kaya tinatawag
     itong "lazy expiration": sa susunod na bukas ng page, doon pa lang
     nari-check kung may nag-expire.

     Hindi direktang binabago ng file na ito ang status ng order. Lahat ay
     dumadaan sa figurify_wf_perform() (helpers/order_helper.php) gamit ang
     SYSTEM actor, kaya may history row at hindi lumalampas sa transition
     rules. Pagkatapos ay may bell + email sa customer
     (figurify_customer_notice sa helpers/messaging_helper.php).

   Tip: kung hindi nag-e-expire ang isang order, tingnan ang
   order_status_history table kung tinanggihan ng workflow, at ang PHP
   error log kung pumalya ang email.
   ===================================================================== */

/* ---------------------------------------------------------------------
   PANTANDAAN (simpleng paliwanag)
   ANO ITO     : taga-"expire" ng mga bagay na lumampas na sa oras.
   PARA SAAN   : 1) Quotation na 2 araw nang hindi tinatanggap = expired.
                 2) Progress photo na hindi sinasagot ng customer: 24 oras = paalala,
                    2 araw (48 oras) = kusang approve.
   PANO NAGANA : walang sariling timer. Tinatawag ito tuwing may nagbubukas ng page at ng
                 Admin/run_scheduled_tasks.php. Kapag may nakitang lampas na, ipapasa nito sa
                 figurify_wf_perform() (order_helper.php) para magpalit ng status, tapos
                 magpapadala ng bell + email sa customer.
   --------------------------------------------------------------------- */

require_once __DIR__ . "/messaging_helper.php";
require_once __DIR__ . "/order_helper.php";


/* =====================================================================
   SECTION 1 - QUOTE EXPIRY
   (galing sa: expire_helper.php)
   Auto-cancel ng "quoted" order na hindi kinumpirma ng customer sa loob
   ng 2 araw. Tinatawag bago kunin ang orders/notifications. Puwede ring
   patakbuhin bilang cron job sa pamamagitan ng Admin/check_expired_orders.php.
   ===================================================================== */

/* Takbo:
   1) ilipat muna ang lumang Dress Up na "pending" papunta sa bayad
   2) hanapin ang lahat ng "quoted" na lampas na sa quote_expires_at
   3) bawat isa: figurify_wf_perform("expire_quote") -> kung pumasa,
      abisuhan ang customer (bell + email) na na-cancel ang order */
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

        // hindi pumasa sa workflow (hal. nabayaran na pala) -> walang abiso
        if (!$result["ok"]) {
            continue;
        }

        figurify_customer_notice(
            $conn,
            (int) $result["order"]["user_id"],
            $orderId,
            "Order #" . $orderId . " was cancelled because the quotation wasn't accepted within 2 days.",
            "Your Order #" . $orderId . " has been cancelled",
            [
                "<strong>Order #" . $orderId . "</strong> was cancelled because the quotation wasn’t accepted within 2 days.",
                "You were not charged. You can place a new order anytime.",
                figurify_email_status_line(),
            ]
        );
    }
}


/* Lumang Dress Up order na "pending" pa (ginawa bago ang direct-payment
   update): walang quotation ang Dress Up, kaya ililipat sa "awaiting_payment"
   para makapagbayad na ang customer. Dumadaan sa workflow (may history).
   Takbo: hanapin ang pending + order_method "create_style" -> perform
   "dressup_to_payment" -> abisuhan ang customer na puwede na magbayad. */
function figurify_move_pending_dressup_to_payment(mysqli $conn): void
{
    $res = $conn->query("SELECT order_id FROM orders WHERE status = 'pending' AND order_method = 'create_style'");

    if (!$res) {
        return;
    }

    foreach (array_map("intval", array_column($res->fetch_all(MYSQLI_ASSOC), "order_id")) as $orderId) {

        $result = figurify_wf_perform($conn, $orderId, "dressup_to_payment", [], figurify_wf_system_actor());

        if (!$result["ok"]) {
            continue;
        }

        figurify_customer_notice(
            $conn,
            (int) $result["order"]["user_id"],
            $orderId,
            "Your Dress Up Order #" . $orderId . " is ready for payment.",
            "Your Dress Up Order #" . $orderId . " is ready for payment",
            [
                "Your Dress Up <strong>Order #" . $orderId . "</strong> is ready for payment.",
                "Go to <strong>My Orders</strong> and click <strong>Proceed to Payment</strong>.",
            ]
        );
    }
}


/* Kapag napuno na ang petsa (2 rush/araw, 10 rush/buwan, 15 non-rush) ng mga
   na-APPROVE ng owner, HINDI na kina-cancel ang iba. Inaabisuhan lang sila na puno
   na ang petsa nila para makapili sila ng ibang petsa (same order) o makapag-cancel
   kung hindi na tutuloy. Ang mga order na hindi pa naaaprubahan (Pending, Correction,
   Quoted, Payment Required, Payment Submitted / For Approval) ang inaabisuhan.

   Takbo:
   1) bilangin ang mga order sa petsang iyon (figurify_get_order_type_day_counts)
   2) rush: puno ba ang buwan (10) o ang araw (2)? non-rush: puno ba ang araw (15)?
   3) kung puno, hanapin ang mga hindi pa naaaprubahang order sa petsa/buwang iyon
      (maliban sa $exceptOrderId, ang order na kaaaprubahan lang)
   4) abisuhan sila (bell + email). Walang kinakansela.
   Ibinabalik ang bilang ng naabisuhan. */
function figurify_notify_full_date_orders(mysqli $conn, string $orderType, string $dateStr, int $exceptOrderId = 0): int
{
    $ts = strtotime($dateStr);

    if (!$ts) {
        return 0;
    }

    $dateStr = date("Y-m-d", $ts);
    $month   = date("Y-m", $ts);

    $counts = figurify_get_order_type_day_counts($conn, $orderType);
    $where  = null;   // [SQL condition, value] kung puno; null kung hindi pa puno

    if ($orderType === "rush") {

        $monthTotal = 0;

        foreach ($counts as $d => $c) {
            if (strpos($d, $month) === 0) {
                $monthTotal += $c;
            }
        }

        if ($monthTotal >= FIGURIFY_RUSH_MONTHLY_CAP) {
            $where = ["DATE_FORMAT(ship_out_date, '%Y-%m') = ?", $month];        // puno ang buong buwan
        } elseif (($counts[$dateStr] ?? 0) >= FIGURIFY_RUSH_DAILY_CAP) {
            $where = ["DATE(ship_out_date) = ?", $dateStr];                       // puno ang araw
        }

    } elseif (($counts[$dateStr] ?? 0) >= FIGURIFY_NONRUSH_DATE_CAP) {
        $where = ["DATE(ship_out_date) = ?", $dateStr];
    }

    if ($where === null) {
        return 0;   // hindi pa puno, walang aabisuhan
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

    foreach ($rows as $row) {

        $orderId = (int) $row["order_id"];

        figurify_customer_notice(
            $conn,
            (int) $row["user_id"],
            $orderId,
            "The ship out date of Order #" . $orderId . " is now full. Please choose a new date or cancel your order in My Orders.",
            "The ship out date of your Order #" . $orderId . " is now full",
            [
                "The ship out date of <strong>Order #" . $orderId . "</strong> is now full.",
                "Your order is <strong>not</strong> cancelled. Pick a new date (or cancel) in <strong>My Orders</strong>.",
                figurify_email_status_line(),
            ]
        );
    }

    return count($rows);
}


/* =====================================================================
   SECTION 2 - PROGRESS EXPIRY
   (galing sa: progress_expire_helper.php)
   Progress photo na hindi sinasagot ng customer:
     - 24 oras: paalala (system + email)
     - 48 oras (2 araw): kusang ma-approve ("auto_approve_progress")
   Tumatakbo pag binuksan ang Active Booking at sa Admin/run_scheduled_tasks.php.
   Ang 24 at 48 ay galing sa FIGURIFY_PROGRESS_REMINDER_HOURS at
   FIGURIFY_PROGRESS_AUTO_APPROVE_HOURS (helpers/order_helper.php).
   ===================================================================== */

/* Takbo: hanapin ang progress photo na "pending", hindi final, ang order ay
   "processing" pa, lampas na sa 24 oras pero wala pang 48, at wala pang
   reminder. Markahan muna ang reminder_sent_at (para hindi madoble kahit
   sabay ang dalawang request), saka magpadala ng paalala.
   Ibinabalik ang bilang ng napadalhan. */
function figurify_send_progress_reminders(mysqli $conn): int
{
    $sent = 0;

    try {

        figurify_wf_ensure_schema($conn);   // siguraduhing may reminder_sent_at column

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
                continue;   // naunahan na ng ibang request
            }

            // kailan kusang ma-aapprove kung walang sagot
            $deadline = date("F j, Y g:i A", strtotime($row["created_at"]) + FIGURIFY_PROGRESS_AUTO_APPROVE_HOURS * 3600);

            figurify_customer_notice(
                $conn,
                (int) $row["user_id"],
                (int) $row["order_id"],
                "Reminder: please review the progress photo for Order #" . $row["order_id"] . " by " . $deadline . ".",
                "Reminder: Please review the progress photo for Order #" . $row["order_id"],
                [
                    "Please review the progress photo for <strong>Order #" . (int) $row["order_id"] . "</strong> in <strong>My Orders</strong>.",
                    "No reply by <strong>" . figurify_email_e($deadline) . "</strong> means it’s approved.",
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


/* Takbo: magpadala muna ng mga paalala (itaas), tapos hanapin ang order na
   may progress photo na "pending" at lampas na sa 48 oras (2 araw) -> perform
   "auto_approve_progress" -> abisuhan ang customer (may balance pa ba o
   ready to ship na). Ibinabalik ang bilang ng na-approve. */
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

            // may natitira pang balance? (galing sa workflow)
            $balance = (float) ($result["data"]["balance_due"] ?? 0);

            figurify_customer_notice(
                $conn,
                (int) $result["order"]["user_id"],
                $orderId,
                "The progress photo for Order #" . $orderId . " was approved automatically."
                    . ($balance > 0 ? (" Please pay the remaining balance of ₱" . number_format($balance, 2) . ".") : " Your order is ready to ship."),
                "The progress photo for Order #" . $orderId . " was approved",
                [
                    "No reply in " . (FIGURIFY_PROGRESS_AUTO_APPROVE_HOURS / 24) . " days, so the photo for <strong>Order #" . $orderId . "</strong> was approved and your order is finalized.",
                    $balance > 0
                        ? ("<strong>Remaining Balance:</strong> ₱" . number_format($balance, 2) . "<br>Go to <strong>My Orders</strong> and click <strong>Pay Balance</strong>.")
                        : "It’s ready to ship. We’ll email you once it ships.",
                    $balance > 0 ? figurify_email_balance_note() : "",
                    figurify_email_status_line(),
                ]
            );
        }

    } catch (\Throwable $e) {
        // tahimik lang: ibabalik ang bilang na na-approve na bago pumalya
        return $approved;
    }

    return $approved;
}
