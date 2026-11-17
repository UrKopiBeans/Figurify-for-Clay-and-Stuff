<?php

require_once __DIR__ . "/owner-header.php";

$figurifyRole    = "owner";
$figurifyPageDir = __DIR__;

/* Owner version ng Active Booking - pareho ng staff, dagdag lang ang Process Refund at ang Overdue notice. */

require_once __DIR__ . "/../helpers/expiration_helper.php";

/* auto-approve progress updates na di na-response ng customer sa loob ng 2 araw (FIGURIFY_PROGRESS_AUTO_APPROVE_HOURS) */
figurify_autoapprove_stale_progress_updates($conn);
require_once __DIR__ . "/../helpers/order_helper.php";
figurify_autocomplete_shipped_orders($conn);

/* session/role check, $ownerName, e, statusLabel galing na sa owner-header.php */

$documentRoot = rtrim(str_replace("\\", "/", realpath($_SERVER["DOCUMENT_ROOT"])), "/");
$projectRoot  = rtrim(str_replace("\\", "/", realpath(__DIR__ . "/..")), "/");
$siteBase     = substr($projectRoot, strlen($documentRoot)) . "/";

/* get only orders na currently active (approved, ginagawa) - pwede i-Ship Out or Update dito */

$orders = [];

$stmt = $conn->prepare(
    "SELECT o.order_id, o.order_type, o.order_method, o.ship_out_date, o.rush_fee,
            o.subtotal, o.total_amount, o.status, o.created_at,
            o.shipping_name, o.shipping_address, o.shipping_contact, o.courier,
            o.tracking_number, o.shipping_fee, o.balance_to_pay, o.shipped_at,
            o.payment_reference, o.payment_proof, o.paid_at,
            o.refund_account_name, o.refund_account_number, o.refund_method,
            u.full_name, u.email
     FROM orders o
     JOIN users u ON u.user_id = o.user_id
     WHERE LOWER(o.status) IN ('processing', 'on process', 'active', 'paid', 'approved', 'awaiting_balance', 'to_ship', 'shipped')
     ORDER BY o.ship_out_date ASC, o.created_at ASC"
);

$stmt->execute();

$ordersResult = $stmt->get_result();

while ($row = $ordersResult->fetch_assoc()) {

    $orders[] = $row;
}

$stmt->close();


/* GET FIGURES + IMAGES FOR EACH ORDER - para makita ng staff ang in-order. */

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


    if ($fstmt === false) {
        $order["figures"] = [];
        continue;
    }

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

        $images = [];

        if ($imgStmt !== false) {

            $imgStmt->bind_param("i", $frow["figure_id"]);
            $imgStmt->execute();

            $imgResult = $imgStmt->get_result();

            while ($irow = $imgResult->fetch_assoc()) {
                $images[] = $irow["image_path"];
            }

            $imgStmt->close();

        }

        $frow["images"] = $images;


        /* box reference images (Hirono Blind Box only) - fails quietly kung wala pang table */

        $boxImages = [];

        $boxImgStmt = $conn->prepare(
            "SELECT image_path
             FROM order_figure_box_images
             WHERE figure_id = ?"
        );

        if ($boxImgStmt !== false) {

            $boxImgStmt->bind_param("i", $frow["figure_id"]);
            $boxImgStmt->execute();

            $boxImgResult = $boxImgStmt->get_result();

            while ($birow = $boxImgResult->fetch_assoc()) {
                $boxImages[] = $birow["image_path"];
            }

            $boxImgStmt->close();

        }

        $frow["box_images"] = $boxImages;


        /* optional Hirono blind box extras, saved as JSON { item_key: price } */

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
}

unset($order);


/* may pending na revision request ba? (base sa pinakahuling progress update) */

foreach ($orders as &$order) {

    $order["has_revision"] = false;

    /* "To Ship" na? - base sa totoong o.status column, di na hula mula sa progress update. */
    $order["is_to_ship"] = (strtolower(trim((string) $order["status"])) === "to_ship");

    /* "Shipped" na? - itinatakda pag Ship Out (Tracking Number/Shipping Fee/Balance to Pay). */
    $order["is_shipped"] = (strtolower(trim((string) $order["status"])) === "shipped");

    if (!$order["is_to_ship"] && !$order["is_shipped"]) {

        $latestStmt = $conn->prepare(
            "SELECT customer_response
             FROM order_progress_updates
             WHERE order_id = ?
             ORDER BY created_at DESC
             LIMIT 1"
        );

        if ($latestStmt !== false) {

            $latestStmt->bind_param("i", $order["order_id"]);
            $latestStmt->execute();

            $latestRow = $latestStmt->get_result()->fetch_assoc();
            $latestStmt->close();

            if ($latestRow && $latestRow["customer_response"] === "revision") {
                $order["has_revision"] = true;
            }

        }

    }

}

unset($order);


/* PROCESSING / REVISION / TO SHIP TAB FILTER */

$activeFilter = isset($_GET["afilter"]) ? strtolower(trim($_GET["afilter"])) : "all";

if (!in_array($activeFilter, ["all", "processing", "revision", "to_ship", "shipped"], true)) {
    $activeFilter = "all";
}

/* 3-column layout, same as Verification page, ginagamit ng lahat ng tabs */
$useVerificationLayout = true;

$requestedActiveOrderId = isset($_GET["order_id"]) ? (int) $_GET["order_id"] : 0;

$revisionCount   = 0;
$processingCount = 0;

foreach ($orders as $countOrder) {
    if ($countOrder["is_shipped"] || $countOrder["is_to_ship"]) {
        continue; 
    } elseif ($countOrder["has_revision"]) {
        $revisionCount++;
    } else {
        $processingCount++;
    }
}

$visibleOrders = array_values(array_filter(
    $orders,
    function ($order) use ($activeFilter, $requestedActiveOrderId) {

        // always show the order requested sa URL kahit anong tab ang active
        if ($requestedActiveOrderId > 0 && (int) $order["order_id"] === $requestedActiveOrderId) {
            return true;
        }

        if ($activeFilter === "all") {
            // "All" = Processing + Revision lang, To Ship/Shipped may sariling page
            return !$order["is_to_ship"] && !$order["is_shipped"];
        }

        if ($activeFilter === "shipped") {
            return $order["is_shipped"];
        }

        if ($activeFilter === "to_ship") {
            return $order["is_to_ship"];
        }

        if ($activeFilter === "revision") {
            return $order["has_revision"] && !$order["is_to_ship"] && !$order["is_shipped"];
        }

        return !$order["has_revision"] && !$order["is_to_ship"] && !$order["is_shipped"];
    }
));


/* which order is open on the right side - no auto-select, click a row muna */
$selectedOrderId = isset($_GET["order_id"])
    ? (int) $_GET["order_id"]
    : 0;

$selectedOrder = null;

foreach ($orders as $order) {

    if ((int) $order["order_id"] === $selectedOrderId) {
        $selectedOrder = $order;
        break;
    }
}


/* PROGRESS UPDATE HISTORY - mga photo update na na-send na (Pending/Approved/Revision + note). */

$progressUpdates = [];
$progressError    = $_GET["progress_error"] ?? "";

if ($selectedOrderId > 0) {

    $progStmt = $conn->prepare(
        "SELECT update_id, image_path, staff_message, customer_response,
                revision_note, staff_reply, is_final, auto_approved,
                created_at, responded_at
         FROM order_progress_updates
         WHERE order_id = ?
         ORDER BY created_at DESC, update_id DESC"
    );

    /* fallback kung wala pang "auto_approved" column, migration lang ang mawawala */
    if ($progStmt === false) {

        $progStmt = $conn->prepare(
            "SELECT update_id, image_path, staff_message, customer_response,
                    revision_note, staff_reply, is_final, created_at, responded_at
             FROM order_progress_updates
             WHERE order_id = ?
             ORDER BY created_at DESC, update_id DESC"
        );

    }

    if ($progStmt) {

        $progStmt->bind_param("i", $selectedOrderId);
        $progStmt->execute();

        $progResult = $progStmt->get_result();

        while ($progRow = $progResult->fetch_assoc()) {

            if (!array_key_exists("auto_approved", $progRow)) {
                $progRow["auto_approved"] = 0;
            }

            $progressUpdates[] = $progRow;

        }

        $progStmt->close();

    }

}

$latestRevisionUpdateId = 0;
$latestResponse         = !empty($progressUpdates) ? $progressUpdates[0]["customer_response"] : null;

if ($latestResponse === "revision") {
    $latestRevisionUpdateId = (int) $progressUpdates[0]["update_id"];
}

/* WORKFLOW-BASED ACTIONS - ang ipinapakitang buttons ay galing sa
   transition map (helpers/order_helper.php). UI convenience lang ito;
   ang backend pa rin ang nagpapasya sa bawat POST. */

$wfState        = "";
$wfAllowed      = [];
$wfRevisionUsed = 0;
$wfBalanceDue   = 0.0;
$wfBalancePaid  = false;
$wfShipChecks   = [];

if ($selectedOrder) {

    $wfLatestRow = !empty($progressUpdates) ? $progressUpdates[0] : null;
    $wfState     = figurify_wf_canonical_from($selectedOrder["status"], $wfLatestRow);
    $wfAllowed   = figurify_wf_allowed_actions($wfState, ($figurifyRole === "owner") ? FIGURIFY_WF_OWNER : FIGURIFY_WF_STAFF);

    $wfFullStmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ?");
    $wfSelId    = (int) $selectedOrder["order_id"];
    $wfFullStmt->bind_param("i", $wfSelId);
    $wfFullStmt->execute();
    $wfFullOrder = $wfFullStmt->get_result()->fetch_assoc() ?: [];
    $wfFullStmt->close();

    $wfRevisionUsed = (int) ($wfFullOrder["revision_count"] ?? 0);
    $wfBalanceDue   = figurify_wf_balance_amount($wfFullOrder);
    $wfBalancePaid  = ($wfBalanceDue <= 0) || !empty($wfFullOrder["balance_paid_at"]);
    $wfShipChecks   = figurify_wf_shipping_checklist($conn, $wfFullOrder, $wfLatestRow);
}

$wfHasFinalPhoto = false;

foreach ($progressUpdates as $wfPu) {
    if (!empty($wfPu["is_final"])) {
        $wfHasFinalPhoto = true;
        break;
    }
}

// Send Update: normal progress (PROCESSING), revised photo (REVISION_IN_PROGRESS), o
// final photo kapag Ready to Ship na (impormasyon lang, walang status change)
$autoFinalUpdate          = ($wfState === "READY_TO_SHIP");
// tinanggap ang revision: ipapadala ang revised photo, tapos diretso na sa balance (walang It's Good)
$sendingRevisedPhoto      = ($wfState === "REVISION_IN_PROGRESS");
$canSendUpdate            = in_array("submit_progress", $wfAllowed, true)
                            || ($autoFinalUpdate && in_array("submit_final_photo", $wfAllowed, true) && !$wfHasFinalPhoto && $wfBalancePaid);
/* kapag naipadala na ang revised photo (pagkatapos ng na-accept
   na revision), wala nang Send Update - kahit ang "final photo". */
$wfRevisedPhotoSent = false;
foreach ($progressUpdates as $wfPuIndex => $wfPu) {
    if ($wfPuIndex > 0 && in_array(($wfPu["customer_response"] ?? ""), ["in_progress", "declined"], true)) {
        $wfRevisedPhotoSent = true;
        break;
    }
}
if ($wfRevisedPhotoSent) {
    $canSendUpdate = false;
}

$awaitingCustomerResponse = ($wfState === "PROGRESS_SUBMITTED");
$awaitingRevisionDecision = ($wfState === "REVISION_REQUESTED");

// "Finalize" - para lang sa lumang naka-approve pero "processing" pa, o
// lumang to_ship na hindi pa nababayaran ang balance. System ang nagko-compute.
$canMarkToShip = in_array("finalize_order", $wfAllowed, true)
    && ($wfState === "CUSTOMER_APPROVED" || !$wfBalancePaid);

$canShipOut = in_array("ship_order", $wfAllowed, true) && $wfBalancePaid;


$conn->close();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php /* [POP UP AT SECURITY] pop up at security script lang (walang CSS) - Shared/popup-and-security.php */ include __DIR__ . "/../Shared/popup-and-security.php"; ?>
    <link rel="icon" type="image/png" href="../Image/brand/logo.png">

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1"
>

<title>Figurify | Active Bookings</title>

<!-- ===================== STYLE NG PAGE NA ITO: active-booking.css ===================== -->
<link rel="stylesheet" href="active-booking.css">

</head>


<body class="<?php echo $useVerificationLayout ? "quotation-page" : ""; ?>">


<div class="app">


<?php require_once __DIR__ . "/../Shared/sidebar.php"; ?>


<!-- MAIN -->

<main class="main">


    <!-- TOPBAR -->

    <header class="topbar">

        <div class="welcome">

            <h2 class="portal-title">
                Active Bookings
            </h2>

        </div>

    
        <div class="top-actions">

<!-- ==========================================================
     [TOPBAR-DATETIME]  (dating Shared/topbar-datetime.php - nasa page na ito na)
     ANO ITO: petsa at oras sa taas ng page, sa kaliwa ng notification bell.
     PAANO GUMAGANA: ang JS sa Shared/sidebar.php ([3] TOPBAR DATE / TIME) ang nagpapalit ng .cs-tb-date at .cs-tb-time
     kada segundo (en-PH). Self-contained: hindi kailangan ng #currentDate / #currentTime.
     ========================================================== -->

<div class="cs-tb-datetime" aria-label="Current date and time">
    <span class="cs-tb-date">Loading date...</span>
    <span class="cs-tb-dot">•</span>
    <span class="cs-tb-time">Loading time...</span>
</div>

<!-- [END TOPBAR-DATETIME] -->

            <?php include __DIR__ . "/../Shared/notification-bell.php"; ?>

        </div>

    </header>


    <!-- VERIFICATION MANAGER -->

    <section class="card booking-workspace <?php echo $useVerificationLayout ? "qv-workspace" : "orders-manager"; ?>">

        <div class="<?php echo $useVerificationLayout ? "qv-shell" : "orders-manager-grid"; ?>">


            <!-- LEFT: ORDER LIST -->

            <?php if ($useVerificationLayout): ?>

                <!-- Processing tab lang - same itsura ng Verification's left panel. Ibang tabs ay nasa staff-order-list pa rin sa ibaba. -->

                <div class="qv-list-panel">

                    <div class="qv-toolbar">

                        <div class="qv-filter-group">

                        <?php figurify_render_active_booking_tabs($activeFilter, $processingCount, $revisionCount); /* Processing / Revision - nasa left filter na */ ?>

                        <?php
                        /* filter ayon sa Order Type - sa browser lang, parehong itsura ng Verification */
                        $qvRushCount = 0;
                        foreach ($visibleOrders as $qvCountRow) {
                            if (($qvCountRow["order_type"] ?? "") === "rush") {
                                $qvRushCount++;
                            }
                        }
                        $qvNonRushCount = count($visibleOrders) - $qvRushCount;
                        $qvAllLabel     = "All (" . count($visibleOrders) . ")";
                        ?>

                        <div class="qv-filter-dropdown" id="qvTypeFilterDropdown">

                            <button
                                type="button"
                                class="qv-filter-trigger"
                                aria-haspopup="true"
                                onclick="figurifyToggleFilterDropdown('qvTypeFilterDropdown')"
                            >
                                <span id="qvTypeFilterLabel"><?php echo e($qvAllLabel); ?></span>
                                <svg class="qv-filter-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>
                            </button>

                            <div class="qv-filter-menu" id="qvTypeFilterMenu">
                                <a href="javascript:void(0)" class="qv-filter-option active" onclick="qvSetTypeFilter(this, 'all')">All (<?php echo count($visibleOrders); ?>)</a>
                                <a href="javascript:void(0)" class="qv-filter-option" onclick="qvSetTypeFilter(this, 'rush')">Rush (<?php echo (int) $qvRushCount; ?>)</a>
                                <a href="javascript:void(0)" class="qv-filter-option" onclick="qvSetTypeFilter(this, 'non-rush')">Non-rush (<?php echo (int) $qvNonRushCount; ?>)</a>
                            </div>

                        </div>

                        </div><!-- /.qv-filter-group -->

                        <div class="qv-search">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            <input
                                type="text"
                                id="qvSearchInput"
                                placeholder="Search order ID, name..."
                                oninput="qvFilterOrders(this.value)"
                            >
                        </div>

                    </div>

                    <?php if (empty($visibleOrders)): ?>

                        <div class="qv-empty">
                            <?php
                            if ($activeFilter === "revision") {
                                echo "No orders need revision right now.";
                            } elseif ($activeFilter === "to_ship") {
                                echo "No orders waiting to ship right now.";
                            } elseif ($activeFilter === "shipped") {
                                echo "No orders shipped yet.";
                            } else {
                                echo "No active bookings right now.";
                            }
                            ?>
                        </div>

                    <?php else: ?>

                        <div class="qv-list" id="qvOrderList">

                            <table class="qv-table">

                                <thead>
                                    <tr>
                                        <th>Order</th>
                                        <th>Email</th>
                                        <th>Order Type</th>
                                        <th>Order Method</th>
                                        <th>Ship Out Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <?php foreach ($visibleOrders as $order): ?>

                                        <?php
                                        $isActive     = ((int) $order["order_id"] === $selectedOrderId);
                                        $qvSearchText = strtolower($order["order_id"] . " " . $order["full_name"] . " " . $order["email"]);
                                        $qvRowHref    = "active-booking.php?afilter=" . e($activeFilter) . "&order_id=" . (int) $order["order_id"];
                                        ?>

                                        <tr
                                            class="qv-row<?php echo $isActive ? " active" : ""; ?>"
                                            data-href="<?php echo e($qvRowHref); ?>"
                                            data-search="<?php echo e($qvSearchText); ?>"
                                            data-order-id="<?php echo (int) $order["order_id"]; ?>"
                                            data-type="<?php echo ($order["order_type"] === "rush") ? "rush" : "non-rush"; ?>"
                                            onclick="window.location.href = this.dataset.href;"
                                        >

                                            <td class="qv-cell-id">
                                                #<?php echo (int) $order["order_id"]; ?>
                                            </td>

                                            <td><?php echo e($order["email"]); ?></td>

                                            <td>
                                                <?php echo ($order["order_type"] === "rush") ? "Rush" : "Non-Rush"; ?>
                                            </td>

                                            <td>
                                                <?php echo e(orderMethodLabel($order["order_method"])); ?>
                                            </td>

                                            <td>
                                                <?php echo date("M d, Y", strtotime($order["ship_out_date"])); ?>
                                                <?php
                                                /* OVERDUE - lampas na sa ship out date pero hindi pa naipapadala */
                                                if (in_array(figurify_status_key($order["status"]), ["processing", "to_ship"], true)
                                                    && strtotime($order["ship_out_date"]) < strtotime(date("Y-m-d"))): ?>
                                                    <span class="overdue-badge">Overdue</span>
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <?php if (!empty($order["has_revision"])): ?>

                                                    <span class="progress-status-badge progress-status-revision">
                                                        Revision
                                                    </span>

                                                <?php else: ?>

                                                    <span class="order-status <?php echo figurify_status_class($order["status"]); ?>">
                                                        <?php echo e(statusLabel($order["status"])); ?>
                                                    </span>

                                                <?php endif; ?>
                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                        <div class="qv-list-footer" id="qvListFooter">
                            Showing <?php echo count($visibleOrders); ?> of <?php echo count($visibleOrders); ?> orders
                        </div>

                    <?php endif; ?>

                </div>

            <?php else: ?>

                <div class="staff-order-list">

                    <?php figurify_render_active_booking_tabs($activeFilter, $processingCount, $revisionCount); ?>

                    <?php if (empty($visibleOrders)): ?>

                        <div class="empty-state">
                            <?php
                            if ($activeFilter === "revision") {
                                echo "No orders need revision right now.";
                            } elseif ($activeFilter === "to_ship") {
                                echo "No orders waiting to ship right now.";
                            } elseif ($activeFilter === "shipped") {
                                echo "No orders shipped yet.";
                            } else {
                                echo "No active bookings right now.";
                            }
                            ?>
                        </div>

                    <?php else: ?>

                        <?php foreach ($visibleOrders as $order): ?>

                            <?php
                            $isActive =
                                ((int) $order["order_id"] === $selectedOrderId);
                            ?>

                            <a
                                href="active-booking.php?afilter=<?php echo e($activeFilter); ?>&order_id=<?php echo (int) $order["order_id"]; ?>"
                                class="staff-order-item <?php echo $isActive ? "active" : ""; ?>"
                            >

                                <div class="staff-order-item-top">

                                    <strong>
                                        Order #<?php echo (int) $order["order_id"]; ?>
                                    </strong>

                                    <?php if (!empty($order["has_revision"])): ?>

                                        <!-- kapag may pending revision request, ito lang ang badge na ipapakita -->
                                        <span class="progress-status-badge progress-status-revision">
                                            Revision
                                        </span>

                                    <?php else: ?>

                                        <span class="order-status <?php echo figurify_status_class($order["status"]); ?>">
                                            <?php echo e(statusLabel($order["status"])); ?>
                                        </span>

                                    <?php endif; ?>

                                </div>

                                <div class="staff-order-item-customer">
                                    <?php echo e($order["full_name"]); ?>
                                </div>

                                <div class="staff-order-item-meta">
                                    <?php
                                    echo !empty($order["paid_at"])
                                        ? "Paid " . date("M j, Y", strtotime($order["paid_at"]))
                                        : date("M j, Y", strtotime($order["created_at"]));
                                    ?>
                                    ·
                                    ₱<?php echo number_format($order["total_amount"], 2); ?>
                                </div>

                            </a>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </div>

            <?php endif; ?>


            <?php if ($useVerificationLayout): ?>
                <div class="qv-right-col<?php echo !$selectedOrder ? " qv-right-col--blank" : ""; ?>">
            <?php endif; ?>


            <!-- RIGHT: ORDER DETAIL -->

            <div class="<?php echo $useVerificationLayout ? "qv-detail-col" : "staff-order-detail"; ?>">

                <?php if (!$selectedOrder): ?>

                    <?php if ($useVerificationLayout): ?>

                        <div class="qv-header-card">
                            <div class="qv-empty">
                                Select an order to check its payment.
                            </div>
                        </div>

                    <?php else: ?>

                        <div class="empty-state">
                            Select an order to check its payment.
                        </div>

                    <?php endif; ?>

                <?php else: ?>

                    <?php if ($useVerificationLayout): ?>

                    <!-- PANEL HEAD: #order · Order + status, status tabs, booking/placed/total -->

                    <?php else: ?>

                    <div class="staff-detail-header">

                        <div>

                            <h3>
                                Order #<?php echo (int) $selectedOrder["order_id"]; ?>
                            </h3>

                            <p>
                                <?php echo e($selectedOrder["full_name"]); ?>
                                ·
                                <?php echo e($selectedOrder["email"]); ?>
                            </p>

                        </div>

                        <div class="staff-detail-total">
                            ₱<?php echo number_format($selectedOrder["total_amount"], 2); ?>
                        </div>

                    </div>


                    <div class="staff-detail-meta">

                        <span>
                            <?php
                            echo ($selectedOrder["order_type"] === "rush")
                                ? "Rush Order"
                                : "Non-Rush Order";
                            ?>
                        </span>

                        <span>
                            Ship Out Date:
                            <?php echo date("F j, Y", strtotime($selectedOrder["ship_out_date"])); ?>
                        </span>

                        <span>
                            Placed:
                            <?php echo date("F j, Y", strtotime($selectedOrder["created_at"])); ?>
                        </span>

                    </div>

                    <?php endif; ?>


                    <!-- PROGRESS UPDATE HISTORY - sa PROCESSING tab, lumilipat ito sa RIGHT column (qv-summary).
                         Dito pa rin sa Revision/To Ship/Shipped tabs. -->

                    <?php if (!$useVerificationLayout): ?>
                        <?php figurify_render_progress_history_block($progressUpdates, $progressError); ?>
                    <?php endif; ?>


                    <!-- SEND PROGRESS UPDATE MODAL -->

                    <div class="progress-modal-overlay" id="progressModalOverlay">

                        <div class="progress-modal">

                            <div class="progress-modal-top">
                                <h3><?php echo $autoFinalUpdate ? "Send Final Photo" : ($sendingRevisedPhoto ? "Send Revised Photo" : "Send Progress Update"); ?></h3>
                            </div>

                            <p class="progress-modal-hint">
                                <?php if ($autoFinalUpdate): ?>
                                    Upload the photo of the finished order. The customer will be notified.
                                <?php elseif ($sendingRevisedPhoto): ?>
                                    Upload a photo of the revised figure. Once sent, the customer goes straight to paying the balance (no need to approve it again).
                                <?php else: ?>
                                    Upload a photo of the current progress. The customer can approve it or ask for a revision.
                                <?php endif; ?>
                            </p>

                            <form
                                method="POST"
                                action="send_progress_update.php"
                                enctype="multipart/form-data"
                            >

                                <input type="hidden" name="order_id" value="<?php echo (int) $selectedOrderId; ?>">

                                <label>Photo</label>

                                <!-- isang larawan lang; may preview, pwedeng tingnan o alisin -->
                                <div class="pu-photo-picker" id="puPhotoPicker">
                                    <input
                                        type="file"
                                        id="progress_image"
                                        name="progress_image"
                                        accept="image/*"
                                        required
                                        class="pu-photo-input"
                                        onchange="figurifyPreviewProgressPhoto(this)"
                                    >
                                    <label for="progress_image" class="pu-photo-empty" id="puPhotoEmpty">
                                        <span class="pu-photo-plus">+</span>
                                        <span>Click to choose a photo</span>
                                        <small>1 image only (JPG, PNG, WEBP)</small>
                                    </label>
                                    <div class="pu-photo-preview" id="puPhotoPreview" hidden>
                                        <!-- click sa larawan = palakihin; X = alisin ang napiling larawan -->
                                        <img id="puPhotoImg" src="" alt="Selected progress photo" onclick="openImageLightbox(this.src, 'Progress photo')">
                                        <button type="button" class="pu-photo-close" aria-label="Remove photo" onclick="figurifyClearProgressPhoto()">&times;</button>
                                    </div>
                                </div>

                                <?php if ($autoFinalUpdate): ?>

                                    <input type="hidden" name="is_final" value="1">

                                <?php else: ?>

                                    <label for="staff_message">Message (optional)</label>
                                    <textarea
                                        id="staff_message"
                                        name="staff_message"
                                        rows="3"
                                        placeholder="e.g. Here's the sculpt so far!"
                                    ></textarea>

                                <?php endif; ?>

                                <div class="pu-modal-actions">
                                    <button type="submit" class="pu-btn pu-btn-send">Send to Customer</button>
                                    <button type="button" class="pu-btn pu-btn-cancel" onclick="figurifyCloseProgressModal()">Cancel</button>
                                </div>

                            </form>

                        </div>

                    </div>

                    
                    <!-- DECLINE REVISION MODAL -->

                    <div class="decline-modal-overlay" id="declineModal_revision">

                        <div class="decline-modal-box">

                            <p class="decline-modal-eyebrow">Revision Request</p>

                            <h3 class="decline-modal-title">Decline Revision</h3>

                            <p class="decline-modal-desc">
                                Pick a reason why this revision can't be done.
                                The customer will see this as a notification
                                and email.
                            </p>

                            <form
                                method="POST"
                                action="decline_revision.php"
                                class="decline-reason-form"
                                onsubmit="return figurifyCheckDeclineForm(this);"
                            >

                                <input type="hidden" name="order_id" value="<?php echo (int) $selectedOrderId; ?>">
                                <input type="hidden" name="update_id" value="<?php echo (int) $latestRevisionUpdateId; ?>">

                                <select
                                    name="decline_reason"
                                    class="decline-reason-select"
                                    required
                                    onchange="figurifyToggleDeclineOther(this)"
                                >
                                    <option value="" disabled selected>Select a reason…</option>
                                    <option value="unable_to_replicate">Unable to Match the Requested Change</option>
                                    <option value="requires_additional_cost">Requires Additional Cost</option>
                                    <option value="beyond_material_capability">Beyond Our Material/Process Capability</option>
                                    <option value="would_delay_completion">Would Significantly Delay Completion</option>
                                    <option value="other">Other (please specify)</option>
                                </select>

                                <textarea
                                    name="decline_reason_other"
                                    class="decline-reason-other"
                                    placeholder="Type the reason…"
                                    maxlength="190"
                                    hidden
                                ></textarea>

                                <div class="decline-modal-actions">
                                    <button type="button" class="decline-modal-btn decline-modal-btn-cancel" onclick="figurifyCloseDeclineModal('declineModal_revision')">Cancel</button>
                                    <button type="submit" class="decline-modal-btn decline-modal-btn-send">Send</button>
                                </div>

                            </form>

                        </div>

                    </div>


                    <!-- REQUEST BALANCE PAYMENT MODAL -->

                    <div class="progress-modal-overlay" id="requestBalanceOverlay">

                        <div class="progress-modal">

                            <div class="progress-modal-top">

                                <h3>Finalize Order</h3>

                                <button
                                    type="button"
                                    class="progress-modal-close"
                                    onclick="document.getElementById('requestBalanceOverlay').classList.remove('show')"
                                >
                                    ×
                                </button>

                            </div>

                            <p class="progress-modal-hint">
                                The system computes the remaining balance from the quotation.
                            </p>

                            <form
                                method="POST"
                                action="update_active_order.php"
                            >

                                <input type="hidden" name="order_id" value="<?php echo (int) $selectedOrder["order_id"]; ?>">
                                <input type="hidden" name="action" value="finalize">
                                <input type="hidden" name="redirect_to" value="active-booking">

                                <div class="qv-total-row">
                                    <span>Balance Due</span>
                                    <strong>₱<?php echo number_format((float) $wfBalanceDue, 2); ?></strong>
                                </div>

                                <div class="pu-modal-actions">
                                    <button type="submit" class="pu-btn pu-btn-send">
                                        Finalize &amp; Request Balance
                                    </button>
                                    <button type="button" class="pu-btn pu-btn-cancel" onclick="document.getElementById('requestBalanceOverlay').classList.remove('show')">Cancel</button>
                                </div>

                            </form>

                        </div>

                    </div>


                    <!-- CUSTOMER ORDER VIEW (FIGURES) - buong detalye ng order: figure details, custom box, larawan, notes at presyo -->


                        <div class="qv-figure-panels">

                            <?php foreach ($selectedOrder["figures"] as $qIndex => $fig): ?>

                            <?php
                            /* rush fee kada figure (₱200 Head Only, ₱500 iba) + breakdown bago ang Total Amount */
                            require_once __DIR__ . "/../helpers/booking_helper.php";
                            $qvOrderFigures = $selectedOrder["figures"] ?? [];
                            $qvRushSplit    = figurify_rush_split($selectedOrder, $qvOrderFigures);
                            $qvFigRushFee   = (float) ($qvRushSplit["per"][$qIndex] ?? 0);
                            $qvFigHasBox    = in_array((string) ($fig["box_addon_type"] ?? ""), ["funko_box", "hirono_blind_box"], true);

                            $qvBreakdownRows = "";
                            if (count($qvOrderFigures) > 1 || $qvRushSplit["total"] > 0) {
                                foreach (array_values($qvOrderFigures) as $qvBi => $qvBf) {
                                    $qvBfPrice = figurify_figure_line_price($qvBf);
                                    /* rush fee ng figure: nasa kaliwa ng presyo, may "+" (hal. + ₱500.00 Rush Fee   ₱1,200.00) */
                                    $qvBfRush = (float) ($qvRushSplit["per"][$qvBi] ?? 0);
                                    $qvBreakdownRows .= '<div class="qv-sub-line"><span>Figure ' . ($qvBi + 1) . (figurify_figure_quantity($qvBf) > 1 ? ' × ' . figurify_figure_quantity($qvBf) : '') . '</span><span>' . ($qvBfRush > 0 ? '<small class="qv-sub-rush-plus">+ ₱' . number_format($qvBfRush, 2) . ' Rush Fee</small>' : '') . '₱' . number_format($qvBfPrice, 2) . '</span></div>';
                                }
                                if ($qvRushSplit["legacy"] > 0) {
                                    $qvBreakdownRows .= '<div class="qv-sub-line"><span>Rush Fee</span><span>₱' . number_format($qvRushSplit["legacy"], 2) . '</span></div>';
                                }
                            }
                            $qvTotalLine = '<div class="qv-total-breakdown">' . $qvBreakdownRows
                                . '<div class="qv-total-line"><span class="qv-total-line-label">Total Amount</span>'
                                . '<strong class="qv-total-line-value">₱' . number_format((float) ($selectedOrder["total_amount"] ?? 0), 2) . '</strong></div></div>';
                            ?>

                                <?php
                                /* try/catch per figure kaya kahit sira yung isa, tuloy pa rin ang iba */
                                try {
                                ob_start();

                                $hasSize = figureHasSize($fig);

                                $existingQuote = ($fig["quoted_price"] !== null)
                                    ? (float) $fig["quoted_price"]
                                    : (float) $fig["figure_total"];

                                $panelId = "verify-figure-panel-" . (int) $selectedOrder["order_id"] . "-" . $qIndex;
                                ?>

                                <div
                                    class="qv-figure-panel<?php echo ($qIndex === 0) ? " active" : ""; ?>"
                                    id="<?php echo $panelId; ?>"
                                >

                                    <div class="qv-viewer-grid<?php echo figurify_dressup_for_figure($fig) !== null ? " dressup-grid" : ""; ?>">

                                    <!-- LEFT: FIGURE DETAILS + GALLERY -->

                                    <div class="qv-viewer-column">

                                    <div class="qv-figure-card">

                                        <?php if (count($selectedOrder["figures"]) > 1): ?>

                                            <!-- FIGURE TABS - nasa loob na ng figure card,
                                                 sa itaas ng "Figure Details" heading. -->

                                            <div class="qv-figure-tabs">

                                                <?php foreach ($selectedOrder["figures"] as $tabIndex => $tabFig): ?>

                                                    <button
                                                        type="button"
                                                        class="qv-figure-tab-pill<?php echo ($tabIndex === $qIndex) ? " active" : ""; ?>"
                                                        data-figure-target="verify-figure-panel-<?php echo (int) $selectedOrder["order_id"]; ?>-<?php echo $tabIndex; ?>"
                                                        onclick="selectFigure(this)"
                                                    >
                                                        Figure <?php echo $tabIndex + 1; ?>
                                                    </button>

                                                <?php endforeach; ?>

                                            </div>

                                        <?php endif; ?>

                                        <div class="qv-details-head">
                                            Figure <?php echo $qIndex + 1; ?> Information
                                        </div>

                                        <div class="qv-figure-body">

                                            <!-- FIGURE SPECS - kaparehong "qv-detail-item-card"
                                                 na disenyo ng Quotation page. -->

                                            <div class="qv-detail-item-list">

                                                <div class="qv-detail-item-card">

                                                    <div class="qv-detail-item-body">
                                                        <span class="qv-detail-item-label">Figure Style</span>
                                                        <span class="qv-detail-item-value"><?php echo e($fig["figure_style"]); ?></span>
                                                    </div>

                                                </div>

                                                <div class="qv-detail-item-card">

                                                    <div class="qv-detail-item-body">
                                                        <span class="qv-detail-item-label">Product Type</span>
                                                        <span class="qv-detail-item-value"><?php echo e($fig["product_type"]); ?></span>
                                                    </div>

                                                    <div class="qv-detail-item-price<?php echo $hasSize ? " qv-detail-item-price-muted" : ""; ?>">
                                                        <?php echo $hasSize ? "" : "₱" . number_format($fig["product_price"], 2); ?>
                                                    </div>

                                                </div>

                                                <div class="qv-detail-item-card">


                                                    <div class="qv-detail-item-body">
                                                        <span class="qv-detail-item-label">Size</span>
                                                        <span class="qv-detail-item-value">
                                                            <?php echo $hasSize ? e($fig["size_label"]) : "No size required"; ?>
                                                        </span>
                                                    </div>

                                                    <div class="qv-detail-item-price<?php echo $hasSize ? "" : " qv-detail-item-price-muted"; ?>">
                                                        <?php echo $hasSize ? "₱" . number_format($fig["product_price"], 2) : ""; ?>
                                                    </div>

                                                </div>

                                                <?php if (figurify_dressup_for_figure($fig) === null): /* walang Figure Name sa Dress Up */ ?>
                                                <div class="qv-detail-item-card">


                                                    <div class="qv-detail-item-body">
                                                        <span class="qv-detail-item-label">Figure Name</span>
                                                        <span class="qv-detail-item-value"><?php require_once __DIR__ . "/../helpers/booking_helper.php"; echo e(figurify_figure_name_display($fig)); ?></span>
                                                    </div>

                                                    <div class="qv-detail-item-price<?php echo ((float) $fig["name_fee"] > 0) ? "" : " qv-detail-item-price-muted"; ?>">
                                                        <?php echo ((float) $fig["name_fee"] > 0) ? "₱" . number_format($fig["name_fee"], 2) : ""; ?>
                                                    </div>

                                                </div>
                                                <?php endif; ?>

                                            <?php /* Quantity: ilang piraso ng figure na ito (isang figure lang kahit may qty 2 pataas) */ ?>
                                            <div class="qv-detail-item-card">

                                                <div class="qv-detail-item-body">
                                                    <span class="qv-detail-item-label">Quantity</span>
                                                    <span class="qv-detail-item-value"><?php echo figurify_figure_quantity($fig); ?> <?php echo figurify_figure_quantity($fig) === 1 ? "piece" : "pieces"; ?></span>
                                                </div>

                                                <div class="qv-detail-item-price qv-detail-item-price-muted"></div>

                                            </div>

                                        <?php if ($qvFigRushFee > 0): ?>
                                                <div class="qv-detail-item-card qv-rush-row">
                                                    <div class="qv-detail-item-body">
                                                        <span class="qv-detail-item-label">Rush Fee</span>
                                                        <span class="qv-detail-item-value">Rush Order</span>
                                                    </div>
                                                    <div class="qv-detail-item-price">₱<?php echo number_format($qvFigRushFee, 2); ?></div>
                                                </div>
                                                <?php endif; ?>

                                            </div>
                                            <!-- /.qv-detail-item-list -->

                                            <!-- REFERENCE PHOTOS -->

                                            <?php if (figurify_dressup_for_figure($fig) !== null): ?>
                                                <?php figurify_render_dressup_design($fig, "staff"); ?>
                                            <?php else: ?>
                                            <div class="qv-reference-row">

                                                <div class="qv-reference-label">
                                                    <?php
                                                    $imgCount = count($fig["images"]);
                                                    echo $imgCount > 0
                                                        ? $imgCount . " Reference " . ($imgCount === 1 ? "Photo" : "Photos")
                                                        : "No Reference Photos Uploaded";
                                                    ?>
                                                </div>

                                                <?php if (!empty($fig["images"])): ?>

                                                    <div class="qv-reference-thumbs">

                                                        <?php foreach ($fig["images"] as $imgIndex => $imgPath): ?>

                                                            <?php $fullImg = $siteBase . $imgPath; ?>

                                                            <button
                                                                type="button"
                                                                class="qv-ref-thumb"
                                                                onclick="openImageLightbox('<?php echo e($fullImg); ?>', '<?php echo e($fig["figure_name"]); ?>')"
                                                            >
                                                                <img
                                                                    src="<?php echo e($fullImg); ?>"
                                                                    alt="<?php echo e($fig["figure_name"]); ?> photo <?php echo $imgIndex + 1; ?>"
                                                                >
                                                            </button>

                                                        <?php endforeach; ?>

                                                    </div>

                                                <?php endif; ?>

                                            </div>
                                            <?php endif; ?>

                                        </div>
                                        <!-- /.qv-figure-body -->

                                        <?php if (!empty($fig["notes"]) && figurify_dressup_for_figure($fig) === null): ?>

                                            <div class="qv-notes-row">
                                                <div class="qv-spec-label">Customer Notes</div>
                                                <div class="qv-note-box"><?php echo nl2br(e($fig["notes"])); ?></div>
                                            </div>

                                        <?php endif; ?>

                                    <?php if (!$qvFigHasBox) { echo $qvTotalLine; } ?>

                                </div>
                                    <!-- /.qv-figure-card -->

                                    </div>
                                    <!-- /.qv-viewer-column (figure details) -->


                                    <!-- RIGHT: BOX & ADD-ONS -->

                                    <?php if ($qvFigHasBox): /* Box & Add-ons: lalabas lang kapag may box na inorder */ ?>
                                    <div class="qv-viewer-column">

                                    <?php if ($fig["box_addon_type"] === "funko_box"): ?>

                                        <div class="qv-box-card">

                                            <div class="qv-box-info-head">Box &amp; Add-ons</div>

                                            <div class="qv-box-spec-grid">

                                                <div>
                                                    <div class="qv-spec-label">Box Type</div>
                                                    <div class="qv-spec-value">
                                                        <?php echo $fig["funko_box_type"] === "solo" ? "Solo Box" : "Couple Box"; ?>
                                                    </div>
                                                    <div class="qv-spec-price">₱<?php echo number_format($fig["box_addon_price"], 2); ?></div>
                                                </div>

                                                <div>
                                                    <div class="qv-spec-label">Name on Box</div>
                                                    <div class="qv-spec-value"><?php echo e($fig["funko_box_name"]); ?></div>
                                                </div>

                                                <div>
                                                    <div class="qv-spec-label">Box Number</div>
                                                    <div class="qv-spec-value"><?php echo e($fig["funko_box_number"]); ?></div>
                                                </div>

                                                <div>
                                                    <div class="qv-spec-label">Box Color</div>
                                                    <div class="qv-spec-value"><?php echo e($fig["funko_box_color"]); ?></div>
                                                </div>

                                            </div>

                                        </div>

                                    <?php elseif ($fig["box_addon_type"] === "hirono_blind_box"): ?>

                                        <div class="qv-box-card">

                                            <div class="qv-box-info-head">Box &amp; Add-ons</div>

                                            <div class="qv-box-spec-grid">

                                                <div>
                                                    <div class="qv-spec-label">Box Type</div>
                                                    <div class="qv-spec-value">
                                                        <?php echo $fig["hirono_blind_type"] === "regular" ? "Regular Blind Box" : "Blind Box Set"; ?>
                                                    </div>
                                                    <div class="qv-spec-price">₱<?php echo number_format(hironoBaseBoxPrice($fig), 2); ?></div>
                                                </div>

                                                <?php if (!empty($fig["hirono_box_design"])): ?>
                                                    <div>
                                                        <div class="qv-spec-label">Box Design</div>
                                                        <div class="qv-spec-value"><?php echo e(hironoBoxDesignLabel($fig["hirono_box_design"])); ?></div>
                                                    </div>
                                                <?php endif; ?>

                                                <div>
                                                    <div class="qv-spec-label">Box Color</div>
                                                    <div class="qv-spec-value"><?php echo e($fig["hirono_box_color"]); ?></div>
                                                </div>

                                                <div>
                                                    <div class="qv-spec-label">Nickname</div>
                                                    <div class="qv-spec-value"><?php echo e($fig["hirono_nickname"]); ?></div>
                                                </div>

                                                <div>
                                                    <div class="qv-spec-label">Date</div>
                                                    <div class="qv-spec-value">
                                                        <?php
                                                        echo !empty($fig["hirono_date"])
                                                            ? date("F j", strtotime($fig["hirono_date"]))
                                                            : e($fig["hirono_date"]);
                                                        ?>
                                                    </div>
                                                </div>

                                                <?php foreach ($fig["blind_items_list"] as $extraIndex => $extra): ?>
                                                    <div<?php echo ($extraIndex === 0) ? ' class="qv-row-start"' : ''; ?>>
                                                        <div class="qv-spec-label">Optional Extra</div>
                                                        <div class="qv-spec-value"><?php echo e($extra["label"]); ?></div>
                                                        <div class="qv-spec-price">₱<?php echo number_format($extra["price"], 2); ?></div>
                                                    </div>
                                                <?php endforeach; ?>

                                                <div class="qv-spec-full">
                                                    <div class="qv-spec-label">Letter</div>
                                                    <div class="qv-spec-value qv-letter-value"><?php echo nl2br(e($fig["hirono_letter"])); ?></div>
                                                </div>

                                            </div>

                                            <?php if (!empty($fig["box_images"])): ?>

                                                <div class="qv-reference-row">

                                                    <div class="qv-reference-label">Box Reference Images</div>

                                                    <div class="qv-reference-thumbs">

                                                        <?php foreach ($fig["box_images"] as $boxImgIndex => $boxImgPath): ?>

                                                            <?php $fullBoxImg = $siteBase . $boxImgPath; ?>

                                                            <button
                                                                type="button"
                                                                class="qv-ref-thumb"
                                                                onclick="openImageLightbox('<?php echo e($fullBoxImg); ?>', 'Box Reference Image')"
                                                            >
                                                                <img
                                                                    src="<?php echo e($fullBoxImg); ?>"
                                                                    alt="Box reference photo <?php echo $boxImgIndex + 1; ?>"
                                                                >
                                                            </button>

                                                        <?php endforeach; ?>

                                                    </div>

                                                </div>

                                            <?php endif; ?>

                                        </div>

                                    <?php endif; ?>

                                    <?php echo $qvTotalLine; ?>

                                </div>
                                    <!-- /.qv-viewer-column (box & add-ons) -->
                                    <?php endif; ?>


                                        <?php if (figurify_dressup_for_figure($fig) !== null): ?>
                                            <!-- DRESS UP: 3D figure, katabi ng Figure Details -->
                                            <div class="qv-viewer-column dressup-3d-column">
                                                <?php figurify_render_dressup_viewer($fig); ?>
                                            </div>
                                        <?php endif; ?>

                                    </div>
                                    <!-- /.qv-viewer-grid -->

                                </div>
                                <!-- /.qv-figure-panel -->

                                <?php
                                /* no error, print the buffered HTML */
                                echo ob_get_clean();

                                } catch (\Throwable $figureRenderError) {

                                    /* error na, drop the half-built HTML */
                                    if (ob_get_level() > 0) {
                                        ob_end_clean();
                                    }
                                    ?>

                                    <div
                                        class="qv-figure-panel<?php echo ($qIndex === 0) ? " active" : ""; ?>"
                                        id="verify-figure-panel-<?php echo (int) $selectedOrder["order_id"]; ?>-<?php echo $qIndex; ?>"
                                    >
                                        <div class="staff-payment-details">
                                            <div class="staff-payment-details-title">
                                                This figure could not be shown (#<?php echo (int) ($fig["figure_id"] ?? 0); ?>)
                                            </div>
                                            <div class="staff-payment-details-grid">
                                                <span>
                                                    <?php echo e($figureRenderError->getMessage()); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <?php
                                }
                                ?>

                            <?php endforeach; ?>

                        </div>
                        <!-- /.qv-figure-panels -->


                    <!-- PAYMENT & SHIPPING DETAILS + REFUND/RETURN DETAILS
                         Nasa ilalim na ito ng Figure Details (hindi na naka-toggle
                         sa likod ng "Payment" button) - laging nakikita na. -->

                        <!-- PAYMENT & SHIPPING DETAILS -->

                        <?php /* walang ipapakita kapag wala pang naisumiteng bayad/shipping (hal. Pending pa) */ ?>
                        <?php if (!empty($selectedOrder["payment_reference"]) || !empty($selectedOrder["payment_proof"]) || !empty($selectedOrder["shipping_name"])): ?>
                        <div class="staff-payment-details">

                            <div class="staff-payment-details-title">
                                Payment &amp; Shipping Information
                            </div>

                            <div class="staff-payment-details-grid">

                                <span>
                                    <strong>Ship To:</strong>
                                    <?php echo e($selectedOrder["shipping_name"]); ?>
                                </span>

                                <span>
                                    <strong>Address:</strong>
                                    <?php echo e($selectedOrder["shipping_address"]); ?>
                                </span>

                                <?php require_once __DIR__ . "/../helpers/content_helper.php"; ?>
                                <?php figurify_render_pin_row((int) ($selectedOrder["order_id"] ?? 0)); ?>

                                <span>
                                    <strong>Contact:</strong>
                                    <?php echo e($selectedOrder["shipping_contact"]); ?>
                                </span>

                                <span>
                                    <strong>Courier:</strong>
                                    <?php echo e(strtoupper((string) ($selectedOrder["courier"] ?? ""))); ?>
                                </span>

                                <span>
                                    <strong>Reference No.:</strong>
                                    <?php echo e($selectedOrder["payment_reference"]); ?>
                                </span>

                                <?php if (!empty($selectedOrder["paid_at"])): ?>
                                    <span>
                                        <strong>Submitted:</strong>
                                        <?php echo date("F j, Y, g:i A", strtotime($selectedOrder["paid_at"])); ?>
                                    </span>
                                <?php endif; ?>

                            </div>

                            <?php require_once __DIR__ . "/../helpers/content_helper.php"; ?>
                            <?php figurify_render_payment_proofs((int) ($selectedOrder["order_id"] ?? 0), "staff"); ?>

                        </div>
                        <?php endif; ?>


                        <!-- REFUND / RETURN DETAILS -->

                        <div class="staff-payment-details">

                            <div class="staff-payment-details-title">
                                Refund Account Information
                            </div>

                            <div class="staff-payment-details-grid">

                                <span>
                                    <strong>Account Name:</strong>
                                    <?php echo e($selectedOrder["refund_account_name"]); ?>
                                </span>

                                <span>
                                    <strong>Account Number:</strong>
                                    <?php echo e($selectedOrder["refund_account_number"]); ?>
                                </span>

                                <span>
                                    <strong>Preferred Bank/Method:</strong>
                                    <?php echo e($selectedOrder["refund_method"]); ?>
                                </span>

                            </div>

                        </div>


                <?php endif; ?>

            </div>


            <!-- RIGHT: ACTIONS / SUMMARY - action buttons at Progress Updates history -->

            <?php if ($useVerificationLayout): ?>

                <div class="qv-summary">

                    <div class="qv-summary-head">
                        <h2>Order Actions</h2>
                    </div>

                    <div class="qv-summary-body">

                        <?php if (!$selectedOrder): ?>

                            <div class="qv-empty">
                                Select an order to see its actions.
                            </div>

                        <?php else: ?>

                            <?php if ($wfState === "READY_TO_SHIP" && !empty($wfShipChecks)): ?>
                                <ul class="ship-checklist">
                                    <?php foreach ($wfShipChecks as $wfCheckLabel => $wfCheckOk): ?>
                                        <li class="<?php echo $wfCheckOk ? "ship-check-ok" : "ship-check-bad"; ?>">
                                            <?php echo $wfCheckOk ? "✓" : "✗"; ?> <?php echo e($wfCheckLabel); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>

                            <!-- REFUND (owner lang) - para sa bayad na order na hindi na itutuloy; mapupunta sa Refunds bilang "For Refund" -->

                            <?php
                            $rfSelStatus = figurify_status_key($selectedOrder["status"] ?? "");
                            $rfOverdue   = in_array($rfSelStatus, ["processing", "to_ship"], true)
                                && !empty($selectedOrder["ship_out_date"])
                                && strtotime($selectedOrder["ship_out_date"]) < strtotime(date("Y-m-d"));
                            ?>

                            <?php if ($rfOverdue): ?>
                                <div class="refund-overdue-note">
                                    Overdue: the ship out date (<?php echo date("M j, Y", strtotime($selectedOrder["ship_out_date"])); ?>)
                                    has already passed. If the customer no longer wants to continue, you can process a refund.
                                </div>
                            <?php endif; ?>


                            <div class="decline-modal-overlay" id="refundModal_start">

                                <div class="decline-modal-box">


                                    <h3 class="decline-modal-title">Cancel Order &amp; Refund</h3>

                                    <p class="decline-modal-desc">
                                        Use this when the customer asked to stop the order or it can no longer be finished.
                                        The paid order will move to Refunds as For Refund and the customer will be notified. Send the refund to the
                                        customer's refund account, then upload the proof in Refunds.
                                    </p>

                                    <form
                                        method="POST"
                                        action="process_refund.php"
                                        class="decline-reason-form"
                                        onsubmit="return figurifyCheckDeclineForm(this);"
                                    >

                                        <input type="hidden" name="order_id" value="<?php echo (int) $selectedOrder["order_id"]; ?>">
                                        <input type="hidden" name="action" value="start">

                                        <select
                                            name="refund_reason"
                                            class="decline-reason-select"
                                            required
                                            onchange="figurifyToggleDeclineOther(this)"
                                        >
                                            <option value="" disabled selected>Select a reason…</option>
                                            <option value="not_completed_by_ship_out_date">Order Not Completed by the Ship Out Date</option>
                                            <option value="unable_to_fulfill">Unable to Fulfill the Order</option>
                                            <option value="customer_cancelled">Customer Cancelled the Order</option>
                                            <option value="other">Other (please specify)</option>
                                        </select>

                                        <textarea
                                            name="refund_reason_other"
                                            class="decline-reason-other"
                                            placeholder="Type the reason…"
                                            maxlength="190"
                                            hidden
                                        ></textarea>

                                        <div class="decline-modal-actions">
                                            <button type="button" class="decline-modal-btn decline-modal-btn-cancel" onclick="figurifyCloseDeclineModal('refundModal_start')">Cancel</button>
                                            <button type="submit" class="decline-modal-btn decline-modal-btn-send">Cancel &amp; Refund</button>
                                        </div>

                                    </form>

                                </div>

                            </div>


                            <!-- PROGRESS UPDATES SENT -->

                            <?php figurify_render_progress_history_block($progressUpdates, $progressError); ?>

                            <!-- action buttons nasa pinakababa -->
                            <!-- ACTION BUTTONS (nakatayo pababa, hindi pahalang) -->

                            <div
                                class="verify-actions ab-bottom-actions ab-actions-row"
                            >

                                <?php if ($canShipOut): ?>

                                    <!-- iisang Ship Out lang sa buong system: nasa Shipments (tracking no., shipping fee, resibo) -->
                                    <a
                                        href="shipments.php?tab=to_ship&amp;order_id=<?php echo (int) $selectedOrderId; ?>"
                                        class="verify-btn verify-btn-approve ab-btn-primary"
                                    >
                                        Ship Out in Shipments
                                    </a>

                                <?php elseif (!empty($selectedOrder["is_shipped"])): ?>

                                    <span
                                        class="verify-btn ab-shipped-badge"
                                    >
                                        Shipped
                                        <?php if (!empty($selectedOrder["tracking_number"])): ?>
                                            (<?php echo e($selectedOrder["tracking_number"]); ?>)
                                        <?php endif; ?>
                                    </span>

                                    <form
                                        method="POST"
                                        action="update_active_order.php"
                                        onsubmit="return csConfirmSubmit(this, 'Mark as complete?\nThis order will move to Completed.', {okText: 'Complete'});"
                                       
                                    >

                                        <input type="hidden" name="order_id" value="<?php echo (int) $selectedOrder["order_id"]; ?>">
                                        <input type="hidden" name="action" value="complete_order">

                                        <button type="submit" class="verify-btn verify-btn-approve ab-btn-primary">
                                            Complete
                                        </button>

                                    </form>

                                <?php endif; ?>

                                <?php if ($canSendUpdate): ?>

                                    <button
                                        type="button"
                                        class="verify-btn verify-btn-approve"
                                        onclick="document.getElementById('progressModalOverlay').classList.add('show')"
                                    >
                                        <?php echo $sendingRevisedPhoto ? "Send Revised Photo" : "Send Progress Update"; ?>
                                    </button>

                                <?php elseif ($awaitingCustomerResponse): ?>

                                    <!-- paalala habang hindi pa sumasagot ang customer sa update -->
                                    <span class="progress-status-badge progress-status-pending">
                                        Waiting for the customer's response
                                    </span>

                                <?php endif; ?>

                                <?php if ($canMarkToShip): ?>

                                    <!-- Bago i-mark na "to ship", kailangan munang hingin
                                         ang natitirang balance sa customer - dito lumalabas
                                         ang popup para ilagay ang halaga (tingnan ang
                                         #requestBalanceOverlay sa ibaba). -->
                                    <button
                                        type="button"
                                        class="verify-btn verify-btn-approve ab-btn-primary"
                                       
                                        onclick="document.getElementById('requestBalanceOverlay').classList.add('show')"
                                    >
                                        <?php echo $wfState === "READY_TO_SHIP" ? "Request Balance" : "Finalize Order"; ?>
                                    </button>

                                <?php endif; ?>

                                <?php if ($awaitingRevisionDecision && $latestRevisionUpdateId > 0): ?>

                                    <div class="revision-decision-actions">

                                        <form
                                            method="POST"
                                            action="accept_revision.php"
                                            onsubmit="return csConfirmSubmit(this, 'Accept this revision?\nThe customer will be notified.', {okText: 'Accept'});"
                                        >

                                            <input type="hidden" name="order_id" value="<?php echo (int) $selectedOrder["order_id"]; ?>">
                                            <input type="hidden" name="update_id" value="<?php echo (int) $latestRevisionUpdateId; ?>">

                                            <button type="submit" class="verify-btn verify-btn-approve">
                                                Accept Revision
                                            </button>

                                        </form>

                                        <button
                                            type="button"
                                            class="verify-btn verify-btn-reject"
                                            onclick="figurifyOpenDeclineModal('declineModal_revision')"
                                        >
                                            Decline Revision
                                        </button>

                                    </div>

                                <?php endif; ?>

                                <?php /* Process Refund - wala sa revision part (Accept / Decline Revision lang doon) */ ?>
                                <?php if (!($awaitingRevisionDecision && $latestRevisionUpdateId > 0)): ?>
                                    <button
                                        type="button"
                                        class="verify-btn verify-btn-reject"
                                        onclick="figurifyOpenDeclineModal('refundModal_start')"
                                    >
                                        Cancel &amp; Refund
                                    </button>
                                <?php endif; ?>


                                <!-- DOWNLOAD ORDER PDF - approved na at nasa Active Booking na ang order -->
                                <button
                                    type="button"
                                    class="verify-btn ab-btn-download"
                                    onclick="figurifyDownloadOrderSummary(<?php echo (int) $selectedOrder["order_id"]; ?>, this)"
                                >
                                    Download Order PDF
                                </button>

                            </div>
                            <!-- /.verify-actions -->
                            <script src="../Shared/order-summary-pdf.js"></script>

                        <?php endif; ?>

                    </div>
                    <!-- /.qv-summary-body -->

                </div>
                <!-- /.qv-summary -->

            <?php endif; ?>


            <?php if ($useVerificationLayout): ?>
                </div>
                <!-- /.qv-right-col -->
            <?php endif; ?>


        </div>

    </section>


</main>

</div>


<!-- IMAGE LIGHTBOX (click any figure photo to enlarge) -->

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


<script>

// ---- DECLINE MODAL, FILTER DROPDOWN, LIVE TOTAL, FIGURE TABS, LIGHTBOX, ORDER SEARCH, SCROLL MEMORY ----

/* Decline modal (pareho ng Quotation at Verification) */

function figurifyCheckDeclineForm(form) {
    var select = form.querySelector(".decline-reason-select");
    if (select && select.value === "other") {
        var other = form.querySelector(".decline-reason-other");
        if (other && other.value.trim() === "") {
            if (window.csToast) { csToast("Type the reason first.", "error"); } else { alert("Type the reason first."); }
            other.focus();
            return false;
        }
    }
    return true;
}

// buksan ang decline pop-up (Decline button)
function figurifyOpenDeclineModal(modalId) {
    var overlay = document.getElementById(modalId);
    if (overlay) {
        overlay.classList.add("active");
    }
}

// isara ang decline pop-up (Cancel button, walang isu-submit)
function figurifyCloseDeclineModal(modalId) {
    var overlay = document.getElementById(modalId);
    if (overlay) {
        overlay.classList.remove("active");
    }
}

// ipakita/itago ang textbox pag pinili/tinanggal ang "Other" sa dropdown
function figurifyToggleDeclineOther(select) {
    var textarea = select.closest("form").querySelector(".decline-reason-other");
    if (!textarea) {
        return;
    }
    if (select.value === "other") {
        textarea.hidden = false;
        textarea.required = true;
    } else {
        textarea.hidden = true;
        textarea.required = false;
        textarea.value = "";
    }
}


/* FILTER DROPDOWN - nagsasara pag nag-click sa labas o Escape. */

function figurifyToggleFilterDropdown(id) {

    var dropdown = document.getElementById(id);

    if (!dropdown) {
        return;
    }

    var isOpen = dropdown.classList.contains("open");

    document.querySelectorAll(".qv-filter-dropdown.open").forEach(function (el) {
        el.classList.remove("open");
    });

    if (!isOpen) {
        dropdown.classList.add("open");
    }

}

document.addEventListener("click", function (event) {

    document.querySelectorAll(".qv-filter-dropdown.open").forEach(function (dropdown) {
        if (!dropdown.contains(event.target)) {
            dropdown.classList.remove("open");
        }
    });

});


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


/* Escape key: isara ang filter dropdown at ang image lightbox */

document.addEventListener("keydown", function (event) {

    if (event.key === "Escape") {
        document.querySelectorAll(".qv-filter-dropdown.open").forEach(function (dropdown) {
            dropdown.classList.remove("open");
        });
        closeImageLightbox();
    }

});


/* SWITCH WHICH FIGURE'S DETAILS ARE SHOWN (kapag may 2+ figure) - same pattern ng My Orders. */

function selectFigure(button) {

    var panelsWrap = button.closest(".qv-figure-panels");

    if (!panelsWrap) {
        return;
    }

    var targetId = button.dataset.figureTarget;

    /* each figure panel has its own tab copies, sync "active" across all of them */
    panelsWrap
        .querySelectorAll(".qv-figure-tab-pill")
        .forEach(function (tab) {
            tab.classList.toggle("active", tab.dataset.figureTarget === targetId);
        });

    panelsWrap
        .querySelectorAll(".qv-figure-panel")
        .forEach(function (panel) {
            panel.classList.toggle("active", panel.id === targetId);
        });

}


/* LEFT PANEL - quick client-side search, same pattern ng Quotation page. */

/* Filter (Order Type) + search sa left panel, parehong sa browser lang */

var qvTypeFilter = "all";

function qvSetTypeFilter(option, type) {

    qvTypeFilter = type;

    document.querySelectorAll("#qvTypeFilterMenu .qv-filter-option").forEach(function (el) {
        el.classList.toggle("active", el === option);
    });

    var label = document.getElementById("qvTypeFilterLabel");

    if (label) {
        label.textContent = option.textContent.trim();
    }

    var dropdown = document.getElementById("qvTypeFilterDropdown");

    if (dropdown) {
        dropdown.classList.remove("open");
    }

    var input = document.getElementById("qvSearchInput");

    qvFilterOrders(input ? input.value : "");

}

function qvFilterOrders(query) {

    var q = query.trim().toLowerCase();
    var cards = document.querySelectorAll("#qvOrderList .qv-row");
    var visibleCount = 0;

    cards.forEach(function (card) {

        var matchesText = card.dataset.search.indexOf(q) !== -1;
        var matchesType = (qvTypeFilter === "all") || (card.dataset.type === qvTypeFilter);
        var matches = matchesText && matchesType;

        card.classList.toggle("qv-hidden", !matches);

        if (matches) {
            visibleCount++;
        }

    });

    var footer = document.getElementById("qvListFooter");

    if (footer) {
        footer.textContent = "Showing " + visibleCount + " of " + cards.length + " orders";
    }

}


/* KEEP SCROLL POSITION ACROSS ORDER CLICKS - parehong
   pattern ng Quotation/Verification pages. */

(function () {

    var panels = [
        { el: document.getElementById("qvOrderList"), key: "qvOrderListScroll" },
        { el: document.querySelector(".qv-right-col"), key: "qvDetailScroll" }
    ];

    panels.forEach(function (panel) {

        if (!panel.el) {
            return;
        }

        var savedScroll = sessionStorage.getItem(panel.key);

        if (savedScroll !== null) {
            panel.el.scrollTop = parseInt(savedScroll, 10) || 0;
        }

    });

    document.querySelectorAll("#qvOrderList .qv-row").forEach(function (card) {
        card.addEventListener("click", function () {

            panels.forEach(function (panel) {

                if (panel.el) {
                    sessionStorage.setItem(panel.key, panel.el.scrollTop);
                }

            });

        });
    });

})();


/* avoid double-submit - these actions email the customer too, so lock the button on click */

document.querySelectorAll("form").forEach(function (actionForm) {

    actionForm.addEventListener("submit", function (event) {

        /* cancelled na ng ibang handler (e.g. confirm sa Complete/To Ship), skip lock */
        if (event.defaultPrevented) {
            return;
        }

        if (actionForm.dataset.figurifySubmitting === "1") {
            event.preventDefault();
            return;
        }

        actionForm.dataset.figurifySubmitting = "1";

        var submitBtn = actionForm.querySelector('button[type="submit"]');

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = "Please wait…";
        }

    });

});

// ---- PROGRESS UPDATE MODAL (photo preview / close) ----

function figurifyPreviewProgressPhoto(input) {
    var file = input.files && input.files[0];
    var empty = document.getElementById("puPhotoEmpty");
    var preview = document.getElementById("puPhotoPreview");
    var img = document.getElementById("puPhotoImg");
    if (!file) { figurifyClearProgressPhoto(); return; }
    if (!/^image\//.test(file.type)) {
        if (window.csToast) { csToast("Please choose an image file.", "error"); }
        figurifyClearProgressPhoto();
        return;
    }
    var reader = new FileReader();
    reader.onload = function (e) {
        img.src = e.target.result;
        empty.hidden = true;
        preview.hidden = false;
    };
    reader.readAsDataURL(file);
}
function figurifyClearProgressPhoto() {
    var input = document.getElementById("progress_image");
    if (input) { input.value = ""; }
    document.getElementById("puPhotoImg").src = "";
    document.getElementById("puPhotoPreview").hidden = true;
    document.getElementById("puPhotoEmpty").hidden = false;
}
function figurifyCloseProgressModal() {
    document.getElementById("progressModalOverlay").classList.remove("show");
    figurifyClearProgressPhoto();
    var msg = document.getElementById("staff_message");
    if (msg) { msg.value = ""; }
}

// ==============================================================
// [FILTER-STATE.JS]  (dating Shared/filter-state.js - nasa page na ito na)
// ANO ITO: PUTI KAPAG 'ALL' ang napili sa filter dropdown, may kulay kapag iba.
// PAANO GUMAGANA: Naglalagay/nag-aalis lang ng class na .is-filtered. Walang binabago sa mismong filtering.
// ==============================================================

/* Filter dropdown: puti kapag "All" (unang option) ang napili, may kulay kapag iba.
   Walang binabago sa filtering mismo - class lang (.is-filtered) ang nilalagay. */
(function () {
    "use strict";

    function update(dd) {
        var opts = Array.prototype.slice.call(dd.querySelectorAll(".qv-filter-option"));
        if (!opts.length) { return; }
        var active = null;
        opts.forEach(function (o) { if (o.classList.contains("active")) { active = o; } });
        var filtered = !!active && opts.indexOf(active) > 0;
        dd.classList.toggle("is-filtered", filtered);
    }

    function init() {
        document.querySelectorAll(".qv-filter-dropdown").forEach(function (dd) {
            update(dd);
            new MutationObserver(function () { update(dd); })
                .observe(dd, { subtree: true, attributes: true, attributeFilter: ["class"] });
            dd.addEventListener("click", function () { setTimeout(function () { update(dd); }, 0); });
        });
    }

    if (document.readyState === "loading") { document.addEventListener("DOMContentLoaded", init); }
    else { init(); }
})();
</script>


</body>
</html>