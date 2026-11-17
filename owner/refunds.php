<?php

require_once "owner-header.php";

$figurifyRole    = "owner";
$figurifyPageDir = __DIR__;

$documentRoot = rtrim(str_replace("\\", "/", realpath($_SERVER["DOCUMENT_ROOT"])), "/");
$projectRoot  = rtrim(str_replace("\\", "/", realpath(__DIR__ . "/..")), "/");
$siteBase     = substr($projectRoot, strlen($documentRoot)) . "/";

/* Shared logic + markup para sa owner/cancelled.php at
   staff/cancelled.php. Kailangan i-set ng entry file bago
   dito mag-require: $figurifyRole ("owner" o "staff") at
   $figurifyPageDir (__DIR__ ng entry file). */

/* REFUNDS (OWNER LANG) - mga order na may bayad na pero hindi
   natuloy: na-decline sa verification (hindi "No Down Payment
   Received"), o sinimulan ng owner ang refund mula sa Active Booking.
   Tab "For Refund": hinihintay na ipadala ang refund.
   Tab "Refunded": naipadala na (may proof at reference no.). */

$refundTab = (isset($_GET["tab"]) && $_GET["tab"] === "refunded") ? "refunded" : "for_refund";

$cancelledOrders = [];

/* JOIN sa users para makuha customer name/email para sa Customer
   column ng qv-table. */
try {
    $result = $conn->query("
        SELECT
            o.order_id,
            o.order_type,
            o.order_method,
            o.ship_out_date,
            o.status,
            o.total_amount,
            o.payment_option,
            o.shipping_name,
            o.shipping_address,
            o.shipping_contact,
            o.courier,
            o.payment_reference,
            o.payment_proof,
            o.paid_at,
            o.refund_account_name,
            o.refund_account_number,
            o.refund_method,
            o.balance_to_pay,
            o.balance_paid_at,
            o.balance_payment_reference,
            o.balance_proof,
            o.refund_reason,
            o.refund_type,
            o.refund_amount,
            o.refund_reference,
            o.refund_proof,
            o.refund_requested_at,
            o.refunded_at,
            o.created_at,
            u.full_name,
            u.email
        FROM orders o
        JOIN users u ON u.user_id = o.user_id
        WHERE LOWER(TRIM(o.status)) = '" . $conn->real_escape_string($refundTab) . "'
        ORDER BY COALESCE(o.refunded_at, o.refund_requested_at, o.created_at) DESC
    ");
} catch (\Throwable $e) {
    $result = false;
}

/* kapag hindi pa na-run ang Admin/refund_migration.sql, babagsak ang
   query sa itaas - ipapakita na lang ang paalala sa page */
$refundColumnsMissing = ($result === false);

/* bilang ng bawat tab (para sa tab pills) */
$refundTabCounts = ["for_refund" => 0, "refunded" => 0];
try {
    $countResult = $conn->query("SELECT LOWER(TRIM(status)) AS s, COUNT(*) AS c FROM orders WHERE LOWER(TRIM(status)) IN ('for_refund','refunded') GROUP BY LOWER(TRIM(status))");
} catch (\Throwable $e) {
    $countResult = false;
}
if ($countResult) {
    while ($countRow = $countResult->fetch_assoc()) {
        $refundTabCounts[$countRow["s"]] = (int) $countRow["c"];
    }
}

if ($result) {

    while ($row = $result->fetch_assoc()) {

        $cancelledOrders[] = $row;

    }

}

/* Parehong "select an order on the left, view details on the
   right" na pattern ito ng Quotation/Verification/Shipments
   pages. */
$selectedOrderId = isset($_GET["order_id"])
    ? (int) $_GET["order_id"]
    : 0;

$selectedCancelledOrder = null;

foreach ($cancelledOrders as $cancelledRow) {

    if ((int) $cancelledRow["order_id"] === $selectedOrderId) {
        $selectedCancelledOrder = $cancelledRow;
        break;
    }

}

$selectedOrder = &$selectedCancelledOrder;

/* Kukuha lang ng figures/images para sa SELECTED order (hindi lahat
   gaya sa Quotation/Active Booking) dahil doon lang naman ito
   ipinapakita - mas magaan sa database. */

if ($selectedOrder) {

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


    $figures = [];

    if ($fstmt !== false) {

        $fstmt->bind_param("i", $selectedOrder["order_id"]);
        $fstmt->execute();

        $fresult = $fstmt->get_result();

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

    }

    $selectedOrder["figures"] = $figures;

}

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

<title>Figurify | Refunds</title>

<!-- ===================== STYLE NG PAGE NA ITO: refunds.css ===================== -->
<link rel="stylesheet" href="refunds.css">

</head>


<body class="quotation-page">

<div class="app">

<?php require_once __DIR__ . "/../Shared/sidebar.php"; ?>


<main class="main">


<header class="topbar">

    <div class="welcome">

        <h2 class="portal-title">
            Refunds
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


<section class="card qv-workspace">

    <div class="qv-shell">

        <!-- LEFT: CANCELLED/DECLINED LIST -->

        <div class="qv-list-panel">

            <div class="qv-toolbar">

                <?php
                $rfFilterLabels = [
                    "for_refund" => "For Refund (" . (int) $refundTabCounts["for_refund"] . ")",
                    "refunded"   => "Refunded (" . (int) $refundTabCounts["refunded"] . ")",
                ];
                ?>

                <details class="rf-filter" id="qvRefundFilterDropdown">

                    <summary class="rf-filter-trigger">
                        <?php echo e($rfFilterLabels[$refundTab]); ?>
                        <svg class="rf-filter-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>
                    </summary>

                    <div class="rf-filter-menu">
                        <?php foreach ($rfFilterLabels as $rfKey => $rfLabel): ?>
                            <a
                                href="refunds.php?tab=<?php echo $rfKey; ?>"
                                class="rf-filter-option<?php echo ($refundTab === $rfKey) ? " active" : ""; ?>"
                            >
                                <?php echo e($rfLabel); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>

                </details>

                <div class="qv-search">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input
                        type="text"
                        id="qvSearchInput"
                        placeholder="Search order ID, name..."
                        oninput="qvFilterCancelled(this.value)"
                    >
                </div>

            </div>


            <?php if (!empty($cancelledOrders)): ?>

                <div class="qv-list" id="qvCancelledList">

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

                            <?php foreach ($cancelledOrders as $order): ?>

                                <?php
                                $isActive     = ((int) $order["order_id"] === $selectedOrderId);
                                $qvSearchText = strtolower($order["order_id"] . " " . ($order["full_name"] ?? "") . " " . ($order["email"] ?? ""));
                                $qvRowHref    = "refunds.php?tab=" . $refundTab . "&order_id=" . (int) $order["order_id"];
                                ?>

                                <tr
                                    class="qv-row<?php echo $isActive ? " active" : ""; ?>"
                                    data-href="<?php echo e($qvRowHref); ?>"
                                    data-search="<?php echo e($qvSearchText); ?>"
                                    onclick="window.location.href = this.dataset.href;"
                                >

                                    <td class="qv-cell-id">
                                        #<?php echo (int) $order["order_id"]; ?>
                                    </td>

                                    <td>
                                        <?php echo e($order["email"] ?? "N/A"); ?>
                                    </td>

                                    <td>
                                        <?php echo ($order["order_type"] ?? "") === "rush" ? "Rush" : "Non-rush"; ?>
                                    </td>

                                    <td>
                                        <?php echo e(orderMethodLabel($order["order_method"] ?? "")); ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo !empty($order["ship_out_date"])
                                            ? date("M j", strtotime($order["ship_out_date"]))
                                            : "N/A";
                                        ?>
                                    </td>

                                    <td>
                                        <?php echo figurify_status_badge($order["status"] ?? "cancelled"); ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

                <div class="qv-list-footer" id="qvListFooter">
                    Showing <?php echo count($cancelledOrders); ?> of <?php echo count($cancelledOrders); ?> orders
                </div>

            <?php else: ?>

                <div class="qv-empty">
                    <?php if ($refundColumnsMissing): ?>
                        Refund columns are missing in the database. Please run <strong>Admin/refund_migration.sql</strong> in phpMyAdmin first.
                    <?php elseif ($refundTab === "refunded"): ?>
                        No refunded orders yet.
                    <?php else: ?>
                        No orders waiting for a refund.
                    <?php endif; ?>
                </div>

            <?php endif; ?>

        </div>


        <!-- RIGHT: ORDER DETAIL -->

        <?php if (!$selectedCancelledOrder): ?>

                        <div class="qv-right-col qv-right-col--blank">

                <div class="qv-detail-col">
                    <div class="qv-header-card">
                        <div class="qv-empty">
                            Select an order to view its details.
                        </div>
                    </div>
                </div>

                <div class="qv-summary">
                    <div class="qv-summary-head"><h2>Payment</h2></div>
                    <div class="qv-summary-body">
                        <div class="qv-empty">Nothing to show yet.</div>
                    </div>
                </div>

            </div>

        <?php else: ?>

            <div class="qv-right-col">

                <div class="qv-detail-col">

                    <?php if (empty($selectedCancelledOrder["figures"])): ?>

                        <div class="qv-header-card">
                            <div class="qv-empty">No figure details found for this order.</div>
                        </div>

                    <?php else: ?>

                    <div class="qv-figure-panels">

                        <?php foreach ($selectedCancelledOrder["figures"] as $qIndex => $fig): ?>

                            <?php
                            /* rush fee kada figure (₱200 Head Only, ₱500 iba) + breakdown bago ang Total Amount */
                            require_once __DIR__ . "/../helpers/booking_helper.php";
                            $qvOrderFigures = $selectedCancelledOrder["figures"] ?? [];
                            $qvRushSplit    = figurify_rush_split($selectedCancelledOrder, $qvOrderFigures);
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
                                . '<strong class="qv-total-line-value">₱' . number_format((float) ($selectedCancelledOrder["total_amount"] ?? 0), 2) . '</strong></div></div>';
                            ?>

                            <?php
                            try {
                            ob_start();

                            $hasSize = figureHasSize($fig);

                            $styleImg   = figureStyleImage($fig["figure_style"]);
                            $productImg = productTypeImage($fig["figure_style"], $fig["product_type"]);
                            // Image Submission: walang larawan ng style/product (magkatabing box na lang)
                            if (figurify_dressup_for_figure($fig) === null) {
                                $styleImg   = null;
                                $productImg = null;
                            }

                            $panelId = "cancelled-figure-panel-" . (int) $selectedCancelledOrder["order_id"] . "-" . $qIndex;
                            ?>

                            <div
                                class="qv-figure-panel<?php echo ($qIndex === 0) ? " active" : ""; ?>"
                                id="<?php echo $panelId; ?>"
                            >

                                <div class="qv-viewer-grid<?php echo figurify_dressup_for_figure($fig) !== null ? " dressup-grid" : ""; ?>">

                                <div class="qv-viewer-column">

                                <div class="qv-figure-card">

                                    <?php if (count($selectedCancelledOrder["figures"]) > 1): ?>

                                        <div class="qv-figure-tabs">

                                            <?php foreach ($selectedCancelledOrder["figures"] as $tabIndex => $tabFig): ?>

                                                <button
                                                    type="button"
                                                    class="qv-figure-tab-pill<?php echo ($tabIndex === $qIndex) ? " active" : ""; ?>"
                                                    data-figure-target="cancelled-figure-panel-<?php echo (int) $selectedCancelledOrder["order_id"]; ?>-<?php echo $tabIndex; ?>"
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

                                            <div class="qv-detail-item-list">

                                                <div class="qv-detail-item-card">

                                                    <?php if ($styleImg): ?>
                                                        <img
                                                            class="qv-detail-item-image"
                                                            src="<?php echo e($siteBase . "Image/" . $styleImg); ?>"
                                                            alt="<?php echo e($fig["figure_style"]); ?>"
                                                        >
                                                    <?php else: ?>
                                                    <?php endif; ?>

                                                    <div class="qv-detail-item-body">
                                                        <span class="qv-detail-item-label">Figure Style</span>
                                                        <span class="qv-detail-item-value"><?php echo e($fig["figure_style"]); ?></span>
                                                    </div>

                                                </div>

                                                <div class="qv-detail-item-card">

                                                    <?php if ($productImg): ?>
                                                        <img
                                                            class="qv-detail-item-image"
                                                            src="<?php echo e($siteBase . "Image/" . $productImg); ?>"
                                                            alt="<?php echo e($fig["product_type"]); ?>"
                                                        >
                                                    <?php else: ?>
                                                    <?php endif; ?>

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

                                        <?php
                                        $blindImg  = hironoBlindTypeImage($fig["hirono_blind_type"]);
                                        $designImg = hironoBoxDesignImage($fig["hirono_box_design"]);
                                        ?>

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
                            echo ob_get_clean();

                            } catch (\Throwable $figureRenderError) {

                                if (ob_get_level() > 0) {
                                    ob_end_clean();
                                }
                                ?>

                                <div
                                    class="qv-figure-panel<?php echo ($qIndex === 0) ? " active" : ""; ?>"
                                    id="cancelled-figure-panel-<?php echo (int) $selectedCancelledOrder["order_id"]; ?>-<?php echo $qIndex; ?>"
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

                    <?php endif; ?>

                </div>

                <div class="qv-summary">

                    <div class="qv-summary-head">
                        <h2>Payment &amp; Refund</h2>
                    </div>

                    <div class="qv-summary-body">

                        <?php /* walang ipapakita kapag wala pang naisumiteng bayad/shipping (hal. Pending pa) */ ?>
                        <?php if (!empty($selectedCancelledOrder["payment_reference"]) || !empty($selectedCancelledOrder["payment_proof"]) || !empty($selectedCancelledOrder["shipping_name"])): ?>
                        <div class="staff-payment-details">

                            <div class="staff-payment-details-title">
                                Payment &amp; Shipping Information
                            </div>

                            <div class="staff-payment-details-grid">

                                <span>
                                    <strong>Ship To:</strong>
                                    <?php echo e($selectedCancelledOrder["shipping_name"] ?? "N/A"); ?>
                                </span>

                                <span>
                                    <strong>Address:</strong>
                                    <?php echo e($selectedCancelledOrder["shipping_address"] ?? "N/A"); ?>
                                </span>

                                <?php require_once __DIR__ . "/../helpers/content_helper.php"; ?>
                                <?php figurify_render_pin_row((int) ($selectedCancelledOrder["order_id"] ?? 0)); ?>

                                <span>
                                    <strong>Contact:</strong>
                                    <?php echo e($selectedCancelledOrder["shipping_contact"] ?? "N/A"); ?>
                                </span>

                                <span>
                                    <strong>Courier:</strong>
                                    <?php echo e(strtoupper((string) ($selectedCancelledOrder["courier"] ?? ""))); ?>
                                </span>

                                <span>
                                    <strong>Reference No.:</strong>
                                    <?php echo e($selectedCancelledOrder["payment_reference"] ?? "N/A"); ?>
                                </span>

                                <?php if (!empty($selectedCancelledOrder["paid_at"])): ?>
                                    <span>
                                        <strong>Submitted:</strong>
                                        <?php echo date("F j, Y, g:i A", strtotime($selectedCancelledOrder["paid_at"])); ?>
                                    </span>
                                <?php endif; ?>

                            </div>

                            <?php require_once __DIR__ . "/../helpers/content_helper.php"; ?>
                            <?php figurify_render_payment_proofs((int) ($selectedCancelledOrder["order_id"] ?? 0), "staff"); ?>

                        </div>
                        <?php endif; ?>

                        <?php if (!empty($selectedCancelledOrder["refund_account_name"]) || !empty($selectedCancelledOrder["refund_account_number"]) || !empty($selectedCancelledOrder["refund_method"])): ?>

                            <div class="staff-payment-details">

                                <div class="staff-payment-details-title">
                                    Refund Account Information
                                </div>

                                <div class="staff-payment-details-grid">

                                    <span>
                                        <strong>Account Name:</strong>
                                        <?php echo e($selectedCancelledOrder["refund_account_name"] ?? "N/A"); ?>
                                    </span>

                                    <span>
                                        <strong>Account Number:</strong>
                                        <?php echo e($selectedCancelledOrder["refund_account_number"] ?? "N/A"); ?>
                                    </span>

                                    <span>
                                        <strong>Preferred Bank/Method:</strong>
                                        <?php echo e($selectedCancelledOrder["refund_method"] ?? "N/A"); ?>
                                    </span>

                                </div>

                            </div>

                        <?php endif; ?>

                        <?php
                        /* magkano na ang naibayad ng customer: unang bayad (50% o full)
                           + balance (kung nakapagbayad na) - helpers/order_helper.php */
                        $rfOrder       = $selectedCancelledOrder;
                        $rfAmountPaid  = figurify_wf_amount_paid($rfOrder);
                        $rfBalancePaid = !empty($rfOrder["balance_paid_at"]) ? (float) $rfOrder["balance_to_pay"] : 0;
                        $rfDownPayment = $rfAmountPaid - $rfBalancePaid;
                        $rfIsPending   = (strtolower(trim((string) $rfOrder["status"])) === "for_refund");
                        ?>

                        <?php if (!empty($rfOrder["balance_proof"])): ?>
                            <div class="staff-payment-details">
                                <div class="staff-payment-details-title">Balance Payment</div>
                                <div class="staff-payment-details-grid">
                                    <span><strong>Reference No.:</strong> <?php echo e($rfOrder["balance_payment_reference"] ?? "N/A"); ?></span>
                                    <?php if (!empty($rfOrder["balance_paid_at"])): ?>
                                        <span><strong>Submitted:</strong> <?php echo date("F j, Y, g:i A", strtotime($rfOrder["balance_paid_at"])); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="staff-payment-proof">
                                    <div class="staff-payment-proof-title">Proof of Balance Payment</div>
                                    <img
                                        src="<?php echo htmlspecialchars(figurify_proof_url($rfOrder["balance_proof"]), ENT_QUOTES, "UTF-8"); ?>"
                                        alt="Proof of balance payment for order <?php echo (int) $rfOrder["order_id"]; ?>"
                                        class="staff-payment-proof-thumb"
                                        onclick="openImageLightbox('<?php echo htmlspecialchars(figurify_proof_url($rfOrder["balance_proof"]), ENT_QUOTES, "UTF-8"); ?>', 'Proof of Balance Payment for Order #<?php echo (int) $rfOrder["order_id"]; ?>')"
                                    >
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="qv-line-list">
                            <div class="qv-line">
                                <span class="qv-line-label">Total Amount</span>
                                <span class="qv-line-value">₱<?php echo number_format((float) ($rfOrder["total_amount"] ?? 0), 2); ?></span>
                            </div>
                            <div class="qv-line">
                                <span class="qv-line-label"><?php echo e(figurify_wf_downpayment_label($rfOrder)); ?> Paid</span>
                                <span class="qv-line-value">₱<?php echo number_format($rfDownPayment, 2); ?></span>
                            </div>
                            <div class="qv-line">
                                <span class="qv-line-label">Balance Paid</span>
                                <span class="qv-line-value">₱<?php echo number_format($rfBalancePaid, 2); ?></span>
                            </div>
                            <div class="qv-line">
                                <span class="qv-line-label"><strong>Total Paid by Customer</strong></span>
                                <span class="qv-line-value"><strong>₱<?php echo number_format($rfAmountPaid, 2); ?></strong></span>
                            </div>
                        </div>

                        <!-- REFUND (OWNER LANG) -->

                        <div class="rf-box">

                            <div class="rf-title">Refund</div>

                            <div class="staff-payment-details-grid">
                                <span><strong>Reason:</strong> <?php echo e($rfOrder["refund_reason"] ?? "N/A"); ?></span>
                                <?php if (!empty($rfOrder["refund_requested_at"])): ?>
                                    <span><strong>Moved to For Refund:</strong> <?php echo date("F j, Y, g:i A", strtotime($rfOrder["refund_requested_at"])); ?></span>
                                <?php endif; ?>
                            </div>

                            <?php if ($rfIsPending): ?>

                                
                                <form method="POST" action="process_refund.php" enctype="multipart/form-data" class="rf-form"
                                      onsubmit="return csConfirmSubmit(this, 'Mark as refunded?\nThe customer will be notified.', {okText: 'Mark Refunded'});">

                                    <input type="hidden" name="order_id" value="<?php echo (int) $rfOrder["order_id"]; ?>">
                                    <input type="hidden" name="action" value="complete">

                                    <label>Refund Type</label>
                                    <div class="rf-radio-row">
                                        <label class="rf-radio">
                                            <input type="radio" name="refund_type" value="full" checked
                                                   onchange="document.getElementById('rfAmount').readOnly = true; document.getElementById('rfAmount').value = '<?php echo number_format($rfAmountPaid, 2, '.', ''); ?>';">
                                            Full Refund
                                        </label>
                                        <label class="rf-radio">
                                            <input type="radio" name="refund_type" value="partial"
                                                   onchange="document.getElementById('rfAmount').readOnly = false; document.getElementById('rfAmount').focus();">
                                            Partial Refund
                                        </label>
                                    </div>

                                    <label for="rfAmount">Refund Amount (max ₱<?php echo number_format($rfAmountPaid, 2); ?>)</label>
                                    <input type="number" id="rfAmount" name="refund_amount" step="0.01" min="0.01"
                                           max="<?php echo number_format($rfAmountPaid, 2, '.', ''); ?>"
                                           value="<?php echo number_format($rfAmountPaid, 2, '.', ''); ?>" readonly required>

                                    <label for="rfReference">Refund Reference No.</label>
                                    <input type="text" id="rfReference" name="refund_reference" inputmode="numeric" pattern="[0-9]+" data-digits-only
                                           placeholder="e.g. GCash ref. no. of the refund you sent" required>

                                    <label for="rfProof">Proof of Refund (screenshot / receipt)</label>
                                    <input type="file" id="rfProof" name="refund_proof" accept="image/jpeg,image/png,image/webp" required>

                                    <div class="rf-btn-row">
                                        <button type="submit" class="rf-btn rf-btn-primary">Refund</button>
                                        <button type="submit" class="rf-btn rf-btn-ghost" form="rfCancelForm" formnovalidate>No Payment Received</button>
                                    </div>
                                </form>

                                <!-- "No Payment Received" = walang pumasok na bayad (walang refund). Hiwalay na
                                     form ito (bawal ang form sa loob ng form) pero ang button nito ay
                                     nasa tabi ng "Refund" sa itaas gamit ang form="rfCancelForm". -->
                                <form method="POST" action="process_refund.php" id="rfCancelForm"
                                      onsubmit="return csConfirmSubmit(this, 'No payment received?\nThe order will be cancelled and nothing will be refunded. Only do this if no payment came in.', {okText: 'No Payment Received', cancelText: 'Back'});">
                                    <input type="hidden" name="order_id" value="<?php echo (int) $rfOrder["order_id"]; ?>">
                                    <input type="hidden" name="action" value="no_payment">
                                </form>

                            <?php else: ?>

                                <div class="staff-payment-details-grid">
                                    <span><strong>Refund Type:</strong> <?php echo e(ucfirst((string) ($rfOrder["refund_type"] ?? "N/A"))); ?></span>
                                    <span><strong>Amount Refunded:</strong> ₱<?php echo number_format((float) ($rfOrder["refund_amount"] ?? 0), 2); ?></span>
                                    <span><strong>Reference No.:</strong> <?php echo e($rfOrder["refund_reference"] ?? "N/A"); ?></span>
                                    <?php if (!empty($rfOrder["refunded_at"])): ?>
                                        <span><strong>Refunded On:</strong> <?php echo date("F j, Y, g:i A", strtotime($rfOrder["refunded_at"])); ?></span>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($rfOrder["refund_proof"])): ?>
                                    <div class="staff-payment-proof">
                                        <div class="staff-payment-proof-title">Proof of Refund</div>
                                        <img
                                            src="<?php echo htmlspecialchars(figurify_proof_url($rfOrder["refund_proof"]), ENT_QUOTES, "UTF-8"); ?>"
                                            alt="Proof of refund for order <?php echo (int) $rfOrder["order_id"]; ?>"
                                            class="staff-payment-proof-thumb"
                                            onclick="openImageLightbox('<?php echo htmlspecialchars(figurify_proof_url($rfOrder["refund_proof"]), ENT_QUOTES, "UTF-8"); ?>', 'Proof of Refund for Order #<?php echo (int) $rfOrder["order_id"]; ?>')"
                                        >
                                    </div>
                                <?php endif; ?>

                            <?php endif; ?>

                        </div>

                        
                    </div>

                </div>

            </div>

        <?php endif; ?>

    </div>

</section>


</main>

</div>


<!-- IMAGE LIGHTBOX (click any figure photo to enlarge) - BAGO, kinopya mula sa active-booking.php para gumana rin ang pag-click sa Figure Details reference photos dito. -->

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
/* ==========================================================================
   [SEARCH NG CANCELLED / DECLINED ORDERS]
   ========================================================================== */
// search cancelled/declined orders - same pattern as qvFilterOrders in Quotation/Verification

function qvFilterCancelled(query) {

    var q = query.trim().toLowerCase();
    var rows = document.querySelectorAll("#qvCancelledList .qv-row");
    var visibleCount = 0;

    rows.forEach(function (row) {

        var matches = row.dataset.search.indexOf(q) !== -1;

        row.classList.toggle("qv-hidden", !matches);

        if (matches) {
            visibleCount++;
        }

    });

    var footer = document.getElementById("qvListFooter");

    if (footer) {
        footer.textContent = "Showing " + visibleCount + " of " + rows.length + " orders";
    }

}


// keep scroll position when clicking through orders

(function () {

    var panels = [
        { el: document.getElementById("qvCancelledList"), key: "qvCancelledListScroll" },
        { el: document.querySelector(".qv-right-col"), key: "qvCancelledDetailScroll" }
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

    document.querySelectorAll("#qvCancelledList .qv-row").forEach(function (row) {
        row.addEventListener("click", function () {

            panels.forEach(function (panel) {

                if (panel.el) {
                    sessionStorage.setItem(panel.key, panel.el.scrollTop);
                }

            });

        });
    });

})();
/* ==========================================================================
   END [SEARCH NG CANCELLED / DECLINED ORDERS]
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


document.addEventListener("keydown", function (event) {

    if (event.key === "Escape") {
        closeImageLightbox();
    }

});


// switch between figures when an order has more than one - only shows one at a time

function selectFigure(button) {

    var panelsWrap = button.closest(".qv-figure-panels");

    if (!panelsWrap) {
        return;
    }

    var targetId = button.dataset.figureTarget;

    /* May sariling kopya ng figure tabs ang bawat figure panel
       (paulit-ulit na render sa foreach), kaya i-sync ang "active"
       state sa LAHAT ng kopya, hindi lang yung pinindot. */
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

document.addEventListener("keydown", function (event) {

    if (event.key === "Escape") {
        document.querySelectorAll(".qv-filter-dropdown.open").forEach(function (dropdown) {
            dropdown.classList.remove("open");
        });
    }

});

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