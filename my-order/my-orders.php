<?php
/* ==========================================================================
   MY ORDERS PAGE   (my-order/my-orders.php)
   Style: my-order/my-orders.css      Script: nasa dulo ng file na ito ([5] SCRIPT)
   Mga action (POST) ng page: confirm_order.php, respond_progress.php,
   order_received.php, submit_payment.php (sa payment-shipping.php).
   Auto-refresh: check_order_updates.php

   LAMAN (taas pababa):
     [1] SETUP       helpers, session, database
     [2] FUNCTIONS   label / presyo / cancel form helpers
     [3] DATA        orders, reviews, progress updates, figures, workflow state
     [4] HTML        navbar, page hero, order list, order details, lightbox, footer
     [5] SCRIPT      search/filter, piliin ang order/figure, progress response,
                     lightbox, auto-open, linis ng URL, auto-refresh
   ========================================================================== */


/* ==========================================================================
   [1] SETUP
   ========================================================================== */
require_once __DIR__ . "/../helpers/security_helper.php";
require_once __DIR__ . "/../helpers/dressup_helper.php";
figurify_start_session();

require_once __DIR__ . "/../Admin/database.php";
require_once __DIR__ . "/../helpers/expiration_helper.php";
require_once __DIR__ . "/../helpers/order_helper.php";
require_once __DIR__ . "/../helpers/booking_helper.php";
require_once __DIR__ . "/../helpers/content_helper.php";
/* ====================== END SETUP ====================== */


/* ==========================================================================
   [2] FUNCTIONS
   (statusLabel / figurify_status_class ay nasa helpers/order_helper.php)
   ========================================================================== */

/* ───── helpers para sa bagong My Orders design (prefix na mo_) ───── */

function mo_e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}


/* Order type label (rush / standard) para sa listahan sa kaliwa */

function mo_order_type_label($type)
{
    return ((string) $type === "rush") ? "Rush Order" : "Standard Order";
}


/* kulay ng status pill - PAREHO sa staff at owner pages (dilaw, berde, asul, pula, pink, lila, kahel) */

function mo_tone_class($statusKey)
{
    $map = [
        "pending"              => "yellow",
        "needs_correction"     => "orange",
        "quoted"               => "green",
        "awaiting_payment"     => "yellow",
        "to_verify"            => "green",
        "for_approval"         => "green",
        "processing"           => "pink",
        "awaiting_balance"     => "yellow",
        "to_verify_balance"    => "green",
        "for_balance_approval" => "green",
        "to_ship"              => "blue",
        "address_returned"     => "orange",
        "shipped"              => "blue",
        "completed"            => "green",
        "cancelled"            => "red",
        "for_refund"           => "red",
        "refunded"             => "purple",
        "revision"             => "yellow",
    ];

    return "tone-" . ($map[$statusKey] ?? "yellow");
}


/* Cancel button na may confirm pop up (iisang form, ginagamit sa ilang state) */

function mo_cancel_form(int $orderId, string $label = "Cancel"): void
{
    ?>
    <form
        method="POST"
        action="confirm_order.php"
        class="mo-inline-form"
        onsubmit="return csConfirmSubmit(this, 'Cancel this order?\nThis can\'t be undone.', {okText: 'Cancel Order', cancelText: 'Keep Order'});"
    >
        <input type="hidden" name="order_id" value="<?php echo (int) $orderId; ?>">
        <input type="hidden" name="action" value="cancel">
        <button type="submit" class="mo-btn mo-btn-secondary"><?php echo mo_e($label); ?></button>
    </form>
    <?php
}


/* Mensahe / dahilan na may sariling container, para alam kung kanino galing
   (hal. "Message from Clay and Stuff"). $fromCustomer = true -> sinulat ng customer mismo. */

function mo_message_box(string $from, string $text, bool $fromCustomer = false): void
{
    if (trim($text) === "") {
        return;
    }
    ?>
    <div class="mo-msg-box<?php echo $fromCustomer ? " is-customer" : ""; ?>">
        <span class="mo-msg-from"><?php echo mo_e($from); ?></span>
        <p><?php echo nl2br(mo_e($text)); ?></p>
    </div>
    <?php
}


/* ====================== END FUNCTIONS ====================== */


/* ==========================================================================
   [3] DATA
   ========================================================================== */

/* site base path - computed base sa project folder location, hindi umaasa sa bilang ng "../" */

$documentRoot = rtrim(str_replace("\\", "/", realpath($_SERVER["DOCUMENT_ROOT"])), "/");
$projectRoot  = rtrim(str_replace("\\", "/", realpath(__DIR__ . "/..")), "/");
$siteBase     = substr($projectRoot, strlen($documentRoot)) . "/";


/* must be logged in */

if (!isset($_SESSION["user_id"])) {

    header("Location: ../Login/Login.php");

    exit();

}

$user_id = $_SESSION["user_id"];


/* clean up any expired quotations (2 days) bago kunin ang list */

figurify_expire_old_quotes($conn);

/* shipped nang 7 araw pero hindi pinindot ang "Order Received" -> complete */
figurify_autocomplete_shipped_orders($conn);


/* banner flags (from confirm_order.php / submit_payment.php) */

$justCancelled  = isset($_GET["cancelled"]) && $_GET["cancelled"] === "1";
$justSubmitted  = isset($_GET["submitted"]) && $_GET["submitted"] === "1";


/* get all orders of this user (newest first) */

/* kasama na ang refund columns (Admin/refund_migration.sql); kapag hindi
   pa na-run ang migration, babalik sa lumang query para hindi masira ang page */
$stmt = false;
try {
    $stmt = $conn->prepare(
        "SELECT order_id, order_type, order_method, ship_out_date, rush_fee,
                subtotal, total_amount, status, created_at,
                quote_expires_at, review_token,
                shipping_name, shipping_address, pin_address, shipping_contact, courier,
                payment_reference, payment_proof, paid_at,
                refund_account_name, refund_account_number, refund_method,
                balance_to_pay,
                refund_reason, refund_type, refund_amount, refund_reference,
                refund_proof, refund_requested_at, refunded_at, decline_reason
         FROM orders
         WHERE user_id = ?
         ORDER BY created_at DESC"
    );
} catch (\Throwable $e) {
    $stmt = false;
}

if ($stmt === false) {
    $stmt = $conn->prepare(
        "SELECT order_id, order_type, order_method, ship_out_date, rush_fee,
                subtotal, total_amount, status, created_at,
                quote_expires_at, review_token,
                shipping_name, shipping_address, shipping_contact, courier,
                payment_reference, payment_proof, paid_at,
                refund_account_name, refund_account_number, refund_method,
                balance_to_pay
         FROM orders
         WHERE user_id = ?
         ORDER BY created_at DESC"
    );
}

$stmt->bind_param("i", $user_id);
$stmt->execute();

$ordersResult = $stmt->get_result();

$orders = [];

while ($row = $ordersResult->fetch_assoc()) {

    $orders[] = $row;

}

$stmt->close();


/* review (kung meron) per "completed" order - para malaman "Leave a Review" vs "View My Review" */

$reviewsByOrder = [];

$reviewStmt = $conn->prepare(
    "SELECT r.order_id, r.rating
     FROM reviews r
     JOIN orders o ON o.order_id = r.order_id
     WHERE o.user_id = ?"
);
$reviewStmt->bind_param("i", $user_id);
$reviewStmt->execute();

$reviewResult = $reviewStmt->get_result();

while ($rRow = $reviewResult->fetch_assoc()) {
    $reviewsByOrder[(int) $rRow["order_id"]] = $rRow;
}

$reviewStmt->close();


/* old completed orders (bago pa yung review feature) walang review_token pa, gawan na */

foreach ($orders as &$reviewTokenOrder) {

    if ($reviewTokenOrder["status"] === "completed" && empty($reviewTokenOrder["review_token"])) {
        $reviewTokenOrder["review_token"] = figurify_ensure_review_token($conn, (int) $reviewTokenOrder["order_id"]);
    }

}
unset($reviewTokenOrder);


/* latest progress update per order, shown inside the order card regardless
   of response status. one query for all orders, matched below. */

$latestProgressStmt = $conn->prepare(
    "SELECT pu.update_id, pu.order_id, pu.image_path, pu.staff_message,
            pu.customer_response, pu.revision_note, pu.staff_reply, pu.is_final,
            pu.auto_approved, pu.created_at
     FROM order_progress_updates pu
     JOIN orders o ON o.order_id = pu.order_id
     WHERE o.user_id = ?
     ORDER BY pu.created_at DESC, pu.update_id DESC"
);

$latestProgressStmt->bind_param("i", $user_id);
$latestProgressStmt->execute();

$latestProgressResult = $latestProgressStmt->get_result();

$latestProgressByOrder = [];

while ($pRow = $latestProgressResult->fetch_assoc()) {

    // DESC order, kaya ang unang makita per order_id ang pinaka-bago - huwag nang palitan
    if (!isset($latestProgressByOrder[$pRow["order_id"]])) {
        $latestProgressByOrder[$pRow["order_id"]] = $pRow;
    }

}

$latestProgressStmt->close();



/* get figures + images for each order */

foreach ($orders as &$order) {

    $fstmt = $conn->prepare(
        "SELECT figure_id, figure_style, product_type, size_label,
                figure_name, notes, product_price, name_fee, figure_total, quantity,
                box_addon_type, box_addon_price,
                funko_box_type, funko_box_name, funko_box_number, funko_box_color,
                hirono_blind_type, hirono_box_design, hirono_box_color, hirono_letter,
                hirono_nickname, hirono_date, hirono_blind_items, hirono_blind_items_total,
                quoted_price
         FROM order_figures
         WHERE order_id = ?"
    );

    $fstmt->bind_param("i", $order["order_id"]);
    $fstmt->execute();

    $fresult = $fstmt->get_result();

    $figures = [];

    while ($frow = $fresult->fetch_assoc()) {

        $imgStmt = $conn->prepare(
            "SELECT image_path
             FROM order_figure_images
             WHERE figure_id = ?"
        );

        $imgStmt->bind_param("i", $frow["figure_id"]);
        $imgStmt->execute();

        $imgResult = $imgStmt->get_result();

        $images = [];

        while ($irow = $imgResult->fetch_assoc()) {

            $images[] = $irow["image_path"];

        }

        $imgStmt->close();

        $frow["images"] = $images;


        /* box reference images, Hirono Blind Box only */

        $boxImgStmt = $conn->prepare(
            "SELECT image_path
             FROM order_figure_box_images
             WHERE figure_id = ?"
        );

        $boxImgStmt->bind_param("i", $frow["figure_id"]);
        $boxImgStmt->execute();

        $boxImgResult = $boxImgStmt->get_result();

        $boxImages = [];

        while ($birow = $boxImgResult->fetch_assoc()) {

            $boxImages[] = $birow["image_path"];

        }

        $boxImgStmt->close();

        $frow["box_images"] = $boxImages;


        /* blind box extras saved as JSON {item_key: price}, decode to a list for display */

        $blindItemLabels = [
            "tear_blind_paper" => "Tear Blind Paper",
            "pouch"             => "Pouch",
            "digital_art"       => "Digital Art (Soft Copy) w/ Photo Card",
        ];

        $frow["blind_items_list"] = [];

        if (!empty($frow["hirono_blind_items"])) {

            $decodedItems = json_decode($frow["hirono_blind_items"], true);

            if (is_array($decodedItems)) {

                foreach ($decodedItems as $itemKey => $itemPrice) {

                    $frow["blind_items_list"][] = [
                        "label" => $blindItemLabels[$itemKey] ?? ucwords(str_replace("_", " ", $itemKey)),
                        "price" => (float) $itemPrice,
                        "image" => hironoBlindItemImage($itemKey),
                    ];

                }

            }

        }


        $figures[] = $frow;

    }

    $fstmt->close();

    $order["figures"] = $figures;

    $order["latest_progress_update"] = $latestProgressByOrder[$order["order_id"]] ?? null;

    /* pending revision request -> show "Revision" status instead of "Processing" */

    $order["has_revision"] = (
        !empty($order["latest_progress_update"])
        && $order["latest_progress_update"]["customer_response"] === "revision"
        && !in_array(
            figurify_status_key($order["status"]),
            ["to_ship", "address_returned", "shipped", "completed", "cancelled"],
            true
        )
    );

}

unset($order);


/* WORKFLOW STATE (iisang source of truth - helpers/order_helper.php):
   canonical state + customer-facing label, revision usage (0/1 o 1/1),
   kasaysayan, at ang huling dahilan kapag ibinalik/na-reject. */

figurify_wf_ensure_schema($conn);

$revisionCounts = [];

$revStmt = $conn->prepare(
    "SELECT order_id, revision_count, tracking_number, shipping_fee, shipped_at,
            balance_payment_reference, balance_paid_at, payment_option
     FROM orders WHERE user_id = ?"
);
$revStmt->bind_param("i", $user_id);
$revStmt->execute();

$orderExtras = [];

foreach ($revStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $revRow) {
    $revisionCounts[(int) $revRow["order_id"]] = (int) $revRow["revision_count"];
    $orderExtras[(int) $revRow["order_id"]]    = $revRow;
}

$revStmt->close();

foreach ($orders as &$order) {

    $oid = (int) $order["order_id"];

    $order["wf_state"]       = figurify_wf_canonical_from($order["status"], $order["latest_progress_update"]);
    $order["revision_count"] = $revisionCounts[$oid] ?? 0;
    $order["cust_label"]     = figurify_wf_state_label($order["wf_state"]);
    // Naipasa na ulit ang progress pagkatapos ng revision -> "Revised"
    if ($order["wf_state"] === "PROGRESS_SUBMITTED" && $order["revision_count"] > 0) {
        $order["cust_label"] = "Revised";
    }
    $order["extra"]          = $orderExtras[$oid] ?? [];
    // Slot hold: puno na ba ang petsa ng Quoted order? (kinukuha dito habang bukas pa ang database)
    $order["problem"]   = ($order["status"] ?? "") === "shipped" ? figurify_delivery_problem($conn, (int) $order["order_id"]) : null;
    $order["slot_open"] = true;
    $order["slot_alts"] = [];
    if (in_array(($order["status"] ?? ""), ["pending", "needs_correction", "quoted", "awaiting_payment", "to_verify", "for_approval"], true) && !empty($order["ship_out_date"])) {
        $slotDate = date("Y-m-d", strtotime((string) $order["ship_out_date"]));
        /* capacity lang (puno na ba?) - hindi ang lead-time rule */
        $order["slot_open"] = figurify_date_has_capacity($conn, (string) $order["order_type"], $slotDate)["ok"];
        if (!$order["slot_open"]) {
            $order["slot_alts"] = figurify_next_available_dates($conn, (string) $order["order_type"], 150);
        }
    }
    $order["payment_option"] = $order["extra"]["payment_option"] ?? "half";
    $order["downpayment"]    = figurify_wf_downpayment_amount($order);
    $order["balance_due"]    = figurify_wf_balance_amount($order);
    $order["history"]        = figurify_wf_history($conn, $oid);

    $order["correction_reason"] = ($order["wf_state"] === "STAFF_CORRECTION_REQUIRED")
        ? figurify_wf_latest_reason($conn, $oid, "STAFF_CORRECTION_REQUIRED")
        : null;

    /* ipakita lang ang rejection reason kung iyon ang pinakahuling nangyari */
    $order["rejection_reason"] = null;
    $lastHistory = !empty($order["history"]) ? $order["history"][count($order["history"]) - 1] : null;

    if ($lastHistory && in_array($lastHistory["action"], ["reject_payment", "reject_balance", "decline_payment_details"], true)) {
        $order["rejection_reason"] = $lastHistory["reason"];
        $order["rejection_action"] = $lastHistory["action"];
    }
}

unset($order);


/* status filter options para sa dropdown katabi ng search bar - base
   lang sa statuses na talagang meron sa orders ng customer na ito,
   para walang option na laging walang laman */

$myOrdersFilterOptions = [];

foreach ($orders as $filterOrder) {

    if ($filterOrder["has_revision"]) {
        $filterKey   = "revision";
        $filterLabel = "Revision Requested";
    } else {
        $filterKey   = figurify_status_key($filterOrder["status"]);
        $filterLabel = $filterOrder["cust_label"];
    }

    if (!isset($myOrdersFilterOptions[$filterKey])) {
        $myOrdersFilterOptions[$filterKey] = $filterLabel;
    }

}


/* payment_unavailable notice from payment-shipping.php */

$paymentNotice       = isset($_GET["notice"]) ? $_GET["notice"] : null;
$paymentNoticeOrder  = null;

if ($paymentNotice === "payment_unavailable" && isset($_GET["order_id"])) {

    $paymentNoticeOrderId = (int) $_GET["order_id"];

    foreach ($orders as $order) {
        if ((int) $order["order_id"] === $paymentNoticeOrderId) {
            $paymentNoticeOrder = $order;
            break;
        }
    }

}

$conn->close();
/* ====================== END DATA ====================== */


/* ==========================================================================
   [4] HTML
   ========================================================================== */
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Orders | Clay and Stuff</title>
    <link rel="icon" type="image/png" href="../Image/brand/logo.png">
    <?php include __DIR__ . "/../Shared/popup-and-security.php"; ?>
    <link rel="stylesheet" href="my-orders.css">
</head>

<body>

<!-- ====================== NAVBAR (Shared/navbar.php) ====================== -->
<?php include __DIR__ . "/../Shared/navbar.php"; ?>
<!-- ====================== END NAVBAR ====================== -->


<!-- ====================== PAGE HERO (css: my-orders.css [2]) ====================== -->
<header class="mo-hero">
    <div class="mo-wrap">
        <span class="mo-eyebrow">TRACK YOUR COMMISSIONS</span>
        <h1>My Orders, All in One Place</h1>
        <p>See and track all your orders here.</p>
    </div>
</header>
<!-- ====================== END PAGE HERO ====================== -->


<!-- ====================== MY ORDERS (css: my-orders.css [3]-[17]) ====================== -->
<main class="orders-page mo-wrap">

    <?php
    /* one-time na mensahe (cancel / bayad / hindi na pwedeng magbayad) bilang pop up */
    $orderPopup = null;

    if ($justCancelled) {
        $orderPopup = [
            "type"    => "error",
            "message" => (isset($_GET["order_id"]) ? "Order #" . (int) $_GET["order_id"] . " has been cancelled." : "The order has been cancelled.")
        ];
    } elseif ($justSubmitted) {
        $orderPopup = [
            "type"    => "success",
            "message" => "Payment submitted! We'll verify it soon."
        ];
    } elseif ($paymentNotice === "payment_unavailable" && $paymentNoticeOrder && figurify_status_key($paymentNoticeOrder["status"]) === "cancelled") {
        $orderPopup = [
            "type"    => "error",
            "message" => "Order #" . (int) $paymentNoticeOrder["order_id"] . " was cancelled and can't be paid."
        ];
    } elseif ($paymentNotice === "payment_unavailable") {
        $orderPopup = [
            "type"    => "info",
            "message" => $paymentNoticeOrder
                ? "Order #" . (int) $paymentNoticeOrder["order_id"] . " can no longer be paid. Its status is now \"" . statusLabel($paymentNoticeOrder["status"]) . "\"."
                : "That order can no longer be paid. Its status has already changed."
        ];
    } elseif ($paymentNotice === "order_not_found") {
        $orderPopup = [
            "type"    => "error",
            "message" => "Order not found."
        ];
    }

    /* shared pop up (Shared/popup-and-security.php) - kapareho ng sa Image Submission */
    if ($orderPopup) {
        echo figurify_toast_now($orderPopup["message"], $orderPopup["type"]);
    }
    ?>


    <?php if (empty($orders)): ?>

        <!-- EMPTY STATE -->
        <div class="no-orders">
            <p>You don't have any orders yet.</p>
            <a href="../Commission/commission.php">Start a Commission</a>
        </div>

    <?php else: ?>

        <!-- filter (iisang dropdown: All + lahat ng status) + search -->

        <div class="orders-toolbar">

            <select class="orders-filter-select" id="myOrdersFilter" aria-label="Filter orders" onchange="myOrdersSetFilter(this.value)">
                <option value="all">All</option>
                <?php foreach ($myOrdersFilterOptions as $filterKey => $filterLabel): ?>
                    <option value="<?php echo mo_e($filterKey); ?>"><?php echo mo_e($filterLabel); ?></option>
                <?php endforeach; ?>
            </select>

            <div class="orders-search-wrap">
                <input
                    type="text"
                    id="myOrdersSearch"
                    class="orders-search-input"
                    placeholder="Search order / status / figure..."
                    oninput="myOrdersApplyFilters()"
                    autocomplete="off"
                >
            </div>

        </div>


        <!-- ===== ORDERS LAYOUT: kaliwa = listahan ng order (scroll kapag lampas 5), kanan = details ===== -->
        <div class="orders-layout">

        <!-- order cards (kaliwa) -->

        <div class="orders-list-scroll" id="myOrdersScroll">

            <?php
            /* "Order 1", "Order 2"... - pinakamatanda = Order 1 (newest first ang $orders) */
            $moSeqMap  = [];
            $moSeqTotal = count($orders);
            foreach (array_values($orders) as $moSeqIdx => $moSeqOrder) {
                $moSeqMap[(int) $moSeqOrder["order_id"]] = $moSeqTotal - $moSeqIdx;
            }
            ?>

            <?php foreach ($orders as $listOrder): ?>

                <?php
                $listSeq = $moSeqMap[(int) $listOrder["order_id"]] ?? (int) $listOrder["order_id"];
                $orderSearchBits = [
                    "order " . $listSeq,
                    "order #" . $listSeq,
                    "order " . $listOrder["order_id"],
                    mo_order_type_label($listOrder["order_type"] ?? ""),
                    (string) $listOrder["order_id"],
                    $listOrder["cust_label"],
                    orderMethodLabel($listOrder["order_method"]),
                    date("F j, Y", strtotime($listOrder["created_at"])),
                ];

                foreach ($listOrder["figures"] as $searchFig) {
                    $orderSearchBits[] = $searchFig["figure_style"] ?? "";
                    $orderSearchBits[] = $searchFig["product_type"] ?? "";
                }

                $orderSearchData = strtolower(implode(" ", array_filter($orderSearchBits)));

                $listStatusKey = $listOrder["has_revision"] ? "revision" : figurify_status_key($listOrder["status"]);

                ?>

                <button
                    type="button"
                    class="order-list-item"
                    data-order-target="order-panel-<?php echo (int) $listOrder["order_id"]; ?>"
                    data-order-id="<?php echo (int) $listOrder["order_id"]; ?>"
                    data-order-status="<?php
                        echo htmlspecialchars(
                            $listOrder["status"]
                            . "::u" . (int) ($listOrder["latest_progress_update"]["update_id"] ?? 0)
                            . "::" . (string) ($listOrder["latest_progress_update"]["customer_response"] ?? "")
                        );
                    ?>"
                    data-search="<?php echo mo_e($orderSearchData); ?>"
                    data-status="<?php echo mo_e($listStatusKey); ?>"
                    onclick="selectOrder(this)"
                >

                    <span class="mo-list-main">
                        <span class="mo-list-top">
                            <span class="order-list-id">Order #<?php echo (int) $listSeq; ?></span>
                            <span class="mo-pill order-list-badge <?php echo mo_tone_class($listStatusKey); ?>"><?php echo mo_e($listOrder["cust_label"]); ?></span>
                        </span>
                        <span class="order-list-date">Ordered on <?php echo mo_e(date("M j, Y", strtotime($listOrder["created_at"]))); ?></span>
                        <?php if (!empty($listOrder["ship_out_date"])): ?>
                            <span class="order-list-date">Ship out date: <?php echo mo_e(date("M j, Y", strtotime($listOrder["ship_out_date"]))); ?></span>
                        <?php endif; ?>
                        <span class="order-list-date"><?php echo mo_e(mo_order_type_label($listOrder["order_type"] ?? "")); ?> · <?php echo mo_e(orderMethodLabel($listOrder["order_method"])); ?></span>
                    </span>

                </button>

            <?php endforeach; ?>

            <div class="orders-search-empty" id="myOrdersSearchEmpty" hidden>
                No orders match your search.
            </div>

        </div>


        <!-- order details (kanan) -->

        <div class="orders-detail-col">

        <?php foreach ($orders as $order): ?>

            <?php
            $oid            = (int) $order["order_id"];
            $statusKey      = figurify_status_key($order["status"] ?? "");
            $wf             = $order["wf_state"];
            $isDressUpOrder = (($order["order_method"] ?? "") === "create_style");
            $extra          = $order["extra"] ?? [];
            $rushSplit      = figurify_rush_split($order, $order["figures"]);
            $payExtras      = figurify_payment_extras($oid);
            ?>

            <div class="order-detail-panel" id="order-panel-<?php echo $oid; ?>">

                <?php
                /* ───── 1. ORDER PROGRESS (6 hakbang, konektado sa totoong workflow state) ───── */
                $trackerSteps = ["Pending", $isDressUpOrder ? "Priced" : "Quoted", "Paid", "Making", "Update", "Shipped"];

                /* pinakahuling hakbang na naabot (0-5) */
                $trackerReachedMap = [
                    "PENDING_STAFF_VERIFICATION" => 0, "STAFF_CORRECTION_REQUIRED" => 0,
                    "QUOTATION_SENT" => 1, "AWAITING_PAYMENT" => 1,
                    "PAYMENT_SUBMITTED" => 1, "PAYMENT_DETAILS_VERIFIED" => 1,
                    "PROCESSING" => 3, "REVISION_REQUESTED" => 3, "REVISION_IN_PROGRESS" => 3,
                    "PROGRESS_SUBMITTED" => 4, "CUSTOMER_APPROVED" => 4,
                    "AWAITING_BALANCE" => 4, "BALANCE_SUBMITTED" => 4, "READY_TO_SHIP" => 4, "ADDRESS_RETURNED" => 4,
                    "SHIPPED" => 5, "COMPLETED" => 5,
                ];
                $trackerStopped = in_array($wf, ["CANCELLED", "REFUND_REQUIRED", "REFUNDED"], true)
                    || in_array($statusKey, ["cancelled", "for_refund", "refunded"], true);
                $trackerReached = $trackerReachedMap[$wf] ?? 0;
                ?>

                <div class="mo-main">

                <!-- order head + tracker + state + progress update = isang card -->
                <section class="mo-card" aria-label="Order progress">

                    <!-- "Order #3" sa kaliwa, "Order Progress" sa dulo ng kanan (iisang linya) -->
                    <div class="mo-order-head">
                        <h2 class="mo-card-title">Order #<?php echo (int) ($moSeqMap[$oid] ?? $oid); ?></h2>
                        <div class="mo-sub">Order Progress</div>
                    </div>

                    <ol class="mo-tracker<?php echo $trackerStopped ? " is-stopped" : ""; ?>">
                        <?php foreach ($trackerSteps as $tIndex => $tLabel): ?>
                            <?php
                            /* done = tapos na, current = kasalukuyan; kapag Shipped/Completed na, lahat done */
                            $tClass = "";
                            if (!$trackerStopped) {
                                if ($tIndex < $trackerReached || $trackerReached === 5) { $tClass = "done"; }
                                elseif ($tIndex === $trackerReached) { $tClass = "current"; }
                                if ($tIndex === 0 && $wf === "STAFF_CORRECTION_REQUIRED") {
                                    $tLabel = "Needs correction";
                                    $tClass = "attention";
                                }
                            }
                            ?>
                            <li class="mo-step <?php echo $tClass; ?>">
                                <span class="mo-dot"><svg class="mo-dot-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="5 12.5 10 17.5 19 7.5"/></svg><span class="mo-dot-num"><?php echo $tIndex + 1; ?></span></span>
                                <span class="mo-step-label"><?php echo mo_e($tLabel); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ol>


                <?php
                /* ───── 2. PROGRESS UPDATE (galing sa staff) - nakatago na kapag naipadala / tapos na ang order ───── */
                if (!empty($order["latest_progress_update"]) && !in_array($statusKey, ["shipped", "completed"], true)):

                    $ppu          = $order["latest_progress_update"];
                    $ppuIsPending = ($ppu["customer_response"] === "pending") && empty($ppu["is_final"]);

                    /* malinaw na salita kung nasaan na ang progress photo / revision */
                    if (!empty($ppu["is_final"])) {
                        $ppuPill = ["Your figure is done!", "tone-green"];
                    } elseif ($ppu["customer_response"] === "pending") {
                        $ppuPill = ["New progress photo", "tone-yellow"];
                    } elseif ($ppu["customer_response"] === "approved") {
                        $ppuPill = ["You approved this photo", "tone-green"];
                    } elseif ($ppu["customer_response"] === "revision") {
                        $ppuPill = ["Revision requested", "tone-yellow"];
                    } elseif ($ppu["customer_response"] === "in_progress") {
                        $ppuPill = ["Revision accepted", "tone-blue"];
                    } else {
                        $ppuPill = ["Revision declined", "tone-red"];
                    }

                    /* ONE-TIME REVISION: nakatago ang button kapag nagamit na -
                       pero ang backend (order_workflow.php) ang tunay na bantay */
                    $canRequestRevision = ((int) $order["revision_count"] < FIGURIFY_WF_MAX_REVISIONS);
                ?>

                <!-- walang container: may guhit sa ilalim ng tracker, at may kulay na background para alam na update ito.
                     kaliwa = malaking larawan (kasing-taas ng laman sa kanan), kanan = title + status, paalala, mensahe, buttons -->
                <section class="mo-progress-card" id="progress-update-<?php echo $oid; ?>">

                    <div class="mo-progress">

                        <img
                            class="mo-progress-img"
                            src="../<?php echo mo_e($ppu["image_path"]); ?>"
                            alt="Order progress photo"
                            onclick="openImageLightbox('../<?php echo mo_e($ppu["image_path"]); ?>', 'Progress Update')"
                        >

                        <div class="mo-progress-body">

                            <!-- "Progress update" sa kaliwa, status (hal. "New progress photo") sa kanan -->
                            <div class="mo-progress-head">
                                <h3 class="mo-progress-title">Progress update</h3>
                                <span class="mo-pill <?php echo $ppuPill[1]; ?>"><?php echo mo_e($ppuPill[0]); ?></span>
                            </div>

                            <?php if ($ppuIsPending): ?>

                                <?php
                                $ppuDeadline = !empty($ppu["created_at"])
                                    ? date("M j, g:i A", strtotime($ppu["created_at"]) + FIGURIFY_PROGRESS_AUTO_APPROVE_HOURS * 3600)
                                    : "";
                                ?>

                                <!-- paalala: nasa taas ng mensahe -->
                                <p class="mo-progress-note">
                                    <?php if ($canRequestRevision): ?>
                                        Happy with it? Tap <strong>It's Good</strong>, or request a change.
                                        <?php if ($ppuDeadline !== ""): ?>No reply by <strong><?php echo mo_e($ppuDeadline); ?></strong> means it's approved.<?php endif; ?>
                                    <?php else: ?>
                                        You've used your revision. This update will be approved automatically<?php echo $ppuDeadline !== "" ? " on <strong>" . mo_e($ppuDeadline) . "</strong>" : ""; ?>.
                                    <?php endif; ?>
                                </p>

                            <?php endif; ?>

                            <?php mo_message_box("Message from Clay and Stuff", (string) ($ppu["staff_message"] ?? "")); ?>

                            <?php if ($ppuIsPending): ?>

                                <form
                                    method="POST"
                                    action="respond_progress.php"
                                    class="progress-update-form"
                                    id="progressUpdateForm-<?php echo $oid; ?>"
                                >

                                    <input type="hidden" name="update_id" value="<?php echo (int) $ppu["update_id"]; ?>">
                                    <input type="hidden" name="order_id" value="<?php echo $oid; ?>">
                                    <input type="hidden" name="response" id="progressResponseField-<?php echo $oid; ?>" value="">

                                    <div class="mo-actions" id="progressButtonsRow-<?php echo $oid; ?>">
                                        <button type="button" class="mo-btn mo-btn-primary" onclick="submitProgressResponse(<?php echo $oid; ?>, 'approved')">It's Good</button>
                                        <?php if ($canRequestRevision): ?>
                                            <button type="button" class="mo-btn mo-btn-secondary" onclick="showProgressRevisionBox(<?php echo $oid; ?>)">Request Revision</button>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($canRequestRevision): ?>
                                    <div class="progress-update-revision-box" id="progressRevisionBox-<?php echo $oid; ?>" hidden>

                                        <label for="progressRevisionNote-<?php echo $oid; ?>">What would you like changed?</label>

                                        <textarea
                                            name="revision_note"
                                            id="progressRevisionNote-<?php echo $oid; ?>"
                                            rows="2"
                                            placeholder="Tell us what to revise..."
                                        ></textarea>

                                        <div class="mo-actions">
                                            <button type="button" class="mo-btn mo-btn-primary" onclick="submitProgressResponse(<?php echo $oid; ?>, 'revision')">Send</button>
                                            <button type="button" class="mo-btn mo-btn-secondary" onclick="hideProgressRevisionBox(<?php echo $oid; ?>)">Cancel</button>
                                        </div>

                                    </div>
                                    <?php endif; ?>

                                </form>

                            <?php elseif (!empty($ppu["is_final"])): ?>

                                <p class="mo-progress-status is-approved">Your figure is done! We'll ship it soon.</p>

                            <?php elseif ($ppu["customer_response"] === "revision" || $ppu["customer_response"] === "in_progress"): ?>

                                <?php mo_message_box("Your revision request", (string) ($ppu["revision_note"] ?? ""), true); ?>

                                <p class="mo-progress-status<?php echo $ppu["customer_response"] === "in_progress" ? " is-approved" : ""; ?>">
                                    <?php echo $ppu["customer_response"] === "in_progress"
                                        ? "Your revision was accepted. We're now working on the changes and will send a new photo."
                                        : "Your revision request was sent. Please wait for the staff to accept it."; ?>
                                </p>

                            <?php elseif ($ppu["customer_response"] === "declined"): ?>

                                <?php mo_message_box("Your revision request", (string) ($ppu["revision_note"] ?? ""), true); ?>

                                <?php mo_message_box("Reply from Clay and Stuff", !empty($ppu["staff_reply"]) ? (string) $ppu["staff_reply"] : "Sorry, we can't make this revision."); ?>

                            <?php endif; ?>

                        </div>

                    </div>

                </section>

                <?php endif; ?>


                <?php
                /* ───── 3. ACTION STATES ───── */

                /* 3a. Puno na ang petsa (hindi pa naaaprubahan ng owner) -> pumili ng ibang petsa
                       (same order, walang bagong bayad) o mag-cancel kung hindi na tutuloy. */
                $quotedDateStr  = !empty($order["ship_out_date"]) ? date("Y-m-d", strtotime((string) $order["ship_out_date"])) : "";
                $quotedDateOpen = !empty($order["slot_open"]);
                $quotedAltDates = $order["slot_alts"] ?? [];
                $dateFullPaid   = in_array($wf, ["PAYMENT_SUBMITTED", "PAYMENT_DETAILS_VERIFIED"], true);
                ?>

                <?php if (!$quotedDateOpen && $quotedDateStr !== ""): ?>
                    <section class="mo-state">
                        <span class="mo-pill tone-yellow">Date is full</span>
                        <p class="mo-state-text">
                            The date you picked (<?php echo mo_e(date("M j, Y", strtotime($quotedDateStr))); ?>) is now full. Please choose a new date<?php echo $dateFullPaid ? ". You don't need to pay again." : ", or cancel the order if you no longer want to continue."; ?>
                        </p>

                        <?php if (!empty($quotedAltDates)): ?>
                            <!-- Calendar: ang may kulay lang na petsa ang pwedeng piliin -->
                            <form method="POST" action="confirm_order.php" class="mo-date-form" data-dates="<?php echo mo_e(implode(",", $quotedAltDates)); ?>">
                                <input type="hidden" name="order_id" value="<?php echo $oid; ?>">
                                <input type="hidden" name="action" value="change_date">
                                <input type="hidden" name="new_date" value="" required>
                                <div class="mo-cal">
                                    <div class="mo-cal-head">
                                        <button type="button" class="mo-cal-nav" data-dir="-1" aria-label="Previous month">&lsaquo;</button>
                                        <strong class="mo-cal-title"></strong>
                                        <button type="button" class="mo-cal-nav" data-dir="1" aria-label="Next month">&rsaquo;</button>
                                    </div>
                                    <div class="mo-cal-grid"></div>
                                    <p class="mo-cal-picked">Pick a highlighted date.</p>
                                </div>
                                <button type="submit" class="mo-btn mo-btn-primary">Use this date</button>
                            </form>
                        <?php else: ?>
                            <p class="mo-state-sub">No dates are available right now. Please try again later.</p>
                        <?php endif; ?>

                        <?php if ($dateFullPaid): ?>
                            <form method="POST" action="confirm_order.php" class="mo-date-cancel">
                                <input type="hidden" name="order_id" value="<?php echo $oid; ?>">
                                <input type="hidden" name="action" value="cancel_full_date">
                                <p class="mo-state-sub">Don't want to change the date? You can cancel and the owner will handle your refund.</p>
                                <button type="submit" class="mo-btn">Cancel &amp; request refund</button>
                            </form>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>


                <?php
                /* 3b. Pangunahing state ng order */
                $refundReasonText = trim((string) ($order["refund_reason"] ?? ""));
                $showRefundState  = in_array($statusKey, ["for_refund", "refunded"], true);
                ?>

                <?php if ($showRefundState): ?>

                    <?php if ($statusKey === "refunded"): ?>

                        <section class="mo-state">
                            <p class="mo-state-text">
                                Your refund has been sent.
                                Amount ₱<?php echo number_format((float) ($order["refund_amount"] ?? 0), 2); ?>
                                <?php if (!empty($order["refund_reference"])): ?> · Ref <?php echo mo_e($order["refund_reference"]); ?><?php endif; ?>
                                <?php if (!empty($order["refunded_at"])): ?> · <?php echo mo_e(date("M j", strtotime($order["refunded_at"]))); ?><?php endif; ?>
                            </p>
                            <?php mo_message_box("Refund reason", $refundReasonText); ?>
                            <?php if (!empty($order["refund_proof"])): ?>
                                <?php $refundProofUrl = figurify_proof_url($order["refund_proof"]); ?>
                                <a
                                    class="mo-proof-link"
                                    href="javascript:void(0)"
                                    onclick="openImageLightbox('<?php echo mo_e($refundProofUrl); ?>', 'Proof of Refund')"
                                >
                                    <img src="<?php echo mo_e($refundProofUrl); ?>" alt="Proof of refund for order <?php echo $oid; ?>">
                                    <span>Proof of Refund</span>
                                </a>
                            <?php endif; ?>
                        </section>

                    <?php else: ?>

                        <section class="mo-state">
                            <p class="mo-state-text">
                                We are processing your refund. It will be sent to the account below within 7 to 14 business days.
                            </p>
                            <?php mo_message_box("Refund reason", $refundReasonText); ?>
                        </section>

                    <?php endif; ?>

                <?php elseif ($wf === "CANCELLED" || $statusKey === "cancelled"): ?>

                    <section class="mo-state">
                        <p class="mo-state-text">
                            This order was cancelled.
                        </p>
                        <?php mo_message_box("Reason from Clay and Stuff", (string) ($order["decline_reason"] ?? "")); ?>
                    </section>

                <?php elseif ($wf === "PENDING_STAFF_VERIFICATION"): ?>

                    <section class="mo-state">
                        <p class="mo-state-text">We're checking your order. Your quotation will be sent soon.</p>
                        <div class="mo-actions"><?php mo_cancel_form($oid, "Cancel Order"); ?></div>
                    </section>

                <?php elseif ($wf === "STAFF_CORRECTION_REQUIRED"): ?>

                    <section class="mo-state">
                        <p class="mo-state-text">Please check and fix your order details, then send it again.</p>
                        <?php mo_message_box("Message from Clay and Stuff", (string) ($order["correction_reason"] ?? "Our staff needs you to review your order details.")); ?>
                        <div class="mo-actions">
                            <a href="correct-order.php?order_id=<?php echo $oid; ?>" class="mo-btn mo-btn-primary">Fix My Order</a>
                            <?php mo_cancel_form($oid, "Cancel"); ?>
                        </div>
                    </section>

                <?php elseif ($statusKey === "quoted"): ?>

                    <section class="mo-state">
                        <p class="mo-state-text">
                            <?php if (!empty($order["quote_expires_at"])): ?>
                                Please respond by <strong><?php echo mo_e(date("M j, Y, g:i A", strtotime($order["quote_expires_at"]))); ?></strong>, or this order will be cancelled automatically.
                            <?php else: ?>
                                Your quotation is ready. Proceed to payment to confirm your order, or cancel it.
                            <?php endif; ?>
                        </p>
                        <div class="mo-actions">
                            <?php if ($quotedDateOpen): ?>
                                <form method="POST" action="confirm_order.php" class="mo-inline-form">
                                    <input type="hidden" name="order_id" value="<?php echo $oid; ?>">
                                    <input type="hidden" name="action" value="continue">
                                    <button type="submit" class="mo-btn mo-btn-primary">Proceed to Payment</button>
                                </form>
                            <?php endif; ?>
                            <?php mo_cancel_form($oid, "Cancel"); ?>
                        </div>
                    </section>

                <?php elseif ($statusKey === "awaiting_payment"): ?>

                    <?php
                    $rejReason = trim((string) ($order["rejection_reason"] ?? ""));
                    $rejAction = (string) ($order["rejection_action"] ?? "");
                    ?>

                    <section class="mo-state">
                        <?php if ($rejReason !== ""): ?>
                            <span class="mo-pill tone-yellow"><?php echo $rejAction === "decline_payment_details" ? "Payment details declined" : "Payment proof declined"; ?></span>
                            <p class="mo-state-text">
                                <?php echo $rejAction === "decline_payment_details" ? "Please submit your details again." : "Please submit a new proof."; ?>
                            </p>
                            <?php mo_message_box("Reason from Clay and Stuff", $rejReason); ?>
                            <div class="mo-actions">
                                <a href="payment-shipping.php?order_id=<?php echo $oid; ?>" class="mo-btn mo-btn-primary">Resubmit payment</a>
                                <?php mo_cancel_form($oid, "Cancel"); ?>
                            </div>
                        <?php else: ?>
                            <p class="mo-state-text">Send your down payment so we can start on your figure.</p>
                            <div class="mo-actions">
                                <a href="payment-shipping.php?order_id=<?php echo $oid; ?>" class="mo-btn mo-btn-primary">Proceed to Payment</a>
                                <?php mo_cancel_form($oid, "Cancel"); ?>
                            </div>
                        <?php endif; ?>
                    </section>

                <?php elseif (in_array($wf, ["PAYMENT_SUBMITTED", "PAYMENT_DETAILS_VERIFIED"], true)): ?>

                    <!-- OWNER lang ang nagve-verify ng bayad -->
                    <section class="mo-state">
                        <p class="mo-state-text">
                            <?php echo $wf === "PAYMENT_DETAILS_VERIFIED"
                                ? "Payment checked. We'll start on your figure once it's approved."
                                : "Payment sent. We'll let you know once it's approved."; ?>
                        </p>
                    </section>

                <?php elseif ($statusKey === "awaiting_balance"): ?>

                    <?php $balRej = trim((string) ($order["rejection_reason"] ?? "")); ?>

                    <section class="mo-state">
                        <p class="mo-state-text">Your figure is approved! You can pay the remaining balance now.</p>
                        <p class="mo-state-sub"><strong>Note:</strong> We will keep working on your order, but we can only ship it after your balance is paid.</p>
                        <?php if ($balRej !== ""): ?>
                            <p class="mo-state-sub">The owner couldn't verify your last balance payment. Please upload a new proof.</p>
                            <?php mo_message_box("Reason from Clay and Stuff", $balRej); ?>
                        <?php endif; ?>
                        <div class="mo-actions">
                            <a href="pay-balance.php?order_id=<?php echo $oid; ?>" class="mo-btn mo-btn-primary">Pay Balance</a>
                        </div>
                    </section>

                <?php elseif ($wf === "BALANCE_SUBMITTED"): ?>

                    <!-- diretso sa owner ang balance payment, walang staff-verification step -->
                    <section class="mo-state">
                        <p class="mo-state-text">Balance sent. We'll ship your order once it's approved.</p>
                    </section>

                <?php elseif (in_array($wf, ["PROCESSING", "REVISION_IN_PROGRESS", "REVISION_REQUESTED"], true) && empty($order["latest_progress_update"])): ?>

                    <section class="mo-state mo-state-making">
                        <span class="mo-state-icon">🎨</span>
                        <div>
                            <p class="mo-state-title">We're making your figure</p>
                            <p class="mo-state-text">We'll send you a progress photo soon.</p>
                        </div>
                    </section>

                <?php elseif ($wf === "READY_TO_SHIP"): ?>

                    <section class="mo-state">
                        <p class="mo-state-text">
                            It will be sent via <?php echo mo_e(strtoupper((string) ($order["courier"] ?? ""))); ?>
                            to <?php echo mo_e((string) ($order["shipping_address"] ?? "")); ?>.
                        </p>
                    </section>

                <?php elseif ($wf === "ADDRESS_RETURNED"): ?>

                    <!-- Ibinalik ng staff: hindi mahanap ng courier ang address -> ayusin dito, babalik sa Ready to Ship -->
                    <section class="mo-state">
                        <span class="mo-pill tone-orange">Fix shipping details</span>
                        <p class="mo-state-text">We couldn't ship your order using these shipping details. Please update them so we can ship it.</p>
                        <?php mo_message_box("Reason from Clay and Stuff", (string) (figurify_wf_latest_reason($conn, $oid, "ADDRESS_RETURNED") ?? "")); ?>

                        <form method="POST" action="confirm_order.php" class="mo-address-form">
                            <input type="hidden" name="order_id" value="<?php echo $oid; ?>">
                            <input type="hidden" name="action" value="update_address">

                            <label>Full Name
                                <input type="text" name="shipping_name" maxlength="150" value="<?php echo mo_e((string) ($order["shipping_name"] ?? "")); ?>" required>
                            </label>
                            <label>Full Address
                                <input type="text" name="shipping_address" maxlength="255" placeholder="House No. / Street / Brgy, City / Province" value="<?php echo mo_e((string) ($order["shipping_address"] ?? "")); ?>" required>
                            </label>
                            <label>Pin Address / Google Maps Link (Optional)
                                <input type="text" name="pin_address" maxlength="255" value="<?php echo mo_e((string) ($order["pin_address"] ?? "")); ?>">
                            </label>
                            <label>Contact Number
                                <input type="text" name="shipping_contact" inputmode="numeric" pattern="[0-9]{11}" maxlength="11" placeholder="e.g. 09171234567" value="<?php echo mo_e((string) ($order["shipping_contact"] ?? "")); ?>" data-digits-only required>
                            </label>
                            <label>Courier
                                <select name="courier" required>
                                    <option value="lbc"<?php echo ($order["courier"] ?? "") === "lbc" ? " selected" : ""; ?>>LBC</option>
                                    <option value="lalamove"<?php echo ($order["courier"] ?? "") === "lalamove" ? " selected" : ""; ?>>Lalamove</option>
                                </select>
                            </label>

                            <div class="mo-actions">
                                <button type="submit" class="mo-btn mo-btn-primary">Update Shipping Details</button>
                            </div>
                        </form>
                    </section>

                <?php elseif ($wf === "SHIPPED"): ?>

                    <section class="mo-state">
                        <p class="mo-state-text">Your order is on the way.</p>

                        <!-- Courier / Tracking Number / Shipping Fee / Shipped On: pare-parehong row, pantay ang value -->
                        <div class="mo-state-meta">
                            <div><span>Courier</span><span><?php echo mo_e(strtoupper((string) ($order["courier"] ?? "N/A"))); ?></span></div>
                            <div><span>Tracking Number</span><span><?php echo mo_e((string) ($extra["tracking_number"] ?? "N/A")); ?></span></div>
                            <?php if (isset($extra["shipping_fee"]) && $extra["shipping_fee"] !== null): ?>
                                <div><span>Shipping Fee</span><span>₱<?php echo number_format((float) $extra["shipping_fee"], 2); ?></span></div>
                            <?php endif; ?>
                            <?php if (!empty($extra["shipped_at"])): ?>
                                <div><span>Shipped On</span><span><?php echo mo_e(date("M j, Y", strtotime($extra["shipped_at"]))); ?></span></div>
                            <?php endif; ?>
                        </div>

                        <?php figurify_render_shipping_receipt($oid, "customer"); ?>

                        <?php /* Order Received - saka lalabas ang Leave a Review */ ?>
                        <div class="mo-received">
                            <p>Got your order? Tap <strong>Order Received</strong>, then you can leave a review.</p>
                            <?php $autoReceive = figurify_auto_receive_date($extra["shipped_at"] ?? null); ?>
                            <?php if ($autoReceive !== ""): ?>
                                <p class="mo-state-sub">If you don't tap it, we'll mark it as received on <?php echo mo_e($autoReceive); ?>.</p>
                            <?php endif; ?>
                            <!-- Order Received at Report: magkatabi -->
                            <div class="mo-actions">
                                <form
                                    method="POST"
                                    action="order_received.php"
                                    class="mo-inline-form"
                                    onsubmit="return csConfirmSubmit(this, 'Did you receive Order #<?php echo $oid; ?>?\nThis will complete your order.', {okText: 'Yes, Received', cancelText: 'Not Yet'});"
                                >
                                    <input type="hidden" name="order_id" value="<?php echo $oid; ?>">
                                    <button type="submit" class="mo-btn mo-btn-primary">Order Received</button>
                                </form>
                                <?php if (empty($order["problem"])): ?>
                                    <button type="button" class="mo-btn mo-btn-secondary" onclick="moToggleReport(<?php echo $oid; ?>, true)">Report</button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php /* May problema sa dumating (hal. sira)? Note + proof -> makikita ng staff/owner sa Shipments */ ?>
                        <?php if (!empty($order["problem"])): ?>
                            <?php echo figurify_delivery_problem_html($order["problem"], "Your delivery problem report"); ?>
                            <p class="mo-state-sub">The owner is checking your report. We will contact you about your refund.</p>
                        <?php else: ?>
                            <!-- REPORT POPUP: lalabas pag-click ng Report (note + photo, Cancel / Send Report) -->
                            <div class="mo-report-modal" id="moReportModal-<?php echo $oid; ?>" data-order="<?php echo $oid; ?>" onclick="if (event.target === this) moToggleReport(<?php echo $oid; ?>, false)" hidden>
                                <form method="POST" action="order_received.php" enctype="multipart/form-data" class="mo-report-form" id="moReportForm-<?php echo $oid; ?>">
                                    <button type="button" class="mo-report-close" aria-label="Close" onclick="moToggleReport(<?php echo $oid; ?>, false)"></button>
                                    <h3 class="mo-report-title">Report a problem</h3>
                                    <input type="hidden" name="order_id" value="<?php echo $oid; ?>">
                                    <input type="hidden" name="problem" value="1">
                                    <label class="mo-report-label" for="moReportNote-<?php echo $oid; ?>">What is wrong with your order?</label>
                                    <textarea name="problem_note" id="moReportNote-<?php echo $oid; ?>" rows="3" maxlength="500" placeholder="e.g. The figure arrived damaged" required></textarea>
                                    <span class="mo-report-label">Photo of the problem</span>
                                    <!-- kapareho ng upload box ng Payment page: buong box ang clickable, nasa loob ang larawan -->
                                    <div class="mo-upload">
                                        <span class="mo-upload-text">Click to upload a photo<small>JPG, PNG, WEBP up to 5MB</small></span>
                                        <img alt="Selected photo" hidden>
                                        <input type="file" name="problem_proof" accept="image/jpeg,image/png,image/webp" required onchange="moPreviewUpload(this)">
                                    </div>
                                    <div class="mo-actions">
                                        <button type="button" class="mo-btn mo-btn-secondary" onclick="moToggleReport(<?php echo $oid; ?>, false)">Cancel</button>
                                        <button type="submit" class="mo-btn mo-btn-primary">Send Report</button>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>
                    </section>

                <?php elseif ($statusKey === "completed"): ?>

                    <?php
                    $orderReview = $reviewsByOrder[$oid] ?? null;
                    $reviewToken = $order["review_token"] ?? "";
                    ?>

                    <section class="mo-state">

                        <?php if ($orderReview): ?>
                            <p class="mo-state-text">
                                Thanks for your review!
                                <span class="mo-gold-stars"><?php echo str_repeat("★", (int) $orderReview["rating"]) . str_repeat("☆", 5 - (int) $orderReview["rating"]); ?></span>
                            </p>
                            <?php if ($reviewToken !== ""): ?>
                                <div class="mo-actions">
                                    <a href="submit_review.php?order_id=<?php echo $oid; ?>&token=<?php echo urlencode($reviewToken); ?>" class="mo-btn mo-btn-primary">View My Review</a>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="mo-state-text">Happy with your figure?</p>
                            <?php if ($reviewToken !== ""): ?>
                                <div class="mo-actions">
                                    <a href="submit_review.php?order_id=<?php echo $oid; ?>&token=<?php echo urlencode($reviewToken); ?>" class="mo-btn mo-btn-primary">Leave a review</a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </section>

                <?php endif; ?>

                </section>
                <!-- /.status card -->


                <!-- ───── 5. FIGURES IN THIS ORDER ───── -->

                <section class="mo-card mo-figures-card">

                    <?php $figureCountInOrder = count($order["figures"]); ?>

                    <h2 class="mo-card-title">Figures in This Order (<?php echo $figureCountInOrder; ?>)</h2>

                    <?php if ($figureCountInOrder > 1): ?>

                        <!-- figure tabs, lalabas lang kapag 2+ figures -->

                        <div class="figure-tabs">
                            <?php foreach ($order["figures"] as $fIndex => $tabFig): ?>
                                <button
                                    type="button"
                                    class="figure-tab-btn<?php echo ($fIndex === 0) ? " active" : ""; ?>"
                                    data-figure-target="figure-panel-<?php echo $oid; ?>-<?php echo $fIndex; ?>"
                                    onclick="selectFigure(this)"
                                >
                                    Figure <?php echo $fIndex + 1; ?>
                                </button>
                            <?php endforeach; ?>
                        </div>

                    <?php endif; ?>

                    <div class="order-figures">

                        <?php foreach ($order["figures"] as $index => $fig): ?>

                            <?php
                            $hasSize       = figureHasSize($fig);
                            $isDressUpFig  = figurify_dressup_for_figure($fig) !== null;
                            $figRush       = (float) ($rushSplit["per"][$index] ?? 0);
                            ?>

                            <div
                                class="order-figure<?php echo ($index === 0) ? " active" : ""; ?>"
                                id="figure-panel-<?php echo $oid; ?>-<?php echo $index; ?>"
                            >

                                <!-- figure information (buong lapad): Figure Style hanggang Rush Fee -->

                                <div class="mo-sub">Figure <?php echo $index + 1; ?> Information</div>

                                <dl class="mo-rows">

                                    <div class="mo-row">
                                        <dt class="mo-k">Figure Style</dt>
                                        <dd class="mo-v"><span class="mo-val-text"><?php echo mo_e($fig["figure_style"]); ?></span></dd>
                                    </div>

                                    <div class="mo-row">
                                        <dt class="mo-k">Product Type</dt>
                                        <dd class="mo-v"><?php echo mo_e($fig["product_type"]); ?></dd>
                                        <dd class="mo-p"><?php if (!$hasSize): ?>₱<?php echo number_format((float) $fig["product_price"], 2); ?><?php endif; ?></dd>
                                    </div>

                                    <div class="mo-row">
                                        <dt class="mo-k">Size</dt>
                                        <dd class="mo-v"><?php echo $hasSize ? mo_e(preg_replace('/^([0-9.]+)\s*(?:"|in|inch|inches)?$/', '$1 inches', trim((string) $fig["size_label"]))) : "No size required"; ?></dd>
                                        <dd class="mo-p"><?php if ($hasSize): ?>₱<?php echo number_format((float) $fig["product_price"], 2); ?><?php endif; ?></dd>
                                    </div>

                                    <?php if (!$isDressUpFig): /* walang Figure Name sa Dress Up */ ?>
                                        <div class="mo-row">
                                            <dt class="mo-k">Figure Name</dt>
                                            <dd class="mo-v"><?php echo mo_e(figurify_figure_name_display($fig)); ?></dd>
                                            <dd class="mo-p"><?php if ((float) $fig["name_fee"] > 0): ?>₱<?php echo number_format((float) $fig["name_fee"], 2); ?><?php endif; ?></dd>
                                        </div>
                                    <?php endif; ?>

                                    <div class="mo-row">
                                        <dt class="mo-k">Quantity</dt>
                                        <dd class="mo-v"><?php echo figurify_figure_quantity($fig); ?></dd>
                                    </div>

                                    <?php if ($figRush > 0): ?>
                                        <div class="mo-row">
                                            <dt class="mo-k">Rush Fee</dt>
                                            <dd class="mo-v">Rush Order</dd>
                                            <dd class="mo-p">₱<?php echo number_format($figRush, 2); ?></dd>
                                        </div>
                                    <?php endif; ?>

                                </dl>

                                <?php if ($isDressUpFig): ?>

                                    <!-- DRESS UP DESIGN: isang container - kaliwa ang napili (larawan | pangalan | kulay | presyo),
                                         kanan ang 3D figure (kasing-taas ng listahan) -->
                                    <div class="mo-dressup-box">
                                        <div class="mo-dressup-list">
                                            <?php figurify_render_dressup_design($fig, "customer"); ?>
                                        </div>
                                        <div class="mo-dressup-view">
                                            <?php figurify_render_dressup_viewer($fig); ?>
                                        </div>
                                    </div>

                                <?php else: ?>

                                    <!-- uploaded reference photos -->

                                    <?php $imgCount = count($fig["images"]); ?>

                                    <div class="mo-gallery-label">Reference Images <span>(<?php echo $imgCount; ?>)</span></div>

                                    <?php if ($imgCount > 0): ?>

                                        <div class="gallery-row">
                                            <?php foreach ($fig["images"] as $imgIndex => $imgPath): ?>
                                                <img
                                                    src="<?php echo mo_e($siteBase . $imgPath); ?>"
                                                    alt="<?php echo mo_e($fig["figure_name"]); ?> photo <?php echo $imgIndex + 1; ?>"
                                                    class="gallery-thumb"
                                                    onclick="openImageLightbox('<?php echo mo_e($siteBase . $imgPath); ?>', '<?php echo mo_e($fig["figure_name"]); ?>')"
                                                >
                                            <?php endforeach; ?>
                                        </div>

                                    <?php else: ?>

                                        <div class="no-image">No Image</div>

                                    <?php endif; ?>

                                <?php endif; ?>

                                <?php if (!empty($fig["notes"])): ?>
                                    <div class="notes-box">
                                        <strong>Your Notes</strong>
                                        <p><?php echo nl2br(mo_e($fig["notes"])); ?></p>
                                    </div>
                                <?php endif; ?>

                                    <!-- Box & Add-ons: nasa baba, sariling container, lalabas lang kapag may box na inorder -->
                                    <?php if (in_array($fig["box_addon_type"], ["funko_box", "hirono_blind_box"], true)): ?>
                                    <div class="box-addons-section">

                                        <div class="mo-sub">Box &amp; Add-ons</div>

                                        <?php if ($fig["box_addon_type"] === "funko_box"): ?>

                                            <dl class="mo-rows">
                                                <div class="mo-row">
                                                    <dt class="mo-k">Box Type</dt>
                                                    <dd class="mo-v"><?php echo $fig["funko_box_type"] === "solo" ? "Solo Box" : "Couple Box"; ?></dd>
                                                    <dd class="mo-p">₱<?php echo number_format((float) $fig["box_addon_price"], 2); ?></dd>
                                                </div>
                                            </dl>

                                            <div class="mo-inline-trio">
                                                <div><span class="mo-k">Name on Box</span><span class="mo-v"><?php echo mo_e($fig["funko_box_name"]); ?></span></div>
                                                <div><span class="mo-k">Box Number</span><span class="mo-v"><?php echo mo_e($fig["funko_box_number"]); ?></span></div>
                                                <div><span class="mo-k">Box Color</span><span class="mo-v"><?php echo mo_e($fig["funko_box_color"]); ?></span></div>
                                            </div>

                                        <?php elseif ($fig["box_addon_type"] === "hirono_blind_box"): ?>

                                            <dl class="mo-rows">

                                                <div class="mo-row">
                                                    <dt class="mo-k">Box Type</dt>
                                                    <dd class="mo-v"><?php echo $fig["hirono_blind_type"] === "regular" ? "Regular Blind Box" : "Blind Box Set"; ?></dd>
                                                    <dd class="mo-p">₱<?php echo number_format(hironoBaseBoxPrice($fig), 2); ?></dd>
                                                </div>

                                                <?php if (!empty($fig["hirono_box_design"])): ?>
                                                    <div class="mo-row">
                                                        <dt class="mo-k">Box Design</dt>
                                                        <dd class="mo-v"><?php echo mo_e(hironoBoxDesignLabel($fig["hirono_box_design"])); ?></dd>
                                                    </div>
                                                <?php endif; ?>

                                                <?php foreach ($fig["blind_items_list"] as $extraItem): ?>
                                                    <div class="mo-row">
                                                        <dt class="mo-k">Optional Extra</dt>
                                                        <dd class="mo-v"><?php echo mo_e($extraItem["label"]); ?></dd>
                                                        <dd class="mo-p">₱<?php echo number_format((float) $extraItem["price"], 2); ?></dd>
                                                    </div>
                                                <?php endforeach; ?>

                                            </dl>

                                            <div class="mo-inline-trio">
                                                <div><span class="mo-k">Box Color</span><span class="mo-v"><?php echo mo_e($fig["hirono_box_color"]); ?></span></div>
                                                <div><span class="mo-k">Nickname</span><span class="mo-v"><?php echo mo_e($fig["hirono_nickname"]); ?></span></div>
                                                <div>
                                                    <span class="mo-k">Date</span>
                                                    <span class="mo-v"><?php echo !empty($fig["hirono_date"]) ? mo_e(date("M j", strtotime($fig["hirono_date"]))) : ""; ?></span>
                                                </div>
                                            </div>

                                            <?php if (!empty($fig["hirono_letter"])): ?>
                                                <div class="notes-box">
                                                    <strong>Letter</strong>
                                                    <p><?php echo nl2br(mo_e($fig["hirono_letter"])); ?></p>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!empty($fig["box_images"])): ?>

                                                <div class="mo-gallery-label">Box Reference Images <span>(<?php echo count($fig["box_images"]); ?>)</span></div>

                                                <div class="gallery-row">
                                                    <?php foreach ($fig["box_images"] as $boxImgIndex => $boxImgPath): ?>
                                                        <img
                                                            src="<?php echo mo_e($siteBase . $boxImgPath); ?>"
                                                            alt="Box reference photo <?php echo $boxImgIndex + 1; ?>"
                                                            class="gallery-thumb"
                                                            onclick="openImageLightbox('<?php echo mo_e($siteBase . $boxImgPath); ?>', 'Box Reference Image')"
                                                        >
                                                    <?php endforeach; ?>
                                                </div>

                                            <?php endif; ?>

                                        <?php endif; ?>

                                    </div>
                                    <!-- /.box-addons-section -->
                                    <?php endif; ?>

                            </div>
                            <!-- /.order-figure -->

                        <?php endforeach; ?>

                    </div>
                    <!-- /.order-figures -->

                </section>

                </div>
                <!-- /.mo-main -->


                <div class="mo-side">


                <!-- ───── 7. ORDER SUMMARY (kanan, taas) ───── -->

                <section class="mo-card">

                    <h2 class="mo-card-title">Order Summary</h2>

                    <div class="mo-sum">

                        <?php
                        /* Bawat figure: "Figure N" + ano ito, tapos Price, Quantity, Rush Fee, Subtotal
                           (pareho sa Image Submission at Dress Up) */
                        foreach (array_values($order["figures"]) as $bIndex => $bFig):

                            /* presyo ng isang piraso (estimate muna kung wala pang quotation) */
                            $bFigUnit = figurify_figure_unit_price($bFig);
                            $bFigQty  = figurify_figure_quantity($bFig);
                            $bFigRush = (float) ($rushSplit["per"][$bIndex] ?? 0);
                        ?>
                            <div class="mo-sum-fig">
                                <div class="mo-sum-fig-head">
                                    <span>Figure <?php echo $bIndex + 1; ?></span>
                                    <small><?php echo mo_e($bFig["figure_style"] . " · " . $bFig["product_type"]); ?></small>
                                </div>
                                <div class="mo-sum-line"><span>Price</span><span>₱<?php echo number_format($bFigUnit, 2); ?></span></div>
                                <div class="mo-sum-line"><span>Quantity</span><span><?php echo $bFigQty; ?></span></div>
                                <?php if ($bFigRush > 0): ?>
                                    <div class="mo-sum-line"><span>Rush Fee</span><span>₱<?php echo number_format($bFigRush, 2); ?></span></div>
                                <?php endif; ?>
                                <div class="mo-sum-line mo-sum-sub"><span>Subtotal</span><span>₱<?php echo number_format($bFigUnit * $bFigQty + $bFigRush, 2); ?></span></div>
                            </div>

                        <?php endforeach; ?>

                        <?php if ($rushSplit["legacy"] > 0): ?>
                            <div class="mo-sum-line">
                                <span>Rush Fee</span>
                                <span>₱<?php echo number_format((float) $order["rush_fee"], 2); ?></span>
                            </div>
                        <?php endif; ?>

                        <?php
                        /* Dress Up: laging Total Price (fixed na, walang quotation) */
                        $notQuotedYet = !$isDressUpOrder
                            && in_array($wf, ["PENDING_STAFF_VERIFICATION", "STAFF_CORRECTION_REQUIRED"], true);
                        ?>
                        <div class="mo-sum-total">
                            <span><?php echo $notQuotedYet ? "Estimated Total" : "Total Price"; ?></span>
                            <span>₱<?php echo number_format((float) $order["total_amount"], 2); ?></span>
                        </div>

                        <?php
                        /* Down Payment / Remaining Balance (Full Payment: isang linya lang, walang balance) */
                        $splitShown = $isDressUpOrder
                            || !in_array($wf, ["PENDING_STAFF_VERIFICATION", "STAFF_CORRECTION_REQUIRED"], true);

                        if ($splitShown && $wf !== "CANCELLED"):

                            /* PAID lang kapag na-approve na ng owner (walang "checking") */
                            $dpPaid = !empty($order["paid_at"]) && !in_array($wf, ["AWAITING_PAYMENT", "QUOTATION_SENT", "PENDING_STAFF_VERIFICATION", "STAFF_CORRECTION_REQUIRED", "PAYMENT_SUBMITTED", "PAYMENT_DETAILS_VERIFIED", "CANCELLED"], true);
                            $balPaid = !empty($extra["balance_paid_at"]) && $wf !== "BALANCE_SUBMITTED" && $wf !== "AWAITING_BALANCE";
                        ?>
                            <div class="mo-split">
                                <div class="mo-sum-line<?php echo $dpPaid ? " is-paid" : ""; ?>">
                                    <span><?php echo e(figurify_wf_downpayment_label($order)); ?><?php if ($dpPaid): ?><em class="pay-tag is-paid">paid</em><?php endif; ?></span>
                                    <span>₱<?php echo number_format((float) $order["downpayment"], 2); ?></span>
                                </div>
                                <?php if (!figurify_wf_is_full_payment($order)): ?>
                                    <div class="mo-sum-line">
                                        <span>Remaining Balance<?php if ($balPaid): ?><em class="pay-tag is-paid">paid</em><?php endif; ?></span>
                                        <span>₱<?php echo number_format((float) $order["balance_due"], 2); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                    </div>

                </section>


                <?php
                /* ───── 8. PAYMENT & SHIPPING DETAILS ───── */
                $hasPaymentInfo = !empty($order["payment_reference"])
                    || !empty($order["payment_proof"])
                    || !empty($order["paid_at"])
                    || !empty($order["shipping_name"])
                    || !empty($order["shipping_address"])
                    || !empty($order["shipping_contact"]);

                $hasRefundInfo = !empty($order["refund_account_name"])
                    || !empty($order["refund_account_number"])
                    || !empty($order["refund_method"]);

                $myPin = trim((string) ($payExtras["pin_address"] ?? ""));
                ?>

                <?php if ($hasPaymentInfo || $hasRefundInfo): ?>

                    <section class="mo-card">

                        <h2 class="mo-card-title">Payment &amp; Shipping Information</h2>

                        <?php if ($hasPaymentInfo): ?>

                            <div class="mo-pay-block">

                                <dl class="mo-rows">

                                    <div class="mo-row"><dt>Ship To</dt><dd><?php echo mo_e($order["shipping_name"] ?? ""); ?></dd></div>

                                    <div class="mo-row"><dt>Address</dt><dd><?php echo mo_e($order["shipping_address"] ?? ""); ?></dd></div>

                                    <?php if ($myPin !== ""): ?>
                                        <div class="mo-row">
                                            <dt>Pin Address</dt>
                                            <dd>
                                                <?php if (preg_match('~^https?://~i', $myPin)): ?>
                                                    <a href="<?php echo mo_e($myPin); ?>" target="_blank" rel="noopener noreferrer">maps link</a>
                                                <?php else: ?>
                                                    <?php echo mo_e($myPin); ?>
                                                <?php endif; ?>
                                            </dd>
                                        </div>
                                    <?php endif; ?>

                                    <div class="mo-row"><dt>Contact</dt><dd><?php echo mo_e($order["shipping_contact"] ?? ""); ?></dd></div>

                                    <?php if (!empty($order["courier"])): ?>
                                        <div class="mo-row"><dt>Courier</dt><dd><?php echo mo_e(strtoupper((string) $order["courier"])); ?></dd></div>
                                    <?php endif; ?>

                                    <div class="mo-row"><dt>Reference No.</dt><dd><?php echo mo_e($order["payment_reference"] ?? ""); ?></dd></div>

                                    <?php if (!empty($order["paid_at"])): ?>
                                        <div class="mo-row"><dt>Submitted</dt><dd><?php echo mo_e(date("M j, Y", strtotime($order["paid_at"]))); ?></dd></div>
                                    <?php endif; ?>

                                </dl>

                                <?php figurify_render_payment_proofs($oid, "customer"); ?>

                            </div>

                        <?php endif; ?>

                        <?php if ($hasRefundInfo): ?>

                            <div class="mo-pay-block">

                                <div class="mo-sub">Refund Account Information</div>

                                <dl class="mo-rows">
                                    <div class="mo-row"><dt>Account Name</dt><dd><?php echo mo_e($order["refund_account_name"] ?? ""); ?></dd></div>
                                    <div class="mo-row"><dt>Account Number</dt><dd><?php echo mo_e($order["refund_account_number"] ?? ""); ?></dd></div>
                                    <div class="mo-row"><dt>Preferred Bank/Method</dt><dd><?php echo mo_e($order["refund_method"] ?? ""); ?></dd></div>
                                </dl>

                            </div>

                        <?php endif; ?>

                    </section>

                <?php endif; ?>


                </div>
                <!-- /.mo-side -->

            </div>
            <!-- /.order-detail-panel -->

        <?php endforeach; ?>

        </div>
        <!-- /.orders-detail-col -->

        </div>
        <!-- ===== END ORDERS LAYOUT ===== -->

    <?php endif; ?>

</main>



<!-- ====================== END MY ORDERS ====================== -->


<!-- ====================== IMAGE LIGHTBOX (css: my-orders.css [16] | js: [5e]) ====================== -->

<div
    class="image-lightbox"
    id="imageLightbox"
    onclick="closeImageLightbox(event)"
>

    <div class="image-lightbox-content">

        <button
            type="button"
            class="image-lightbox-close"
            onclick="closeImageLightbox()"
            aria-label="Close"
        ></button>

        <img
            id="lightboxImage"
            src=""
            alt="Enlarged figure photo"
        >

        <div
            class="image-lightbox-label"
            id="lightboxLabel"
        ></div>

    </div>

</div>
<!-- ====================== END IMAGE LIGHTBOX ====================== -->


<!-- ====================== FOOTER (Shared/footer.php) ====================== -->
<?php include __DIR__ . "/../Shared/footer.php"; ?>
<!-- ====================== END FOOTER ====================== -->


<!-- ====================== [5] SCRIPT ====================== -->
<script>

/* ==========================================================================
   [5a] SEARCH + STATUS FILTER  (client-side lang, walang extra request sa server)
   ========================================================================== */

var myOrdersStatusFilter = "all";

function myOrdersApplyFilters() {

    var searchInput = document.getElementById("myOrdersSearch");
    var q = searchInput ? searchInput.value.trim().toLowerCase() : "";

    var items = document.querySelectorAll("#myOrdersScroll .order-list-item");
    var visibleCount = 0;

    items.forEach(function (item) {

        var matchesSearch = (item.dataset.search || "").indexOf(q) !== -1;
        var matchesStatus = (myOrdersStatusFilter === "all") || (item.dataset.status === myOrdersStatusFilter);
        var matches = matchesSearch && matchesStatus;

        item.hidden = !matches;

        if (matches) {
            visibleCount++;
        }

    });

    var emptyMessage = document.getElementById("myOrdersSearchEmpty");

    if (emptyMessage) {
        emptyMessage.hidden = !(visibleCount === 0 && items.length > 0);
    }

    /* nagbago ang bilang ng kita -> ayusin ulit ang taas ng listahan */
    myOrdersFitListHeight();

}


/* status pills - isa lang ang active; saka i-apply ang filter + search */

function myOrdersSetFilter(value) {

    myOrdersStatusFilter = value;

    myOrdersApplyFilters();

}

/* ====================== END SEARCH + STATUS FILTER ====================== */


/* ==========================================================================
   [5b] PILIIN ANG ORDER  (lalabas ang details nito sa kanan)
   ========================================================================== */

function selectOrder(button, noScroll) {

    document
        .querySelectorAll(".order-list-item")
        .forEach(function (item) {
            item.classList.remove("active");
        });

    button.classList.add("active");

    document
        .querySelectorAll(".order-detail-panel")
        .forEach(function (panel) {
            panel.classList.remove("active");
        });

    var target = document.getElementById(button.dataset.orderTarget);

    if (target) {

        target.classList.add("active");

        /* sa desktop, mag-scroll lang kung nakalampas na ang taas ng panel;
           sa mobile (stacked na ang layout) laging bumaba sa details */
        var panelTop = target.getBoundingClientRect().top;
        var isStacked = window.innerWidth <= 1100;

        if (!noScroll && (isStacked || panelTop < 0)) {

            target.scrollIntoView({
                behavior: "smooth",
                block: "start"
            });

        }

    }

}

/* ===== taas ng listahan sa kaliwa: eksaktong 5 order ang kita, scroll kapag lampas ===== */
function myOrdersFitListHeight() {

    var list = document.getElementById("myOrdersScroll");

    if (!list) {
        return;
    }

    var items = Array.prototype.filter.call(list.querySelectorAll(".order-list-item"), function (item) {
        return !item.hidden;
    });

    /* naka-stack na (phone/tablet) o 5 pababa lang ang order: walang limit */
    if (window.innerWidth <= 1100 || items.length <= 5) {
        list.style.maxHeight = "";
        return;
    }

    var style  = window.getComputedStyle(list);
    var top    = items[0].offsetTop;
    var bottom = items[4].offsetTop + items[4].offsetHeight;

    list.style.maxHeight = (bottom - top + parseFloat(style.paddingTop) + parseFloat(style.paddingBottom)) + "px";
}

window.addEventListener("load", myOrdersFitListHeight);
window.addEventListener("resize", myOrdersFitListHeight);

/* ====================== END PILIIN ANG ORDER ====================== */


/* ==========================================================================
   [5c] PROGRESS UPDATE RESPONSE  (approve o request revision, kada order_id)
   ========================================================================== */

function showProgressRevisionBox(orderId) {

    var buttonsRow = document.getElementById("progressButtonsRow-" + orderId);
    var revisionBox = document.getElementById("progressRevisionBox-" + orderId);

    if (buttonsRow) {
        buttonsRow.hidden = true;
    }

    if (revisionBox) {
        revisionBox.hidden = false;
    }

}

function hideProgressRevisionBox(orderId) {

    var buttonsRow = document.getElementById("progressButtonsRow-" + orderId);
    var revisionBox = document.getElementById("progressRevisionBox-" + orderId);
    var noteField = document.getElementById("progressRevisionNote-" + orderId);

    if (revisionBox) {
        revisionBox.hidden = true;
    }

    if (noteField) {
        noteField.value = "";
    }

    if (buttonsRow) {
        buttonsRow.hidden = false;
    }

}

function submitProgressResponse(orderId, response) {

    if (response === "revision") {

        var noteField = document.getElementById("progressRevisionNote-" + orderId);
        var note = noteField ? noteField.value.trim() : "";

        if (note === "") {
            csToast("Tell us what you'd like changed.", "error");
            return;
        }

    }

    var responseField = document.getElementById("progressResponseField-" + orderId);
    var form = document.getElementById("progressUpdateForm-" + orderId);

    if (responseField) {
        responseField.value = response;
    }

    if (form) {
        form.submit();
    }

}

/* ====================== END PROGRESS UPDATE RESPONSE ====================== */


/* ==========================================================================
   [5c] CALENDAR NG BAGONG PETSA  (kapag puno na ang napiling ship out date)
   ========================================================================== */

document.querySelectorAll(".mo-date-form[data-dates]").forEach(function (form) {

    var dates   = form.dataset.dates.split(",");
    var cur     = new Date(dates[0] + "T00:00:00");   /* unang bukas na petsa */
    var grid    = form.querySelector(".mo-cal-grid");
    var title   = form.querySelector(".mo-cal-title");
    var picked  = form.querySelector(".mo-cal-picked");
    var field   = form.querySelector("input[name=new_date]");
    var months  = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

    function pad(n) { return (n < 10 ? "0" : "") + n; }

    function draw() {

        var y = cur.getFullYear(), m = cur.getMonth();
        var html = ["Su", "Mo", "Tu", "We", "Th", "Fr", "Sa"].map(function (d) { return "<span class='mo-cal-dow'>" + d + "</span>"; }).join("");

        for (var i = 0; i < new Date(y, m, 1).getDay(); i++) { html += "<span></span>"; }

        for (var d = 1; d <= new Date(y, m + 1, 0).getDate(); d++) {
            var key  = y + "-" + pad(m + 1) + "-" + pad(d);
            var open = dates.indexOf(key) !== -1;
            html += open
                ? "<button type='button' class='mo-cal-day is-open" + (field.value === key ? " is-picked" : "") + "' data-date='" + key + "'>" + d + "</button>"
                : "<span class='mo-cal-day'>" + d + "</span>";
        }

        title.textContent = months[m] + " " + y;
        grid.innerHTML = html;
    }

    form.querySelectorAll(".mo-cal-nav").forEach(function (btn) {
        btn.addEventListener("click", function () {
            cur.setMonth(cur.getMonth() + parseInt(btn.dataset.dir, 10), 1);
            draw();
        });
    });

    grid.addEventListener("click", function (e) {
        var day = e.target.closest(".mo-cal-day.is-open");
        if (!day) { return; }
        field.value = day.dataset.date;
        picked.textContent = "Selected: " + months[parseInt(day.dataset.date.substr(5, 2), 10) - 1] + " " + parseInt(day.dataset.date.substr(8, 2), 10) + ", " + day.dataset.date.substr(0, 4);
        draw();
    });

    form.addEventListener("submit", function (e) {
        if (!field.value) { e.preventDefault(); picked.textContent = "Please pick a highlighted date first."; }
    });

    draw();
});


/* ==========================================================================
   [5d] PILIIN ANG FIGURE  (kapag 2+ figures, isa lang ang nakikita)
   ========================================================================== */

function selectFigure(button) {

    var tabsWrap = button.closest(".figure-tabs");

    if (tabsWrap) {

        tabsWrap
            .querySelectorAll(".figure-tab-btn")
            .forEach(function (tab) {
                tab.classList.remove("active");
            });

    }

    button.classList.add("active");

    var figuresWrap = tabsWrap ? tabsWrap.nextElementSibling : null;

    if (figuresWrap) {

        figuresWrap
            .querySelectorAll(".order-figure")
            .forEach(function (panel) {
                panel.classList.remove("active");
            });

    }

    var target = document.getElementById(button.dataset.figureTarget);

    if (target) {
        target.classList.add("active");
    }

}


/* ====================== END PILIIN ANG FIGURE ====================== */


/* ==========================================================================
   [5d2] REPORT A PROBLEM  (Report button -> popup na may note + photo, Cancel / Send Report)
   ========================================================================== */

function moToggleReport(orderId, show) {
    var form = document.getElementById("moReportForm-" + orderId);
    document.getElementById("moReportModal-" + orderId).hidden = !show;
    document.body.classList.toggle("no-scroll", show);
    if (!show) {
        form.reset();
        moPreviewUpload(form.querySelector('input[type="file"]'));
    }
}

/* larawan na pinili = makikita sa loob ng upload box */
function moPreviewUpload(input) {
    var box  = input.closest(".mo-upload");
    var img  = box.querySelector("img");
    var text = box.querySelector(".mo-upload-text");
    var file = input.files && input.files[0];

    if (img.src) { URL.revokeObjectURL(img.src); }
    img.hidden  = !file;
    text.hidden = !!file;
    img.src     = file ? URL.createObjectURL(file) : "";
}

/* ====================== END REPORT A PROBLEM ====================== */


/* ==========================================================================
   [5e] IMAGE LIGHTBOX  (click ng larawan = palakihin)
   ========================================================================== */

function openImageLightbox(src, label) {

    var lightbox = document.getElementById("imageLightbox");
    var image = document.getElementById("lightboxImage");
    var caption = document.getElementById("lightboxLabel");

    if (!lightbox || !image) {
        return;
    }

    image.src = src;

    if (caption) {
        caption.textContent = label || "";
    }

    lightbox.classList.add("show");

    document.body.classList.add("no-scroll");

}


function closeImageLightbox(event) {

    /* kapag nag-click sa loob mismo ng picture, huwag isara */
    if (event && event.target && event.target.id === "lightboxImage") {
        return;
    }

    var lightbox = document.getElementById("imageLightbox");

    if (!lightbox) {
        return;
    }

    lightbox.classList.remove("show");

    document.body.classList.remove("no-scroll");

}


/* Escape = isara ang lightbox at ang Report popup */
document.addEventListener("keydown", function (event) {

    if (event.key === "Escape") {
        closeImageLightbox();
        document.querySelectorAll(".mo-report-modal:not([hidden])").forEach(function (modal) {
            moToggleReport(modal.dataset.order, false);
        });
    }

});
/* ====================== END IMAGE LIGHTBOX ====================== */


/* ==========================================================================
   [5f] AUTO-OPEN NG ORDER
   ========================================================================== */

/* Auto-open a specific order (galing sa Notifications page) - ?order_id=12 auto-opens
   at scrolls dito, imbes na maghanap sa listahan. */

(function openOrderFromQueryString() {

    var params = new URLSearchParams(window.location.search);
    var targetOrderId = params.get("order_id");

    var targetButton = targetOrderId
        ? document.querySelector('.order-list-item[data-order-id="' + targetOrderId + '"]')
        : null;

    if (targetButton) {

        selectOrder(targetButton);

        targetButton.scrollIntoView({
            behavior: "smooth",
            block: "nearest"
        });

        return;

    }

    /* walang piniling order -> buksan agad ang pinakabago (nasa itaas ng listahan) */
    var firstButton = document.querySelector(".order-list-item");

    if (firstButton) {
        selectOrder(firstButton, true);
    }

})();

/* ====================== END AUTO-OPEN NG ORDER ====================== */


/* ==========================================================================
   [5g] LINIS NG URL
   ========================================================================== */

/* remove ?submitted=1 / ?cancelled=1 from URL after showing the banner once,
   para di na ulit lumabas pag nag-reload */

(function cleanUpOneTimeBannerParams() {

    var url = new URL(window.location.href);
    var changed = false;

    if (url.searchParams.has("submitted")) {
        url.searchParams.delete("submitted");
        changed = true;
    }

    if (url.searchParams.has("cancelled")) {
        url.searchParams.delete("cancelled");
        changed = true;
    }

    // Tinatanggal din ang "notice" query param (mula sa payment-shipping.php),
    // para hindi paulit-ulit lumabas kapag ni-reload ang page.
    if (url.searchParams.has("notice")) {
        url.searchParams.delete("notice");
        changed = true;
    }

    if (changed) {
        window.history.replaceState({}, document.title, url.toString());
    }

})();

/* ====================== END LINIS NG URL ====================== */


/* ==========================================================================
   [5h] AUTO-REFRESH  (check_order_updates.php)
   ========================================================================== */

/* poll check_order_updates.php every few seconds, auto-reload kapag may nabagong status */

(function pollForOrderStatusChanges() {

    var orderButtons = document.querySelectorAll(".order-list-item[data-order-id]");

    // Huwag mag-stop kahit walang laman ang listahan - kailangan pa ring mag-poll
    // para ma-detect ang unang order habang bukas ang page.

    var POLL_INTERVAL_MS = 3000; // 3 seconds

    // Extra safety net: hintayin munang makita ang PAREHONG bagong estado sa
    // SUSUNOD na poll bago mag-reload, laban sa transient timing issues.
    var pendingChangeSnapshot = null;

    // Huwag i-reload ang page habang nagta-type ang customer (revision note, atbp.)
    function isUserComposingRightNow() {

        var active = document.activeElement;
        var tag = active && active.tagName ? active.tagName.toLowerCase() : "";

        /* bukas ang Report form = may sinusulat pa ang customer */
        return (tag === "textarea" || tag === "input" || tag === "select")
            || !!document.querySelector(".mo-report-modal:not([hidden])");
    }

    function checkForUpdates() {

        // Huwag mag-poll habang naka-minimize/naka-ibang tab, para di sayang ang request
        if (document.hidden) {
            return;
        }

        if (isUserComposingRightNow()) {
            return;
        }

        fetch("check_order_updates.php", {
            method: "GET",
            credentials: "same-origin",
            cache: "no-store"
        })
            .then(function (response) {
                return response.ok ? response.json() : null;
            })
            .then(function (latestStatuses) {

                if (!latestStatuses) {
                    return;
                }

                // I-double-check ulit - baka nagsimula mag-type habang hinihintay ang fetch
                if (isUserComposingRightNow()) {
                    return;
                }

                var knownOrderIds = Array.prototype.map.call(orderButtons, function (button) {
                    return button.dataset.orderId;
                });

                var hasChanged = false;

                orderButtons.forEach(function (button) {

                    var orderId = button.dataset.orderId;
                    var currentStatus = button.dataset.orderStatus;
                    var latestStatus = latestStatuses[orderId];

                    if (latestStatus && latestStatus !== currentStatus) {
                        hasChanged = true;
                    }

                });

                // Tignan din kung may bagong order (order id na wala pa sa listahan)
                if (!hasChanged) {
                    hasChanged = Object.keys(latestStatuses).some(function (orderId) {
                        return knownOrderIds.indexOf(orderId) === -1;
                    });
                }

                if (!hasChanged) {
                    // walang totoong bago - reset ang pending confirmation (baka false-positive lang)
                    pendingChangeSnapshot = null;
                    return;
                }

                var currentSnapshot = JSON.stringify(latestStatuses);

                if (pendingChangeSnapshot === currentSnapshot) {

                    // Pareho sa 2 magkasunod na poll - sigurado na, mag-reload
                    window.location.reload();

                } else {

                    // Unang beses pa lang, i-store muna, hintayin ang pagkumpirma
                    pendingChangeSnapshot = currentSnapshot;

                }

            })
            .catch(function () {
                // tahimik mabigo - susubukan ulit sa susunod na interval
            });

    }

    setInterval(checkForUpdates, POLL_INTERVAL_MS);

})();
/* ====================== END AUTO-REFRESH ====================== */
</script>
<!-- ====================== END SCRIPT ====================== -->

</body>
</html>
<!-- ====================== END HTML ====================== -->
