<?php

require_once __DIR__ . "/../helpers/session_helper.php";
require_once __DIR__ . "/../helpers/dressup_helper.php";
figurify_start_session();

require_once __DIR__ . "/../Admin/database.php";
require_once __DIR__ . "/../helpers/expire_helper.php";
require_once __DIR__ . "/../helpers/order_status_helper.php";
require_once __DIR__ . "/../helpers/review_helper.php";
require_once __DIR__ . "/../helpers/order_workflow.php";
require_once __DIR__ . "/../helpers/proof_helper.php";


/* statusLabel and figurify_status_class are in order_status_helper.php,
   shared across Owner/Staff Dashboard, Calendar, and this page */


/* Friendly text para sa order method: "reference" = Image Submission, "create_style" = Dress Up */

function orderMethodLabel($method)
{
    $labels = [
        "reference"    => "Image Submission",
        "create_style" => "Dress Up",
    ];

    return $labels[$method] ?? "Image Submission";
}


/* friendly text for the Hirono box design */

function hironoBoxDesignLabel($design)
{
    $labels = [
        "checkered"   => "Checkered",
        "hirono_peek" => "Hirono Peek",
    ];

    return $labels[$design] ?? $design;
}


/* price helper: presyo sa "Product Type" row kung walang size, sa "Size" row kung may size
   (kapareho ng Commission form's Order Summary) */

function figureHasSize($fig)
{
    return !empty($fig["size_label"]);
}


/* Hirono base blind box price (walang extras) - box_addon_price ay kabuuan na
   ng box + extras, kaya ibawas ang extras total */

function hironoBaseBoxPrice($fig)
{
    return (float) $fig["box_addon_price"] - (float) $fig["hirono_blind_items_total"];
}


/* reference images - parehong larawan gaya ng Commission form, para magkatugma */

function figureStyleImage($style)
{
    $map = [
        "Chibi"     => "figures/chibi/chibi-style.jpg",
        "Funko Pop" => "figures/funko-pop/funko-pop-style.jpg",
        "Hirono"    => "figures/hirono/hirono-style.jpg",
    ];

    return $map[$style] ?? null;
}

function productTypeImage($style, $product)
{
    $map = [
        "Chibi" => [
            "Full Body Standee"  => "figures/chibi/chibi-full-body-standee.jpg",
            "Full Body Keychain" => "figures/chibi/chibi-full-body-keychain.jpg",
            "Half Body Keychain" => "figures/chibi/chibi-half-body-keychain.jpg",
            "Head Only Keychain" => "figures/chibi/chibi-head-only-keychain.jpg",
        ],
        "Funko Pop" => [
            "Full Body Standee"  => "figures/funko-pop/funko-pop-full-body-standee.jpg",
            "Full Body Keychain" => "figures/funko-pop/funko-pop-full-body-keychain.jpg",
            "Half Body Keychain" => "figures/funko-pop/funko-pop-half-body-keychain.jpg",
            "Head Only Keychain" => "figures/funko-pop/funko-pop-head-only-keychain.jpg",
        ],
        "Hirono" => [
            "Full Body Standee"  => "figures/hirono/hirono-full-body-standee.jpg",
            "Full Body Keychain" => "figures/hirono/hirono-full-body-keychain.jpg",
            "Half Body Keychain" => "figures/hirono/hirono-half-body-keychain.jpg",
            "Head Only Keychain" => "figures/hirono/hirono-head-only-keychain.jpg",
        ],
    ];

    return $map[$style][$product] ?? null;
}

function hironoBlindTypeImage($blindType)
{
    return ($blindType === "set") ? "boxes/blind-box-set.jpg" : "figures/hirono/hirono-style.jpg";
}

function hironoBoxDesignImage($design)
{
    $map = [
        "checkered"   => "boxes/hirono-box-checkered.jpg",
        "hirono_peek" => "boxes/hirono-box-peek.jpg",
    ];

    return $map[$design] ?? null;
}


/* image for each Hirono blind box optional extra - parehong placeholder image gaya ng Commission form */

function hironoBlindItemImage($itemKey)
{
    return "figures/hirono/hirono-style.jpg";
}


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


/* kulay ng status pill (galing sa design system) */

function mo_tone_class($statusKey)
{
    $map = [
        "pending"              => "warn",
        "needs_correction"     => "danger",
        "quoted"               => "warn",
        "awaiting_payment"     => "warn",
        "to_verify"            => "info",
        "for_approval"         => "info",
        "processing"           => "ok",
        "awaiting_balance"     => "danger",
        "to_verify_balance"    => "info",
        "for_balance_approval" => "info",
        "to_ship"              => "info",
        "shipped"              => "info",
        "completed"            => "ok",
        "cancelled"            => "danger",
        "for_refund"           => "warn",
        "refunded"             => "danger",
        "revision"             => "warn",
    ];

    return "tone-" . ($map[$statusKey] ?? "warn");
}


/* maliit na larawan sa order list: unang reference image, kung wala
   ay ang larawan ng figure style */

function mo_order_thumb(array $order, string $siteBase): string
{
    foreach (($order["figures"] ?? []) as $thumbFig) {
        if (!empty($thumbFig["images"][0])) {
            return $siteBase . $thumbFig["images"][0];
        }
    }

    $firstFig = $order["figures"][0] ?? null;

    if ($firstFig) {
        $styleImg = figureStyleImage($firstFig["figure_style"] ?? "");

        if ($styleImg) {
            return $siteBase . "Image/" . $styleImg;
        }
    }

    return "";
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
        <button type="submit" class="mo-btn mo-btn-soft"><?php echo mo_e($label); ?></button>
    </form>
    <?php
}



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
require_once __DIR__ . "/../helpers/order_complete_helper.php";
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
        "SELECT order_id, order_type, order_method, booking_date, rush_fee,
                subtotal, total_amount, status, created_at,
                quote_expires_at, review_token,
                shipping_name, shipping_address, shipping_contact, courier,
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
        "SELECT order_id, order_type, order_method, booking_date, rush_fee,
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
                figure_name, notes, product_price, name_fee, figure_total,
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
            ["to_ship", "shipped", "completed", "cancelled"],
            true
        )
    );

}

unset($order);


/* WORKFLOW STATE (iisang source of truth - helpers/order_workflow.php):
   canonical state + customer-facing label, revision usage (0/1 o 1/1),
   kasaysayan, at ang huling dahilan kapag ibinalik/na-reject. */

figurify_wf_ensure_schema($conn);

$revisionCounts = [];

$revStmt = $conn->prepare(
    "SELECT order_id, revision_count, tracking_number, shipping_fee, shipped_at,
            balance_payment_reference, balance_paid_at
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
    $order["slot_open"] = true;
    $order["slot_alts"] = [];
    if (in_array(($order["status"] ?? ""), ["pending", "needs_correction", "quoted", "awaiting_payment", "to_verify", "for_approval"], true) && !empty($order["booking_date"])) {
        $slotDate = date("Y-m-d", strtotime((string) $order["booking_date"]));
        /* capacity lang (puno na ba?) - hindi ang lead-time rule */
        $order["slot_open"] = figurify_date_has_capacity($conn, (string) $order["order_type"], $slotDate)["ok"];
        if (!$order["slot_open"]) {
            $order["slot_alts"] = figurify_next_available_dates($conn, (string) $order["order_type"], 8);
        }
    }
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
        $filterLabel = "For Revision";
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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php /* [ALL-IN-ONE] font, background at pop up ng buong system - Shared/all-in-one.php */ include __DIR__ . "/../Shared/all-in-one.php"; ?>
    <link rel="icon" type="image/png" href="../Image/brand/logo.png">

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>My Orders — Clay and Stuff</title>


<?php /* bagong stylesheet (design system). Hindi na nilo-load dito ang lumang
         my-orders.css at receipt-panels.css; ang payment-shipping.php ay
         gumagamit pa ng my-orders.css kaya hindi ito binago. */ ?>
<link rel="stylesheet" href="my-orders-v2.css?v=2">

<style>
/* [MY ORDER] Figure information: presyo walang border at bg, same sa owner/staff */
body .orders-page .mo-row .mo-chip:not(.is-free) {
    border: 0 !important;
    border-radius: 0 !important;
    background: none !important;
    padding: 0 !important;
    color: var(--p-plum, #4A1D3B) !important;
    font-size: 14px !important;
    font-weight: 800 !important;
    margin-left: auto !important;
}
</style>
</head>
<body>

<?php include "../Shared/navbar.php"; ?>

<?php
require_once __DIR__ . "/../helpers/rush_split_helper.php";
require_once __DIR__ . "/../helpers/payment_view_helper.php";
?>

<header class="mo-hero">
    <div class="mo-wrap">
        <span class="mo-eyebrow">TRACK YOUR COMMISSIONS</span>
        <h1>My Orders, All in One Place</h1>
        <p>Here's a list of every figure you've commissioned with us.</p>
    </div>
</header>

<main class="orders-page mo-wrap">

    <?php
    /* one-time na mensahe (cancel / bayad / hindi na pwedeng magbayad) bilang pop up */
    $orderPopup = null;

    if ($justCancelled) {
        $orderPopup = [
            "type"    => "error",
            "message" => (isset($_GET["order_id"]) ? "Order " . (int) $_GET["order_id"] . " has been cancelled." : "The order has been cancelled.")
        ];
    } elseif ($justSubmitted) {
        $orderPopup = [
            "type"    => "success",
            "message" => "Payment submitted! We'll verify it soon."
        ];
    } elseif ($paymentNotice === "payment_unavailable" && $paymentNoticeOrder && figurify_status_key($paymentNoticeOrder["status"]) === "cancelled") {
        $orderPopup = [
            "type"    => "error",
            "message" => "Order " . (int) $paymentNoticeOrder["order_id"] . " was cancelled and can't be paid."
        ];
    } elseif ($paymentNotice === "payment_unavailable") {
        $orderPopup = [
            "type"    => "info",
            "message" => $paymentNoticeOrder
                ? "Order " . (int) $paymentNoticeOrder["order_id"] . " can no longer be paid — its status is now \"" . statusLabel($paymentNoticeOrder["status"]) . "\"."
                : "That order can no longer be paid — its status has already changed."
        ];
    } elseif ($paymentNotice === "order_not_found") {
        $orderPopup = [
            "type"    => "error",
            "message" => "Order not found."
        ];
    }

    /* shared pop up (Shared/all-in-one.php) - kapareho ng sa Image Submission */
    if ($orderPopup) {
        echo figurify_toast_now($orderPopup["message"], $orderPopup["type"]);
    }
    ?>


    <?php if (empty($orders)): ?>

        <!-- EMPTY STATE -->
        <div class="no-orders">
            <p>You don't have any orders yet.</p>
            <a href="../Commission/commission.php">Start a Commission →</a>
        </div>

    <?php else: ?>

        <div class="orders-layout">

        <!-- left: order list -->

        <div class="orders-list-col">

            <div class="orders-list-shell">

                <div class="orders-toolbar">

                    <div class="orders-filter-dropdown" id="myOrdersFilterDropdown">

                        <button
                            type="button"
                            class="orders-filter-trigger"
                            onclick="myOrdersToggleFilter()"
                        >
                            <span id="myOrdersFilterLabel">All</span>
                            <svg class="orders-filter-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>
                        </button>

                        <div class="orders-filter-menu">

                            <a
                                href="javascript:void(0)"
                                class="orders-filter-option active"
                                onclick="myOrdersSetFilter(this, 'all', 'All')"
                            >
                                All
                            </a>

                            <?php foreach ($myOrdersFilterOptions as $filterKey => $filterLabel): ?>
                                <a
                                    href="javascript:void(0)"
                                    class="orders-filter-option"
                                    onclick="myOrdersSetFilter(this, '<?php echo mo_e($filterKey); ?>', '<?php echo mo_e($filterLabel); ?>')"
                                >
                                    <?php echo mo_e($filterLabel); ?>
                                </a>
                            <?php endforeach; ?>

                        </div>

                    </div>

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
                            <span class="order-list-id">Order <?php echo (int) $listSeq; ?></span>
                            <span class="order-list-date">Ordered on <?php echo mo_e(date("M j, Y", strtotime($listOrder["created_at"]))); ?></span>
                            <?php if (!empty($listOrder["booking_date"])): ?>
                                <span class="order-list-date">Booking date: <?php echo mo_e(date("M j, Y", strtotime($listOrder["booking_date"]))); ?></span>
                            <?php endif; ?>
                            <span class="order-list-date"><?php echo mo_e(mo_order_type_label($listOrder["order_type"] ?? "")); ?> · <?php echo mo_e(orderMethodLabel($listOrder["order_method"])); ?></span>
                        </span>

                        <span class="mo-pill order-list-badge <?php echo mo_tone_class($listStatusKey); ?>"><?php echo mo_e($listOrder["cust_label"]); ?></span>

                    </button>

                <?php endforeach; ?>

                <div class="orders-search-empty" id="myOrdersSearchEmpty" style="display:none;">
                    No orders match your search.
                </div>

                </div>

            </div>

        </div>


        <!-- right: order details -->

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
                    "AWAITING_BALANCE" => 4, "BALANCE_SUBMITTED" => 4, "READY_TO_SHIP" => 4,
                    "SHIPPED" => 5, "COMPLETED" => 5,
                ];
                $trackerStopped = in_array($wf, ["CANCELLED", "REFUND_REQUIRED", "REFUNDED"], true)
                    || in_array($statusKey, ["cancelled", "for_refund", "refunded"], true);
                $trackerReached = $trackerReachedMap[$wf] ?? 0;
                ?>

                <section class="mo-card" aria-label="Order progress">

                    <h2 class="mo-card-title">Order Progress</h2>

                    <ol class="mo-tracker<?php echo $trackerStopped ? " is-stopped" : ""; ?>">
                        <?php foreach ($trackerSteps as $tIndex => $tLabel): ?>
                            <?php
                            $tClass = "";
                            if (!$trackerStopped) {
                                if ($tIndex <= $trackerReached) { $tClass = "done"; }
                                if ($tIndex === $trackerReached) { $tClass .= " current"; }
                                if ($tIndex === 0 && $wf === "STAFF_CORRECTION_REQUIRED") {
                                    $tLabel = "Needs correction";
                                    $tClass = "attention current";
                                }
                            }
                            ?>
                            <li class="mo-step <?php echo $tClass; ?>">
                                <span class="mo-dot"><svg class="mo-dot-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="5 12.5 10 17.5 19 7.5"/></svg></span>
                                <span class="mo-step-label"><?php echo mo_e($tLabel); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ol>

                </section>


                <?php
                /* ───── 2. PROGRESS UPDATE (galing sa staff) ───── */
                if (!empty($order["latest_progress_update"])):

                    $ppu          = $order["latest_progress_update"];
                    $ppuIsPending = ($ppu["customer_response"] === "pending") && empty($ppu["is_final"]);

                    if (!empty($ppu["is_final"])) {
                        $ppuPill = ["Your figure is done!", "tone-ok"];
                    } elseif ($ppu["customer_response"] === "pending") {
                        $ppuPill = ["Update from staff", "tone-warn"];
                    } elseif ($ppu["customer_response"] === "approved") {
                        $ppuPill = ["Progress approved", "tone-ok"];
                    } elseif ($ppu["customer_response"] === "revision") {
                        $ppuPill = ["For revision", "tone-warn"];
                    } elseif ($ppu["customer_response"] === "in_progress") {
                        $ppuPill = [
                            in_array($order["status"], ["to_ship", "shipped", "completed"], true) ? "Revision accepted" : "Revising",
                            "tone-info",
                        ];
                    } else {
                        $ppuPill = ["Revision not possible", "tone-danger"];
                    }

                    /* ONE-TIME REVISION: nakatago ang button kapag nagamit na -
                       pero ang backend (order_workflow.php) ang tunay na bantay */
                    $canRequestRevision = ((int) $order["revision_count"] < FIGURIFY_WF_MAX_REVISIONS);
                ?>

                <section class="mo-card" id="progress-update-<?php echo $oid; ?>">

                    <h2 class="mo-card-title">Progress update</h2>

                    <div class="mo-progress">

                        <img
                            class="mo-progress-img"
                            src="../<?php echo mo_e($ppu["image_path"]); ?>"
                            alt="Order progress photo"
                            onclick="openImageLightbox('../<?php echo mo_e($ppu["image_path"]); ?>', 'Progress Update')"
                        >

                        <div class="mo-progress-body">

                            <span class="mo-pill <?php echo $ppuPill[1]; ?>"><?php echo mo_e($ppuPill[0]); ?></span>

                            <?php if (!empty($ppu["staff_message"])): ?>
                                <p class="mo-progress-msg"><?php echo nl2br(mo_e($ppu["staff_message"])); ?></p>
                            <?php endif; ?>

                            <?php if ($ppuIsPending): ?>

                                <?php
                                $ppuDeadline = !empty($ppu["created_at"])
                                    ? date("M j, g:i A", strtotime($ppu["created_at"]) + FIGURIFY_PROGRESS_AUTO_APPROVE_HOURS * 3600)
                                    : "";
                                ?>

                                <p class="mo-progress-note">
                                    <?php if ($canRequestRevision): ?>
                                        Happy with it? Tap <strong>It's Good</strong>, or request a change.
                                        <?php if ($ppuDeadline !== ""): ?>No reply by <strong><?php echo mo_e($ppuDeadline); ?></strong> means it's approved.<?php endif; ?>
                                    <?php else: ?>
                                        You've used your revision. This update will be approved automatically<?php echo $ppuDeadline !== "" ? " on <strong>" . mo_e($ppuDeadline) . "</strong>" : ""; ?>.
                                    <?php endif; ?>
                                </p>

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
                                    <div class="progress-update-revision-box" id="progressRevisionBox-<?php echo $oid; ?>">

                                        <label for="progressRevisionNote-<?php echo $oid; ?>">What would you like changed?</label>

                                        <textarea
                                            name="revision_note"
                                            id="progressRevisionNote-<?php echo $oid; ?>"
                                            rows="2"
                                            placeholder="Tell us what to revise..."
                                        ></textarea>

                                        <div class="mo-actions" style="margin-top:2px;">
                                            <button type="button" class="mo-btn mo-btn-primary" onclick="submitProgressResponse(<?php echo $oid; ?>, 'revision')">Send</button>
                                            <button type="button" class="mo-btn mo-btn-soft" onclick="hideProgressRevisionBox(<?php echo $oid; ?>)">Cancel</button>
                                        </div>

                                    </div>
                                    <?php endif; ?>

                                </form>

                            <?php elseif (!empty($ppu["is_final"])): ?>

                                <p class="mo-progress-status is-approved">Your figure is done! We'll ship it soon.</p>

                            <?php elseif ($ppu["customer_response"] === "revision" || $ppu["customer_response"] === "in_progress"): ?>

                                <?php if (!empty($ppu["revision_note"])): ?>
                                    <p class="mo-progress-revnote"><strong>Your revision request:</strong> <?php echo nl2br(mo_e($ppu["revision_note"])); ?></p>
                                <?php endif; ?>

                            <?php elseif ($ppu["customer_response"] === "declined"): ?>

                                <?php if (!empty($ppu["revision_note"])): ?>
                                    <p class="mo-progress-revnote"><strong>Your revision request:</strong> <?php echo nl2br(mo_e($ppu["revision_note"])); ?></p>
                                <?php endif; ?>

                                <p class="mo-progress-status is-declined">
                                    <?php
                                    echo !empty($ppu["staff_reply"])
                                        ? nl2br(mo_e($ppu["staff_reply"]))
                                        : "Sorry, we can't make this revision.";
                                    ?>
                                </p>

                            <?php endif; ?>

                        </div>

                    </div>

                </section>

                <?php endif; ?>


                <?php
                /* ───── 3. ACTION STATES ───── */

                /* 3a. Puno na ang petsa (hindi pa naaaprubahan ng owner) -> pumili ng ibang petsa
                       (same order, walang bagong bayad) o mag-cancel kung hindi na tutuloy. */
                $quotedDateStr  = !empty($order["booking_date"]) ? date("Y-m-d", strtotime((string) $order["booking_date"])) : "";
                $quotedDateOpen = !empty($order["slot_open"]);
                $quotedAltDates = $order["slot_alts"] ?? [];
                $dateFullPaid   = in_array($wf, ["PAYMENT_SUBMITTED", "PAYMENT_DETAILS_VERIFIED"], true);
                ?>

                <?php if (!$quotedDateOpen && $quotedDateStr !== ""): ?>
                    <section class="mo-card mo-state">
                        <span class="mo-pill tone-warn">Date is full</span>
                        <p class="mo-state-text">
                            The date you picked (<?php echo mo_e(date("M j, Y", strtotime($quotedDateStr))); ?>) is now full. Please choose a new date<?php echo $dateFullPaid ? " — you don't need to pay again." : ", or cancel the order if you no longer want to continue."; ?>
                        </p>

                        <?php if (!empty($quotedAltDates)): ?>
                            <form method="POST" action="confirm_order.php" class="mo-date-form">
                                <input type="hidden" name="order_id" value="<?php echo $oid; ?>">
                                <input type="hidden" name="action" value="change_date">
                                <select name="new_date" required>
                                    <option value="" disabled selected>Pick new date</option>
                                    <?php foreach ($quotedAltDates as $altDate): ?>
                                        <option value="<?php echo mo_e($altDate); ?>"><?php echo mo_e(date("M j, Y", strtotime($altDate))); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="mo-btn mo-btn-primary">Use this date</button>
                            </form>
                        <?php else: ?>
                            <p class="mo-state-sub">No dates are available right now. Please try again later.</p>
                        <?php endif; ?>

                        <?php if ($dateFullPaid): ?>
                            <p class="mo-state-sub">Don't want to continue? Please contact us so we can cancel your order and process your refund.</p>
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

                        <section class="mo-card mo-state">
                            <span class="mo-pill tone-danger">Refunded</span>
                            <p class="mo-state-text">
                                Your refund has been sent.
                                <?php if ($refundReasonText !== ""): ?>Reason: <?php echo mo_e($refundReasonText); ?>.<?php endif; ?>
                                Amount ₱<?php echo number_format((float) ($order["refund_amount"] ?? 0), 2); ?>
                                <?php if (!empty($order["refund_reference"])): ?> · Ref <?php echo mo_e($order["refund_reference"]); ?><?php endif; ?>
                                <?php if (!empty($order["refunded_at"])): ?> · <?php echo mo_e(date("M j", strtotime($order["refunded_at"]))); ?><?php endif; ?>
                            </p>
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

                        <section class="mo-card mo-state">
                            <span class="mo-pill tone-warn">For refund</span>
                            <p class="mo-state-text">
                                The owner is reviewing your payment. Your refund will be sent to the refund account below within 7–14 business days.
                                <?php if ($refundReasonText !== ""): ?>Reason: <?php echo mo_e($refundReasonText); ?>.<?php endif; ?>
                            </p>
                        </section>

                    <?php endif; ?>

                <?php elseif ($wf === "CANCELLED" || $statusKey === "cancelled"): ?>

                    <section class="mo-card mo-state">
                        <span class="mo-pill tone-danger">Cancelled</span>
                        <p class="mo-state-text">
                            This order was cancelled.
                            <?php if (!empty($order["decline_reason"])): ?>Reason: <?php echo mo_e($order["decline_reason"]); ?>.<?php endif; ?>
                        </p>
                    </section>

                <?php elseif ($wf === "PENDING_STAFF_VERIFICATION"): ?>

                    <section class="mo-card mo-state">
                        <span class="mo-pill tone-info">Order being reviewed</span>
                        <p class="mo-state-text">We're checking your order. Your quotation will be sent soon.</p>
                        <div class="mo-actions"><?php mo_cancel_form($oid, "Cancel Order"); ?></div>
                    </section>

                <?php elseif ($wf === "STAFF_CORRECTION_REQUIRED"): ?>

                    <section class="mo-card mo-state">
                        <span class="mo-pill tone-danger">Correction required</span>
                        <p class="mo-state-text">
                            <?php echo nl2br(mo_e((string) ($order["correction_reason"] ?? "Our staff needs you to review your order details."))); ?>
                        </p>
                        <div class="mo-actions">
                            <a href="correct-order.php?order_id=<?php echo $oid; ?>" class="mo-btn mo-btn-primary">Fix My Order</a>
                            <?php mo_cancel_form($oid, "Cancel"); ?>
                        </div>
                    </section>

                <?php elseif ($statusKey === "quoted"): ?>

                    <section class="mo-card mo-state">
                        <span class="mo-pill tone-warn">Quotation ready</span>
                        <p class="mo-state-text">
                            <?php if (!empty($order["quote_expires_at"])): ?>
                                Please respond by <strong><?php echo mo_e(date("M j, Y — g:i A", strtotime($order["quote_expires_at"]))); ?></strong>, or this order will be cancelled automatically.
                            <?php else: ?>
                                Your quotation is ready. Continue to confirm your order, or cancel it.
                            <?php endif; ?>
                        </p>
                        <div class="mo-actions">
                            <?php if ($quotedDateOpen): ?>
                                <form method="POST" action="confirm_order.php" class="mo-inline-form">
                                    <input type="hidden" name="order_id" value="<?php echo $oid; ?>">
                                    <input type="hidden" name="action" value="continue">
                                    <button type="submit" class="mo-btn mo-btn-primary">Continue Order</button>
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

                    <section class="mo-card mo-state">
                        <?php if ($rejReason !== ""): ?>
                            <span class="mo-pill tone-danger"><?php echo $rejAction === "decline_payment_details" ? "Payment details declined" : "Payment proof declined"; ?></span>
                            <p class="mo-state-text">
                                Reason: <?php echo mo_e($rejReason); ?>.
                                <?php echo $rejAction === "decline_payment_details" ? "Please submit your details again." : "Please submit a new proof."; ?>
                            </p>
                            <div class="mo-actions">
                                <a href="payment-shipping.php?order_id=<?php echo $oid; ?>" class="mo-btn mo-btn-primary">Resubmit payment</a>
                                <?php mo_cancel_form($oid, "Cancel"); ?>
                            </div>
                        <?php else: ?>
                            <span class="mo-pill tone-warn">Ready for payment</span>
                            <p class="mo-state-text">Send your down payment so we can start on your figure.</p>
                            <div class="mo-actions">
                                <a href="payment-shipping.php?order_id=<?php echo $oid; ?>" class="mo-btn mo-btn-primary">Proceed to Payment</a>
                                <?php mo_cancel_form($oid, "Cancel"); ?>
                            </div>
                        <?php endif; ?>
                    </section>

                <?php elseif (in_array($wf, ["PAYMENT_SUBMITTED", "PAYMENT_DETAILS_VERIFIED"], true)): ?>

                    <!-- OWNER lang ang nagve-verify ng bayad -->
                    <section class="mo-card mo-state">
                        <span class="mo-pill tone-info"><?php echo $wf === "PAYMENT_DETAILS_VERIFIED" ? "Waiting for approval" : "Checking your payment"; ?></span>
                        <p class="mo-state-text">
                            <?php echo $wf === "PAYMENT_DETAILS_VERIFIED"
                                ? "Payment checked — we'll start on your figure once it's approved."
                                : "Payment sent — we'll let you know once it's approved."; ?>
                        </p>
                    </section>

                <?php elseif ($statusKey === "awaiting_balance"): ?>

                    <?php $balRej = trim((string) ($order["rejection_reason"] ?? "")); ?>

                    <section class="mo-card mo-state">
                        <span class="mo-pill tone-danger">Pay your balance</span>
                        <p class="mo-state-text">Your figure is done! Pay the remaining balance so we can ship it.</p>
                        <?php if ($balRej !== ""): ?>
                            <p class="mo-state-sub">The owner couldn't verify your last balance payment: <strong><?php echo mo_e($balRej); ?></strong>. Please upload a new proof.</p>
                        <?php endif; ?>
                        <div class="mo-actions">
                            <a href="pay-balance.php?order_id=<?php echo $oid; ?>" class="mo-btn mo-btn-primary">Pay Balance</a>
                        </div>
                    </section>

                <?php elseif ($wf === "BALANCE_SUBMITTED"): ?>

                    <!-- diretso sa owner ang balance payment, walang staff-verification step -->
                    <section class="mo-card mo-state">
                        <span class="mo-pill tone-info">Waiting for approval</span>
                        <p class="mo-state-text">Balance sent — we'll ship your order once it's approved.</p>
                    </section>

                <?php elseif (in_array($wf, ["PROCESSING", "REVISION_IN_PROGRESS", "REVISION_REQUESTED"], true) && empty($order["latest_progress_update"])): ?>

                    <section class="mo-card mo-state">
                        <span class="mo-pill tone-ok">We're making your figure</span>
                        <p class="mo-state-text">We'll send you a progress photo soon.</p>
                    </section>

                <?php elseif ($wf === "READY_TO_SHIP"): ?>

                    <section class="mo-card mo-state">
                        <span class="mo-pill tone-info">Ready to ship</span>
                        <p class="mo-state-text">
                            It will be sent via <?php echo mo_e(strtoupper((string) ($order["courier"] ?? ""))); ?>
                            to <?php echo mo_e((string) ($order["shipping_address"] ?? "")); ?>.
                        </p>
                    </section>

                <?php elseif ($wf === "SHIPPED"): ?>

                    <section class="mo-card mo-state">
                        <span class="mo-pill tone-info">Shipped</span>
                        <p class="mo-state-text">
                            <?php echo mo_e(strtoupper((string) ($order["courier"] ?? "—"))); ?>
                            · Tracking <?php echo mo_e((string) ($extra["tracking_number"] ?? "—")); ?>
                        </p>

                        <?php if ((isset($extra["shipping_fee"]) && $extra["shipping_fee"] !== null) || !empty($extra["shipped_at"])): ?>
                            <div class="mo-state-meta">
                                <?php if (isset($extra["shipping_fee"]) && $extra["shipping_fee"] !== null): ?>
                                    <div><span>Shipping Fee</span><span>₱<?php echo number_format((float) $extra["shipping_fee"], 2); ?></span></div>
                                <?php endif; ?>
                                <?php if (!empty($extra["shipped_at"])): ?>
                                    <div><span>Shipped On</span><span><?php echo mo_e(date("M j, Y", strtotime($extra["shipped_at"]))); ?></span></div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php figurify_render_shipping_receipt($oid, "customer"); ?>

                        <?php /* Order Received - saka lalabas ang Leave a Review */ ?>
                        <div class="mo-received">
                            <p>Got your order? Tap <strong>Order Received</strong>, then you can leave a review.</p>
                            <?php $autoReceive = figurify_auto_receive_date($extra["shipped_at"] ?? null); ?>
                            <?php if ($autoReceive !== ""): ?>
                                <p class="mo-state-sub">If you don't tap it, we'll mark it as received on <?php echo mo_e($autoReceive); ?>.</p>
                            <?php endif; ?>
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
                            </div>
                        </div>
                    </section>

                <?php elseif ($statusKey === "completed"): ?>

                    <?php
                    $orderReview = $reviewsByOrder[$oid] ?? null;
                    $reviewToken = $order["review_token"] ?? "";
                    ?>

                    <section class="mo-card mo-state">
                        <span class="mo-pill tone-ok">Completed</span>

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


                <!-- ───── 5. FIGURES IN THIS ORDER ───── -->

                <?php
                /* Image Submission: Figures card katabi ang Custom Box card (kung meron),
                   o ang Order Summary (kung walang custom box). Dress Up: dating layout. */
                $moPair    = !$isDressUpOrder;
                $moBoxHtml = [];
                $moHasBox  = [];
                foreach (array_values($order["figures"]) as $moBi => $moBf) {
                    $moHasBox[$moBi] = in_array((string) ($moBf["box_addon_type"] ?? ""), ["funko_box", "hirono_blind_box"], true);
                }
                $moFirstHasBox = !empty($moHasBox[0]);
                ob_start(); /* buong Figures + Summary region, pagsasamahin sa dulo */
                ?>

                <?php if ($moPair): ?><div class="mo-pair" data-has-box="<?php echo $moFirstHasBox ? "1" : "0"; ?>"><?php endif; ?>

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

                                <div class="viewer-grid<?php echo $isDressUpFig ? " dressup-grid" : ""; ?>">

                                    <!-- figure information -->

                                    <div class="viewer-column">

                                        <div class="mo-sub">Figure <?php echo $index + 1; ?> Information</div>

                                        <dl class="mo-rows mo-rows-split">

                                            <div class="mo-row">
                                                <dt class="mo-k">Figure Style</dt>
                                                <dd class="mo-v"><span class="mo-val-text"><?php echo mo_e($fig["figure_style"]); ?></span></dd>
                                            </div>

                                            <div class="mo-row">
                                                <dt class="mo-k">Product Type</dt>
                                                <dd class="mo-v">
                                                    <span class="mo-val-text"><?php echo mo_e($fig["product_type"]); ?></span>
                                                    <?php if (!$hasSize): ?><span class="mo-chip">₱<?php echo number_format((float) $fig["product_price"], 2); ?></span><?php endif; ?>
                                                </dd>
                                            </div>

                                            <div class="mo-row">
                                                <dt class="mo-k">Size</dt>
                                                <dd class="mo-v">
                                                    <span class="mo-val-text"><?php echo $hasSize ? mo_e($fig["size_label"]) : "No size required"; ?></span>
                                                    <?php if ($hasSize): ?><span class="mo-chip">₱<?php echo number_format((float) $fig["product_price"], 2); ?></span><?php endif; ?>
                                                </dd>
                                            </div>

                                            <?php if (!$isDressUpFig): /* walang Figure Name sa Dress Up */ ?>
                                                <div class="mo-row">
                                                    <dt class="mo-k">Figure Name</dt>
                                                    <dd class="mo-v">
                                                        <span class="mo-val-text"><?php echo mo_e(figurify_figure_name_display($fig)); ?></span>
                                                        <?php if ((float) $fig["name_fee"] > 0): ?>
                                                            <span class="mo-chip">₱<?php echo number_format((float) $fig["name_fee"], 2); ?></span>
                                                        <?php endif; ?>
                                                    </dd>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($figRush > 0): ?>
                                                <div class="mo-row">
                                                    <dt class="mo-k">Rush Fee</dt>
                                                    <dd class="mo-v"><span class="mo-val-text"></span><span class="mo-chip">₱<?php echo number_format($figRush, 2); ?></span></dd>
                                                </div>
                                            <?php endif; ?>

                                        </dl>

                                        <?php if ($isDressUpFig): ?>

                                            <?php figurify_render_dressup_design($fig, "customer"); ?>

                                        <?php else: ?>

                                            <!-- uploaded reference photos -->

                                            <?php $imgCount = count($fig["images"]); ?>

                                            <div class="mo-gallery-label">Reference Images <span>(<?php echo $imgCount; ?>)</span></div>

                                            <?php if ($imgCount > 0): ?>

                                                <div class="gallery-row">
                                                    <?php foreach ($fig["images"] as $imgIndex => $imgPath): ?>
                                                        <img
                                                            src="<?php echo mo_e($siteBase . $imgPath); ?>"
                                                            alt="<?php echo mo_e($fig["figure_name"]); ?> — photo <?php echo $imgIndex + 1; ?>"
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
                                                <strong>Customer Notes</strong>
                                                <p><?php echo nl2br(mo_e($fig["notes"])); ?></p>
                                            </div>
                                        <?php endif; ?>

                                    </div>
                                    <!-- /.viewer-column (figure information) -->


                                    <!-- custom box -->

                                    <?php if ($moPair) { ob_start(); } ?>
                                    <div class="viewer-column">

                                        <div class="mo-sub">Custom Box</div>

                                        <?php if ($fig["box_addon_type"] === "funko_box"): ?>

                                            <dl class="mo-rows mo-rows-split">
                                                <div class="mo-row">
                                                    <dt class="mo-k">Box Type</dt>
                                                    <dd class="mo-v"><?php echo $fig["funko_box_type"] === "solo" ? "Solo Box" : "Couple Box"; ?> · ₱<?php echo number_format((float) $fig["box_addon_price"], 2); ?></dd>
                                                </div>
                                            </dl>

                                            <div class="mo-inline-trio">
                                                <div><span class="mo-k">Name on Box</span><span class="mo-v"><?php echo mo_e($fig["funko_box_name"]); ?></span></div>
                                                <div><span class="mo-k">Box Number</span><span class="mo-v"><?php echo mo_e($fig["funko_box_number"]); ?></span></div>
                                                <div><span class="mo-k">Box Color</span><span class="mo-v"><?php echo mo_e($fig["funko_box_color"]); ?></span></div>
                                            </div>

                                        <?php elseif ($fig["box_addon_type"] === "hirono_blind_box"): ?>

                                            <dl class="mo-rows mo-rows-split">

                                                <div class="mo-row">
                                                    <dt class="mo-k">Box Type</dt>
                                                    <dd class="mo-v"><?php echo $fig["hirono_blind_type"] === "regular" ? "Regular Blind Box" : "Blind Box Set"; ?> · ₱<?php echo number_format(hironoBaseBoxPrice($fig), 2); ?></dd>
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
                                                        <dd class="mo-v"><?php echo mo_e($extraItem["label"]); ?> · ₱<?php echo number_format((float) $extraItem["price"], 2); ?></dd>
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
                                                <dl class="mo-rows mo-rows-split">
                                                    <div class="mo-row">
                                                        <dt class="mo-k">Letter</dt>
                                                        <dd class="mo-v"><?php echo nl2br(mo_e($fig["hirono_letter"])); ?></dd>
                                                    </div>
                                                </dl>
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

                                        <?php else: ?>

                                            <p class="mo-muted">No custom box added to this figure.</p>

                                        <?php endif; ?>

                                    </div>
                                    <!-- /.viewer-column (custom box) -->
                                    <?php if ($moPair) { $moBoxHtml[$index] = ob_get_clean(); } ?>


                                    <?php if ($isDressUpFig): ?>
                                        <!-- DRESS UP: 3D figure, katabi ng Figure Information -->
                                        <div class="viewer-column dressup-3d-column">
                                            <?php figurify_render_dressup_viewer($fig); ?>
                                        </div>
                                    <?php endif; ?>

                                </div>
                                <!-- /.viewer-grid -->

                            </div>
                            <!-- /.order-figure -->

                        <?php endforeach; ?>

                    </div>
                    <!-- /.order-figures -->

                </section>

                <?php if ($moPair): ?>
                    <div class="mo-pair-side">
                        <?php foreach ($moBoxHtml as $moBoxIdx => $moBoxMarkup): ?>
                            <?php if (!empty($moHasBox[$moBoxIdx])): ?>
                                <section class="mo-card mo-box-card<?php echo ($moBoxIdx === 0) ? " is-on" : ""; ?>" data-box-for="<?php echo $oid; ?>-<?php echo (int) $moBoxIdx; ?>">
                                    <?php echo $moBoxMarkup; ?>
                                </section>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <div class="mo-summary-slot mo-summary-side<?php echo $moFirstHasBox ? "" : " is-on"; ?>">
                            <!--MO_SUMMARY-->
                        </div>
                    </div>
                </div><!-- /.mo-pair -->
                <div class="mo-summary-slot mo-summary-below<?php echo $moFirstHasBox ? " is-on" : ""; ?>" data-pair-summary="<?php echo $oid; ?>">
                    <!--MO_SUMMARY-->
                </div>
                <?php endif; ?>


                <!-- ───── 6. ORDER SUMMARY ───── -->

                <?php ob_start(); ?>
                <section class="mo-card">

                    <h2 class="mo-card-title">Order Summary</h2>

                    <div class="mo-sum">

                        <?php
                        /* Down Payment / Remaining Balance */
                        $splitShown = $isDressUpOrder
                            || !in_array($wf, ["PENDING_STAFF_VERIFICATION", "STAFF_CORRECTION_REQUIRED"], true);

                        if ($splitShown && $wf !== "CANCELLED"):

                            /* PAID lang kapag na-approve na ng owner (walang "checking") */
                            $dpPaid = !empty($order["paid_at"]) && !in_array($wf, ["AWAITING_PAYMENT", "QUOTATION_SENT", "PENDING_STAFF_VERIFICATION", "STAFF_CORRECTION_REQUIRED", "PAYMENT_SUBMITTED", "PAYMENT_DETAILS_VERIFIED", "CANCELLED"], true);
                            $balPaid = !empty($extra["balance_paid_at"]) && $wf !== "BALANCE_SUBMITTED" && $wf !== "AWAITING_BALANCE";
                        ?>
                            <div class="mo-split">
                                <div class="mo-sum-line">
                                    <span>Down Payment (50%)<?php if ($dpPaid): ?><em class="pay-tag is-paid">paid</em><?php endif; ?></span>
                                    <span>₱<?php echo number_format((float) $order["downpayment"], 2); ?></span>
                                </div>
                                <div class="mo-sum-line">
                                    <span>Remaining Balance<?php if ($balPaid): ?><em class="pay-tag is-paid">paid</em><?php endif; ?></span>
                                    <span>₱<?php echo number_format((float) $order["balance_due"], 2); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php
                        /* Bawat figure - "Figure N" row, at ang rush fee niya sa ilalim */
                        foreach (array_values($order["figures"]) as $bIndex => $bFig):

                            /* wala pang quotation -> estimate (figure_total) muna */
                            $bFigPrice = ($bFig["quoted_price"] !== null)
                                ? (float) $bFig["quoted_price"]
                                : (float) ($bFig["figure_total"] ?? 0);
                        ?>
                            <div class="mo-sum-line">
                                <span>Figure <?php echo $bIndex + 1; ?></span>
                                <span>₱<?php echo number_format($bFigPrice, 2); ?></span>
                            </div>

                            <?php if (($rushSplit["per"][$bIndex] ?? 0) > 0): ?>
                                <div class="mo-sum-line is-sub">
                                    <span>Rush Fee</span>
                                    <span>₱<?php echo number_format((float) $rushSplit["per"][$bIndex], 2); ?></span>
                                </div>
                            <?php endif; ?>

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

                    </div>

                </section>
                <?php
                $moSummaryHtml = ob_get_clean();   /* Order Summary card */
                $moRegionHtml  = ob_get_clean();   /* Figures (+ Custom Box) region */
                echo $moPair
                    ? str_replace("<!--MO_SUMMARY-->", $moSummaryHtml, $moRegionHtml)
                    : $moRegionHtml . $moSummaryHtml;
                ?>


                <?php
                /* ───── 7. PAYMENT & SHIPPING DETAILS ───── */
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

                        <h2 class="mo-card-title">Payment &amp; Shipping Details</h2>

                        <?php if ($hasPaymentInfo): ?>

                            <div class="mo-pay-block">

                                <div class="mo-sub">Payment &amp; Shipping Information</div>

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
            <!-- /.order-detail-panel -->

        <?php endforeach; ?>

        </div>
        <!-- /.orders-detail-col -->

        </div>
        <!-- /.orders-layout -->

    <?php endif; ?>

</main>



<!-- image lightbox, click photo to enlarge -->

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
        >
            ✕
        </button>

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


<script>

/* client-side search + status filter lang, walang extra request sa server */

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

        item.style.display = matches ? "" : "none";

        if (matches) {
            visibleCount++;
        }

    });

    var emptyMessage = document.getElementById("myOrdersSearchEmpty");

    if (emptyMessage) {
        emptyMessage.style.display = (visibleCount === 0 && items.length > 0) ? "" : "none";
    }

}


/* status filter dropdown - bukas/sara, isa lang bukas sa isang pagkakataon */

function myOrdersToggleFilter() {

    var dropdown = document.getElementById("myOrdersFilterDropdown");

    if (!dropdown) {
        return;
    }

    var isOpen = dropdown.classList.contains("open");

    dropdown.classList.toggle("open", !isOpen);

}

function myOrdersSetFilter(el, value, label) {

    myOrdersStatusFilter = value;

    var labelEl = document.getElementById("myOrdersFilterLabel");
    if (labelEl) {
        labelEl.textContent = label;
    }

    document.querySelectorAll("#myOrdersFilterDropdown .orders-filter-option").forEach(function (opt) {
        opt.classList.toggle("active", opt === el);
    });

    var dropdown = document.getElementById("myOrdersFilterDropdown");
    if (dropdown) {
        dropdown.classList.remove("open");
    }

    myOrdersApplyFilters();

}

document.addEventListener("click", function (event) {

    var dropdown = document.getElementById("myOrdersFilterDropdown");

    if (dropdown && dropdown.classList.contains("open") && !dropdown.contains(event.target)) {
        dropdown.classList.remove("open");
    }

});

document.addEventListener("keydown", function (event) {

    if (event.key === "Escape") {
        var dropdown = document.getElementById("myOrdersFilterDropdown");
        if (dropdown) {
            dropdown.classList.remove("open");
        }
    }

});


/* switch which order's details show on the right */

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
        var isStacked = window.innerWidth <= 1000;

        if (!noScroll && (isStacked || panelTop < 0)) {

            target.scrollIntoView({
                behavior: "smooth",
                block: "start"
            });

        }

    }

}


/* progress update response - approve or request revision, per order_id */

function showProgressRevisionBox(orderId) {

    var buttonsRow = document.getElementById("progressButtonsRow-" + orderId);
    var revisionBox = document.getElementById("progressRevisionBox-" + orderId);

    if (buttonsRow) {
        buttonsRow.style.display = "none";
    }

    if (revisionBox) {
        revisionBox.style.display = "flex";
    }

}

function hideProgressRevisionBox(orderId) {

    var buttonsRow = document.getElementById("progressButtonsRow-" + orderId);
    var revisionBox = document.getElementById("progressRevisionBox-" + orderId);
    var noteField = document.getElementById("progressRevisionNote-" + orderId);

    if (revisionBox) {
        revisionBox.style.display = "none";
    }

    if (noteField) {
        noteField.value = "";
    }

    if (buttonsRow) {
        buttonsRow.style.display = "";
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




/* switch which figure's details show (kapag 2+ figures), isa lang sa isang time */

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

    moSyncPair(button);

}


/* Image Submission: kapag nagpalit ng figure tab, ipakita ang Custom Box ng figure na iyon
   katabi ng Figures card; kung wala siyang box, Order Summary ang katabi. */

function moSyncPair(button) {

    var pair = button.closest(".mo-pair");

    if (!pair) {
        return;
    }

    var panelId = button.dataset.figureTarget || "";   /* figure-panel-{oid}-{idx} */
    var match = panelId.match(/^figure-panel-(\d+)-(\d+)$/);

    if (!match) {
        return;
    }

    var boxKey = match[1] + "-" + match[2];
    var hasBox = false;

    pair.querySelectorAll(".mo-box-card").forEach(function (card) {
        var on = card.getAttribute("data-box-for") === boxKey;
        card.classList.toggle("is-on", on);
        if (on) {
            hasBox = true;
        }
    });

    var sideSummary = pair.querySelector(".mo-summary-side");
    var belowSummary = pair.parentNode.querySelector('.mo-summary-below[data-pair-summary="' + match[1] + '"]');

    if (sideSummary) {
        sideSummary.classList.toggle("is-on", !hasBox);
    }

    if (belowSummary) {
        belowSummary.classList.toggle("is-on", hasBox);
    }

}


/* click a figure photo to enlarge it */

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

    document.body.style.overflow = "hidden";

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

    document.body.style.overflow = "";

}


document.addEventListener("keydown", function (event) {

    if (event.key === "Escape") {
        closeImageLightbox();
    }

});


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

</script>


<script>

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

        return (tag === "textarea" || tag === "input" || tag === "select");
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

</script>


<?php include "../Shared/footer.php"; ?>

</body>
</html>